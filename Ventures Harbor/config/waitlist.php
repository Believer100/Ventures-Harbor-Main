<?php
/*
 * Waitlist and vacated slots.
 *
 * An Asset that is fully funded but whose listing has not expired is not closed —
 * it is simply out of room. Its Co-Own button becomes Join Waitlist, and when a
 * partner later exits, the seat they vacate is offered to everybody waiting at
 * once, with the reason they left.
 *
 * The seat is a first-class row (`venture_slots`), not a flag: several partners
 * can leave, so several seats can stand open at once, each with its own role,
 * amount and claimant.
 *
 * WHY "CLAIM" AND NOT "WHOEVER PAYS FIRST"
 * PayU is a redirect gateway. If the race were settled at payment, five waitlisters
 * could all be at the gateway for one seat and four of them would be charged for
 * nothing — and every refund on this platform is a manual admin payout, so each
 * collision would become a support case and an angry partner. So the race is
 * settled BEFORE the money moves: the first person to claim holds the seat
 * exclusively for VH_SLOT_CLAIM_MINUTES and pays behind that lock. Everybody else
 * is told the seat is being claimed and when it may come back.
 *
 * The lock is a single guarded UPDATE whose affected_rows decides the winner — the
 * same concurrency shape as vh_cancel_venture()'s status guard and
 * vh_send_expiry_reminder()'s claim. Two simultaneous claims cannot both succeed,
 * because the database, not PHP, arbitrates.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/venture-lifecycle.php'; // vh_notify(), vh_venture_openness() callers

/**
 * How long a claimed seat is held for its claimant before it returns to the pool.
 *
 * 10, down from 15 — the client, 6 Sep 2026: "time to 10 minute le lete, it's
 * enough". This is the ONLY place the number lives: every message, the email, the
 * dialog and the SQL interval read it from here, so changing it here changes all
 * of them. Anything that writes the figure into copy has drifted and is a bug.
 */
const VH_SLOT_CLAIM_MINUTES = 10;

/**
 * How long a vacated seat stays on offer before it is quietly withdrawn.
 *
 * Doubles as the founder's window to choose a candidate for an ACTIVE seat — the
 * client asked for "1 week" and this was already 7, so there is one clock, not two
 * that could disagree.
 */
const VH_SLOT_OFFER_DAYS = 7;

/**
 * How long a founder-selected ACTIVE candidate has to pay, in days.
 *
 * Deliberately NOT VH_SLOT_CLAIM_MINUTES. Those 10 minutes exist only because a
 * silent seat is a race — they stop five people reaching PayU for one seat. An
 * active seat has no race: one person is chosen and nobody is competing with them,
 * so a 10-minute timer would simply expire on somebody who reads the notification
 * the next morning. Two days, per the client, 6 Sep 2026.
 */
const VH_SLOT_ACTIVE_PAY_DAYS = 2;

/**
 * Is this seat the founder's to give rather than the first claimant's?
 *
 * The one place the two routes fork, so every caller asks the same question. An
 * active seat is an operational role: letting it go to whoever clicked first put a
 * partner into the business the founder never chose, skipping the resume, the skill
 * match and the Select/Reject every other active partner passes through.
 */
function vh_slot_is_founder_choice(array $slot): bool
{
    return (string)($slot['role'] ?? '') === 'active';
}

/** Same email-time budget the cancellation blast uses, for the same reason. */
const VH_WAITLIST_EMAIL_BUDGET_SECONDS = 10;

/**
 * Capital that is spoken for by seats still on offer.
 *
 * When a partner exits a full Asset their money leaves the raised total, so the
 * listing technically has room again — and without this, the first passer-by
 * could Co-Own that room through the ordinary route while the people who had been
 * waiting for exactly this were still reading the notification. The freed capital
 * belongs to the waitlist until the seat is filled or withdrawn.
 *
 * Bulk-attached like vh_attach_capital_split(), so a list page costs one query
 * rather than one per card.
 */
function vh_attach_reserved_seats(mysqli $mysqli, array &$ventures): void
{
    if (!$ventures) {
        return;
    }

    $ids = [];
    foreach ($ventures as $v) {
        $id = (int)($v['id'] ?? 0);
        if ($id > 0) { $ids[$id] = true; }
    }
    if (!$ids) {
        return;
    }

    $in = implode(',', array_map('intval', array_keys($ids)));
    $res = $mysqli->query("
        SELECT venture_id, SUM(invested_amount) AS reserved
          FROM venture_slots
         WHERE status IN ('open', 'claiming') AND venture_id IN ($in)
         GROUP BY venture_id
    ");

    $map = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $map[(int)$row['venture_id']] = (int)$row['reserved'];
    }

    foreach ($ventures as &$v) {
        $v['reserved_seat_capital'] = $map[(int)($v['id'] ?? 0)] ?? 0;
    }
    unset($v);
}

/** The same figure for one Asset. */
function vh_reserved_seat_capital(mysqli $mysqli, int $ventureId): int
{
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(invested_amount), 0) AS reserved
          FROM venture_slots
         WHERE venture_id = ? AND status IN ('open', 'claiming')
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['reserved'] ?? 0);
}

/**
 * Is this Asset in the state where the waitlist replaces Co-Own?
 *
 * Full, still live, and still inside its listing period. A showcase never
 * qualifies (nobody can join one at all), and an expired or cancelled listing
 * does not either — there is no seat to wait for once the listing is over.
 */
