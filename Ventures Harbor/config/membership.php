<?php

require_once __DIR__ . '/mailer.php';
// vh_inr() lives in session.php — the one file every entry point loads. Stated
// here because this file uses it and is included by callers (test_silent_cap.php)
// that don't pull session.php in themselves.
require_once __DIR__ . '/session.php';

const VH_FEE_RATE = 0.005;

function vh_founder_listing_fee(int $founderContribution): int
{
    if ($founderContribution <= 0) {
        return 0;
    }
    return (int)round($founderContribution * VH_FEE_RATE);
}

function vh_activate_pending_venture(mysqli $mysqli, int $ventureId): bool
{
    $stmt = $mysqli->prepare("
        UPDATE ventures
           SET status = 'active',
               listing_ends_at = DATE_ADD(NOW(), INTERVAL days_left DAY)
         WHERE id = ? AND status = 'pending_payment'
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $activated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$activated) {
        return false;
    }

    
    
    
    $stmt = $mysqli->prepare("SELECT founder_user_id, title FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($venture) {
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Venture Live 🚀', ?)");
        $msg = "Your listing fee has been received and your Venture '" . $venture['title'] . "' is now live in the gallery.";
        $stmt->bind_param('is', $venture['founder_user_id'], $msg);
        $stmt->execute();
        $stmt->close();
    }

    return true;
}

/**
 * "3/5 required skills matched" — the soft, non-blocking note the founder is shown
 * beside an active-partner application. Returns null when the listing named no
 * skills, because "0/0 matched" says nothing about anybody.
 *
 * Extracted so the two application routes cannot score differently: an ordinary
 * apply on a listing with room, and an application for a vacated ACTIVE seat from
 * the waitlist (which the founder chooses between the same way). Both are judged
 * against the same list, so they must be judged by the same code.
 *
 * Case-insensitive and whitespace-tolerant on both sides, because one is a founder's
 * comma list and the other a partner's profile, typed months apart.
 */
function vh_skill_match_note(?string $requiredSkills, ?string $applicantSkills): ?string
{
    $required = array_filter(array_map('trim', explode(',', strtolower((string)$requiredSkills))));
    if (!$required) {
        return null;
    }
    $applicant = array_filter(array_map('trim', explode(',', strtolower((string)$applicantSkills))));
    $matched = array_intersect($required, $applicant);

    return count($matched) . '/' . count($required) . ' required skills matched';
}

function vh_pledge_limits(array $venture, string $role = 'any'): array
{
    $minTicket = (int)($venture['min_investment'] ?? 0);
    $buckets   = vh_capital_buckets($venture);




    /* Both named roles are bounded by their own bucket; 'any' (the default, used
       where the role genuinely is not known yet) keeps the whole remaining target.
       PASS THE ROLE EXPLICITLY at every call site — defaulting to 'any' on a join
       path is what would quietly restore the unbounded active pledge. */
    $remaining = match ($role) {
        'silent' => $buckets['silent_remaining'],
        'active' => $buckets['active_remaining'],
        default  => $buckets['remaining'],
    };

    return [
        'remaining' => $remaining,
        'min'       => $remaining > 0 ? (int)min(max($minTicket, 1), $remaining) : 0,
        'max'       => $remaining,
        'is_full'   => $remaining <= 0,
        
        
        'buckets'   => $buckets,
    ];
}

/*
 * Meetup mode — offline is a small-group privilege, not a right.
 *
 * A physical meeting only works while everyone can fit in one room, so an Asset
 * runs in "offline allowed" mode up to VH_OFFLINE_MEETUP_MAX_MEMBERS people and
 * flips to online-only the moment an eleventh joins — or the moment it is fully
 * funded, since a closed Asset's meeting is the one every partner attends.
 *
 * Both triggers are DERIVED from columns that already exist (members_count,
 * raised_capital vs target_capital), so there is no stored flag to drift out of
 * step with the memberships it describes — the same rule that keeps
 * raised_silent / raised_active out of the schema.
 *
 * members_count includes the founder, and that is deliberate: the founder sits
 * in the room too, so the cap counts everyone who would have to be there.
 */
/*
 * ── An Asset may not need more than 25 partners ──────────────────────────────
 *
 * The founder does not pick a member count directly; it falls out of the arithmetic.
 * Partner pool = target - the founder's own contribution, and however many times the
 * minimum ticket divides into that pool is how many people have to turn up. So the way
 * to cap the headcount is to put a FLOOR under the minimum ticket.
 *
 * The client's example: 1Cr target, 10L from the founder, so 90L from partners. The 25 is
 * the whole room, FOUNDER INCLUDED — he was explicit about that — so there are 24 partner
 * seats and the ticket floors at 90,00,000 / 24 = 3,75,000. Counting the founder matches
 * `members_count`, which has always included them, and the offline-meetup cap, which counts
 * them because they sit in the room too. His reason, in his words: "to make venture exits
 * and operations easier for members" — every member is somebody who has to be met, paid
 * out and agreed with, and that includes the founder.
 *
 * Derived, never stored. `ventures.max_members` exists and is dead: `create` has written
 * a hardcoded 0 into it for a long time and nothing has ever read it, so the values still
 * sitting in old rows are meaningless. Deriving keeps this in step with the target and
 * contribution the way members_count and raised_silent already are.
 */
/** The whole room, founder included. */
const VH_MAX_MEMBERS = 25;

