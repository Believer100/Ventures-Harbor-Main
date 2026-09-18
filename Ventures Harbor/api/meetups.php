<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/membership.php'; // vh_meetup_mode()

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Decode json payload if present
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
if (!$action) {
    $action = $input['action'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $userId = requireAuth();

        // Fetch meetups for ventures where this user is a member
        $stmt = $mysqli->prepare("
            SELECT DISTINCT m.*, v.title as ventureName, v.founder_user_id as founderUserId, m.created_by as createdBy
            FROM meetups m
            JOIN ventures v ON m.venture_id = v.id
            JOIN venture_members vm ON vm.venture_id = v.id
            WHERE vm.user_id = ?
            ORDER BY m.date ASC, m.time ASC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rawMeetups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $meetups = [];
        foreach ($rawMeetups as $m) {
            $meetupId = $m['id'];

            // Get attendee names and IDs
            $astmt = $mysqli->prepare("
                SELECT ma.user_id, u.name 
                FROM meetup_attendees ma 
                JOIN users u ON ma.user_id = u.id 
                WHERE ma.meetup_id = ? AND ma.rsvp_status = 'accepted'
            ");
            $astmt->bind_param("i", $meetupId);
            $astmt->execute();
            $attendeesResult = $astmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $attendees = array_map(function($row) {
                return [
                    'id' => (string)$row['user_id'],
                    'name' => $row['name']
                ];
            }, $attendeesResult);
            $astmt->close();

            // Get active user RSVP status
            $rstmt = $mysqli->prepare("SELECT rsvp_status FROM meetup_attendees WHERE meetup_id = ? AND user_id = ?");
            $rstmt->bind_param("ii", $meetupId, $userId);
            $rstmt->execute();
            $rRow = $rstmt->get_result()->fetch_assoc();
            $userRsvp = $rRow ? $rRow['rsvp_status'] : 'pending';
            $rstmt->close();

            $m['attendees'] = $attendees;
            $m['rsvpStatus'] = $userRsvp;

            // Ensure ID is a string to match mock format if needed
            $m['id'] = (string)$m['id'];
            $m['ventureId'] = (string)$m['venture_id'];

            $meetups[] = $m;
        }

        echo json_encode([
            'success' => true,
            'data' => $meetups
        ]);
        exit;
    }

    // What the schedule dialog asks before it offers a venue field, so it never
    // presents an option the create action would refuse.
    if ($action === 'meetup_mode') {
        requireAuth();
        $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;

        $stmt = $mysqli->prepare("SELECT members_count, target_capital, raised_capital FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $ventureRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ventureRow) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'mode'    => vh_meetup_mode($ventureRow, vh_offline_meetup_max_members($mysqli)),
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
        $title = trim($input['title'] ?? '');
        $date = trim($input['date'] ?? '');
        $time = trim($input['time'] ?? '');
        // Only two venues exist. Anything else a crafted request sends would be
        // stored verbatim and then rendered as a badge, so fold it to the default.
        $locationType = trim($input['location_type'] ?? 'physical') === 'online' ? 'online' : 'physical';
        $location = trim($input['location'] ?? '');
        $meetingLink = trim($input['meeting_link'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if (!$userId || !$ventureId || !$title || !$date || !$time) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        /* Calling a meetup is the FOUNDER's, not any member's — "remove schedule meetup
           from investor side ... only select venture jiska vo founder ha".
           
           This used to check membership alone, which meant any partner could schedule a
           meetup on somebody else's Asset and notify every other member of it. Worse,
           the `complete` action opens the 24-hour refund window, so a meetup an investor
           called was a meetup an investor could use to start everybody's quit clock.
           
           The dropdown now lists only founded Assets, but that is the courtesy half —
           this is the rule, and it is what a hand-made POST meets. */
        if (!isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'founderOnly' => true,
                'message' => 'Only the Asset founder can schedule a meetup.',
            ]);
            exit;
        }

        // Offline is only available to a small enough group. The dialog greys the
        // Physical card out, but this is the guard that actually holds — the same
        // reason vh_payu_allows_manual_payment() lives on the server and not in
        // join-venture.js: a hand-made POST must not be able to route around it.
        $stmt = $mysqli->prepare("SELECT members_count, target_capital, raised_capital FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $ventureRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ventureRow) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        $mode = vh_meetup_mode($ventureRow, vh_offline_meetup_max_members($mysqli));

        // Refuse rather than silently converting: the dialog may simply be stale
        // (the eleventh partner joined while it was open), and the person deserves
        // to be told why their venue was rejected instead of watching it vanish.
        if ($mode['online_only'] && $locationType !== 'online') {
            http_response_code(409);
            echo json_encode([
                'success'    => false,
                'message'    => $mode['message'],
                'onlineOnly' => true,
                'reason'     => $mode['reason'],
            ]);
            exit;
        }

        // An online meetup nobody can dial into is worse than none at all. This
        // was never enforced while offline was a fallback; once online is the only
        // option it has to be.
        if ($locationType === 'online' && $meetingLink === '') {
            echo json_encode([
                'success' => false,
                'message' => $mode['online_only']
                    ? $mode['message'] . ' Please paste the meeting link (Zoom, Google Meet, or similar).'
                    : 'Please paste the meeting link for an online meetup.',
                'onlineOnly' => $mode['online_only'],
            ]);
            exit;
        }

        if ($locationType === 'online') {
            $location = '';
        } else {
            $meetingLink = '';
        }

        // Format time to AM/PM for visual compatibility if it's 24h
        if (preg_match("/^([0-9]{2}):([0-9]{2})$/", $time, $matches)) {
            $hour = (int)$matches[1];
            $minute = $matches[2];
            $ampm = $hour >= 12 ? 'PM' : 'AM';
            $hour12 = $hour % 12;
            if ($hour12 === 0) $hour12 = 12;
            $time = sprintf("%02d:%s %s", $hour12, $minute, $ampm);
        }

        // Insert meetup
        $stmt = $mysqli->prepare("
            INSERT INTO meetups (venture_id, title, date, time, location_type, location, meeting_link, rsvp_status, notes, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'accepted', ?, 'upcoming', ?)
        ");
        $stmt->bind_param("isssssssi", $ventureId, $title, $date, $time, $locationType, $location, $meetingLink, $notes, $userId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to schedule meetup.']);
            exit;
        }
        $meetupId = $stmt->insert_id;
        $stmt->close();

        // Add creator as accepted attendee
        $stmt = $mysqli->prepare("INSERT INTO meetup_attendees (meetup_id, user_id, rsvp_status) VALUES (?, ?, 'accepted')");
        $stmt->bind_param("ii", $meetupId, $userId);
        $stmt->execute();
        $stmt->close();

        // Invite all other venture members as pending attendees
        $stmt = $mysqli->prepare("SELECT user_id FROM venture_members WHERE venture_id = ? AND user_id != ?");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($members as $m) {
            $otherUserId = $m['user_id'];
            $istmt = $mysqli->prepare("INSERT INTO meetup_attendees (meetup_id, user_id, rsvp_status) VALUES (?, ?, 'pending')");
            $istmt->bind_param("ii", $meetupId, $otherUserId);
            $istmt->execute();
            $istmt->close();

            // Create notification for other user
            $nstmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'meetup', 'New Meetup Scheduled 📅', ?)");
            $notifMsg = "A new meetup '$title' has been scheduled for your Asset.";
            $nstmt->bind_param("is", $otherUserId, $notifMsg);
            $nstmt->execute();
            $nstmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Meetup scheduled successfully!']);
        exit;
    }

    if ($action === 'rsvp') {
        $userId = requireAuth();
        $meetupId = isset($input['meetup_id']) ? (int)$input['meetup_id'] : 0;
        $rsvpStatus = trim($input['rsvp_status'] ?? 'pending');

        if (!$userId || !$meetupId || !in_array($rsvpStatus, ['accepted', 'declined', 'pending'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        $stmt = $mysqli->prepare("
            INSERT INTO meetup_attendees (meetup_id, user_id, rsvp_status) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE rsvp_status = ?
        ");
        $stmt->bind_param("iiss", $meetupId, $userId, $rsvpStatus, $rsvpStatus);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update RSVP.']);
            exit;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'RSVP status updated.']);
        exit;
    }

    if ($action === 'cancel') {
        $userId = requireAuth();
        $meetupId = isset($input['meetup_id']) ? (int)$input['meetup_id'] : 0;

        if (!$meetupId) {
            echo json_encode(['success' => false, 'message' => 'Invalid meetup.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT m.title, m.created_by, v.title as ventureTitle FROM meetups m JOIN ventures v ON m.venture_id = v.id WHERE m.id = ?");
        $stmt->bind_param("i", $meetupId);
        $stmt->execute();
        $meetup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$meetup) {
            echo json_encode(['success' => false, 'message' => 'Meetup not found.']);
            exit;
        }

        if ((int)$meetup['created_by'] !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the person who scheduled this meetup can cancel it.']);
            exit;
        }

        // Notify all invited attendees before removing the meetup
        $stmt = $mysqli->prepare("SELECT user_id FROM meetup_attendees WHERE meetup_id = ? AND user_id != ?");
        $stmt->bind_param("ii", $meetupId, $userId);
        $stmt->execute();
        $attendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($attendees as $a) {
            $nstmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'meetup', 'Meetup Cancelled', ?)");
            $notifMsg = "The meetup '" . $meetup['title'] . "' for " . $meetup['ventureTitle'] . " has been cancelled.";
            $nstmt->bind_param("is", $a['user_id'], $notifMsg);
            $nstmt->execute();
            $nstmt->close();
        }

        $stmt = $mysqli->prepare("DELETE FROM meetups WHERE id = ?");
        $stmt->bind_param("i", $meetupId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel meetup.']);
            exit;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Meetup cancelled and members notified.']);
        exit;
    }

    if ($action === 'complete') {
        $userId = requireAuth();
        $meetupId = isset($input['meetup_id']) ? (int)$input['meetup_id'] : 0;

        if (!$meetupId) {
            echo json_encode(['success' => false, 'message' => 'Invalid meetup.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT m.id, m.venture_id, m.location_type, m.status, v.quit_window_opened_at FROM meetups m JOIN ventures v ON m.venture_id = v.id WHERE m.id = ?");
        $stmt->bind_param("i", $meetupId);
        $stmt->execute();
        $meetup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$meetup) {
            echo json_encode(['success' => false, 'message' => 'Meetup not found.']);
            exit;
        }

        if (!isFounderOf($mysqli, (int)$meetup['venture_id'], $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the Asset founder can mark a meetup as completed.']);
            exit;
        }

        // Online meetups count. The 24-hour window exists so a partner can
        // reconsider AFTER meeting the team — a video call satisfies that just as
        // a room does, and once an Asset past the offline cap can only meet
        // online, gating the window on 'physical' would quietly delete the refund
        // for the largest Assets on the platform.

        if ($meetup['status'] === 'past') {
            echo json_encode(['success' => false, 'message' => 'This meetup is already marked completed.']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE meetups SET status = 'past', completed_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $meetupId);
        $stmt->execute();
        $stmt->close();

        if (!$meetup['quit_window_opened_at']) {
            $stmt = $mysqli->prepare("UPDATE ventures SET quit_window_opened_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $meetup['venture_id']);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Meetup marked as completed.']);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
