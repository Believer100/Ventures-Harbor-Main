<?php

require_once __DIR__ . '/session.php';

const VH_SWEEP_INTERVAL = 300;

const VH_LISTING_MIN_DAYS   = 1;
const VH_LISTING_MAX_DAYS   = 30;

const VH_EXTENSION_MIN_DAYS = 1;
const VH_EXTENSION_MAX_DAYS = 10;

const VH_DECISION_GRACE_DAYS = 2;

const VH_EXPIRY_REMINDER_INTERVAL_HOURS = 23;

const VH_REMINDER_EMAIL_BUDGET_SECONDS = 10;

const VH_SHOWCASE_MAX = 3;

function vh_showcase_count(mysqli $mysqli): int
{
    $res = $mysqli->query("SELECT COUNT(*) AS n FROM ventures WHERE is_showcase = 1");
    $row = $res ? $res->fetch_assoc() : null;
    return (int)($row['n'] ?? 0);
}

/** Whether another sample listing may be published. */
function vh_can_add_showcase(mysqli $mysqli): bool
{
    return vh_showcase_count($mysqli) < VH_SHOWCASE_MAX;
}

function vh_max_listing_days(mysqli $mysqli): int
{
    $days = (int)vh_lifecycle_setting($mysqli, 'venture_max_listing_days', VH_LISTING_MAX_DAYS);
    return max(VH_LISTING_MIN_DAYS, min(VH_LISTING_MAX_DAYS, $days));
}

/** The resolved ceiling for the one extension. */
function vh_max_extension_days(mysqli $mysqli): int
{
    $days = (int)vh_lifecycle_setting($mysqli, 'venture_max_extension_days', VH_EXTENSION_MAX_DAYS);
    return max(VH_EXTENSION_MIN_DAYS, min(VH_EXTENSION_MAX_DAYS, $days));
}

function vh_decision_grace_days(mysqli $mysqli): int
{
    return max(1, (int)vh_lifecycle_setting($mysqli, 'venture_decision_grace_days', VH_DECISION_GRACE_DAYS));
}

const VH_CANCEL_EMAIL_BUDGET_SECONDS = 10;

function vh_lifecycle_setting(mysqli $mysqli, string $key, $default)
{
    $stmt = $mysqli->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['setting_value'] === null || $row['setting_value'] === '') {
        return $default;
    }
    return $row['setting_value'];
}

function vh_generate_txn_id(mysqli $mysqli): string
{
    $stmt = $mysqli->prepare("SELECT id FROM transactions WHERE txn_id = ?");
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = 'VH' . random_int(10000000, 99999999);
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->free_result();
        if (!$taken) {
            $stmt->close();
            return $candidate;
        }
    }
    $stmt->close();
    
    
    return 'VH' . substr((string)microtime(true), -8) . random_int(10, 99);
}

