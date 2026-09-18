<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/venture-lifecycle.php';
require_once __DIR__ . '/../config/membership.php';

header('Content-Type: application/json');

$userId = requireAuth();

vh_run_lifecycle_sweep($mysqli);

// Get user info
$stmt = $mysqli->prepare("SELECT name, city FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$userName = $user['name'];

$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM ventures WHERE founder_user_id = ? AND status <> 'cancelled'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myVenturesCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $mysqli->prepare("
    SELECT COUNT(*) as count 
    FROM venture_members vm 
    JOIN ventures v ON vm.venture_id = v.id 
    WHERE vm.user_id = ? AND (v.founder_user_id IS NULL OR v.founder_user_id != ?)
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$joinedCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $mysqli->prepare("SELECT SUM(invested_amount) as total FROM venture_members WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$investedTotal = $stmt->get_result()->fetch_assoc()['total'] ?: 0;
$stmt->close();

// 4. List of user's own ventures
$stmt = $mysqli->prepare("SELECT * FROM ventures WHERE founder_user_id = ? AND status <> 'cancelled' ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myVentures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/**
 * What is happening on each of the founder's listings, for the chips on its card.
 *
 * The client's ask: "application aayi, Q&A ma kisi ne kuch pucha, chat par kuch send
 * kiya — card par show kar de, outside hi". Until now every one of those lived only in
 * the bell dropdown, so a founder with three listings could not tell which one needed him
 * without opening each in turn. He reviewed the first cut and dropped the partner count
 * ("1 partner joined mat karo yaha") — the card already states Raised and % Funded, so a
 * headcount was a third way of saying the same thing.
 *
 * Deliberately measured as WORK OUTSTANDING, not as "unread". Nothing in this app tracks
 * what anyone has read, and inventing that would mean a seen-marker on every surface. Two
 * of the three already carry an honest marker of their own — an application is `pending`
 * until it is decided, a question has a NULL answer until it is answered — and those are
 * the two that actually need the founder. Chat is a plain total, shown for context rather
 * than as a to-do.
 *
 * Post-fetch enrichment, the same shape as vh_attach_capital_split(): three grouped
 * queries whatever the number of listings, rather than three per listing.
 */
$vIds = array_map(static fn($v) => (int)$v['id'], $myVentures);
if ($vIds) {
    $in = implode(',', $vIds);          // ints from the DB, never request input
    $tally = [];
    $collect = static function (string $sql, string $key) use ($mysqli, &$tally) {
        $res = $mysqli->query($sql);
        while ($row = $res->fetch_assoc()) {
            $tally[(int)$row['venture_id']][$key] = (int)$row['n'];
        }
    };
    // Waiting on the founder to decide. Scoped to role = 'active' deliberately: a silent
    // partner never files an application at all — they join and pay in one step — so the
    // chip would be lying if a stray row ever said otherwise. The only INSERT hardcodes
    // 'active', which makes this a guard rather than a filter.
    $collect("SELECT venture_id, COUNT(*) n FROM venture_applications
               WHERE venture_id IN ($in) AND status = 'pending' AND role = 'active'
               GROUP BY venture_id", 'pending_applications');
    // Waiting on the founder to answer.
    $collect("SELECT venture_id, COUNT(*) n FROM venture_questions
               WHERE venture_id IN ($in) AND (answer IS NULL OR answer = '') GROUP BY venture_id", 'unanswered_questions');
    // Context, not a to-do: the founder's own messages are excluded, so the number is
    // what other people have said.
    $collect("SELECT c.venture_id, COUNT(*) n FROM venture_chat_messages c
                JOIN ventures v ON v.id = c.venture_id
               WHERE c.venture_id IN ($in) AND c.user_id <> v.founder_user_id GROUP BY c.venture_id", 'chat_messages');
    foreach ($myVentures as &$mv) {
        $t = $tally[(int)$mv['id']] ?? [];
        $mv['activity'] = [
            'pending_applications' => $t['pending_applications'] ?? 0,
            'unanswered_questions' => $t['unanswered_questions'] ?? 0,
            'chat_messages'        => $t['chat_messages'] ?? 0,
        ];
    }
    unset($mv);
}

$stmt = $mysqli->prepare("
    SELECT v.*, vm.role as partner_type, vm.invested_amount as my_investment,
           vm.equity_min_investment, vm.equity_active_percent, vm.equity_active_ops_percent,
           vm.equity_silent_percent, vm.equity_partner_types, vm.equity_agreed_at,
           (SELECT CASE
                       WHEN t.bank_account_number IS NULL OR t.bank_account_number = ''
                       THEN 'needs_bank' ELSE 'processing'
                   END
              FROM transactions t
             WHERE t.venture_id = v.id AND t.user_id = vm.user_id
               AND t.type = 'refund_request' AND t.status = 'pending'
             ORDER BY t.id DESC LIMIT 1) AS refund_state
    FROM ventures v
    JOIN venture_members vm ON v.id = vm.venture_id
    WHERE vm.user_id = ? AND (v.founder_user_id IS NULL OR v.founder_user_id != ?)
    ORDER BY vm.joined_at DESC
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$joinedVentures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// The equity each of these partners agreed to, frozen at the moment they joined, so the
// card can state it instead of leaving them to re-read a listing the founder can edit.
// Keyed the way VH.equity already reads a venture — the browser runs the one calculator
// over the snapshot rather than carrying a second copy of the rule. This query already
// excludes ventures the viewer founded, so no row here is a founder's.
foreach ($joinedVentures as &$jv) {
    $terms = vh_member_equity_terms($jv);
    $jv['equity_terms']   = $terms;
    $jv['equity_drifted'] = $terms ? vh_member_equity_drifted($terms, $jv) : false;
    unset($jv['equity_min_investment'], $jv['equity_active_percent'],
          $jv['equity_active_ops_percent'], $jv['equity_silent_percent'],
          $jv['equity_partner_types'], $jv['equity_agreed_at']);
}
unset($jv);

// 6. Upcoming meetups
$stmt = $mysqli->prepare("
    SELECT DISTINCT m.*, v.title as ventureName 
    FROM meetups m 
    JOIN ventures v ON m.venture_id = v.id 
    JOIN venture_members vm ON vm.venture_id = v.id 
    WHERE vm.user_id = ? AND m.status = 'upcoming' 
    ORDER BY m.date ASC, m.time ASC 
    LIMIT 3
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$rawMeetups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$meetups = [];
foreach ($rawMeetups as $m) {
    $meetupId = $m['id'];
    
    // Get attendees
    $astmt = $mysqli->prepare("SELECT user_id FROM meetup_attendees WHERE meetup_id = ? AND rsvp_status = 'accepted'");
    $astmt->bind_param("i", $meetupId);
    $astmt->execute();
    $attendeesResult = $astmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $attendeeIds = array_map(function($row) { return 'u' . $row['user_id']; }, $attendeesResult);
    $astmt->close();

    // Get active user RSVP status
    $rstmt = $mysqli->prepare("SELECT rsvp_status FROM meetup_attendees WHERE meetup_id = ? AND user_id = ?");
    $rstmt->bind_param("ii", $meetupId, $userId);
    $rstmt->execute();
    $rRow = $rstmt->get_result()->fetch_assoc();
    $userRsvp = $rRow ? $rRow['rsvp_status'] : 'pending';
    $rstmt->close();

    $m['attendees'] = $attendeeIds;
    $m['rsvpStatus'] = $userRsvp;
    $m['id'] = (string)$m['id'];
    $m['ventureId'] = (string)$m['venture_id'];
    $meetups[] = $m;
}

// 7. Notifications (Limit 5)
/* seconds_ago is computed by the DATABASE, not by PHP and not by the browser.
   `created_at` is a bare "Y-m-d H:i:s" with no timezone in it, and the browser was
   parsing it as ITS OWN local time — so every relative time was wrong by however
   far MySQL's clock sits from the viewer's. That is what produced "5 hours ago"
   on a notification written minutes earlier (client, 7 Sep 2026: "No exact time"):
   5.5 hours is precisely IST's offset from UTC.
   TIMESTAMPDIFF compares NOW() to created_at inside SQL, so both sides are the
   same clock and the answer is right whatever timezone anything is set to. */
$stmt = $mysqli->prepare(
    "SELECT *, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds_ago
       FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$notifications = [];
foreach ($notifs as $n) {
    $notifications[] = [
        'id' => (string)$n['id'],
        'type' => $n['type'],
        'title' => $n['title'],
        'message' => $n['message'],
        'time' => $n['created_at'],
        'seconds_ago' => max(0, (int)$n['seconds_ago']),
        'read' => $n['is_read'] == 1
    ];
}

// 8. Total unread notifications count
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $userId);
$stmt->execute();
$unreadCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $mysqli->prepare(
    "SELECT message, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds_ago
       FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$acts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$activities = [];
foreach ($acts as $a) {
    $activities[] = [
        'text' => $a['message'],
        'time' => $a['created_at'],
        'seconds_ago' => max(0, (int)$a['seconds_ago'])
    ];
}

$stmt = $mysqli->prepare("
    SELECT a.id, a.venture_id, a.role, a.invested_amount, a.status, a.applied_at, a.decided_at,
           v.title AS venture_title
    FROM venture_applications a
    JOIN ventures v ON a.venture_id = v.id
    WHERE a.user_id = ? AND a.status IN ('pending', 'selected')
    ORDER BY FIELD(a.status, 'selected', 'pending'), a.applied_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success' => true,
    'myApplications' => $myApplications,
    'stats' => [
        'myVenturesCount' => $myVenturesCount,
        'joinedCount' => $joinedCount,
        'investedTotal' => $investedTotal,
        'city' => $user['city']
    ],
    'myVentures' => $myVentures,
    'joinedVentures' => $joinedVentures,
    'upcomingMeetups' => $meetups,
    'notifications' => $notifications,
    'unreadCount' => $unreadCount,
    'activities' => $activities
]);