/** The resolved member ceiling (a settings row may lower it, never raise it). */
function vh_max_members(mysqli $mysqli): int
{
    require_once __DIR__ . '/venture-lifecycle.php';
    $max = (int)vh_lifecycle_setting($mysqli, 'venture_max_members', VH_MAX_MEMBERS);
    return max(2, min(VH_MAX_MEMBERS, $max));
}

/** Seats left for partners once the founder has taken theirs. 25 members = 24 partners. */
function vh_max_partner_slots(int $maxMembers): int
{
    return max(1, $maxMembers - 1);
}

/** What partners have to bring between them: the target less the founder's own money. */
function vh_partner_pool(int $targetCapital, int $founderContribution): int
{
    return max(0, $targetCapital - min(max(0, $founderContribution), max(0, $targetCapital)));
}

/**
 * The smallest minimum-investment that keeps the partner count at or under the ceiling.
 * 0 when the founder is funding the whole thing — there are no partners to count.
 */
function vh_min_investment_floor(int $targetCapital, int $founderContribution, int $maxMembers): int
{
    $pool = vh_partner_pool($targetCapital, $founderContribution);
    if ($pool <= 0 || $maxMembers <= 0) return 0;
    return (int)ceil($pool / vh_max_partner_slots($maxMembers));
}

/** How many partners a given ticket implies — the founder is not one of them. */
function vh_partners_needed(int $targetCapital, int $founderContribution, int $minInvestment): int
{
    $pool = vh_partner_pool($targetCapital, $founderContribution);
    if ($pool <= 0 || $minInvestment <= 0) return 0;
    return (int)ceil($pool / $minInvestment);
}

/**
 * Everyone in the room: the partners a ticket implies, plus the founder. This is the
 * figure the create form shows and the one measured against the ceiling, because the
 * ceiling counts the founder.
 */
function vh_members_needed(int $targetCapital, int $founderContribution, int $minInvestment): int
{
    $partners = vh_partners_needed($targetCapital, $founderContribution, $minInvestment);
    return $partners > 0 ? $partners + 1 : 0;
}

/** One wording, server and client, carrying the client's own stated reason. */
function vh_member_limit_message(int $floor, int $maxMembers): string
{
    return 'Minimum investment per member must be at least ₹' . vh_inr($floor) . '. '
         . 'Maximum Member Limit: ' . $maxMembers . ' (upper limit), to make venture exits '
         . 'and operations easier for members.';
}

const VH_OFFLINE_MEETUP_MAX_MEMBERS = 10;

/** The resolved member ceiling for offline meetups (a settings row may lower it). */
function vh_offline_meetup_max_members(mysqli $mysqli): int
{
    require_once __DIR__ . '/venture-lifecycle.php';
    $max = (int)vh_lifecycle_setting($mysqli, 'venture_offline_meetup_max_members', VH_OFFLINE_MEETUP_MAX_MEMBERS);
    return max(1, min(VH_OFFLINE_MEETUP_MAX_MEMBERS, $max));
}

/**
 * The one place the offline/online decision is made. Everything else — the API
 * guard, the schedule dialog, the tests — reads this.
 *
 * Pure, like vh_capital_buckets(): pass the venture row and the resolved cap.
 */
function vh_meetup_mode(array $venture, int $maxMembers = VH_OFFLINE_MEETUP_MAX_MEMBERS): array
{
    $members = max(0, (int)($venture['members_count'] ?? 0));
    $target  = max(0, (int)($venture['target_capital'] ?? 0));
    $raised  = max(0, (int)($venture['raised_capital'] ?? 0));

    
    
    $fullyFunded = $target > 0 && $raised >= $target;

    /* ONLINE-ONLY IS SWITCHED OFF. "Isko remove hi kar dete ha — offline bhi kar sakta
       ha, online bhi, kyunki 25 members I think kuch bahot zyada nahi ha aur physical ma
       thoda better bhi hoga" — the client, 5 Sep 2026.

       The rule existed on the theory that a physical meeting stops working past ten
       people. The 25-member ceiling shipped since, and a room of 25 is an ordinary
       meeting — so the cap it was protecting against no longer exists, and the client
       would rather have the in-person option than the guard.

       The FUNCTION stays, and so does every caller, the 409 in api/meetups.php and the
       dialog that reads it. Only the answer changed. Deleting the machinery would mean
       rebuilding it if he changes his mind, and it is the one place the decision lives —
       flip $onlineOnly back and the whole guard returns. `fully_funded` is still
       reported because the dialog says something useful with it; it just no longer
       forces the venue. */
    $onlineOnly = false;
    $reason     = '';
    $message    = 'Meetups can be held in person or online — whichever suits the group.';

    return [
        'online_only'  => $onlineOnly,
        'reason'       => $reason,          
        'members'      => $members,
        'max_members'  => $maxMembers,
        'fully_funded' => $fullyFunded,
        'message'      => $message,
    ];
}