function vh_waitlist_is_open(array $venture): bool
{
    if (!empty($venture['is_showcase'])) {
        return false;
    }
    if ((string)($venture['status'] ?? '') !== 'active') {
        return false;
    }

    // Reserved seats count as still taken. Without that, an Asset flips back to
    // Co-Own the instant somebody exits and the waitlist it kept never gets its
    // turn — see vh_attach_reserved_seats().
    $reserved = (int)($venture['reserved_seat_capital'] ?? 0);
    $openness = vh_venture_openness($venture);

    if (!$openness['is_full'] && $openness['remaining'] > $reserved) {
        return false;
    }

    // "time remaining" — the client's condition. A NULL deadline means the clock
    // was never started, which only happens for a showcase (excluded above) or a
    // listing still at pending_payment (not 'active'), so treat it as no time left.
    $endsAt = $venture['listing_ends_at'] ?? null;
    if (!$endsAt) {
        return false;
    }

    return strtotime((string)$endsAt) > time();
}

/**
 * May this member take the "Exit Without Refund" route right now?
 *
 * "Jese hi co-own changes to Join Waitlist, exit without refund is not available…
 * even yaha meeting complete bhi nahi hui ha" — the client, 6 Sep 2026, looking at
 * a funded Asset whose seat had just been vacated by somebody who left before
 * anybody had met anybody, giving no reason.
 *
 * So the forfeit route closes on a **full** Asset whose **meetup has not been
 * completed**. Both halves matter and the second is the whole design:
 *
 *   - Blocking on fullness ALONE would trap people. The refund route needs a
 *     completed meetup and you must be inside the 24 hours after it, so with the
 *     forfeit route also gone a partner in a funded Asset could never leave — and
 *     the founder, who decides whether a meetup is ever marked complete, would
 *     hold the only key. Faraaz took option (a) for exactly this reason.
 *   - Once a meetup HAS been completed the route comes back permanently, refund
 *     window open or closed, so nobody is ever without a way out for good.
 *
 * $quitWindowOpenedAt is the member's own window resolved against the venture's
 * (vh_member_quit_window()), not the raw column — somebody who took a vacated seat
 * has their own, and reading the venture's alone would block them wrongly.
 *
 * The venture array must have been through vh_attach_capital_split() and carry
 * reserved_seat_capital, exactly as vh_waitlist_is_open() requires.
 */
function vh_no_refund_exit_blocked(array $venture, ?string $quitWindowOpenedAt): bool
{
    if (!empty($quitWindowOpenedAt)) {
        return false;
    }
    return vh_waitlist_is_open($venture);
}

/** One wording for the refusal, so the action and its read-only companion agree. */
function vh_no_refund_blocked_message(): string
{
    return 'This Asset is fully funded and its meetup has not been marked completed yet. '
         . 'Leaving now would hand your seat to the waitlist before anyone has met the team, '
         . 'so exiting is paused until that meetup happens — and once it does you can leave '
         . 'with your commitment fee refunded in full, within 24 hours.';
}