function vh_notify(mysqli $mysqli, int $userId, string $type, string $title, string $message): void
{
    $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $type, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function vh_cancel_venture(mysqli $mysqli, int $ventureId, string $cancelledBy, ?string $reason = null): array
{
    $stmt = $mysqli->prepare("SELECT id, title, status, founder_user_id FROM ventures WHERE id = ?");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$venture) {
        return ['success' => false, 'message' => 'Asset not found.'];
    }
    if ($venture['status'] === 'cancelled') {
        return ['success' => false, 'message' => 'This Asset is already cancelled.'];
    }

    $reason = $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null;

    
    
    
    $stmt = $mysqli->prepare("
        UPDATE ventures
        SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ?, days_left = 0
        WHERE id = ? AND status <> 'cancelled'
    ");
    $stmt->bind_param('ssi', $cancelledBy, $reason, $ventureId);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$claimed) {
        return ['success' => false, 'message' => 'This Asset is already cancelled.'];
    }

    
    
    
    
    
    $stmt = $mysqli->prepare("
        SELECT a.id, a.user_id FROM venture_applications a
        WHERE a.venture_id = ? AND a.status IN ('pending', 'selected')
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $openApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($openApplications) {
        $stmt = $mysqli->prepare("
            UPDATE venture_applications SET status = 'cancelled', decided_at = NOW()
            WHERE venture_id = ? AND status IN ('pending', 'selected')
        ");
        $stmt->bind_param('i', $ventureId);
        $stmt->execute();
        $stmt->close();

        foreach ($openApplications as $application) {
            vh_notify(
                $mysqli,
                (int)$application['user_id'],
                'venture',
                'Application Closed',
                'The Asset "' . $venture['title'] . '" has been cancelled, so your application is now closed. '
                    . 'Nothing was charged to you at any point.'
            );
        }
    }

    $refunds = vh_initiate_cancellation_refunds($mysqli, $ventureId, (string)$venture['title'], $reason);

    if (!empty($venture['founder_user_id'])) {
        $founderMsg = $cancelledBy === 'founder'
            ? 'You cancelled your Venture "' . $venture['title'] . '".'
            : 'Your Venture "' . $venture['title'] . '" has been cancelled automatically. ' . ($reason ?: '');
        if ($refunds['count'] > 0) {
            $founderMsg .= ' ' . $refunds['count'] . ' partner(s) have had their commitment fee '
                . '(₹' . vh_inr($refunds['total']) . ' in total) queued for refund by the Ventures Harbor team. '
                . 'Any investment pledged to you offline should be settled directly with them.';
        }
        vh_notify($mysqli, (int)$venture['founder_user_id'], 'venture', 'Venture Cancelled', trim($founderMsg));
    }

    return [
        'success'       => true,
        'venture_id'    => $ventureId,
        'title'         => $venture['title'],
        'refund_count'  => $refunds['count'],
        'refund_total'  => $refunds['total'],
        'message'       => 'Asset cancelled.',
    ];
}

function vh_initiate_cancellation_refunds(mysqli $mysqli, int $ventureId, string $ventureTitle, ?string $reason = null): array
{
    
    
    
    
    
    
    
    
    
    $stmt = $mysqli->prepare("
        SELECT t.user_id,
               SUM(t.fee_amount)       AS fee_total,
               SUM(t.principal_amount) AS principal_total,
               u.name  AS user_name,
               u.email AS user_email
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        WHERE t.venture_id = ?
          AND t.type = 'venture_investment'
          AND t.status = 'completed'
          AND t.fee_amount > 0
          AND EXISTS (
                SELECT 1 FROM venture_members m
                WHERE m.venture_id = t.venture_id AND m.user_id = t.user_id
              )
          AND NOT EXISTS (
                SELECT 1 FROM transactions r
                WHERE r.venture_id = t.venture_id
                  AND r.user_id = t.user_id
                  AND r.type = 'refund_request'
              )
        GROUP BY t.user_id, u.name, u.email
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $partners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$partners) {
        return ['count' => 0, 'total' => 0];
    }

    $insert = $mysqli->prepare("
        INSERT INTO transactions
            (user_id, venture_id, venture_name, amount, principal_amount, fee_amount,
             type, status, refund_type, txn_id, payment_gateway)
        VALUES (?, ?, ?, ?, ?, ?, 'refund_request', 'pending', 'venture_cancelled', ?, 'manual')
    ");

    $emailsAllowedUntil = microtime(true) + VH_CANCEL_EMAIL_BUDGET_SECONDS;
    $count = 0;
    $total = 0;

    foreach ($partners as $partner) {
        $userId    = (int)$partner['user_id'];
        $fee       = (int)$partner['fee_total'];
        $principal = (int)$partner['principal_total'];
        $txnId     = vh_generate_txn_id($mysqli);

        $insert->bind_param('iisiiis', $userId, $ventureId, $ventureTitle, $fee, $principal, $fee, $txnId);
        if (!$insert->execute()) {
            continue;
        }

        $count++;
        $total += $fee;

        vh_notify(
            $mysqli,
            $userId,
            'payment',
            'Refund Started 💸',
            'The Asset "' . $ventureTitle . '" has been cancelled' . ($reason ? ' — ' . $reason : '') . '. '
                . 'Your commitment fee of ₹' . vh_inr($fee) . ' is being refunded to you; you do not need to request it. '
                . 'Please add your bank details on your Payment Statement page so the team can transfer it.'
        );

        if (!empty($partner['user_email']) && microtime(true) < $emailsAllowedUntil) {
            vh_send_cancellation_refund_email(
                (string)$partner['user_email'],
                (string)$partner['user_name'],
                $ventureTitle,
                $fee,
                $principal,
                $reason
            );
        }
    }

    $insert->close();

    return ['count' => $count, 'total' => $total];
}

function vh_send_cancellation_refund_email(string $email, string $name, string $ventureTitle, int $fee, int $principal, ?string $reason): void
{
    if (!function_exists('sendEmail') || !function_exists('renderEmailTemplate')) {
        require_once __DIR__ . '/mailer.php';
    }

    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeVenture = htmlspecialchars($ventureTitle, ENT_QUOTES, 'UTF-8');
    $safeReason  = $reason ? htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') : '';

    $inner = '<h1 style="margin:0 0 8px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:22px;color:#0f172a;">This Asset has been cancelled</h1>'
        . '<p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;color:#475569;">Hi ' . $safeName . ', <strong>' . $safeVenture . '</strong> has been cancelled'
        . ($safeReason ? ' — ' . $safeReason : '') . '.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;font-size:14.5px;color:#334155;background:#f2f6fb;border-radius:10px;">'
        . '<tr><td style="padding:14px 18px 4px 18px;color:#94a3b8;">Commitment fee being refunded to you</td></tr>'
        . '<tr><td style="padding:0 18px 14px 18px;font-family:\'Outfit\',Arial,sans-serif;font-size:20px;font-weight:700;color:#0f172a;">₹' . vh_inr($fee) . '</td></tr>'
        . '<tr><td style="padding:0 18px 14px 18px;border-top:1px solid #e2e8f0;">Your pledged investment of <strong>₹' . vh_inr($principal) . '</strong> was never charged online — it was only ever recorded for tracking and settled directly with the founder.</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 10px 0;font-size:14.5px;line-height:1.6;color:#334155;"><strong>You do not need to request this refund</strong> — it has already been opened for you. '
        . 'To receive it, sign in and add your bank details on your <strong>Payment Statement</strong> page.</p>'
        . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#94a3b8;">Refunds are transferred manually by the Ventures Harbor team once your bank details are on file.</p>';

    $body = renderEmailTemplate($inner, [
        'heading'   => 'Asset Cancelled',
        'preheader' => $ventureTitle . ' was cancelled — your ₹' . vh_inr($fee) . ' commitment fee is being refunded.',
    ]);

    @sendEmail($email, 'Asset cancelled — your ₹' . vh_inr($fee) . ' commitment fee is being refunded', $body);
}

function vh_send_expiry_reminder(
    mysqli $mysqli,
    int $ventureId,
    string $title,
    int $founderId,
    int $secondsLeft,
    int $remindersAlreadySent,
    int $graceDays,
    bool $mayEmail = true
): bool {
    $stmt = $mysqli->prepare("
        UPDATE ventures
        SET expiry_reminders_sent = expiry_reminders_sent + 1, last_expiry_reminder_at = NOW()
        WHERE id = ? AND status = 'expired' AND expiry_reminders_sent = ?
    ");
    $stmt->bind_param('ii', $ventureId, $remindersAlreadySent);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$claimed) {
        return false;
    }

    $daysLeft = max(0, (int)ceil($secondsLeft / 86400));
    $isFinal  = $daysLeft <= 1;

    $whenClosing = $daysLeft <= 0
        ? 'within the next few hours'
        : ($daysLeft === 1 ? 'in less than 24 hours' : 'in ' . $daysLeft . ' days');

    $message = 'Your Venture "' . $title . '" reached the end of its listing period without being fully funded, '
        . 'so it is not accepting new partners right now. You can extend the listing once to keep raising capital, '
        . 'or close it. If you do neither it will be closed automatically ' . $whenClosing
        . ', and any commitment fees your partners paid will be refunded to them.';

    vh_notify(
        $mysqli,
        $founderId,
        'venture',
        $isFinal ? 'Final Reminder — Listing Closing ⏰' : 'Listing Period Ended ⏳',
        $message
    );

    if ($mayEmail) {
        $stmt = $mysqli->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->bind_param('i', $founderId);
        $stmt->execute();
        $founder = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($founder['email'])) {
            vh_send_expiry_reminder_email(
                (string)$founder['email'],
                (string)$founder['name'],
                $title,
                $ventureId,
                $daysLeft,
                $isFinal
            );
        }
    }

    return true;
}

function vh_send_expiry_reminder_email(
    string $email,
    string $name,
    string $ventureTitle,
    int $ventureId,
    int $daysLeft,
    bool $isFinal
): void {
    if (!function_exists('sendEmail') || !function_exists('renderEmailTemplate')) {
        require_once __DIR__ . '/mailer.php';
    }

    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeVenture = htmlspecialchars($ventureTitle, ENT_QUOTES, 'UTF-8');

    $whenClosing = $daysLeft <= 0
        ? 'within the next few hours'
        : ($daysLeft === 1 ? 'in less than 24 hours' : 'in ' . $daysLeft . ' days');

    $dashboardUrl = vh_site_url('/pages/dashboard.php');

    $inner = '<h1 style="margin:0 0 8px 0;font-family:\'Outfit\',Arial,sans-serif;font-size:22px;color:#0f172a;">'
        . ($isFinal ? 'Final reminder — your listing closes soon' : 'Your listing period has ended')
        . '</h1>'
        . '<p style="margin:0 0 4px 0;font-size:15px;line-height:1.6;color:#475569;">Hi ' . $safeName . ', <strong>' . $safeVenture . '</strong> '
        . 'reached the end of its listing period without being fully funded, so it has stopped accepting new partners.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;font-size:14.5px;color:#334155;background:'
        . ($isFinal ? '#fef2f2' : '#f2f6fb') . ';border-radius:10px;">'
        . '<tr><td style="padding:14px 18px 4px 18px;color:#94a3b8;">If you do nothing, this Asset closes</td></tr>'
        . '<tr><td style="padding:0 18px 14px 18px;font-family:\'Outfit\',Arial,sans-serif;font-size:20px;font-weight:700;color:'
        . ($isFinal ? '#b91c1c' : '#0f172a') . ';">' . htmlspecialchars($whenClosing, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:0 18px 14px 18px;border-top:1px solid #e2e8f0;">Closing it refunds the commitment fee to every partner who paid one, and the listing cannot be reopened afterwards.</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 10px 0;font-size:14.5px;line-height:1.6;color:#334155;">You have two options, both from your dashboard:</p>'
        . '<p style="margin:0 0 6px 0;font-size:14.5px;line-height:1.6;color:#334155;"><strong>Extend the listing</strong> — one extension is available, giving you more days to keep raising capital.</p>'
        . '<p style="margin:0 0 18px 0;font-size:14.5px;line-height:1.6;color:#334155;"><strong>Close it now</strong> — if you no longer want to raise for this Asset.</p>'
        . '<p style="margin:0 0 18px 0;"><a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="display:inline-block;background:#1D4ED8;color:#ffffff;text-decoration:none;font-family:\'Outfit\',Arial,sans-serif;font-size:15px;font-weight:600;padding:12px 26px;border-radius:8px;">Extend or close my listing</a></p>'
        . '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#94a3b8;">You are receiving this because you are the founder of this Asset. '
        . 'Once you extend or close it, these reminders stop.</p>';

    $body = renderEmailTemplate($inner, [
        'heading'   => $isFinal ? 'Final Reminder' : 'Listing Period Ended',
        'preheader' => $safeVenture . ' closes ' . $whenClosing . ' unless you extend it.',
    ]);

    @sendEmail(
        $email,
        ($isFinal ? 'Final reminder: ' : 'Action needed: ') . $ventureTitle . ' closes ' . $whenClosing,
        $body
    );
}

function vh_site_url(string $path): string
{
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    if ($host === '') {
        return $path;
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $scheme = $https ? 'https' : 'http';

    
    
    
    $base = '';
    $self = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (['/api/', '/pages/', '/users/', '/admin/'] as $marker) {
        $at = strpos($self, $marker);
        if ($at !== false) {
            $base = substr($self, 0, $at);
            break;
        }
    }

    return $scheme . '://' . $host . $base . $path;
}

function vh_claim_lifecycle_sweep(mysqli $mysqli): bool
{
    $now    = date('Y-m-d H:i:s');
    $cutoff = date('Y-m-d H:i:s', time() - VH_SWEEP_INTERVAL);

    $stmt = $mysqli->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('venture_lifecycle_last_sweep', ?)
        ON DUPLICATE KEY UPDATE
            setting_value = IF(setting_value IS NULL OR setting_value < ?, ?, setting_value)
    ");
    $stmt->bind_param('sss', $now, $cutoff, $now);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    return $claimed;
}

function vh_run_lifecycle_sweep(mysqli $mysqli, bool $force = false): array
{
    $summary = ['ran' => false, 'expired' => 0, 'reminders_sent' => 0, 'cancelled' => 0, 'refunds_opened' => 0, 'seats_released' => 0];

    if (!$force && !vh_claim_lifecycle_sweep($mysqli)) {
        return $summary;
    }
    $summary['ran'] = true;

    // A waitlist seat held by somebody who never paid comes back to everyone else.
    // The waitlist page releases it lazily too, so it can never sit locked just
    // because nobody happened to trigger a sweep — this is the belt to that braces.
    if (function_exists('vh_release_expired_slots')) {
        $summary['seats_released'] = vh_release_expired_slots($mysqli);
    }

    
    
    
    
    $graceDays           = vh_decision_grace_days($mysqli);
    $reminderEmailsUntil = microtime(true) + VH_REMINDER_EMAIL_BUDGET_SECONDS;

    
    
    
    
    
    
    
    
    
    
    
    $mysqli->query("
        UPDATE ventures
        SET days_left = GREATEST(0, CEIL(TIMESTAMPDIFF(SECOND, NOW(), listing_ends_at) / 86400))
        WHERE status IN ('active', 'expired') AND listing_ends_at IS NOT NULL AND is_showcase = 0
    ");

    
    
    
    
    $due = $mysqli->query("
        SELECT id, title, founder_user_id, extension_count
        FROM ventures
        WHERE status = 'active'
          AND is_showcase = 0
          AND listing_ends_at IS NOT NULL
          AND listing_ends_at <= NOW()
          AND (target_capital <= 0 OR raised_capital < target_capital)
    ");
    $dueRows = $due ? $due->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($dueRows as $row) {
        $ventureId = (int)$row['id'];

        if ((int)$row['extension_count'] > 0) {
            
            
            $outcome = vh_cancel_venture(
                $mysqli,
                $ventureId,
                'system',
                'The extended listing period ended without reaching the funding goal.'
            );
            if (!empty($outcome['success'])) {
                $summary['cancelled']++;
                $summary['refunds_opened'] += (int)$outcome['refund_count'];
            }
            continue;
        }

        // Rule 1: first expiry — hand the decision to the founder.
        $stmt = $mysqli->prepare("
            UPDATE ventures
            SET status = 'expired', expired_at = NOW(), days_left = 0,
                expiry_reminders_sent = 0, last_expiry_reminder_at = NULL
            WHERE id = ? AND status = 'active'
        ");
        $stmt->bind_param('i', $ventureId);
        $stmt->execute();
        $claimed = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$claimed) {
            continue;
        }
        $summary['expired']++;

        
        
        if (!empty($row['founder_user_id'])) {
            $sent = vh_send_expiry_reminder(
                $mysqli,
                $ventureId,
                (string)$row['title'],
                (int)$row['founder_user_id'],
                
                
                $graceDays * 86400,
                0,
                $graceDays,
                microtime(true) < $reminderEmailsUntil
            );
            if ($sent) {
                $summary['reminders_sent']++;
            }
        }
    }

    
    
    
    
    
    
    
    
    
    
    
    
    
    $stmt = $mysqli->prepare("
        SELECT id, title, founder_user_id, expiry_reminders_sent,
               TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(expired_at, INTERVAL ? DAY)) AS seconds_left
        FROM ventures
        WHERE status = 'expired'
          AND is_showcase = 0
          AND founder_user_id > 0
          AND expired_at IS NOT NULL
          AND expiry_reminders_sent < ?
          AND expired_at > DATE_SUB(NOW(), INTERVAL ? DAY)
          AND (last_expiry_reminder_at IS NULL
               OR last_expiry_reminder_at <= DATE_SUB(NOW(), INTERVAL ? HOUR))
    ");
    $reminderInterval = VH_EXPIRY_REMINDER_INTERVAL_HOURS;
    $stmt->bind_param('iiii', $graceDays, $graceDays, $graceDays, $reminderInterval);
    $stmt->execute();
    $dueReminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($dueReminders as $row) {
        $sent = vh_send_expiry_reminder(
            $mysqli,
            (int)$row['id'],
            (string)$row['title'],
            (int)$row['founder_user_id'],
            (int)$row['seconds_left'],
            (int)$row['expiry_reminders_sent'],
            $graceDays,
            microtime(true) < $reminderEmailsUntil
        );
        if ($sent) {
            $summary['reminders_sent']++;
        }
    }

    
    
    
    $stmt = $mysqli->prepare("
        SELECT id FROM ventures
        WHERE status = 'expired'
          AND is_showcase = 0
          AND expired_at IS NOT NULL
          AND expired_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->bind_param('i', $graceDays);
    $stmt->execute();
    $stale = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($stale as $row) {
        $outcome = vh_cancel_venture(
            $mysqli,
            (int)$row['id'],
            'system',
            'The listing period ended and no decision was made within ' . $graceDays . ' days, '
                . 'despite ' . $graceDays . ' daily reminder(s) to extend or close it.'
        );
        if (!empty($outcome['success'])) {
            $summary['cancelled']++;
            $summary['refunds_opened'] += (int)$outcome['refund_count'];
        }
    }

    return $summary;
}

function vh_closed_message(string $status): string
{
    switch ($status) {
        case 'cancelled':
            return 'This Asset has been cancelled and is no longer accepting partners.';
        case 'expired':
            return 'This venture\'s listing period has ended, so it is not accepting new partners. '
                 . 'The founder may extend it — check back, or follow it for updates.';
        case 'suspended':
            return 'This listing is suspended and cannot accept new partners at the moment.';
        default:
            return 'This Asset is not currently accepting new members.';
    }
}

function vh_showcase_message(): string
{
    return 'This is a sample listing — an example of how an Asset looks on Ventures Harbor. '
         . 'It is not a Real Asset, so it cannot be joined. Browse the Real Assets below it, '
         . 'or create your own Venture.';
}

function vh_lifecycle_view(array $venture): array
{
    $status   = $venture['status'] ?? 'active';
    $endsAt   = $venture['listing_ends_at'] ?? null;
    $extended = (int)($venture['extension_count'] ?? 0);

    
    
    
    
    $secondsLeft = ($endsAt && $status !== 'cancelled') ? strtotime($endsAt) - time() : null;
    $daysLeft    = $status === 'cancelled' ? 0 : ($secondsLeft === null ? null : max(0, (int)ceil($secondsLeft / 86400)));

    $fullyFunded = (int)($venture['target_capital'] ?? 0) > 0
        && (int)($venture['raised_capital'] ?? 0) >= (int)($venture['target_capital'] ?? 0);

    return [
        'status'          => $status,
        'listing_ends_at' => $endsAt,
        'days_left'       => $daysLeft,
        'seconds_left'    => $secondsLeft === null ? null : max(0, $secondsLeft),
        'is_expired'      => $status === 'expired',
        'is_cancelled'    => $status === 'cancelled',
        'fully_funded'    => $fullyFunded,
        'extension_count' => $extended,
        
        
        
        'can_extend'      => $status === 'expired' && $extended < 1,
        'can_cancel'      => in_array($status, ['active', 'expired'], true),
        'cancelled_at'    => $venture['cancelled_at'] ?? null,
        'cancelled_by'    => $venture['cancelled_by'] ?? null,
        'cancel_reason'   => $venture['cancel_reason'] ?? null,
    ];
}

/**
 * Why this account cannot be deleted yet — or an empty list, meaning it can.
 *
 * A founder who walks away mid-listing leaves partners holding a commitment in a
 * venture nobody can run: the listing stays live, applications go undecided, and
 * refunds opened by any later cancellation have no one to answer for them. So
 * deletion waits until the founder has wound their ventures up themselves.
 *
 * Two things block it:
 *
 *  - **A venture that is still going.** Anything not `cancelled` counts, which
 *    includes `pending_payment` (never published, but still theirs to finish or
 *    drop) and `expired` (past its deadline and awaiting the founder's decision,
 *    which is exactly the decision they would be abandoning).
 *
 *  - **A refund their venture owes that an admin has not paid out.** Cancelling
 *    opens a `refund_request` per paying partner; until those are `completed` or
 *    `rejected` there is real money outstanding against this person's ventures.
 *    A `waived` row is settled by definition — the partner chose to forfeit.
 *
 * Deliberately NOT a blocker: a refund THEY are owed, including the one the
 * deletion form itself files. That claim is theirs to lose, the transaction row
 * survives the deletion, and blocking on it would make the "delete and request
 * my refund" option impossible to complete.
 *
 * Being a partner in someone else's venture is also not a blocker — they can
 * exit those, and their membership rows are kept either way.
 */
function vh_account_deletion_blockers(mysqli $mysqli, int $userId): array
{
    $stmt = $mysqli->prepare("
        SELECT id, title, status, members_count
        FROM ventures
        WHERE founder_user_id = ? AND status <> 'cancelled'
        ORDER BY FIELD(status, 'active', 'expired', 'pending_payment', 'suspended'), id
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $ventures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($ventures as &$v) {
        $v['id']            = (int)$v['id'];
        $v['members_count'] = (int)$v['members_count'];
    }
    unset($v);

    $stmt = $mysqli->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(SUM(t.amount), 0) AS total
        FROM transactions t
        JOIN ventures v ON v.id = t.venture_id
        WHERE v.founder_user_id = ?
          AND t.type = 'refund_request'
          AND t.status = 'pending'
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $refunds = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $pendingRefunds = (int)($refunds['cnt'] ?? 0);
    $refundTotal    = (int)($refunds['total'] ?? 0);

    return [
        'ventures'        => $ventures,
        'venture_count'   => count($ventures),
        'pending_refunds' => $pendingRefunds,
        'refund_total'    => $refundTotal,
        'can_delete'      => count($ventures) === 0 && $pendingRefunds === 0,
    ];
}

/**
 * The refusal, worded from whichever blocker actually applies so the founder is
 * told what to go and do rather than just being turned away.
 */
function vh_account_deletion_message(array $blockers): string
{
    $parts = [];

    if ($blockers['venture_count'] > 0) {
        $titles = array_map(function ($v) { return '"' . $v['title'] . '"'; }, $blockers['ventures']);
        $parts[] = $blockers['venture_count'] === 1
            ? 'You still have an Asset running — ' . $titles[0] . '. Close it from your dashboard first.'
            : 'You still have ' . $blockers['venture_count'] . ' Assets running — ' . implode(', ', $titles)
              . '. Close them from your dashboard first.';
    }

    if ($blockers['pending_refunds'] > 0) {
        $parts[] = $blockers['pending_refunds'] === 1
            ? 'One refund of ₹' . vh_inr($blockers['refund_total']) . ' from your Ventures is still waiting to be paid out by our team.'
            : $blockers['pending_refunds'] . ' refunds totalling ₹' . vh_inr($blockers['refund_total'])
              . ' from your Ventures are still waiting to be paid out by our team.';
    }

    return 'Your account cannot be deleted yet. ' . implode(' ', $parts)
         . ' Once every Asset is closed and all refunds have been settled, you can delete your account.';
}