function vh_capital_buckets(array $venture): array
{
    $target  = max(0, (int)($venture['target_capital'] ?? 0));
    $raised  = max(0, (int)($venture['raised_capital'] ?? 0));
    
    
    $founder = max(0, min((int)($venture['founder_contribution'] ?? 0), $target));

    $partnerPool   = max(0, $target - $founder);
    $partnerRaised = max(0, $raised - $founder);

    $splitKnown   = array_key_exists('raised_silent', $venture) && $venture['raised_silent'] !== null;
    $raisedSilent = $splitKnown
        ? max(0, (int)$venture['raised_silent'])
        : $partnerRaised;
    $raisedActive = ($splitKnown && ($venture['raised_active'] ?? null) !== null)
        ? max(0, (int)$venture['raised_active'])
        : max(0, $partnerRaised - $raisedSilent);

    
    
    $rawCap    = $venture['silent_capital_limit'] ?? null;
    $capSet    = $rawCap !== null && $rawCap !== '' && (int)$rawCap > 0;
    $silentCap = $capSet ? min((int)$rawCap, $partnerPool) : $partnerPool;

    $activeReserve = max(0, $partnerPool - $silentCap);
    $remaining     = max(0, $target - $raised);

    
    
    $silentRemaining = max(0, min($silentCap - $raisedSilent, $remaining));

    /* The active side is bucketed too, and symmetrically — client, 6 Sep 2026:
     * "maximum investment for active shows 90 lakhs but its 8 lakhs max".
     *
     * It used to be uncapped on the theory that an active partner could draw on
     * silent capacity nobody had taken. But the role card states "ACTIVE CAPITAL
     * ₹0 / ₹8L" and the join form then offered ₹90,00,000 — the same screen making
     * two different promises, and the looser one let a single active partner take
     * the entire raise and shut every silent partner out of a split the founder
     * had deliberately configured.
     *
     * $hasCap, not $capSet: an uncapped listing has activeReserve = 0 (the silent
     * cap IS the whole pool), so bucketing on $capSet alone would bar active
     * partners from every listing without a cap, and from the legacy ones whose
     * cap swallowed the pool. Where no reserve is advertised, nothing is bounded —
     * exactly the behaviour those listings have today.
     *
     * The two buckets sum to the partner pool by construction, so a listing can
     * still reach its target exactly. What it can no longer do is let one side
     * cover for the other's shortfall. */
    $hasCap = $capSet && $activeReserve > 0;
    $activeRemaining = $hasCap
        ? max(0, min($activeReserve - $raisedActive, $remaining))
        : $remaining;

    return [
        'target'           => $target,
        'founder'          => $founder,
        'partner_pool'     => $partnerPool,
        'silent_cap'       => $silentCap,
        'active_reserve'   => $activeReserve,
        'raised_silent'    => $raisedSilent,
        'raised_active'    => $raisedActive,
        'silent_remaining' => $silentRemaining,
        'active_remaining' => $activeRemaining,
        'remaining'        => $remaining,
        'has_cap'          => $hasCap,
        'silent_full'      => $silentRemaining <= 0,
        'active_full'      => $hasCap && $activeRemaining <= 0,
        'split_known'      => $splitKnown,
    ];
}

/**
 * The silent side's ceiling on a listing that accepts BOTH partner types.
 *
 * It stopped being optional on 5 Sep 2026 — "we want it compulsory not optional" — and
 * gained a gap rule: "capital from partners or maximum from silent partners ma min
 * investment per member ka difference hona hi chahiye, zyada bhi ho sakta ha". So the
 * active reserve (pool − cap) has to be at least one whole ticket, or the listing
 * advertises an active role with no room in it for a single person to take.
 *
 * The mirror of that is enforced too, and it is the same defect facing the other way: a
 * cap BELOW one ticket leaves a silent role nobody can ever pledge into, because
 * vh_pledge_limits() bounds a silent pledge by the bucket. Together they mean a
 * both-types listing needs a partner pool of at least two tickets, which is the honest
 * minimum for a venture claiming to want one of each.
 *
 * $existingCap grandfathers an edit. Every listing published before this shipped has a
 * NULL cap, and demanding one before its founder may fix a typo is the trap the equity
 * percentages and the member limit both already taught us. So on `update` a stored NULL
 * may stay NULL; anything the founder actually types is held to the full rule.
 * `create` passes VH_CAP_REQUIRED, since a new listing has no history to protect.
 *
 * Returns [cap|null, error|null]. A cap is only ever null for a non-'both' listing or a
 * grandfathered edit — never as a silent way of saying "that value was no good".
 */
const VH_CAP_REQUIRED = -1;

/**
 * Every column the create form's **Capital** step writes.
 *
 * "Capital page ko complete unedit kar deta ha jese partner type kiya hua ha ... ek baar
 * partner type or capital fill kardi or kisi ne join karliya, ye sections unedit ho
 * jaayenge" — the client, 4 Sep 2026. He was asked explicitly whether a founder may
 * still lower their own contribution, and said no.
 *
 * It replaces a patchwork: target_capital and partner_types froze, while the ticket, the
 * founder contribution and the whole equity table stayed open and were kept honest by a
 * rescale, a snapshot and a floor apiece. Every one of those was a rule somebody had to
 * hold in their head, and the client — reasonably — did not want to. Freezing the page
 * is one rule instead of five, and it is the stricter direction, so nothing a partner
 * agreed to can move underneath them.
 *
 * The list is the columns, not the field ids, because that is what `update` writes and
 * what the stored row hands back. Anything ADDED to the Capital step must be added here
 * too, or it stays quietly editable after a partner has joined.
 */
function vh_capital_step_columns(): array
{
    return [
        // The capital figures themselves.
        'target_capital', 'founder_contribution', 'min_investment', 'silent_capital_limit',
        // The equity and salary table.
        'equity_distribution',
        'founder_equity_percent', 'founder_ops_equity_percent', 'founder_monthly_salary',
        'active_equity_percent', 'active_ops_equity_percent', 'active_monthly_salary',
        'silent_equity_percent',
        // Return, holding period and exit terms — all of them things a partner joined on.
        'expected_roi', 'expected_roi_min', 'expected_roi_max',
        'lockin_period', 'expected_exit_timeline', 'exit_options',
        'early_exit_allowed', 'early_exit_notice_period', 'early_exit_conditions',
        'ownership_transfer_allowed', 'ownership_transfer_approval',
        'profit_distribution_frequency', 'exit_valuation_method', 'exit_valuation_notes',
    ];
}