/** Where this user stands with a venture's waitlist: '' | 'waiting' | 'joined' | 'left'. */
function vh_waitlist_status(mysqli $mysqli, int $ventureId, int $userId): string
{
    if ($ventureId <= 0 || $userId <= 0) {
        return '';
    }
    $stmt = $mysqli->prepare("SELECT status FROM venture_waitlist WHERE venture_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $ventureId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string)$row['status'] : '';
}

/** How many people are waiting on this Asset. */
function vh_waitlist_count(mysqli $mysqli, int $ventureId): int
{
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM venture_waitlist WHERE venture_id = ? AND status = 'waiting'");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
}

/**
 * Release seats whose claimant never paid, and withdraw seats nobody took.
 *
 * Idempotent and cheap; called from the lifecycle sweep and again at the top of
 * any read or claim, so a stale lock can never outlive its window just because the
 * sweep has not run. Note it only touches rows that are actually past their time,
 * so running it twice changes nothing.
 */
function vh_release_expired_slots(mysqli $mysqli): int
{
    $released = 0;

    $mysqli->query("
        UPDATE venture_slots
           SET status = 'open', claimed_by_user_id = NULL, claim_expires_at = NULL
         WHERE status = 'claiming'
           AND claim_expires_at IS NOT NULL
           AND claim_expires_at < NOW()
    ");
    $released += $mysqli->affected_rows;

    /* The NOT(...) is what keeps a live hold from being withdrawn out from under
       its holder. It matters far more now than it did: a founder may select an
       active candidate on day 6 of the seven, and that candidate then has two days
       to pay — which runs past the withdrawal date. Without this the seat would
       vanish mid-payment for somebody who had been chosen. It protects a silent
       10-minute hold taken on day 7 for the same reason. An EXPIRED hold is not
       protected, and does not need to be: the release above has already turned it
       back to 'open' in this same sweep. */
    $mysqli->query("
        UPDATE venture_slots
           SET status = 'withdrawn'
         WHERE status IN ('open', 'claiming')
           AND created_at < DATE_SUB(NOW(), INTERVAL " . (int)VH_SLOT_OFFER_DAYS . " DAY)
           AND NOT (status = 'claiming'
                    AND claim_expires_at IS NOT NULL
                    AND claim_expires_at > NOW())
    ");

    // A seat on a listing that has since expired or been cancelled is not a seat
    // any more. Withdraw it rather than leaving it claimable.
    $mysqli->query("
        UPDATE venture_slots s
          JOIN ventures v ON v.id = s.venture_id
           SET s.status = 'withdrawn'
         WHERE s.status IN ('open', 'claiming')
           AND v.status <> 'active'
    ");

    return $released;
}

/**
 * A partner has left a full Asset — open their seat and tell everybody waiting.
 *
 * Called from api/ventures.php's `exit` AFTER the member row is deleted and the
 * venture totals are adjusted, so `$wasFull` has to be measured before that and
 * passed in: by the time we run, the Asset has room again by definition.
 *
 * Returns [slotId, notifiedCount]. Both are 0 when no seat was opened.
 */
function vh_open_slot_for_exit(
    mysqli $mysqli,
    int $ventureId,
    array $venture,
    int $leaverUserId,
    string $leaverName,
    string $role,
    int $investedAmount,
    string $exitReason,
    bool $wasFull
): array {
    // Only a full Asset has a waitlist, so only a full Asset has a seat to offer.
    // Somebody leaving a half-funded listing just frees capacity anyone can take
    // through the ordinary Co-Own route.
    if (!$wasFull || (string)($venture['status'] ?? '') !== 'active' || !empty($venture['is_showcase'])) {
        return [0, 0];
    }

    $feeAmount = (int)round($investedAmount * VH_FEE_RATE);
    $role = $role === 'active' ? 'active' : 'silent';

    $stmt = $mysqli->prepare("
        INSERT INTO venture_slots
            (venture_id, vacated_by_user_id, vacated_by_name, role, invested_amount, fee_amount, exit_reason, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
    ");
    $stmt->bind_param('iissiis', $ventureId, $leaverUserId, $leaverName, $role, $investedAmount, $feeAmount, $exitReason);
    if (!$stmt->execute()) {
        $stmt->close();
        return [0, 0];
    }
    $slotId = $stmt->insert_id;
    $stmt->close();

    $notified = vh_notify_waitlist($mysqli, $ventureId, $slotId, $venture, $role, $investedAmount, $feeAmount, $exitReason);

    $stmt = $mysqli->prepare("UPDATE venture_slots SET notified_count = ? WHERE id = ?");
    $stmt->bind_param('ii', $notified, $slotId);
    $stmt->execute();
    $stmt->close();

    return [$slotId, $notified];
}

/**
 * The blast: every waiting person, at once, with the reason.
 *
 * Same shape as vh_initiate_cancellation_refunds() — notification for everyone,
 * email only while there is time budget left, because this runs inside the
 * departing partner's own request and they should not wait on an SMTP queue.
 */
function vh_notify_waitlist(
    mysqli $mysqli,
    int $ventureId,
    int $slotId,
    array $venture,
    string $role,
    int $investedAmount,
    int $feeAmount,
    string $exitReason,
    bool $isReopen = false
): int {
    $stmt = $mysqli->prepare("
        SELECT w.user_id, u.name AS user_name, u.email AS user_email
          FROM venture_waitlist w
          JOIN users u ON u.id = w.user_id
         WHERE w.venture_id = ? AND w.status = 'waiting' AND u.is_deactivated = 0
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $waiting = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$waiting) {
        return 0;
    }

    $title = (string)($venture['title'] ?? 'an Asset');
    $roleWord = $role === 'active' ? 'active partner' : 'silent partner';
    // "A active partner" — the article has to follow the role, not be hardcoded.
    $article  = $role === 'active' ? 'An' : 'A';

    /* The role belongs in the TITLE, not only in the body: the bell dropdown shows
       the title and truncates the rest, so a waitlister deciding whether to open
       this at all should already know whether the seat is capital or work.
       (Client, 6 Sep 2026.) */
    $isActive = ($role === 'active');

    $notifTitle = $isReopen
        ? 'The Active Seat Is Open Again 🪑'
        : $article . ' ' . ucwords($roleWord) . ' Seat Has Opened 🎟️';

    $reasonLine = $exitReason !== ''
        ? ' Reason given for leaving: "' . $exitReason . '".'
        : ' No reason was given for leaving.';

    /* The two routes are genuinely different things to be told about, so they are
       told differently. Saying "first to claim it wins" about an active seat would
       be a lie that costs somebody a scramble for a seat that was never a race. */
    $howItWorks = $isActive
        ? 'This is an active partner seat, so it is not first-come: apply with your resume, a short message '
          . 'and a WhatsApp number, and the founder chooses from the applicants. Whoever is selected then has '
          . VH_SLOT_ACTIVE_PAY_DAYS . ' days to pay. Open your Waitlist page to apply.'
        : 'Everyone on the waitlist has been told at the same moment: the first person to claim it holds it for '
          . VH_SLOT_CLAIM_MINUTES . ' minutes to pay, and no meeting is required. Open your Waitlist page to claim it.';

    $opening = $isReopen
        ? 'The active partner seat on "' . $title . '" is open for applications again — the founder has '
          . 'reopened it. If you applied before you will need to apply again.'
        : $article . ' ' . $roleWord . ' seat has opened on "' . $title . '" — the partner who held it has exited.'
          . $reasonLine;

    $body = $opening
          . ' The seat is for ₹' . vh_inr($investedAmount) . ', with a commitment fee of ₹' . vh_inr($feeAmount) . '. '
          . $howItWorks;

    $emailsAllowedUntil = microtime(true) + VH_WAITLIST_EMAIL_BUDGET_SECONDS;
    $sent = 0;

    foreach ($waiting as $person) {
        vh_notify($mysqli, (int)$person['user_id'], 'venture', $notifTitle, $body);
        $sent++;

        if (!empty($person['user_email']) && microtime(true) < $emailsAllowedUntil) {
            vh_send_slot_opened_email(
                (string)$person['user_email'],
                (string)$person['user_name'],
                $title,
                $roleWord,
                $investedAmount,
                $feeAmount,
                $exitReason
            );
        }
    }

    return $sent;
}

function vh_send_slot_opened_email(
    string $email,
    string $name,
    string $ventureTitle,
    string $roleWord,
    int $investedAmount,
    int $feeAmount,
    string $exitReason
): void {
    require_once __DIR__ . '/mailer.php';

    $reasonBlock = $exitReason !== ''
        ? '<tr><td style="padding:0 18px 14px 18px;">Reason for leaving: <strong>' . htmlspecialchars($exitReason) . '</strong></td></tr>'
        : '<tr><td style="padding:0 18px 14px 18px;color:#94a3b8;">No reason was given for leaving.</td></tr>';

    // Same article rule as the in-app notification, and the same reason for naming
    // the role in the subject: an inbox shows the subject and nothing else.
    $article   = str_starts_with($roleWord, 'active') ? 'An' : 'A';
    $roleTitle = ucwords($roleWord);

    $inner = '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Hi ' . htmlspecialchars($name) . ',</p>'
        . '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">' . $article . ' <strong>' . htmlspecialchars($roleWord)
        . '</strong> seat has opened on <strong>' . htmlspecialchars($ventureTitle) . '</strong>, '
        . 'the Asset you are waitlisted for.</p>'
        . '<table role="presentation" width="100%" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:13.5px;color:#475569;">'
        . '<tr><td style="padding:14px 18px 4px 18px;color:#94a3b8;">Seat value</td></tr>'
        . '<tr><td style="padding:0 18px 14px 18px;font-family:\'Outfit\',Arial,sans-serif;font-size:20px;font-weight:700;color:#0f172a;">₹' . vh_inr($investedAmount) . '</td></tr>'
        . '<tr><td style="padding:0 18px 14px 18px;border-top:1px solid #e2e8f0;padding-top:14px;">Commitment fee to claim it: <strong>₹' . vh_inr($feeAmount) . '</strong></td></tr>'
        . $reasonBlock
        . '</table>'
        . '<p style="margin:18px 0 0;font-size:14px;line-height:1.6;">Everyone on the waitlist was told at the same moment. '
        . 'The first person to claim the seat holds it for <strong>' . VH_SLOT_CLAIM_MINUTES . ' minutes</strong> to pay the commitment fee, '
        . 'and <strong>no meeting is required</strong> — open your Waitlist page to claim it.</p>';

    $body = renderEmailTemplate($inner, [
        'heading'   => $article . ' ' . $roleTitle . ' Seat Has Opened',
        'preheader' => $article . ' ' . $roleWord . ' seat on ' . $ventureTitle
                     . ' is available — first to claim it takes it.',
    ]);

    @sendEmail($email, $article . ' ' . $roleWord . ' seat has opened on ' . $ventureTitle, $body);
}

/**
 * Try to take a seat. The guarded UPDATE is the whole race.
 *
 * Succeeds only when the row is genuinely available: still open, or held by a
 * claim that has already run out. affected_rows tells us whether WE were the one
 * who moved it, so two simultaneous callers cannot both come away believing they
 * won — MySQL serialises the update and the loser sees 0 rows changed.
 *
 * Returns [ok, message, slotRow].
 */
function vh_claim_slot(mysqli $mysqli, int $slotId, int $userId): array
{
    vh_release_expired_slots($mysqli);

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        return [false, 'That seat no longer exists.', null];
    }
    if ($slot['status'] === 'filled') {
        return [false, 'That seat has already been taken.', $slot];
    }
    if ($slot['status'] === 'withdrawn') {
        return [false, 'That seat is no longer on offer.', $slot];
    }

    /* An ACTIVE seat is not claimable at all — it is applied for and the founder
       chooses. The refusal lives here rather than only in the UI because this is
       reachable by a hand-made POST, and winning the race on an active seat would
       be exactly the thing the client asked to stop: an operator in the business
       nobody selected. See vh_apply_for_slot(). */
    if (vh_slot_is_founder_choice($slot)) {
        return [false, 'This is an active partner seat, so it is not first-come. '
                     . 'Apply for it and the founder will choose from the applicants.', $slot];
    }

    // Already ours — re-claiming just extends nothing and is not an error, so the
    // claimant can reload the page or come back from an abandoned payment.
    if ($slot['status'] === 'claiming' && (int)$slot['claimed_by_user_id'] === $userId) {
        return [true, 'You are holding this seat.', $slot];
    }

    if (vh_waitlist_status($mysqli, (int)$slot['venture_id'], $userId) !== 'waiting') {
        return [false, 'Only people on this Asset\'s waitlist can claim a seat.', $slot];
    }

    $stmt = $mysqli->prepare("
        UPDATE venture_slots
           SET status = 'claiming',
               claimed_by_user_id = ?,
               claim_expires_at = DATE_ADD(NOW(), INTERVAL " . (int)VH_SLOT_CLAIM_MINUTES . " MINUTE)
         WHERE id = ?
           AND status IN ('open', 'claiming')
           AND (claim_expires_at IS NULL OR claim_expires_at < NOW())
    ");
    $stmt->bind_param('ii', $userId, $slotId);
    $stmt->execute();
    $won = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$won) {
        return [false, 'Somebody claimed that seat a moment before you. If they do not complete payment within '
                     . VH_SLOT_CLAIM_MINUTES . ' minutes it comes back — you will not be notified again, so keep this page open.', $slot];
    }

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [true, 'The seat is yours for the next ' . VH_SLOT_CLAIM_MINUTES . ' minutes. Pay the commitment fee to confirm it.', $slot];
}

/** Give a held seat back voluntarily. Only the holder may. */
function vh_release_slot(mysqli $mysqli, int $slotId, int $userId): bool
{
    $stmt = $mysqli->prepare("
        UPDATE venture_slots
           SET status = 'open', claimed_by_user_id = NULL, claim_expires_at = NULL
         WHERE id = ? AND status = 'claiming' AND claimed_by_user_id = ?
    ");
    $stmt->bind_param('ii', $slotId, $userId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Is this user allowed to pay for this seat right now?
 *
 * Both the manual route and api/payments.php's `initiate` ask this before taking
 * any money, so a request that skips the claim step cannot buy a seat somebody
 * else is holding.
 *
 * Returns [ok, message, slotRow].
 */
function vh_slot_payable_by(mysqli $mysqli, int $slotId, int $userId): array
{
    vh_release_expired_slots($mysqli);

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        return [false, 'That seat no longer exists.', null];
    }
    if ($slot['status'] === 'filled') {
        return [false, 'That seat has already been taken.', $slot];
    }
    if ($slot['status'] !== 'claiming' || (int)$slot['claimed_by_user_id'] !== $userId) {
        return [false, 'You are not holding that seat. Claim it first.', $slot];
    }
    if ($slot['claim_expires_at'] && strtotime((string)$slot['claim_expires_at']) <= time()) {
        return [false, 'Your hold on that seat has expired. Claim it again if it is still free.', $slot];
    }

    return [true, '', $slot];
}

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIVE SEATS — applied for, then chosen by the founder

   The whole fork from the silent route. A silent seat is capital and nothing
   else, so first-come is fair and the 10-minute hold settles the race. An active
   seat is a job in the business, and until now it went to whoever clicked first —
   an operator arriving without the founder ever choosing them, skipping the
   resume, the skill match and the Select/Reject every other active partner goes
   through. The client closed that on 6 Sep 2026.

   The candidates are rows in venture_applications carrying a slot_id (step 56),
   not a new table, so the founder reviews them with the screen, the scoring and
   the decide action that already exist.

   Timeline: seat opens -> waitlisters apply (founder has VH_SLOT_OFFER_DAYS to
   choose) -> founder selects -> that person has VH_SLOT_ACTIVE_PAY_DAYS to pay ->
   vh_fill_slot(). Nothing is held while applications come in: the seat stays
   'open' and only the selection moves it to 'claiming'.
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * May this person apply for this seat right now? Returns [ok, whyNot].
 *
 * Split out so the endpoint can refuse BEFORE it writes the uploaded resume to
 * disk, while vh_apply_for_slot() below asks the identical question at the point
 * of writing. One implementation, two callers — the alternative was a pre-check
 * that slowly stopped matching the real one, and a resume orphaned in
 * uploads/resumes/ every time they disagreed.
 */
function vh_slot_open_for_applications(mysqli $mysqli, int $slotId, int $userId): array
{
    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot)                            { return [false, 'That seat no longer exists.']; }
    if (!vh_slot_is_founder_choice($slot)) { return [false, 'This is a silent partner seat — claim it instead; it goes to whoever claims first.']; }
    if ($slot['status'] === 'filled')      { return [false, 'That seat has already been taken.']; }
    if ($slot['status'] === 'withdrawn')   { return [false, 'That seat is no longer on offer.']; }
    if ($slot['status'] === 'claiming')    { return [false, 'The founder has already chosen someone for this seat.']; }

    if (vh_waitlist_status($mysqli, (int)$slot['venture_id'], $userId) !== 'waiting') {
        return [false, 'Only people on this Asset\'s waitlist can apply for a seat.'];
    }
    return [true, ''];
}

/**
 * Apply for a vacated ACTIVE seat. Returns [ok, message, applicationId].
 *
 * Deliberately does NOT touch the slot. Applying is not claiming — several people
 * apply for one seat and the founder picks, so there is no race here to settle and
 * nothing to lock. That is the whole difference from vh_claim_slot().
 *
 * Re-applying replaces your own pending application rather than erroring, the same
 * courtesy vh_claim_slot() extends to somebody re-claiming their own hold: a person
 * may want to swap a better resume in while the founder is still deciding.
 */
function vh_apply_for_slot(mysqli $mysqli, int $slotId, int $userId, array $data): array
{
    vh_release_expired_slots($mysqli);

    [$mayApply, $whyNot] = vh_slot_open_for_applications($mysqli, $slotId, $userId);
    if (!$mayApply) {
        return [false, $whyNot, 0];
    }

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $ventureId = (int)$slot['venture_id'];

    $stmt = $mysqli->prepare("SELECT required_skills FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT skills FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $note = vh_skill_match_note($venture['required_skills'] ?? null, $user['skills'] ?? null);

    // The seat's own amount, never a figure from the request: this seat is worth
    // exactly what the partner who vacated it had in.
    $amount   = (int)$slot['invested_amount'];
    $message  = ($data['message'] ?? '') !== '' ? (string)$data['message'] : null;
    $resume   = ($data['resume_path'] ?? '') !== '' ? (string)$data['resume_path'] : null;
    $resumeNm = ($data['resume_name'] ?? '') !== '' ? (string)$data['resume_name'] : null;
    $whatsapp = ($data['whatsapp_number'] ?? '') !== '' ? (string)$data['whatsapp_number'] : null;

    $stmt = $mysqli->prepare(
        "SELECT id, resume_path FROM venture_applications WHERE slot_id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $slotId, $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Replacing an earlier attempt: drop the resume it referenced, or every
        // re-application leaves an orphan file behind in uploads/resumes/.
        if ($resume && !empty($existing['resume_path']) && $existing['resume_path'] !== $resume) {
            @unlink(__DIR__ . '/../' . ltrim((string)$existing['resume_path'], '/'));
        }
        $appId = (int)$existing['id'];
        $stmt = $mysqli->prepare("
            UPDATE venture_applications
               SET message = ?, resume_path = COALESCE(?, resume_path),
                   resume_name = COALESCE(?, resume_name),
                   whatsapp_number = ?, skill_match_note = ?,
                   status = 'pending', decided_at = NULL, invested_amount = ?
             WHERE id = ?
        ");
        $stmt->bind_param('sssssii', $message, $resume, $resumeNm, $whatsapp, $note, $amount, $appId);
        $stmt->execute();
        $stmt->close();
        return [true, 'Your application for this seat has been updated. The founder will choose from the applicants.', $appId];
    }

    $stmt = $mysqli->prepare("
        INSERT INTO venture_applications
            (venture_id, user_id, slot_id, role, invested_amount, message,
             resume_path, resume_name, skill_match_note, whatsapp_number, status)
        VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, 'pending')
    ");
    // 4 ints (venture, user, slot, amount) then 5 strings — 9 placeholders, since
    // role and status are literals in the SQL above.
    $stmt->bind_param('iiiisssss', $ventureId, $userId, $slotId, $amount,
                      $message, $resume, $resumeNm, $note, $whatsapp);
    $stmt->execute();
    $appId = $stmt->insert_id;
    $stmt->close();

    vh_notify_founder_of_slot_application($mysqli, $slot, $userId);

    return [true, 'Your application has been sent to the founder. You will be told as soon as they decide.', $appId];
}

/**
 * How long is left on a seat's hold, in seconds — measured by the DATABASE.
 *
 * NEVER compute this as strtotime($slot['claim_expires_at']) - time(). MySQL and
 * PHP are not on the same clock here (MariaDB Asia/Calcutta, PHP Europe/Berlin,
 * 3.5 hours apart), and every such subtraction was reporting a 10-minute hold as
 * 3h 40m — telling a claimant they had hours while the database released the seat
 * from under them ten minutes later. The expiry itself was always right, because
 * the guards compare NOW() to claim_expires_at inside SQL; only the number shown
 * to the person was wrong. Same rule as vh_send_expiry_reminder()'s TIMESTAMPDIFF.
 */
function vh_slot_seconds_left(mysqli $mysqli, int $slotId): int
{
    $stmt = $mysqli->prepare(
        "SELECT GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), claim_expires_at), 0)) AS s
           FROM venture_slots WHERE id = ?"
    );
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['s'] ?? 0);
}

/** Everyone who has applied for one seat, newest first, with the person attached. */
function vh_slot_candidates(mysqli $mysqli, int $slotId): array
{
    $stmt = $mysqli->prepare("
        SELECT a.*, u.name AS user_name, u.email AS user_email,
               u.avatar_url, u.skills, u.occupation, u.city
          FROM venture_applications a
          JOIN users u ON u.id = a.user_id
         WHERE a.slot_id = ?
      ORDER BY FIELD(a.status, 'selected', 'pending', 'rejected'), a.applied_at ASC
    ");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['whatsapp_link']    = vh_whatsapp_link($row['whatsapp_number'] ?? null);
        $row['whatsapp_display'] = vh_whatsapp_display($row['whatsapp_number'] ?? null);
    }
    return $rows;
}

/**
 * The founder chooses. Returns [ok, message, slotRow].
 *
 * The seat moves to 'claiming' with the chosen person as claimant and a
 * VH_SLOT_ACTIVE_PAY_DAYS expiry — deliberately reusing the silent route's hold
 * columns rather than inventing a parallel state, so vh_release_expired_slots(),
 * vh_reserved_seat_capital() and the Pay button all keep working untouched. If the
 * chosen person does not pay, the seat returns to 'open' with every application
 * still on it, and the founder picks again.
 *
 * The move is one guarded UPDATE whose affected_rows decides it, the same shape as
 * vh_claim_slot(): two founders' tabs, or a double-click, cannot select two people.
 */
function vh_select_slot_candidate(mysqli $mysqli, int $slotId, int $applicationId, int $founderId): array
{
    vh_release_expired_slots($mysqli);

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot)                            { return [false, 'That seat no longer exists.', null]; }
    if (!vh_slot_is_founder_choice($slot)) { return [false, 'A silent seat goes to whoever claims it first — there is nobody to select.', null]; }
    if ($slot['status'] === 'filled')      { return [false, 'That seat has already been taken.', null]; }
    if ($slot['status'] === 'withdrawn')   { return [false, 'That seat is no longer on offer.', null]; }

    if (!isFounderOf($mysqli, (int)$slot['venture_id'], $founderId)) {
        return [false, 'Only the Asset founder can choose who takes a vacated active seat.', null];
    }

    $stmt = $mysqli->prepare("SELECT * FROM venture_applications WHERE id = ? AND slot_id = ?");
    $stmt->bind_param('ii', $applicationId, $slotId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$app) {
        return [false, 'That application is not for this seat.', null];
    }

    $candidateId = (int)$app['user_id'];

    $stmt = $mysqli->prepare("
        UPDATE venture_slots
           SET status = 'claiming',
               claimed_by_user_id = ?,
               claim_expires_at = DATE_ADD(NOW(), INTERVAL " . (int)VH_SLOT_ACTIVE_PAY_DAYS . " DAY)
         WHERE id = ?
           AND status = 'open'
    ");
    $stmt->bind_param('ii', $candidateId, $slotId);
    $stmt->execute();
    $won = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$won) {
        return [false, 'Somebody has already been selected for this seat.', $slot];
    }

    $stmt = $mysqli->prepare("UPDATE venture_applications SET status = 'selected', decided_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $stmt->close();

    /* The others stay PENDING, not rejected. If the chosen person never pays, the
       founder picks again from exactly this list — rejecting them now would throw
       away the shortlist they just reviewed. vh_reopen_slot() is the deliberate way
       to clear them. */

    vh_notify_slot_selection($mysqli, $slot, $candidateId);

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $fresh = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [true, 'Selected. They have ' . VH_SLOT_ACTIVE_PAY_DAYS . ' days to pay the commitment fee and take the seat.', $fresh];
}

/**
 * "None of these are good enough — ask again." Returns [ok, message, notified].
 *
 * Rejects every pending application on the seat and tells the waitlist the seat is
 * open again, so people who did not apply the first time get another prompt. The
 * seat itself is untouched: it was never held while applications were arriving, and
 * its VH_SLOT_OFFER_DAYS clock keeps running — reopening does not buy a fresh week,
 * or a founder could keep one seat open forever.
 */
function vh_reopen_slot(mysqli $mysqli, int $slotId, int $founderId): array
{
    vh_release_expired_slots($mysqli);

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot)                            { return [false, 'That seat no longer exists.', 0]; }
    if (!vh_slot_is_founder_choice($slot)) { return [false, 'A silent seat has no applications to reopen.', 0]; }
    if ($slot['status'] === 'filled')      { return [false, 'That seat has already been taken.', 0]; }
    if ($slot['status'] === 'withdrawn')   { return [false, 'That seat is no longer on offer.', 0]; }
    if ($slot['status'] === 'claiming') {
        return [false, 'Someone is already selected and paying for this seat. '
                     . 'If they do not pay in time it comes back to you automatically.', 0];
    }

    if (!isFounderOf($mysqli, (int)$slot['venture_id'], $founderId)) {
        return [false, 'Only the Asset founder can reopen a vacated active seat.', 0];
    }

    $stmt = $mysqli->prepare(
        "UPDATE venture_applications SET status = 'rejected', decided_at = NOW()
          WHERE slot_id = ? AND status = 'pending'"
    );
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $rejected = $stmt->affected_rows;
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT * FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $slot['venture_id']);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $notified = vh_notify_waitlist(
        $mysqli, (int)$slot['venture_id'], $slotId, $venture, 'active',
        (int)$slot['invested_amount'], (int)$slot['fee_amount'],
        (string)($slot['exit_reason'] ?? ''), true
    );

    return [true, $rejected . ' application(s) closed. The waitlist has been told the seat is open again.', $notified];
}

/** The founder has somebody to review — tell them, since nothing else will. */
function vh_notify_founder_of_slot_application(mysqli $mysqli, array $slot, int $applicantId): void
{
    $stmt = $mysqli->prepare("SELECT founder_user_id, title FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $slot['venture_id']);
    $stmt->execute();
    $v = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$v) { return; }

    $stmt = $mysqli->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param('i', $applicantId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $pending = 0;
    $stmt = $mysqli->prepare("SELECT COUNT(*) c FROM venture_applications WHERE slot_id = ? AND status = 'pending'");
    $stmt->bind_param('i', $slot['id']);
    $stmt->execute();
    $pending = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    vh_notify($mysqli, (int)$v['founder_user_id'], 'venture',
        'Someone Applied for the Open Seat 🪑',
        htmlspecialchars_decode((string)($u['name'] ?? 'Someone')) . ' has applied for the vacated active partner seat on "'
        . $v['title'] . '". You now have ' . $pending . ' applicant(s) to choose from. '
        . 'You have up to ' . VH_SLOT_OFFER_DAYS . ' days from when the seat opened to select one — '
        . 'open the Asset\'s Applications tab to review them.');
}

/** Tell the chosen candidate, and start their clock in words as well as in SQL. */
function vh_notify_slot_selection(mysqli $mysqli, array $slot, int $candidateId): void
{
    $stmt = $mysqli->prepare("SELECT title FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $slot['venture_id']);
    $stmt->execute();
    $v = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    vh_notify($mysqli, $candidateId, 'venture',
        'You Have Been Chosen for the Seat 🎉',
        'The founder has selected you for the vacated active partner seat on "'
        . ($v['title'] ?? 'an Asset') . '". The seat is held for you for '
        . VH_SLOT_ACTIVE_PAY_DAYS . ' days: pay the commitment fee of ₹'
        . vh_inr((int)$slot['fee_amount']) . ' from your Waitlist page to take it. '
        . 'If it is not paid in that time the seat goes back to the other applicants.');
}

/**
 * The seat is paid for — hand it over.
 *
 * Marks the slot filled under a guard (so a duplicated PayU callback cannot fill
 * it twice), grants the membership through the one function that does that, and
 * stamps the member's own 24-hour refund window because this person joined
 * without a meeting and the venture-level window may long since have closed.
 *
 * Returns the vh_grant_membership() result, with 'slot_filled' added.
 */
function vh_fill_slot(mysqli $mysqli, int $slotId, int $userId, int $feePaid, string $txnId = ''): array
{
    $stmt = $mysqli->prepare("
        UPDATE venture_slots
           SET status = 'filled', filled_by_user_id = ?, filled_at = NOW()
         WHERE id = ? AND status = 'claiming' AND claimed_by_user_id = ?
    ");
    $stmt->bind_param('iii', $userId, $slotId, $userId);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT * FROM venture_slots WHERE id = ?");
    $stmt->bind_param('i', $slotId);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        return ['success' => false, 'message' => 'That seat no longer exists.', 'slot_filled' => false];
    }

    // Not the winner of the guard — but if WE are the one already recorded as
    // having filled it, this is a repeated callback and must stay harmless.
    if (!$claimed && (int)$slot['filled_by_user_id'] !== $userId) {
        return ['success' => false, 'message' => 'That seat has already been taken.', 'slot_filled' => false];
    }

    $ventureId = (int)$slot['venture_id'];
    $role      = (string)$slot['role'];
    $invested  = (int)$slot['invested_amount'];

    $result = vh_grant_membership($mysqli, [
        'user_id'         => $userId,
        'venture_id'      => $ventureId,
        'role'            => $role,
        'invested_amount' => $invested,
        'fee_amount'      => $feePaid,
        'txn_id'          => $txnId,
    ]);

    if (!empty($result['success'])) {
        // Their own 24-hour window, opening now. They were admitted without a
        // meeting by design, so the venture-level window — which needs a completed
        // meetup — can never open for them.
        $stmt = $mysqli->prepare("
            UPDATE venture_members
               SET quit_window_opened_at = NOW(), joined_via_slot_id = ?
             WHERE venture_id = ? AND user_id = ?
        ");
        $stmt->bind_param('iii', $slotId, $ventureId, $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE venture_waitlist SET status = 'joined' WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $ventureId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    $result['slot_filled'] = !empty($result['success']);
    return $result;
}

/**
 * When did this member's 24-hour window open?
 *
 * Their own stamp wins, then the venture's. Every pre-existing member has NULL in
 * the first, so they behave exactly as they did before this feature existed.
 */
function vh_member_quit_window(?string $memberOpenedAt, ?string $ventureOpenedAt): ?string
{
    return $memberOpenedAt ?: ($ventureOpenedAt ?: null);
}

/**
 * Seconds left in the 24-hour refund window, measured by the DATABASE.
 * Null when no window has opened; negative once it has closed.
 *
 * NOT `86400 - (time() - strtotime($openedAt))`, which is what both callers used
 * to do. `quit_window_opened_at` is written by MySQL and carries no timezone, so
 * subtracting PHP's `time()` from it measures the gap between the two clocks as
 * well as the elapsed time — and on this install PHP runs 3.5 hours behind MySQL,
 * which quietly turned the 24-hour window into a 27.5-hour one. That is not a
 * display bug: `exit` uses the same arithmetic to decide whether a refund is
 * allowed at all, so it was handing out refunds three and a half hours late.
 *
 * TIMESTAMPDIFF puts both sides of the comparison on MySQL's clock, so the answer
 * is right whatever PHP, MySQL or the viewer's browser is set to. Same rule as
 * vh_slot_seconds_left() and vh_send_expiry_reminder().
 */
function vh_quit_window_seconds_left(mysqli $mysqli, ?string $openedAt): ?int
{
    if (!$openedAt) {
        return null;
    }
    $stmt = $mysqli->prepare("SELECT 86400 - TIMESTAMPDIFF(SECOND, ?, NOW()) AS s");
    $stmt->bind_param('s', $openedAt);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return isset($row['s']) ? (int)$row['s'] : null;
}