/** The stored value of every one of those, for an update that must ignore what was posted. */
function vh_frozen_capital_terms(mysqli $mysqli, int $ventureId): array
{
    $cols = vh_capital_step_columns();
    $sql  = 'SELECT `' . implode('`, `', $cols) . '` FROM ventures WHERE id = ?';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return $row;
}

/**
 * Resolve the silent-partner cap for a create or an update. Returns [cap, error]:
 * exactly one is ever non-null, and a null cap with a null error means "no cap",
 * which is a legitimate answer for the two cases below.
 *
 * The cap is COMPULSORY on a both-types listing (client, 4 Sep 2026) and must
 * leave the active side room: `cap >= ticket` and `pool - cap >= ticket`, so such
 * a listing needs a pool of at least two tickets. Without the first, a silent
 * pledge could never fit the bucket; without the second, the listing advertises
 * an active role with no room for a single person in it.
 *
 * NULL means "no cap" in exactly two places:
 *   - `$partnerType !== 'both'` — a silent-only listing has nothing to cap, and
 *     an active-only one has no silent side at all;
 *   - a GRANDFATHERED edit — `$existingCap` is the stored value, and the
 *     VH_CAP_REQUIRED sentinel distinguishes "this is a create" from "stored NULL".
 *     Every listing published before the rule has NULL, and demanding a cap before
 *     its founder may fix a typo is the trap the equity percentages and the member
 *     limit both already taught us. A stored NULL may stay NULL; anything typed is
 *     held to the full rule; a listing that HAS a cap may not blank it.
 *
 * $alreadyRaisedSilent is checked FIRST, ahead of every sizing rule and before the
 * ticket is consulted. It is the one check here protecting money somebody actually
 * committed, and a caller that omits $minInvestment must not thereby skip it — an
 * early return once did exactly that.
 */
function vh_resolve_silent_cap(
    array $input,
    int $targetCapital,
    int $founderContribution,
    string $partnerType,
    int $alreadyRaisedSilent = 0,
    int $minInvestment = 0,
    int $existingCap = VH_CAP_REQUIRED
): array {
    // Nothing to cap: no silent side (active-only), or no active side to protect
    // from it (silent-only). Either way the bucket is the whole partner pool.
    if ($partnerType !== 'both') {
        return [null, null];
    }

    $raw = $input['silent_capital_limit'] ?? null;
    $blank = ($raw === null || $raw === '' || (int)$raw <= 0);

    $partnerPool = max(0, $targetCapital - max(0, min($founderContribution, $targetCapital)));
    $ticket      = max(0, $minInvestment);
    $maxCap      = $partnerPool - $ticket;

    if ($blank) {
        // Grandfathered: this listing already had no cap, so it may keep having none.
        if ($existingCap !== VH_CAP_REQUIRED && $existingCap <= 0) {
            return [null, null];
        }
        return [null, 'Enter the total contribution you will accept from silent partners. '
            . 'It is required on an Asset that takes both partner types, because it is what '
            . 'reserves the rest of your target for active partners.'];
    }

    $cap = (int)$raw;

    /* Money already in comes FIRST, before any of the sizing rules. It does not depend
       on the ticket, and a caller that omits the ticket must not thereby skip it — this
       is the one check here that protects a commitment somebody has actually made. */
    if ($cap < $alreadyRaisedSilent) {
        return [null, 'Silent partners have already committed ₹' . vh_inr($alreadyRaisedSilent)
            . ' to this Asset, so the silent partner limit cannot be set below that.'];
    }

    // Without a ticket there is nothing to measure a gap against; the min-investment
    // check upstream has already refused, so just take the old "cap at or above the
    // pool means no cap" behaviour rather than inventing an error about a second field.
    if ($ticket <= 0) {
        return $cap >= $partnerPool ? [null, null] : [$cap, null];
    }

    if ($partnerPool < $ticket * 2) {
        return [null, 'An Asset taking both partner types needs room for at least one of each, '
            . 'so partners must be able to bring at least ₹' . vh_inr($ticket * 2)
            . ' between them. Raise your total capital, lower your own contribution, or lower '
            . 'the minimum investment per member.'];
    }

    if ($cap > $maxCap) {
        return [null, 'Silent partners can bring at most ₹' . vh_inr($maxCap)
            . '. Partners bring ₹' . vh_inr($partnerPool) . ' in total, and at least ₹'
            . vh_inr($ticket) . ' — the minimum investment per member — has to stay reserved '
            . 'for active partners.'];
    }

    if ($cap < $ticket) {
        return [null, 'The silent limit must be at least ₹' . vh_inr($ticket)
            . ', the minimum investment per member. Below that no silent partner could '
            . 'ever join, because their smallest allowed pledge would exceed the limit.'];
    }

    return [$cap, null];
}

function vh_capital_split(mysqli $mysqli, int $ventureId, array $venture): array
{
    $stmt = $mysqli->prepare("
        SELECT m.role, COALESCE(SUM(m.invested_amount), 0) AS total
        FROM venture_members m
        JOIN ventures v ON v.id = m.venture_id
        WHERE m.venture_id = ?
          AND (v.founder_user_id IS NULL OR m.user_id <> v.founder_user_id)
        GROUP BY m.role
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $venture['raised_silent'] = 0;
    $venture['raised_active'] = 0;
    foreach ($rows as $row) {
        $key = $row['role'] === 'active' ? 'raised_active' : 'raised_silent';
        $venture[$key] = (int)$row['total'];
    }

    return vh_apply_showcase_split($venture);
}

function vh_apply_showcase_split(array $venture): array
{
    if (empty($venture['is_showcase'])) {
        return $venture;
    }

    $silent = $venture['showcase_raised_silent'] ?? null;
    $active = $venture['showcase_raised_active'] ?? null;
    if ($silent === null && $active === null) {
        return $venture;
    }

    $venture['raised_silent'] = max(0, (int)$silent);
    $venture['raised_active'] = max(0, (int)$active);
    return $venture;
}

function vh_attach_capital_split(mysqli $mysqli, array &$ventures): void
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

    $idList = array_keys($ids);
    $placeholders = implode(',', array_fill(0, count($idList), '?'));

    $stmt = $mysqli->prepare("
        SELECT m.venture_id, m.role, COALESCE(SUM(m.invested_amount), 0) AS total
        FROM venture_members m
        JOIN ventures v ON v.id = m.venture_id
        WHERE m.venture_id IN ($placeholders)
          AND (v.founder_user_id IS NULL OR m.user_id <> v.founder_user_id)
        GROUP BY m.venture_id, m.role
    ");
    $stmt->bind_param(str_repeat('i', count($idList)), ...$idList);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $split = [];
    foreach ($rows as $row) {
        $vid = (int)$row['venture_id'];
        if (!isset($split[$vid])) {
            $split[$vid] = ['raised_silent' => 0, 'raised_active' => 0];
        }
        $key = $row['role'] === 'active' ? 'raised_active' : 'raised_silent';
        $split[$vid][$key] = (int)$row['total'];
    }

    foreach ($ventures as &$v) {
        $vid = (int)($v['id'] ?? 0);
        
        
        
        $v['raised_silent'] = $split[$vid]['raised_silent'] ?? 0;
        $v['raised_active'] = $split[$vid]['raised_active'] ?? 0;

        
        
        $v = vh_apply_showcase_split($v);

        
        
        
        $v['capital_buckets'] = vh_capital_buckets($v);
    }
    unset($v);
}

function vh_validate_pledge(array $venture, int $amount, string $role = 'silent'): ?string
{
    $l = vh_pledge_limits($venture, $role);
    $b = $l['buckets'];

    
    
    
    
    $cappedBySilent = $role === 'silent' && $b['has_cap'] && $b['silent_remaining'] < $b['remaining'];

    if ($l['is_full']) {
        if ($role === 'silent' && $b['has_cap'] && $b['remaining'] > 0) {
            return 'This Asset has taken its full ₹' . vh_inr($b['silent_cap']) . ' of silent (capital-only) partnership, '
                 . 'so the silent side is now closed. The remaining ₹' . vh_inr($b['remaining']) . ' is reserved for active '
                 . 'partners, who join the team and work in the business — apply for that role instead.';
        }
        return 'This Asset has reached its funding target and is not accepting further commitments.';
    }
    if ($amount < $l['min']) {
        return $l['min'] < (int)($venture['min_investment'] ?? 0)


            ? 'Only ₹' . vh_inr($l['remaining']) . ' of this Asset is still available to silent partners, so that is the exact amount to commit.'
            : 'The minimum investment for this Asset is ₹' . vh_inr($l['min']) . '.';
    }
    if ($amount > $l['max']) {
        return $cappedBySilent
            ? 'Silent partners can bring at most ₹' . vh_inr($b['silent_cap']) . ' to this Asset and ₹'
              . vh_inr($b['raised_silent']) . ' is already committed, so ₹' . vh_inr($l['remaining'])
              . ' is the most you can commit as a silent partner. The rest of the target is reserved for active partners.'
            : 'This Asset only needs ₹' . vh_inr($l['remaining']) . ' more to reach its target, so that is the most you can commit.';
    }
    return null;
}

/**
 * An active-partner applicant's WhatsApp number, reduced to digits.
 *
 * The founder has no way to ask a follow-up question before deciding — the group chat
 * is members-only — so the number exists to be *dialled*, which means it has to survive
 * as something a wa.me link can be built from. Everything a person might type around
 * the digits is dropped: spaces, dashes, brackets, a leading + or 00.
 *
 * India is assumed only where the input is ambiguous: a bare 10-digit number starting
 * 6-9 is a local mobile and gets 91. A number that already carries a country code is
 * left alone, so this does not quietly break for a founder or partner abroad.
 *
 * Returns null when what is left cannot be a mobile number. Null is a real answer —
 * see vh_whatsapp_link(), which draws nothing rather than a dead link.
 */
function vh_normalize_whatsapp(?string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if ($digits === '') return null;

    // 00 is the other way of writing +, and some people type a leading 0 out of habit.
    if (strncmp($digits, '00', 2) === 0) $digits = substr($digits, 2);
    if (strlen($digits) === 11 && $digits[0] === '0') $digits = substr($digits, 1);

    // A bare Indian mobile: 10 digits opening 6-9. Anything longer already has a code.
    if (preg_match('/^[6-9]\d{9}$/', $digits)) $digits = '91' . $digits;

    // Shortest real E.164 subscriber numbers are 8 digits plus a code; 15 is the cap.
    return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : null;
}

/**
 * The same number written for a human to read: "+91 98765 43210".
 *
 * What is stored is bare digits, because that is what wa.me needs — but a founder
 * glancing at an application should not have to parse "919876543210". Only the Indian
 * shape is grouped; any other country code is left as "+<digits>" rather than guessed at.
 */
function vh_whatsapp_display(?string $stored): ?string
{
    $n = vh_normalize_whatsapp($stored);
    if ($n === null) return null;
    if (strlen($n) === 12 && strncmp($n, '91', 2) === 0) {
        return '+91 ' . substr($n, 2, 5) . ' ' . substr($n, 7);
    }
    return '+' . $n;
}

/** A wa.me link, or null when there is no usable number. Never a dead link. */
function vh_whatsapp_link(?string $stored): ?string
{
    $n = vh_normalize_whatsapp($stored);
    return $n === null ? null : 'https://wa.me/' . $n;
}

function vh_venture_openness(array $venture): array
{
    $status  = (string)($venture['status'] ?? 'active');
    $types   = (string)($venture['partner_types'] ?? 'both');
    $limits  = vh_pledge_limits($venture);
    $buckets = $limits['buckets'];

    
    $live = $status === 'active' && empty($venture['is_showcase']);

    $listsSilent = in_array($types, ['silent', 'both'], true);
    $listsActive = in_array($types, ['active', 'both'], true);

    
    
    
    
    
    
    
    $isFull = (int)($venture['target_capital'] ?? 0) > 0 && $limits['is_full'];

    
    
    
    
    
    
    $silentCapped = !$isFull && $buckets['has_cap'] && $buckets['silent_full'];
    $silentFull   = $isFull || $silentCapped;
    $acceptsSilent = $live && $listsSilent && !$silentFull;
    // A fully funded Asset takes no more applications either.
    //
    // This used to be `$live && $listsActive` alone, on the theory that an active
    // partner brings work rather than capital, so the team could stay open after the
    // money closed. In practice it left "Apply as Active Partner" sitting on a finished
    // Asset forever, and every such application was one the founder could only reject —
    // the seat it would fill does not exist. The client asked for it gone, from the card
    // and from the Asset page both.
    //
    // The silent-capped case is deliberately NOT affected: that Asset is not funded, the
    // active reserve is real money still wanted, and closing it would hide the one route
    // a visitor can still take.
    //
    // ...and now the mirror of it. Since the active side is bucketed too, its
    // reserve can fill while silent capital is still wanted. Such an Asset must
    // stop taking active applications for the same reason a funded one does: the
    // seat that application would fill does not exist, so the founder could only
    // ever reject it. `active_capped` is the state; the silent route stays open.
    $activeCapped  = !$isFull && $buckets['has_cap'] && $buckets['active_full'];
    $acceptsActive = $live && $listsActive && !$isFull && !$activeCapped;

    return [
        'status'         => $status,
        'is_full'        => $isFull,
        'remaining'      => $limits['remaining'],
        'lists_silent'   => $listsSilent,
        'lists_active'   => $listsActive,
        'accepts_silent' => $acceptsSilent,
        'accepts_active' => $acceptsActive,
        
        
        'silent_capped'  => $silentCapped,
        'active_capped'  => $activeCapped,
        'buckets'        => $buckets,
        
        
        
        
        
        'active_only'    => $acceptsActive && ($isFull || $silentCapped),
        // Nothing left for anyone. Hidden from Browse.
        'is_closed'      => !$acceptsSilent && !$acceptsActive,
    ];
}

/**
 * The same answer, computed as if the listing were LIVE — for a sample listing's
 * walkthrough, and for nothing else.
 *
 * A showcase sets $live = false above, which is exactly what makes api/ventures.php's
 * `join` and api/payments.php's `initiate` refuse one. Those gates are untouched and
 * must stay untouched: a sample can never accrue a member, a payment or a refund.
 *
 * What $live = false ALSO did was close every role card on the join page, so the
 * preview could not leave step 1 — the client's own active-only example listing read
 * "NOT ACCEPTING" on the single role it lists (9 Sep 2026). Turning people away at the
 * first step teaches them nothing about the flow, which is the whole point of a sample.
 *
 * Everything else is the listing's own truth: `vh_capital_split()` has already applied
 * the showcase split by the time this is called, so the buckets, the cap and `is_full`
 * are the figures the admin published. Only "is it live" is overridden.
 */
function vh_venture_openness_preview(array $venture): array
{
    if (empty($venture['is_showcase'])) {
        return vh_venture_openness($venture);
    }

    $venture['is_showcase'] = 0;
    return vh_venture_openness($venture);
}

function vh_role_closed_message(array $venture, string $role): string
{
    $o = vh_venture_openness($venture);

    if ($role === 'active') {
        if (!$o['lists_active']) {
            return 'This Asset is only looking for silent (capital) partners, so there is no active-partner role to apply for.';
        }
        // The mirror of the silent_capped message below: name the figure, and point
        // at the route that IS still open rather than a flat "not accepting".
        if ($o['active_capped']) {
            $b = $o['buckets'];
            return 'The active-partner side of this Asset is full — it has taken the full ₹' . vh_inr($b['active_reserve'])
                 . ' the founder reserved for partners who work in the business. The remaining ₹' . vh_inr($b['silent_remaining'])
                 . ' is open to silent partners, who invest capital only: join as one of those instead.';
        }
        return 'This Asset is not accepting new partners at the moment.';
    }

    // Silent.
    if (!$o['lists_silent']) {
        return 'This Asset is only looking for active partners, who join the team and work in the business. '
             . 'There is no silent (capital-only) role on this listing.';
    }
    
    
    
    
    if ($o['silent_capped']) {
        $b = $o['buckets'];
        return 'Silent partnership on this Asset is full — it has taken the full ₹' . vh_inr($b['silent_cap'])
             . ' the founder opened to capital-only partners. The remaining ₹' . vh_inr($b['remaining'])
             . ' is reserved for active partners, who join the team and work in the business: apply for that role instead.';
    }
    if ($o['is_full']) {
        return $o['accepts_active']
            ? 'This Asset has raised its full target of ₹' . vh_inr((int)($venture['target_capital'] ?? 0)) . ', '
              . 'so it is no longer taking silent partners — there is no capital left to commit. '
              . 'It is still looking for active partners, who join the team rather than invest: apply for that role instead.'
            : 'This Asset has raised its full target of ₹' . vh_inr((int)($venture['target_capital'] ?? 0)) . ' and is now closed to new partners.';
    }
    return 'This Asset is not accepting new partners at the moment.';
}

function vh_active_pledge_for(array $venture, int $requested): int
{
    
    
    
    $limits = vh_pledge_limits($venture, 'active');
    return max(0, min($requested, $limits['max']));
}

/*
 * ── A partner's equity is frozen at the terms they joined on ──────────────────
 *
 * `venture_members` used to hold only `invested_amount` and `role`. Every equity
 * percentage lives on the `ventures` row, which the founder may edit at any time,
 * so a partner's share was not a record of what they agreed to — it was recomputed
 * live from whatever the listing happened to say. Dropping `min_investment` from
 * 50,000 to 25,000 moved somebody who had paid 50,000 from 6% to 12%, because
 * VH.equity divides the pledge by the CURRENT minimum.
 *
 * The founder legitimately needs to keep editing those fields — fewer days left, so
 * lower the ticket; good partners arriving, so lower their own contribution — so the
 * answer is not to lock the listing. It is to give every member their own copy of the
 * terms, taken once, at the moment they join.
 *
 * These are exactly the five inputs VH.equity.forPledge() reads, so the snapshot goes
 * back through the SAME calculator instead of a second implementation of the rule.
 */

/** The equity terms currently on offer, as they should be stamped on a new member. */
function vh_equity_terms(array $venture): array
{
    $num = static function ($x) {
        return ($x === null || $x === '') ? null : (float)$x;
    };
    return [
        'min_investment'     => (int)($venture['min_investment'] ?? 0),
        'active_percent'     => $num($venture['active_equity_percent'] ?? null),
        'active_ops_percent' => $num($venture['active_ops_equity_percent'] ?? null),
        'silent_percent'     => $num($venture['silent_equity_percent'] ?? null),
        'partner_types'      => (string)($venture['partner_types'] ?? 'both'),
    ];
}

/**
 * Stamp the venture's current terms onto one member row.
 *
 * Guarded on `equity_min_investment IS NULL`, so it is idempotent: a duplicated PayU
 * callback or a re-run cannot rewrite a snapshot that was already taken, which is the
 * whole point of it. Same shape as the guarded UPDATEs elsewhere in this codebase.
 */
function vh_snapshot_member_equity(mysqli $mysqli, int $ventureId, int $userId, array $venture): void
{
    $t = vh_equity_terms($venture);
    $stmt = $mysqli->prepare("
        UPDATE venture_members
           SET equity_min_investment     = ?,
               equity_active_percent     = ?,
               equity_active_ops_percent = ?,
               equity_silent_percent     = ?,
               equity_partner_types      = ?,
               equity_agreed_at          = NOW()
         WHERE venture_id = ? AND user_id = ? AND equity_min_investment IS NULL
    ");
    $stmt->bind_param(
        "idddsii",
        $t['min_investment'], $t['active_percent'], $t['active_ops_percent'],
        $t['silent_percent'], $t['partner_types'], $ventureId, $userId
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Shape one member row's snapshot for the browser, under the same key names
 * VH.equity.forPledge() already reads off a venture. That is deliberate: the client
 * hands this object straight to the existing calculator, so a member's frozen share
 * and a visitor's quoted share can never be worked out two different ways.
 *
 * Returns null when the row carries no snapshot, which the caller reads as "fall back
 * to the venture's live terms" — the pre-existing behaviour for any row the migration
 * did not reach.
 */
function vh_member_equity_terms(?array $row): ?array
{
    if (!$row || ($row['equity_min_investment'] ?? null) === null) return null;
    return [
        'min_investment'            => (int)$row['equity_min_investment'],
        'active_equity_percent'     => $row['equity_active_percent'],
        'active_ops_equity_percent' => $row['equity_active_ops_percent'],
        'silent_equity_percent'     => $row['equity_silent_percent'],
        'partner_types'             => $row['equity_partner_types'] ?: 'both',
        'agreed_at'                 => $row['equity_agreed_at'] ?? null,
    ];
}

/**
 * Has the founder edited the listing away from what this member agreed to?
 *
 * Only used to caption the frozen figure ("the listing now quotes X"), never to change
 * it. A member whose terms still match sees no caption at all.
 */
function vh_member_equity_drifted(?array $terms, array $venture): bool
{
    if (!$terms) return false;
    $same = static function ($a, $b) {
        if ($a === null || $a === '') return ($b === null || $b === '');
        if ($b === null || $b === '') return false;
        return abs((float)$a - (float)$b) < 0.005;
    };
    return (int)$terms['min_investment'] !== (int)($venture['min_investment'] ?? 0)
        || !$same($terms['active_equity_percent'],     $venture['active_equity_percent'] ?? null)
        || !$same($terms['active_ops_equity_percent'], $venture['active_ops_equity_percent'] ?? null)
        || !$same($terms['silent_equity_percent'],     $venture['silent_equity_percent'] ?? null);
}

function vh_grant_membership(mysqli $mysqli, array $ctx): array
{
    $userId        = (int)($ctx['user_id'] ?? 0);
    $ventureId     = (int)($ctx['venture_id'] ?? 0);
    $role          = ($ctx['role'] ?? 'silent') === 'active' ? 'active' : 'silent';
    $invested      = (int)($ctx['invested_amount'] ?? 0);
    $feeAmount     = (int)($ctx['fee_amount'] ?? 0);
    $txnId         = (string)($ctx['txn_id'] ?? '');
    $applicationId = isset($ctx['application_id']) ? (int)$ctx['application_id'] : 0;

    if (!$userId || !$ventureId) {
        return ['success' => false, 'message' => 'Missing membership details.'];
    }

    $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ventureId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $alreadyMember = $stmt->num_rows > 0;
    $stmt->close();

    if ($alreadyMember) {
        return [
            'success' => true,
            'already_member' => true,
            'message' => 'You are already a member of this Asset.',
        ];
    }

    $stmt = $mysqli->prepare("
        SELECT title, raised_capital, target_capital, members_count,
               min_investment, partner_types,
               active_equity_percent, active_ops_equity_percent, silent_equity_percent
          FROM ventures WHERE id = ?
    ");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$venture) {
        return ['success' => false, 'message' => 'Asset not found.'];
    }

    $stmt = $mysqli->prepare("INSERT INTO venture_members (venture_id, user_id, role, invested_amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $ventureId, $userId, $role, $invested);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to record the membership.'];
    }
    $stmt->close();

    // Freeze the terms this partner just bought on. Every paid route into a membership
    // — silent join, active confirmation, the PayU callback and a claimed waitlist seat
    // — comes through here, so there is exactly one place a snapshot is taken, the same
    // reason this function owns the members row in the first place.
    vh_snapshot_member_equity($mysqli, $ventureId, $userId, $venture);

    // Venture running totals.
    $newRaised  = (int)$venture['raised_capital'] + $invested;
    $newMembers = (int)$venture['members_count'] + 1;
    $newProg    = (int)$venture['target_capital'] > 0
        ? (int)(($newRaised / (int)$venture['target_capital']) * 100)
        : 0;

    $stmt = $mysqli->prepare("UPDATE ventures SET raised_capital = ?, members_count = ?, progress_percent = ? WHERE id = ?");
    $stmt->bind_param("iiii", $newRaised, $newMembers, $newProg, $ventureId);
    $stmt->execute();
    $stmt->close();

    
    if ($applicationId > 0) {
        $stmt = $mysqli->prepare("UPDATE venture_applications SET status = 'confirmed', decided_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $applicationId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $notifMsg = "₹" . vh_inr($feeAmount) . " commitment fee charged for " . $venture['title']
        . ". Your pledged investment of ₹" . vh_inr($invested)
        . " is recorded and will be settled offline with the founder. Transaction ID: $txnId.";
    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'payment', 'Payment Confirmed 💰', ?)");
    $stmt->bind_param("is", $userId, $notifMsg);
    $stmt->execute();
    $stmt->close();

    
    if (!empty($user['email'])) {
        $inner = '<h1 style="margin:0 0 8px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:22px;color:#0f172a;">Payment confirmed ✅</h1>'
            . '<p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;color:#475569;">Hi ' . htmlspecialchars($user['name']) . ', you\'ve successfully joined <strong>' . htmlspecialchars($venture['title']) . '</strong> as a ' . htmlspecialchars($role) . ' partner.</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;font-size:14.5px;color:#334155;background:#f2f6fb;border-radius:10px;">'
            . '<tr><td style="padding:14px 18px 4px 18px;color:#94a3b8;">Commitment fee charged</td></tr>'
            . '<tr><td style="padding:0 18px 14px 18px;font-family:\'Outfit\',Arial,sans-serif;font-size:20px;font-weight:700;color:#0f172a;">₹' . vh_inr($feeAmount) . '</td></tr>'
            . '<tr><td style="padding:0 18px 14px 18px;border-top:1px solid #e2e8f0;padding-top:14px;">Pledged investment: <strong>₹' . vh_inr($invested) . '</strong> (settled offline with the founder)</td></tr>'
            . '<tr><td style="padding:0 18px 14px 18px;">Transaction ID: <strong>' . htmlspecialchars($txnId) . '</strong></td></tr>'
            . '</table>'
            . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#94a3b8;">You can track this Asset and its updates anytime from your dashboard.</p>';
        $emailBody = renderEmailTemplate($inner, [
            'heading'   => 'Payment Confirmed',
            'preheader' => 'You joined ' . $venture['title'] . ' — ₹' . vh_inr($feeAmount) . ' commitment fee charged.',
        ]);
        @sendEmail($user['email'], 'Payment Confirmed — You joined ' . $venture['title'], $emailBody);
    }

    return [
        'success'        => true,
        'already_member' => false,
        'venture_title'  => $venture['title'],
        'message'        => 'Successfully joined the Asset!',
    ];
}
