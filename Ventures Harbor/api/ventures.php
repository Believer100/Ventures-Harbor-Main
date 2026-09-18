<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/venture-lifecycle.php';
require_once __DIR__ . '/../config/membership.php';
require_once __DIR__ . '/../config/payu.php';
require_once __DIR__ . '/../config/lookups.php';
require_once __DIR__ . '/../config/waitlist.php';

header('Content-Type: application/json');

vh_run_lifecycle_sweep($mysqli);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper to sanitize outputs
function sanitize($arr) {
    return array_map(function($val) {
        return is_string($val) ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : $val;
    }, $arr);
}

function getPaidCommitmentFee(mysqli $mysqli, int $userId, int $ventureId): int {
    $stmt = $mysqli->prepare("
        SELECT fee_amount FROM transactions
        WHERE user_id = ? AND venture_id = ? AND type = 'venture_investment' AND status = 'completed'
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("ii", $userId, $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['fee_amount'] : 0;
}

function readExitTerms(array $input): array {
    $str = function ($key, $max) use ($input) {
        $v = trim((string)($input[$key] ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $max);
    };
    // Tri-state: null (not stated), 0 (no), 1 (yes).
    $bool = function ($key) use ($input) {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) return null;
        return (int)$input[$key] === 1 ? 1 : 0;
    };
    $enum = function ($key, array $allowed) use ($input) {
        $v = trim((string)($input[$key] ?? ''));
        return in_array($v, $allowed, true) ? $v : null;
    };

    $earlyExit = $bool('early_exit_allowed');
    $transfer  = $bool('ownership_transfer_allowed');

    
    
    
    
    
    
    $pct = function ($key) use ($input) {
        $raw = $input[$key] ?? '';
        if ($raw === '' || $raw === null || !is_numeric($raw)) return null;
        $v = round((float)$raw, 2);
        return ($v < 0 || $v > 100) ? null : $v;
    };
    $roiMin = $pct('expected_roi_min');
    $roiMax = $pct('expected_roi_max');
    
    
    
    if ($roiMin !== null && $roiMax !== null && $roiMin > $roiMax) {
        $roiMin = null;
        $roiMax = null;
    }

    // The equity table's amounts are NOT read here — the founder's row is
    // founder_contribution and both partner rows are min_investment, already
    // collected on the Capital step. Only the percentages and salaries are new.
    // A silent partner has no salary: capital-only is what defines the role.
    $money = function ($key) use ($input) {
        $raw = $input[$key] ?? '';
        if ($raw === '' || $raw === null || !is_numeric($raw)) return null;
        $v = (int)round((float)$raw);
        return $v < 0 ? null : $v;
    };

    // Equity is earned two ways: capital in (*_equity_percent) and operations
    // done (*_ops_equity_percent). The listing states both and the sum. A role's
    // two halves cannot exceed 100% between them — $pct already bounds each one,
    // but 70 + 60 would pass that and publish an impossible 130% stake, so the
    // pair is dropped together rather than silently clamped to something the
    // founder never agreed to. Silent partners have no operations half by
    // definition: capital-only is what the role means.
    $opsPair = function (string $investKey, string $opsKey) use ($pct) {
        $invest = $pct($investKey);
        $ops = $pct($opsKey);
        if ($invest !== null && $ops !== null && ($invest + $ops) > 100) {
            return [null, null];
        }
        return [$invest, $ops];
    };
    [$founderEquity, $founderOpsEquity] = $opsPair('founder_equity_percent', 'founder_ops_equity_percent');
    [$activeEquity, $activeOpsEquity]   = $opsPair('active_equity_percent', 'active_ops_equity_percent');

    return [
        'equity_distribution'           => $enum('equity_distribution', ['capital_based', 'equal_split', 'negotiated']),
        'founder_equity_percent'        => $founderEquity,
        'founder_ops_equity_percent'    => $founderOpsEquity,
        'founder_monthly_salary'        => $money('founder_monthly_salary'),
        'active_equity_percent'         => $activeEquity,
        'active_ops_equity_percent'     => $activeOpsEquity,
        'active_monthly_salary'         => $money('active_monthly_salary'),
        'silent_equity_percent'         => $pct('silent_equity_percent'),
        'expected_roi'                  => $str('expected_roi', 120),
        'expected_roi_min'              => $roiMin,
        'expected_roi_max'              => $roiMax,
        'lockin_period'                 => $str('lockin_period', 60),
        'expected_exit_timeline'        => $str('expected_exit_timeline', 120),
        'exit_options'                  => $str('exit_options', 1000),
        'early_exit_allowed'            => $earlyExit,
        
        
        'early_exit_notice_period'      => $earlyExit === 1 ? $str('early_exit_notice_period', 120) : null,
        'early_exit_conditions'         => $earlyExit === 1 ? $str('early_exit_conditions', 1000) : null,
        'ownership_transfer_allowed'    => $transfer,
        'ownership_transfer_approval'   => $transfer === 1 ? $enum('ownership_transfer_approval', ['founder', 'partners', 'both']) : null,
        'profit_distribution_frequency' => $enum('profit_distribution_frequency', ['monthly', 'quarterly', 'yearly', 'none']),
        'exit_valuation_method'         => $enum('exit_valuation_method', ['fixed', 'business_valuation', 'multiple', 'custom']),
        'exit_valuation_notes'          => $str('exit_valuation_notes', 1000),
    ];
}

function vh_venture_partner_count(mysqli $mysqli, int $ventureId, int $founderId): int
{
    $stmt = $mysqli->prepare("
        SELECT
          (SELECT COUNT(*) FROM venture_members WHERE venture_id = ? AND user_id != ?) AS others,
          (SELECT COUNT(*) FROM transactions WHERE venture_id = ? AND user_id != ? AND type = 'venture_investment' AND status = 'completed') AS paid
    ");
    $stmt->bind_param("iiii", $ventureId, $founderId, $ventureId, $founderId);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)$counts['others'] + (int)$counts['paid'];
}

function vh_venture_delete_preview(mysqli $mysqli, int $ventureId, int $founderId): array
{
    
    
    
    
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) AS people FROM (
            SELECT user_id FROM venture_members
             WHERE venture_id = ? AND user_id != ?
            UNION
            SELECT user_id FROM transactions
             WHERE venture_id = ? AND user_id != ?
               AND type = 'venture_investment' AND status = 'completed'
        ) AS involved
    ");
    $stmt->bind_param("iiii", $ventureId, $founderId, $ventureId, $founderId);
    $stmt->execute();
    $partners = (int)$stmt->get_result()->fetch_assoc()['people'];
    $stmt->close();

    $stmt = $mysqli->prepare("
        SELECT COUNT(DISTINCT t.user_id) AS people, COALESCE(SUM(t.fee_amount), 0) AS total
        FROM transactions t
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
    ");
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $refund = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'partners'      => $partners,
        'refund_count'  => (int)$refund['people'],
        'refund_total'  => (int)$refund['total'],
        
        
        'mode'          => $partners > 0 ? 'closed' : 'removed',
        'reason_required' => $partners > 0,
    ];
}

function getWishlistedIds(mysqli $mysqli, int $userId): array {
    if ($userId <= 0) return [];
    $stmt = $mysqli->prepare("SELECT venture_id FROM venture_wishlist WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $ids = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $ids[(int)$row['venture_id']] = true;
    }
    $stmt->close();
    return $ids;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'detail') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid Asset ID']);
            exit;
        }

        // Fetch venture
        $stmt = $mysqli->prepare("SELECT * FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Asset not found']);
            exit;
        }

        
        
        $venture = vh_capital_split($mysqli, $id, $venture);
        $venture['capital_buckets'] = vh_capital_buckets($venture);
        $venture['openness'] = vh_venture_openness($venture);

        // A sample listing is walked through rather than joined, so it also carries the
        // openness it WOULD have if it were live — see vh_venture_openness_preview().
        // Sent only for a showcase: a real listing's payload is unchanged, and the join
        // page falls back to `openness` when the key is absent.
        if (!empty($venture['is_showcase'])) {
            $venture['openness_preview'] = vh_venture_openness_preview($venture);
        }

        // Fetch members of this venture
        $stmt = $mysqli->prepare("
            SELECT vm.role, vm.invested_amount, u.name, u.avatar, u.avatar_url, u.id as user_id,
                   u.linkedin_url, u.twitter_url, u.instagram_url, u.website_url, u.past_experience,
                   vm.equity_min_investment, vm.equity_active_percent, vm.equity_active_ops_percent,
                   vm.equity_silent_percent, vm.equity_partner_types, vm.equity_agreed_at
            FROM venture_members vm
            JOIN users u ON vm.user_id = u.id
            WHERE vm.venture_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Hand each member the terms they joined on, keyed the way VH.equity already reads
        // a venture, so the browser runs the ONE calculator over the snapshot instead of
        // re-deriving the rule. The founder is excluded on purpose: their percentage is
        // `founder_equity_percent`, which rescales with their own contribution rather than
        // being frozen, so their row's snapshot (written by the step-53 back-fill) is not
        // theirs to read. `drifted` only captions the figure — it never changes it.
        $founderId = (int)($venture['founder_user_id'] ?? 0);
        foreach ($members as &$m) {
            $terms = ((int)$m['user_id'] === $founderId) ? null : vh_member_equity_terms($m);
            $m['equity_terms']    = $terms;
            $m['equity_drifted']  = $terms ? vh_member_equity_drifted($terms, $venture) : false;
            $m['is_founder']      = (int)$m['user_id'] === $founderId;
            unset($m['equity_min_investment'], $m['equity_active_percent'],
                  $m['equity_active_ops_percent'], $m['equity_silent_percent'],
                  $m['equity_partner_types'], $m['equity_agreed_at']);
        }
        unset($m);

        // What the edit form must render read-only. Same shape as the duration lock.
        $venture['terms_locked'] = vh_venture_partner_count($mysqli, $id, $founderId) > 0;

        // Fetch meetups
        $stmt = $mysqli->prepare("SELECT * FROM meetups WHERE venture_id = ? ORDER BY date ASC");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $meetups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch related ventures (same industry, limit 2)
        $stmt = $mysqli->prepare("SELECT id, title, industry, progress_percent, days_left FROM ventures WHERE industry = ? AND id != ? LIMIT 2");
        $stmt->bind_param("si", $venture['industry'], $id);
        $stmt->execute();
        $related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        
        
        
        // name/avatar/avatar_url are read LIVE from the account rather than from
        // the ventures.founder_avatar snapshot the row was created with. That
        // snapshot is initials-or-nothing frozen at publish time, so a founder
        // who later uploaded a logo still showed their old initials on the
        // Founder Info card while the profile popup showed the new picture.
        $founder = null;
        if ($venture['founder_user_id']) {
            $stmt = $mysqli->prepare("SELECT id, name, avatar, avatar_url, linkedin_url, twitter_url, instagram_url, website_url, past_experience FROM users WHERE id = ?");
            $stmt->bind_param("i", $venture['founder_user_id']);
            $stmt->execute();
            $founder = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        
        
        
        $viewerId = (int)($_SESSION['user_id'] ?? 0);
        $venture['is_wishlisted'] = isset(getWishlistedIds($mysqli, $viewerId)[$id]);

        
        
        $viewerStates = getViewerVentureStates($mysqli, $viewerId);
        $venture['viewer_state'] = $viewerStates[$id]['state'] ?? null;
        $venture['viewer_application_id'] = $viewerStates[$id]['application_id'] ?? null;
        $venture['reserved_seat_capital'] = vh_reserved_seat_capital($mysqli, $id);
        $venture['waitlist_open']  = vh_waitlist_is_open($venture);
        $venture['waitlist_count'] = vh_waitlist_count($mysqli, $id);

        /* The refund countdown, measured by the database and honouring this
           viewer's OWN window if they took a vacated seat. The page used to build
           it from the raw timestamp in the browser, which parses a bare
           "Y-m-d H:i:s" as browser-local — so the clock it showed was out by the
           gap between MySQL's timezone and the viewer's. */
        $viewerWindowOpenedAt = null;
        if ($viewerId) {
            $stmt = $mysqli->prepare(
                "SELECT quit_window_opened_at FROM venture_members WHERE venture_id = ? AND user_id = ?"
            );
            $stmt->bind_param('ii', $id, $viewerId);
            $stmt->execute();
            $mw = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $viewerWindowOpenedAt = vh_member_quit_window(
                $mw['quit_window_opened_at'] ?? null,
                $venture['quit_window_opened_at'] ?? null
            );
        }
        $venture['quit_window_seconds_left'] =
            vh_quit_window_seconds_left($mysqli, $viewerWindowOpenedAt);

        echo json_encode([
            'success' => true,
            'venture' => $venture,
            
            
            'lifecycle' => vh_lifecycle_view($venture),
            'founder' => $founder,
            'members' => $members,
            'meetups' => $meetups,
            'related' => $related
        ]);
        exit;
    }

    if ($action === 'applications') {
        $userId = requireAuth();
        $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;

        if (!$ventureId || !isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the Asset founder can view applications.']);
            exit;
        }

        $stmt = $mysqli->prepare("
            SELECT a.id, a.role, a.invested_amount, a.message, a.resume_path, a.resume_name, a.skill_match_note,
                   a.whatsapp_number, a.status, a.applied_at,
                   u.name, u.city, u.occupation, u.skills
            FROM venture_applications a
            JOIN users u ON a.user_id = u.id
            WHERE a.venture_id = ? AND a.status = 'pending' AND a.slot_id IS NULL
            ORDER BY a.applied_at ASC
        ");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // The link is built here, not in the browser, so vh_whatsapp_link() stays the
        // one place a wa.me URL is formed. Null for an application filed before this
        // shipped — the card says so rather than drawing a dead button.
        foreach ($applications as &$a) {
            $a['whatsapp_link']    = vh_whatsapp_link($a['whatsapp_number'] ?? null);
            $a['whatsapp_display'] = vh_whatsapp_display($a['whatsapp_number'] ?? null);
        }
        unset($a);

        /* Vacated ACTIVE seats waiting on this founder, each with its applicants.
           A separate list, not mixed into the one above, because the action is
           different: selecting here holds the seat for that person and starts their
           payment clock, where selecting an ordinary applicant does neither. Sent
           with the same response so the tab makes one request, not two. */
        $seats = [];
        $stmt = $mysqli->prepare("
            SELECT * FROM venture_slots
             WHERE venture_id = ? AND role = 'active' AND status IN ('open', 'claiming')
          ORDER BY created_at ASC
        ");
        $stmt->bind_param('i', $ventureId);
        $stmt->execute();
        $seatRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($seatRows as $seat) {
            $seat['candidates']   = vh_slot_candidates($mysqli, (int)$seat['id']);
            // SQL, not PHP — the two clocks differ by hours on this install.
            $seat['seconds_left'] = vh_slot_seconds_left($mysqli, (int)$seat['id']);
            $seat['pay_days']     = VH_SLOT_ACTIVE_PAY_DAYS;
            $seat['offer_days']   = VH_SLOT_OFFER_DAYS;
            $seats[] = $seat;
        }

        echo json_encode(['success' => true, 'applications' => $applications, 'seats' => $seats]);
        exit;
    }

    
    
    
    
    
    
    if ($action === 'exit_eligibility') {
        $userId = requireAuth();
        $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;

        // m.quit_window_opened_at is the member's OWN window — set only for
        // somebody who took a vacated seat, who joined without a meeting and so can
        // never be covered by the venture-level stamp. NULL for everyone else, which
        // is why every pre-existing member behaves exactly as before.
        /* v.* rather than the four columns this used to name: vh_waitlist_is_open()
           reads the target, the raise, the deadline and the partner types, and a
           narrow SELECT here would make the forfeit-block silently answer "not
           full" for every Asset — the same narrow-SELECT trap as `update`, just
           costing a rule instead of a column. */
        $stmt = $mysqli->prepare("
            SELECT v.*, m.quit_window_opened_at AS member_window
            FROM venture_members m JOIN ventures v ON m.venture_id = v.id
            WHERE m.venture_id = ? AND m.user_id = ?
        ");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'You are not a member of this Asset.']);
            exit;
        }

        // Same answer as `exit` gives, so the dialog never offers a route the action
        // would refuse — the split that vh_payu_allows_manual_payment() keeps too.
        if ((int)$row['founder_user_id'] === (int)$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'You founded this Asset, so there is nothing to exit from. '
                           . 'To close it, use Delete Listing — every partner is refunded automatically.',
            ]);
            exit;
        }

        
        
        
        if ($row['status'] === 'cancelled') {
            echo json_encode([
                'success' => false,
                'message' => 'This Asset has been cancelled, so there is nothing to exit from. '
                           . 'Your commitment fee refund was opened for you automatically — track it on your Payment Statement page.',
            ]);
            exit;
        }

        $feeAmount = getPaidCommitmentFee($mysqli, $userId, $ventureId);
        $openedAt = vh_member_quit_window($row['member_window'] ?? null, $row['quit_window_opened_at']);
        $secondsLeft = 0;
        $windowState = 'not_opened'; // 'not_opened' | 'open' | 'closed'

        if ($openedAt) {
            // Measured in SQL — see vh_quit_window_seconds_left().
            $secondsLeft = (int)vh_quit_window_seconds_left($mysqli, $openedAt);
            $windowState = $secondsLeft > 0 ? 'open' : 'closed';
        }

        $refundable = ($windowState === 'open' && $feeAmount > 0);

        // The forfeit route, answered the same way the `exit` action answers it —
        // the read-only companion must never offer a route the action refuses.
        $eligRows = [$row];
        vh_attach_capital_split($mysqli, $eligRows);
        vh_attach_reserved_seats($mysqli, $eligRows);
        $noRefundBlocked = vh_no_refund_exit_blocked($eligRows[0], $openedAt);

        echo json_encode([
            'success' => true,
            'venture_title' => $row['title'],
            'window_state' => $windowState,
            'seconds_left' => max(0, $secondsLeft),
            'fee_paid' => $feeAmount,
            'refundable' => $refundable,
            'no_refund_blocked' => $noRefundBlocked,
            'no_refund_blocked_reason' => $noRefundBlocked ? vh_no_refund_blocked_message() : '',
        ]);
        exit;
    }

    
    
    
    
    if ($action === 'delete_preview') {
        $userId = requireAuth();
        $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;

        if (!$ventureId || !isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can delete this Asset.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT title, status, extension_count FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        echo json_encode(array_merge(
            ['success' => true, 'title' => $venture['title'], 'status' => $venture['status']],
            vh_venture_delete_preview($mysqli, $ventureId, $userId),
            
            
            ['can_extend' => $venture['status'] === 'expired' && (int)$venture['extension_count'] < 1]
        ));
        exit;
    }

    if ($action === 'application_detail') {
        $userId = requireAuth();
        $applicationId = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;

        $stmt = $mysqli->prepare("SELECT a.id, a.venture_id, a.user_id, a.role, a.invested_amount, a.status, v.min_investment FROM venture_applications a JOIN ventures v ON a.venture_id = v.id WHERE a.id = ?");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$application || (int)$application['user_id'] !== $userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            exit;
        }

        if ($application['status'] !== 'selected') {
            echo json_encode(['success' => false, 'message' => 'This application has not been selected for payment.']);
            exit;
        }
        if ($application['role'] === 'active' && (int)$application['invested_amount'] <= 0) {
            $application['invested_amount'] = (int)$application['min_investment'];
        }

        echo json_encode(['success' => true, 'application' => $application]);
        exit;
    }

    
    
    if ($action === 'wishlist') {
        $userId = requireAuth();
        $ids = array_map('intval', array_keys(getWishlistedIds($mysqli, $userId)));
        echo json_encode(['success' => true, 'venture_ids' => $ids]);
        exit;
    }

    
    
    
    
    if ($action === 'wishlist_ventures') {
        $userId = requireAuth();
        $stmt = $mysqli->prepare("
            SELECT v.*, w.created_at AS saved_at
            FROM venture_wishlist w
            JOIN ventures v ON v.id = w.venture_id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC, w.id DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        
        
        $viewerStates = getViewerVentureStates($mysqli, $userId);
        vh_attach_reserved_seats($mysqli, $rows);
        foreach ($rows as &$row) {
            $id = (int)$row['id'];
            $row['is_wishlisted'] = true;
            $row['viewer_state'] = $viewerStates[$id]['state'] ?? null;
            $row['viewer_application_id'] = $viewerStates[$id]['application_id'] ?? null;
            $row['waitlist_open'] = vh_waitlist_is_open($row);
        }
        unset($row);

        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // Default: list ventures with filters/search/sorting
    $category = $_GET['category'] ?? '';
    $assetClass = vh_normalize_asset_class($_GET['asset_class'] ?? '');
    $industry = $_GET['industry'] ?? '';
    $city = $_GET['city'] ?? '';
    $min_investment = isset($_GET['min_investment']) ? (int)$_GET['min_investment'] : 0;
    $pType = $_GET['pType'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';
    $status = $_GET['status'] ?? ''; // comma-separated status list
    $ownership = $_GET['ownership'] ?? 'all'; // 'all', 'individual', or 'company'
    $profitFreq = $_GET['profit_freq'] ?? 'all'; // a stored frequency, 'unspecified', or 'all'
    $roiBand = $_GET['roi_band'] ?? 'all';       // '0-10' | '10-20' | '20-30' | '30+' | 'unspecified' | 'all'
    $wishlistedOnly = !empty($_GET['wishlisted']);

    $viewerId = (int)($_SESSION['user_id'] ?? 0);

    $query = "SELECT * FROM ventures WHERE 1=1";
    $params = [];
    $types = "";

    
    
    if ($wishlistedOnly) {
        if ($viewerId <= 0) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        $query .= " AND id IN (SELECT venture_id FROM venture_wishlist WHERE user_id = ?)";
        $params[] = $viewerId;
        $types .= "i";
    }

    if ($category) {
        $query .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    // The marketplace filter pills. Normalised above, so an unknown value is
    // already '' and widens the list rather than returning nothing.
    if ($assetClass) {
        // asset_class holds a comma-separated list (migration step 50), so a
        // listing in Real Estate + Infrastructure must answer to both pills.
        $query .= " AND FIND_IN_SET(?, asset_class)";
        $params[] = $assetClass;
        $types .= "s";
    }
    if ($industry) {
        $query .= " AND industry = ?";
        $params[] = $industry;
        $types .= "s";
    }
    if ($city) {
        $query .= " AND location = ?";
        $params[] = $city;
        $types .= "s";
    }
    
    $state = $_GET['state'] ?? '';
    if ($state) {
        $query .= " AND location LIKE ?";
        $params[] = '%' . $state . '%';
        $types .= "s";
    }
    if ($min_investment > 0) {
        $query .= " AND min_investment >= ?";
        $params[] = $min_investment;
        $types .= "i";
    }
    if ($pType !== 'all') {
        $query .= " AND (partner_types = ? OR partner_types = 'both')";
        $params[] = $pType;
        $types .= "s";
    }
    if ($search) {
        // asset_class is searched too: a founder-named class ("Vintage Cars")
        // never becomes a pill, so search is the only way to reach it.
        $query .= " AND (title LIKE ? OR industry LIKE ? OR location LIKE ? OR founder_name LIKE ? OR REPLACE(asset_class, '_', ' ') LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sssss";
    }
    if ($status) {
        
        
        
        $statusArray = array_values(array_intersect(
            explode(',', $status),
            ['active', 'expired', 'cancelled', 'suspended']
        ));
        if (!$statusArray) { $statusArray = ['active']; }
        $placeholders = implode(',', array_fill(0, count($statusArray), '?'));
        $query .= " AND status IN ($placeholders)";
        foreach ($statusArray as $s) {
            $params[] = $s;
            $types .= "s";
        }
    } else {
        
        
        
        
        $query .= " AND status = 'active'";
    }
    
    
    
    
    
    
    if (in_array($profitFreq, ['monthly', 'quarterly', 'yearly', 'none'], true)) {
        $query .= " AND profit_distribution_frequency = ?";
        $params[] = $profitFreq;
        $types .= "s";
    } elseif ($profitFreq === 'unspecified') {
        $query .= " AND (profit_distribution_frequency IS NULL OR profit_distribution_frequency = '')";
    }

    
    
    
    
    $roiBands = [
        '0-10'  => [0, 10],
        '10-20' => [10, 20],
        '20-30' => [20, 30],
        '30+'   => [30, 1000],
    ];
    if (isset($roiBands[$roiBand])) {
        [$bandMin, $bandMax] = $roiBands[$roiBand];
        $query .= " AND expected_roi_min IS NOT NULL AND expected_roi_max IS NOT NULL
                    AND expected_roi_min <= ? AND expected_roi_max >= ?";
        $params[] = $bandMax;
        $params[] = $bandMin;
        $types .= "dd";
    } elseif ($roiBand === 'unspecified') {
        $query .= " AND (expected_roi_min IS NULL OR expected_roi_max IS NULL)";
    }

    
    if ($ownership === 'individual') {
        $query .= " AND founder_type = 'individual'";
    } elseif ($ownership === 'company') {
        $query .= " AND founder_type = 'brand'";
    }

    
    
    
    
    
    
    
    /* A funded silent-only listing drops off Browse ONLY once its listing has also run
       out. While it still has time on the clock it is out of room, not closed — a partner
       may yet exit and free a seat — so it stays in the marketplace showing Join Waitlist.

       "Vo own change to waitlist and show karta rehta marketplace hi, but vo gayab hi ho
       gaya" — the client, 5 Sep 2026. This clause predates the waitlist: back then a
       funded silent-only Asset genuinely had no route left, so hiding it was right. It
       has one now, and hiding it took away the only way to queue for it.

       The deadline is compared with NOW() inside SQL, never against PHP's clock — MySQL
       runs on Asia/Calcutta here and PHP on Europe/Berlin, 3.5 hours apart. A NULL
       listing_ends_at means the clock never started, so it counts as no time left. The
       condition is the SQL twin of vh_waitlist_is_open(); keep the two in step. */
    $query .= " AND NOT (target_capital > 0 AND raised_capital >= target_capital AND partner_types = 'silent' AND (listing_ends_at IS NULL OR listing_ends_at <= NOW()))";

    
    
    
    
    
    
    $query .= " ORDER BY is_showcase DESC,";

    
    
    
    if ($ownership === 'all') {
        $query .= " CASE WHEN founder_type = 'brand' THEN 0 ELSE 1 END,";
    }

    // Sorting
    switch ($sort) {
        case 'most_funded':
            $query .= " progress_percent DESC";
            break;
        case 'ending_soon':
            $query .= " days_left ASC";
            break;
        case 'capital_low':
            $query .= " target_capital ASC";
            break;
        case 'capital_high':
            $query .= " target_capital DESC";
            break;
        case 'newest':
        default:
            $query .= " id DESC";
            break;
    }

    $stmt = $mysqli->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $ventures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    
    
    
    
    $wishlisted = getWishlistedIds($mysqli, $viewerId);
    $viewerStates = getViewerVentureStates($mysqli, $viewerId);

    
    
    
    vh_attach_capital_split($mysqli, $ventures);
    vh_attach_reserved_seats($mysqli, $ventures);

    foreach ($ventures as &$v) {
        $id = (int)$v['id'];
        $v['is_wishlisted'] = isset($wishlisted[$id]);
        $v['viewer_state'] = $viewerStates[$id]['state'] ?? null;
        $v['viewer_application_id'] = $viewerStates[$id]['application_id'] ?? null;
        // Full but still inside its listing period — what turns Co-Own into Join
        // Waitlist. Attached AFTER the split above, because fullness is derived
        // from it. VH.card.waitlistOpen() is the reader.
        $v['waitlist_open'] = vh_waitlist_is_open($v);
    }
    unset($v);

    echo json_encode([
        'success' => true,
        'data' => $ventures
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine action from post body
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = $input['action'] ?? '';

    
    
    if ($action === 'toggle_wishlist') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        if (!$ventureId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Asset ID.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT id FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        $stmt = $mysqli->prepare("DELETE FROM venture_wishlist WHERE user_id = ? AND venture_id = ?");
        $stmt->bind_param("ii", $userId, $ventureId);
        $stmt->execute();
        $removed = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$removed) {
            $stmt = $mysqli->prepare("INSERT INTO venture_wishlist (user_id, venture_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $ventureId);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode([
            'success' => true,
            'wishlisted' => !$removed,
            'message' => $removed ? 'Removed from your list.' : 'Saved to your list.'
        ]);
        exit;
    }

    if ($action === 'upload_cover') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        if (!$ventureId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Asset ID.']);
            exit;
        }

        $stmt = $mysqli->prepare('SELECT founder_user_id, cover_image FROM ventures WHERE id = ?');
        $stmt->bind_param('i', $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }
        // Founder, or any admin when this is a sample listing. A bare
        // founder_user_id check locked every admin except the one who
        // published it out of the samples — see canEditVentureContent().
        if (!canEditVentureContent($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can update this venture\'s cover image.']);
            exit;
        }

        if (!isset($_FILES['cover']) || $_FILES['cover']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'No image file was received. Please choose a photo and try again.']);
            exit;
        }

        $file = $_FILES['cover'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ').']);
            exit;
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image must be smaller than 5MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/venture_covers';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Server could not create the uploads/venture_covers folder.']);
            exit;
        }
        if (!is_writable($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'The uploads/venture_covers folder is not writable by the server.']);
            exit;
        }

        $newFileName = 'cover_' . $ventureId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . '/' . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded image to disk.']);
            exit;
        }

        $relativePath = 'uploads/venture_covers/' . $newFileName;

        if (!empty($venture['cover_image'])) {
            $oldFile = __DIR__ . '/../' . $venture['cover_image'];
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $stmt = $mysqli->prepare('UPDATE ventures SET cover_image = ? WHERE id = ?');
        $stmt->bind_param('si', $relativePath, $ventureId);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Cover image updated successfully.',
            'coverImage' => $relativePath
        ]);
        exit;
    }

    if ($action === 'upload_logo') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        if (!$ventureId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Asset ID.']);
            exit;
        }

        $stmt = $mysqli->prepare('SELECT founder_user_id, logo_url FROM ventures WHERE id = ?');
        $stmt->bind_param('i', $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }
        // Founder, or any admin when this is a sample listing. A bare
        // founder_user_id check locked every admin except the one who
        // published it out of the samples — see canEditVentureContent().
        if (!canEditVentureContent($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can update this venture\'s logo.']);
            exit;
        }

        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'No image file was received. Please choose a photo and try again.']);
            exit;
        }

        $file = $_FILES['logo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ').']);
            exit;
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image must be smaller than 5MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/venture_logos';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Server could not create the uploads/venture_logos folder.']);
            exit;
        }
        if (!is_writable($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'The uploads/venture_logos folder is not writable by the server.']);
            exit;
        }

        $newFileName = 'logo_' . $ventureId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . '/' . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded image to disk.']);
            exit;
        }

        $relativePath = 'uploads/venture_logos/' . $newFileName;

        if (!empty($venture['logo_url'])) {
            $oldFile = __DIR__ . '/../' . $venture['logo_url'];
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $stmt = $mysqli->prepare('UPDATE ventures SET logo_url = ? WHERE id = ?');
        $stmt->bind_param('si', $relativePath, $ventureId);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Asset logo updated successfully.',
            'logoUrl' => $relativePath
        ]);
        exit;
    }

    if ($action === 'create') {
        $userId = requireAuth();
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $fullDescription = trim($input['full_description'] ?? '');
        $useOfFunds = trim($input['use_of_funds'] ?? '');
        $industry = trim($input['industry'] ?? '');
        $location = trim($input['location'] ?? '');
        $preferredMeetingVenue = trim($input['preferred_meeting_venue'] ?? '') ?: null;
        $targetCapital = isset($input['target_capital']) ? (int)$input['target_capital'] : 0;
        $minInvestment = isset($input['min_investment']) ? (int)$input['min_investment'] : 0;
        $founderContribution = isset($input['founder_contribution']) ? (int)$input['founder_contribution'] : 0;
        // Partner count is no longer capped — 0 means unlimited.
        $maxMembers = 0;
        
        
        
        
        
        $maxListingDays = vh_max_listing_days($mysqli);
        $daysLeft = isset($input['days_left'])
            ? max(VH_LISTING_MIN_DAYS, min($maxListingDays, (int)$input['days_left']))
            : min(15, $maxListingDays);
        
        
        
        
        
        $founderType = 'individual';
        $partnerType = trim($input['partner_type'] ?? 'both');
        $requirements = trim($input['requirements'] ?? '');
        $requiredSkills = trim($input['required_skills'] ?? '') ?: null;
        $silentRequirements = trim($input['silent_requirements'] ?? '') ?: null;
        $silentRequiredSkills = trim($input['silent_required_skills'] ?? '') ?: null;
        $applicationDeadline = trim($input['application_deadline'] ?? '') ?: null;

        
        
        
        
        
        
        
        if ($partnerType === 'silent') {
            $requiredSkills = null;
            $applicationDeadline = null;
            $requirements = '';
        }


        if ($partnerType === 'active') {
            $silentRequirements = null;
            $silentRequiredSkills = null;
        }
        $exit = readExitTerms($input);

        if (!$userId || !$title || !$description || !$industry || !$location || !$targetCapital || !$minInvestment) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        // The minimum ticket has a floor, because the partner headcount falls out of it:
        // pool / ticket is how many people have to turn up, and that is capped. Applies to
        // silent-only and both-types alike — the client was explicit that the two behave
        // the same, and a silent partner is a member who has to be paid out like any other.
        $memberCap = vh_max_members($mysqli);
        $minFloor  = vh_min_investment_floor($targetCapital, $founderContribution, $memberCap);
        if ($minFloor > 0 && $minInvestment < $minFloor) {
            echo json_encode([
                'success'      => false,
                'memberLimit'  => true,
                'minInvestment'=> $minFloor,
                'maxMembers'   => $memberCap,
                'message'      => vh_member_limit_message($minFloor, $memberCap),
            ]);
            exit;
        }

        
        
        
        
        if ($exit['profit_distribution_frequency'] === null) {
            echo json_encode(['success' => false, 'message' => 'Please choose how often profit will be distributed.']);
            exit;
        }

        
        
        
        
        
        if ($exit['expected_roi_min'] === null || $exit['expected_roi_max'] === null) {
            echo json_encode([
                'success' => false,
                'message' => 'Please enter the expected ROI range — two percentages between 0 and 100, with the lower figure first.',
            ]);
            exit;
        }







        // Role-specific equity is optional for the neutral Partner flow.





        // A blank legacy silent-cap field is allowed for the neutral Partner flow.
        [$silentCapitalLimit, $capError] = vh_resolve_silent_cap(
            $input, $targetCapital, $founderContribution, $partnerType,
            0, $minInvestment
        );
        if ($capError !== null && trim((string)($input['silent_capital_limit'] ?? '')) !== '') {
            echo json_encode(['success' => false, 'message' => $capError]);
            exit;
        }
        if ($capError !== null) $silentCapitalLimit = null;

        // Fetch founder details
        $stmt = $mysqli->prepare("SELECT name, avatar, bio, role, account_type FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Invalid user.']);
            exit;
        }

        
        
        
        $founderType = ($user['account_type'] ?? 'individual') === 'company' ? 'brand' : 'individual';

        
        
        
        
        
        
        
        
        $isShowcase = !empty($input['is_showcase']) && $user['role'] === 'admin';

        if ($user['role'] === 'admin' && !$isShowcase) {
            echo json_encode(['success' => false, 'message' => 'Admin accounts cannot create Assets. Use the Sample Listings section to publish an example.']);
            exit;
        }

        if ($isShowcase && !vh_can_add_showcase($mysqli)) {
            echo json_encode([
                'success' => false,
                'message' => 'You already have ' . VH_SHOWCASE_MAX . ' sample listings. Delete one before adding another.'
            ]);
            exit;
        }








        // Role-specific skills are optional until the partner model is finalized.

        
        
        
        
        $showcaseFounderName = $isShowcase
            ? (trim($input['showcase_founder_name'] ?? '') ?: 'Ventures Harbor')
            : null;

        
        
        
        
        
        $showcaseSilent = null;
        $showcaseActive = null;
        if ($isShowcase) {
            $pool = max(0, $targetCapital - min($founderContribution, $targetCapital));
            $readShowcase = function ($key) use ($input, $pool) {
                $raw = $input[$key] ?? '';
                if ($raw === '' || $raw === null || !is_numeric($raw)) return null;
                return max(0, min((int)$raw, $pool));
            };
            $showcaseSilent = $readShowcase('showcase_raised_silent');
            $showcaseActive = $readShowcase('showcase_raised_active');
        }

        // Derive category from industry
        $catMap = [
            'food & beverage' => 'food',
            'technology' => 'tech',
            'infrastructure' => 'infra',
            'franchise' => 'franchise',
            'manufacturing' => 'tech',
            'healthcare' => 'healthcare',
            'education' => 'education',
            'logistics' => 'logistics',
            'agriculture' => 'agriculture',
            'real estate' => 'real_estate'
        ];
        $category = $catMap[strtolower($industry)] ?? 'franchise';

        // The coarse marketplace axis the filter pills read. Taken from the form
        // when the founder chose one, otherwise derived from the industry by the
        // same map migration step 49 back-fills with — so a row written here and
        // a row fixed up there can never disagree. Never left NULL.
        // Accepts the form's array of classes (canonical and custom), capped and
        // de-duplicated by the helper. Falls back to the industry map so a row is
        // never written without a class.
        $assetClass = vh_normalize_asset_class_list($input['asset_class'] ?? '');
        if ($assetClass === '') $assetClass = vh_asset_class_for_industry($industry);

        $style = ['bg' => '#f1f5f9', 'color' => '#0f172a'];

        $raisedCapital = $founderContribution;
        $progressPercent = $targetCapital > 0 ? (int)(($raisedCapital / $targetCapital) * 100) : 0;
        $membersCount = 1; // Founder is first member

        
        
        
        
        
        
        $listingFee = $isShowcase ? 0 : vh_founder_listing_fee($founderContribution);
        $payu = vh_payu_config($mysqli);
        $needsGatewayPayment = $listingFee > 0 && $payu['enabled'] && $payu['error'] === null;

        // A live site whose credentials don't resolve fails $needsGatewayPayment
        // above, which used to publish the listing free instead of holding it at
        // pending_payment. In live mode the fee is owed through PayU or the
        // listing is not created at all.
        if ($listingFee > 0 && !$needsGatewayPayment && !vh_payu_allows_manual_payment($payu)) {
            http_response_code(402);
            echo json_encode([
                'success'         => false,
                'gatewayRequired' => true,
                'message'         => vh_payu_manual_blocked_message($payu),
            ]);
            exit;
        }

        
        
        
        
        $status = $needsGatewayPayment ? 'pending_payment' : 'active';

        
        
        
        $stmt = $mysqli->prepare("
            INSERT INTO ventures (founder_user_id, title, description, full_description, use_of_funds, industry, location, preferred_meeting_venue, target_capital, raised_capital, progress_percent, members_count, max_members, days_left, min_investment, silent_capital_limit, founder_contribution, founder_name, founder_avatar, founder_bio, founder_type, category, asset_class, icon_bg, icon_color, status, is_showcase, showcase_raised_silent, showcase_raised_active, partner_types, requirements, silent_requirements, required_skills, silent_required_skills, application_deadline,
                                  equity_distribution, founder_equity_percent, founder_ops_equity_percent, founder_monthly_salary, active_equity_percent, active_ops_equity_percent, active_monthly_salary, silent_equity_percent, expected_roi, expected_roi_min, expected_roi_max, lockin_period, expected_exit_timeline, exit_options, early_exit_allowed, early_exit_notice_period, early_exit_conditions, ownership_transfer_allowed, ownership_transfer_approval, profit_distribution_frequency, exit_valuation_method, exit_valuation_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $founderName = $showcaseFounderName ?: $user['name'];
        $founderAvatar = $user['avatar'] ?: strtoupper(substr($founderName, 0, 2));
        
        
        
        $founderBio = $isShowcase ? 'Sample listing' : ($user['bio'] ?: 'Venture Founder');
        $showcaseFlag = $isShowcase ? 1 : 0;
        $stmt->bind_param(
            
            
            
            
            
            "isssssss" . "iiiiiiiii" . "sssssssss" . "iii" . "ssssss" . "sddiddid" . "ssssssississss",
            $userId, $title, $description, $fullDescription, $useOfFunds, $industry, $location, $preferredMeetingVenue,
            $targetCapital, $raisedCapital, $progressPercent, $membersCount, $maxMembers,
            $daysLeft, $minInvestment, $silentCapitalLimit, $founderContribution, $founderName,
            $founderAvatar, $founderBio, $founderType, $category, $assetClass, $style['bg'], $style['color'],
            $status, $showcaseFlag, $showcaseSilent, $showcaseActive, $partnerType, $requirements, $silentRequirements, $requiredSkills, $silentRequiredSkills, $applicationDeadline,
            $exit['equity_distribution'],
            $exit['founder_equity_percent'], $exit['founder_ops_equity_percent'], $exit['founder_monthly_salary'],
            $exit['active_equity_percent'], $exit['active_ops_equity_percent'], $exit['active_monthly_salary'],
            $exit['silent_equity_percent'],
            $exit['expected_roi'], $exit['expected_roi_min'], $exit['expected_roi_max'],
            $exit['lockin_period'], $exit['expected_exit_timeline'], $exit['exit_options'],
            $exit['early_exit_allowed'], $exit['early_exit_notice_period'], $exit['early_exit_conditions'],
            $exit['ownership_transfer_allowed'], $exit['ownership_transfer_approval'],
            $exit['profit_distribution_frequency'], $exit['exit_valuation_method'], $exit['exit_valuation_notes']
        );

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to create Asset listing.']);
            exit;
        }

        $ventureId = $stmt->insert_id;
        $stmt->close();

        
        
        
        
        
        
        
        
        
        
        if (!$needsGatewayPayment && !$isShowcase) {
            $stmt = $mysqli->prepare("UPDATE ventures SET listing_ends_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
            $stmt->bind_param("ii", $daysLeft, $ventureId);
            $stmt->execute();
            $stmt->close();
        }

        
        
        
        
        
        
        if (!$isShowcase) {
            $stmt = $mysqli->prepare("INSERT INTO venture_members (venture_id, user_id, role, invested_amount) VALUES (?, ?, 'active', ?)");
            $stmt->bind_param("iii", $ventureId, $userId, $founderContribution);
            $stmt->execute();
            $stmt->close();
        }

        if ($isShowcase) {
            echo json_encode([
                'success'      => true,
                'id'           => $ventureId,
                'is_showcase'  => true,
                'message'      => 'Sample listing published. It now appears at the top of Browse.',
                'showcase_left' => VH_SHOWCASE_MAX - vh_showcase_count($mysqli),
            ]);
            exit;
        }

        if ($needsGatewayPayment) {
            
            echo json_encode([
                'success'       => true,
                'id'            => $ventureId,
                'needs_payment' => true,
                'listing_fee'   => $listingFee,
                'message'       => 'Your Venture is ready. Pay the ₹' . vh_inr($listingFee)
                                   . ' listing fee to publish it.',
            ]);
            exit;
        }

        
        
        
        
        if ($listingFee > 0) {
            $txnId = 'VH' . rand(10000000, 99999999);
            $stmt = $mysqli->prepare("
                INSERT INTO transactions
                    (user_id, venture_id, venture_name, amount, principal_amount, fee_amount,
                     type, status, txn_id, payment_gateway)
                VALUES (?, ?, ?, ?, ?, ?, 'venture_listing_fee', 'completed', ?, 'manual')
            ");
            $stmt->bind_param("iisiiis", $userId, $ventureId, $title, $listingFee, $founderContribution, $listingFee, $txnId);
            $stmt->execute();
            $stmt->close();
        }

        // Log notification
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Venture Live 🚀', ?)");
        $notifMsg = "Your Venture '$title' is now live and listed in the gallery.";
        $stmt->bind_param("is", $userId, $notifMsg);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Asset published successfully!',
            'id' => $ventureId,
            'listing_fee' => $listingFee
        ]);
        exit;
    }

    if ($action === 'update') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        $stmt = $mysqli->prepare("SELECT raised_capital, founder_contribution, status, days_left, is_showcase, asset_class, preferred_meeting_venue, founder_user_id, target_capital, min_investment, founder_equity_percent, active_equity_percent, silent_equity_percent, partner_types, silent_capital_limit FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ventureId || !$existing) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }





        // A sample listing is platform content rather than anyone's venture, so
        // any admin may edit it — not only the one who happened to publish it.
        $isShowcaseVenture = !empty($existing['is_showcase']);
        if ($isShowcaseVenture) {
            $roleStmt = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
            $roleStmt->bind_param("i", $userId);
            $roleStmt->execute();
            $isAdminUser = ($roleStmt->get_result()->fetch_assoc()['role'] ?? '') === 'admin';
            $roleStmt->close();
            if (!$isAdminUser) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only an administrator can edit a sample listing.']);
                exit;
            }
        } elseif (!isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can edit this Asset.']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $fullDescription = trim($input['full_description'] ?? '');
        $useOfFunds = trim($input['use_of_funds'] ?? '');
        $industry = trim($input['industry'] ?? '');
        $location = trim($input['location'] ?? '');
        // The create form no longer asks for a venue, so an edit payload no longer
        // carries one. Absent has to mean "leave it alone", not "erase it" — the
        // same narrow-SELECT trap that once re-filed a listing's asset_class as
        // 'businesses' every time its founder fixed a typo.
        $preferredMeetingVenue = array_key_exists('preferred_meeting_venue', $input)
            ? (trim((string)$input['preferred_meeting_venue']) ?: null)
            : ($existing['preferred_meeting_venue'] ?? null);
        $targetCapital = isset($input['target_capital']) ? (int)$input['target_capital'] : 0;
        $minInvestment = isset($input['min_investment']) ? (int)$input['min_investment'] : 0;
        $founderContribution = isset($input['founder_contribution']) ? (int)$input['founder_contribution'] : 0;

        // Total capital is frozen the moment anybody joins.
        //
        // It is the one figure a partner cannot re-read for themselves: their share of
        // the raise is measured against it, so moving it after they have paid changes the
        // deal they bought. The minimum ticket and the founder's own contribution stay
        // editable on purpose — a founder with days left needs to be able to drop the
        // ticket, or take less of the raise themselves, to attract partners. Neither can
        // hurt an existing member now that their equity is frozen (migration step 53).
        //
        // Ignored rather than refused, the same way `days_left` is: the edit form may
        // simply be stale, and rejecting the whole save would throw away every other
        // change the founder made. lockJoinedTerms() in create-venture.js is the
        // visible half, exactly as lockListingDuration() is for the duration.
        $ventureFounderId = (int)($existing['founder_user_id'] ?? 0);
        $termsLocked = vh_venture_partner_count($mysqli, $ventureId, $ventureFounderId) > 0;
        if ($termsLocked) {
            $targetCapital = (int)$existing['target_capital'];
        }
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $durationEditable = ($existing['status'] ?? '') === 'pending_payment';
        $maxListingDays = vh_max_listing_days($mysqli);
        $daysLeft = (int)($existing['days_left'] ?? 0);
        if ($durationEditable && isset($input['days_left'])) {
            $daysLeft = max(VH_LISTING_MIN_DAYS, min($maxListingDays, (int)$input['days_left']));
        }
        
        
        $ftStmt = $mysqli->prepare("SELECT account_type FROM users WHERE id = ?");
        $ftStmt->bind_param("i", $userId);
        $ftStmt->execute();
        $ftRow = $ftStmt->get_result()->fetch_assoc();
        $ftStmt->close();
        $founderType = ($ftRow['account_type'] ?? 'individual') === 'company' ? 'brand' : 'individual';
        $partnerType = trim($input['partner_type'] ?? 'both');
        if ($termsLocked) {
            $partnerType = (string)($existing['partner_types'] ?? $partnerType);
        }
        $requirements = trim($input['requirements'] ?? '');
        $requiredSkills = trim($input['required_skills'] ?? '') ?: null;
        $silentRequirements = trim($input['silent_requirements'] ?? '') ?: null;
        $silentRequiredSkills = trim($input['silent_required_skills'] ?? '') ?: null;
        $applicationDeadline = trim($input['application_deadline'] ?? '') ?: null;

        
        
        
        
        
        
        
        if ($partnerType === 'silent') {
            $requiredSkills = null;
            $applicationDeadline = null;
            $requirements = '';
        }


        if ($partnerType === 'active') {
            $silentRequirements = null;
            $silentRequiredSkills = null;
        }
        $exit = readExitTerms($input);

        /* THE WHOLE CAPITAL STEP FREEZES ONCE A PARTNER JOINS.
        
           "Capital page ko complete unedit kar deta ha jese partner type kiya hua ha" —
           the client, 4 Sep 2026, and he confirmed the founder may not lower their own
           contribution either. Everything the step writes is replaced with what is
           stored, so whatever the form posted is ignored wholesale.
        
           Ignored rather than refused, the same as `days_left` and `partner_types`: the
           edit form may simply be stale, and rejecting the save would throw away the
           title, description and requirements edits the founder actually came to make.
        
           It sits HERE, right after the terms are read and before any of the rules that
           police changes to them, because with the values frozen there is no change left
           to police — the rescales, the member-limit floor and the silent-cap gap all
           become checks against a listing editing itself, and a founder must never be
           refused an edit over a figure they were not allowed to touch. */
        if ($termsLocked) {
            $frozen = vh_frozen_capital_terms($mysqli, $ventureId);

            $targetCapital       = (int)($frozen['target_capital'] ?? $targetCapital);
            $founderContribution = (int)($frozen['founder_contribution'] ?? $founderContribution);
            $minInvestment       = (int)($frozen['min_investment'] ?? $minInvestment);

            foreach ($exit as $k => $_) {
                if (array_key_exists($k, $frozen)) $exit[$k] = $frozen[$k];
            }
        }

        // The founder's investment equity follows their own money.
        //
        // Halving your contribution while keeping the same percentage is giving yourself
        // a better deal after the fact, so the investment half is rescaled by the ratio of
        // the change. The OPERATIONS half deliberately does not move — it is paid for the
        // work the founder does, not for capital, the same rule that stops an active
        // partner's ops equity scaling with their pledge.
        //
        // Only applied when the founder left the percentage alone. If they typed a new
        // one, that is a deliberate figure and it wins — this exists to stop the number
        // going stale behind their back, not to take the field away from them.
        $oldContribution  = (int)$existing['founder_contribution'];
        $storedFounderPct = $existing['founder_equity_percent'];
        $postedFounderPct = $exit['founder_equity_percent'];
        $founderPctUntouched = ($storedFounderPct === null && $postedFounderPct === null)
            || ($storedFounderPct !== null && $postedFounderPct !== null
                && abs((float)$storedFounderPct - (float)$postedFounderPct) < 0.005);
        if ($founderPctUntouched && $storedFounderPct !== null
            && $oldContribution > 0 && $founderContribution !== $oldContribution) {
            $scaled = round((float)$storedFounderPct * $founderContribution / $oldContribution, 2);
            $exit['founder_equity_percent'] = max(0.0, min(100.0, $scaled));
        }

        // The PARTNER percentages follow the minimum ticket, for the same reason.
        //
        // Every partner percentage is quoted "per member at the minimum investment" — that
        // ticket is the unit the whole equity table is measured in. So moving the ticket
        // without moving the percentage silently changes the price of the company: drop the
        // minimum from 50,000 to 25,000 and keep 6%, and the founder is now handing over
        // twice as much equity for every rupee raised (240% instead of 120% for the same
        // 10L). The client asked for exactly this, in his own worked example: "pehle minimum
        // 50000 tha equity 6% ... ab 25000 kardi toh investment equity 3% per shift ho jaaye".
        //
        // It also makes the equity SNAPSHOT and the listing agree instead of diverging. A
        // member who paid 50,000 under 50,000/6% holds 6%; after the rescale a newcomer
        // paying 50,000 under 25,000/3% also gets 6%. Same deal, smaller entry ticket —
        // which is the only thing the founder actually wanted to change.
        //
        // OPERATIONS equity does not move: it is paid for the work an active partner does,
        // not for the capital they bring, the same rule as the founder's own ops half.
        $oldMinInvestment = (int)($existing['min_investment'] ?? 0);
        if ($oldMinInvestment > 0 && $minInvestment > 0 && $minInvestment !== $oldMinInvestment) {
            $ratio = $minInvestment / $oldMinInvestment;
            foreach (['active_equity_percent', 'silent_equity_percent'] as $key) {
                $stored = $existing[$key] ?? null;
                $posted = $exit[$key];
                // A percentage the founder retyped is deliberate and wins, exactly as with
                // their own. This only stops a number going stale behind their back.
                $untouched = ($stored !== null && $posted !== null
                    && abs((float)$stored - (float)$posted) < 0.005);
                if ($untouched) {
                    $exit[$key] = max(0.0, min(100.0, round((float)$stored * $ratio, 2)));
                }
            }
        }

        if (!$title || !$description || !$industry || !$location || !$targetCapital || !$minInvestment) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        // Same floor as `create`, with one exemption: a listing published before the rule
        // may already sit under it, and holding its founder to a number they never agreed
        // to would lock them out of fixing a typo — the trap the equity percentages already
        // taught us. So the test is "do not make it worse": keeping or RAISING the existing
        // ticket is always allowed, lowering it below the floor is not.
        $memberCap = vh_max_members($mysqli);
        $minFloor  = vh_min_investment_floor($targetCapital, $founderContribution, $memberCap);
        $storedMin = (int)($existing['min_investment'] ?? 0);
        // Skipped when the step is frozen: min_investment is the stored value by then, so
        // a listing under the floor would refuse its own founder every edit.
        if (!$termsLocked && $minFloor > 0 && $minInvestment < $minFloor && $minInvestment < $storedMin) {
            echo json_encode([
                'success'      => false,
                'memberLimit'  => true,
                'minInvestment'=> $minFloor,
                'maxMembers'   => $memberCap,
                'message'      => vh_member_limit_message($minFloor, $memberCap),
            ]);
            exit;
        }

        
        
        
        
        // Both of these read frozen values once a partner has joined, so a listing that
        // predates either rule and holds a NULL would refuse its founder every edit —
        // over a field they are no longer allowed to fill in.
        if (!$termsLocked && $exit['profit_distribution_frequency'] === null) {
            echo json_encode(['success' => false, 'message' => 'Please choose how often profit will be distributed.']);
            exit;
        }

        
        
        
        
        
        if (!$termsLocked && ($exit['expected_roi_min'] === null || $exit['expected_roi_max'] === null)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please enter the expected ROI range — two percentages between 0 and 100, with the lower figure first.',
            ]);
            exit;
        }

        
        
        
        
        
        $existingSplit = vh_capital_split($mysqli, $ventureId, $existing);
        /* Frozen with the rest of the Capital step once a partner has joined — there is
           nothing to validate, and refusing an edit over a field the founder cannot
           reach would lock them out of the page entirely.
        
           Otherwise: the stored cap is passed so a listing published before the field
           became compulsory may keep its NULL, while anything the founder actually types
           is held to the full rule — the "do not make it worse" shape the member limit
           uses. */
        if ($termsLocked) {
            $silentCapitalLimit = $frozen['silent_capital_limit'] === null
                ? null : (int)$frozen['silent_capital_limit'];
        } else {
            [$silentCapitalLimit, $capError] = vh_resolve_silent_cap(
                $input, $targetCapital, $founderContribution, $partnerType,
                (int)$existingSplit['raised_silent'], $minInvestment,
                (int)($existing['silent_capital_limit'] ?? 0)
            );
            if ($capError !== null) {
                echo json_encode(['success' => false, 'message' => $capError]);
                exit;
            }
        }

        
        
        
        $delta = $founderContribution - (int)$existing['founder_contribution'];
        $raisedCapital = max(0, (int)$existing['raised_capital'] + $delta);
        $progressPercent = $targetCapital > 0 ? (int)(($raisedCapital / $targetCapital) * 100) : 0;

        $catMap = [
            'food & beverage' => 'food', 'technology' => 'tech', 'infrastructure' => 'infra',
            'franchise' => 'franchise', 'manufacturing' => 'tech', 'healthcare' => 'healthcare',
            'education' => 'education', 'logistics' => 'logistics', 'agriculture' => 'agriculture',
            'real estate' => 'real_estate'
        ];
        $category = $catMap[strtolower($industry)] ?? 'franchise';

        // Keep whatever class the listing already carries unless the form sends a
        // new one; only fall back to the industry map for a row that predates the
        // column. Re-saving an edit must never silently re-file a listing.
        $assetClass = vh_normalize_asset_class_list($input['asset_class'] ?? '');
        if ($assetClass === '') $assetClass = vh_normalize_asset_class_list($existing['asset_class'] ?? '');
        if ($assetClass === '') $assetClass = vh_asset_class_for_industry($industry);

        $stmt = $mysqli->prepare("
            UPDATE ventures SET
              title = ?, description = ?, full_description = ?, use_of_funds = ?, industry = ?, location = ?,
              preferred_meeting_venue = ?, target_capital = ?, min_investment = ?, silent_capital_limit = ?,
              founder_contribution = ?, raised_capital = ?, progress_percent = ?, days_left = ?,
              founder_type = ?, partner_types = ?, requirements = ?, silent_requirements = ?,
              required_skills = ?, silent_required_skills = ?, application_deadline = ?, category = ?, asset_class = ?,
              equity_distribution = ?, founder_equity_percent = ?, founder_ops_equity_percent = ?, founder_monthly_salary = ?,
              active_equity_percent = ?, active_ops_equity_percent = ?, active_monthly_salary = ?, silent_equity_percent = ?,
              expected_roi = ?, expected_roi_min = ?, expected_roi_max = ?,
              lockin_period = ?, expected_exit_timeline = ?, exit_options = ?,
              early_exit_allowed = ?, early_exit_notice_period = ?, early_exit_conditions = ?,
              ownership_transfer_allowed = ?, ownership_transfer_approval = ?,
              profit_distribution_frequency = ?, exit_valuation_method = ?, exit_valuation_notes = ?
            WHERE id = ?
        ");
        
        
        
        $stmt->bind_param(
            "sssssss" . "iiiiiii" . "sssssssss" . "sddiddid" . "ssssssississss" . "i",
            $title, $description, $fullDescription, $useOfFunds, $industry, $location,
            $preferredMeetingVenue, $targetCapital, $minInvestment, $silentCapitalLimit,
            $founderContribution, $raisedCapital, $progressPercent, $daysLeft,
            $founderType, $partnerType, $requirements, $silentRequirements,
            $requiredSkills, $silentRequiredSkills, $applicationDeadline, $category, $assetClass,
            $exit['equity_distribution'],
            $exit['founder_equity_percent'], $exit['founder_ops_equity_percent'], $exit['founder_monthly_salary'],
            $exit['active_equity_percent'], $exit['active_ops_equity_percent'], $exit['active_monthly_salary'],
            $exit['silent_equity_percent'],
            $exit['expected_roi'], $exit['expected_roi_min'], $exit['expected_roi_max'],
            $exit['lockin_period'], $exit['expected_exit_timeline'], $exit['exit_options'],
            $exit['early_exit_allowed'], $exit['early_exit_notice_period'], $exit['early_exit_conditions'],
            $exit['ownership_transfer_allowed'], $exit['ownership_transfer_approval'],
            $exit['profit_distribution_frequency'], $exit['exit_valuation_method'], $exit['exit_valuation_notes'],
            $ventureId
        );

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update Asset.']);
            exit;
        }
        $stmt->close();

        // The showcase-only columns are not in the shared UPDATE above, because a
        // real venture must never carry them. Guarded on is_showcase for the same
        // reason, and clamped to the partner pool so a bar cannot exceed 100%.
        if ($isShowcaseVenture) {
            $pool = max(0, $targetCapital - min($founderContribution, $targetCapital));
            $readShowcase = function ($key) use ($input, $pool) {
                $raw = $input[$key] ?? '';
                if ($raw === '' || $raw === null || !is_numeric($raw)) return null;
                return max(0, min((int)$raw, $pool));
            };
            $scSilent = $readShowcase('showcase_raised_silent');
            $scActive = $readShowcase('showcase_raised_active');
            $scName   = trim($input['showcase_founder_name'] ?? '') ?: 'Ventures Harbor';

            $stmt = $mysqli->prepare("
                UPDATE ventures
                   SET founder_name = ?, showcase_raised_silent = ?, showcase_raised_active = ?
                 WHERE id = ? AND is_showcase = 1
            ");
            $stmt->bind_param("siii", $scName, $scSilent, $scActive, $ventureId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $mysqli->prepare("UPDATE venture_members SET invested_amount = ? WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $founderContribution, $ventureId, $userId);
        $stmt->execute();
        $stmt->close();

        
        
        
        
        
        

        
        
        
        
        $stmt = $mysqli->prepare("
            UPDATE ventures
            SET days_left = GREATEST(0, CEIL(TIMESTAMPDIFF(SECOND, NOW(), listing_ends_at) / 86400))
            WHERE id = ? AND listing_ends_at IS NOT NULL
        ");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Asset updated successfully.', 'id' => $ventureId]);
        exit;
    }

    if ($action === 'delete') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
        $reason = trim($input['reason'] ?? '');

        if (!$ventureId || !isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can delete this Asset.']);
            exit;
        }

        
        
        
        
        $others = vh_venture_partner_count($mysqli, $ventureId, $userId);

        if ($others > 0) {
            
            
            
            
            if ($reason === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please give a reason — it is shown to the partners who joined this Asset and are being refunded.',
                ]);
                exit;
            }

            $stmt = $mysqli->prepare("SELECT status FROM ventures WHERE id = ?");
            $stmt->bind_param("i", $ventureId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Asset not found.']);
                exit;
            }
            if (!in_array($row['status'], ['active', 'expired'], true)) {
                echo json_encode([
                    'success' => false,
                    'message' => $row['status'] === 'cancelled'
                        ? 'This Asset has already been deleted.'
                        : 'A suspended listing can only be closed by an administrator.',
                ]);
                exit;
            }

            
            
            
            
            $outcome = vh_cancel_venture($mysqli, $ventureId, 'founder', $reason);
            if (empty($outcome['success'])) {
                echo json_encode(['success' => false, 'message' => $outcome['message'] ?? 'Could not delete this Asset.']);
                exit;
            }

            echo json_encode([
                'success'     => true,
                'mode'        => 'closed',
                'refundCount' => (int)$outcome['refund_count'],
                'refundTotal' => (int)$outcome['refund_total'],
                'message'     => $outcome['refund_count'] > 0
                    ? 'Asset deleted. Full commitment-fee refunds totalling ₹' . vh_inr((int)$outcome['refund_total'])
                        . ' have been opened automatically for ' . (int)$outcome['refund_count'] . ' partner(s), and every open application has been closed.'
                    : 'Asset deleted. No partner had paid a commitment fee, so there is nothing to refund.',
            ]);
            exit;
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $outcome = vh_cancel_venture(
            $mysqli,
            $ventureId,
            'founder',
            $reason !== '' ? $reason : 'Deleted by the founder before any partner joined.'
        );
        if (empty($outcome['success'])) {
            echo json_encode(['success' => false, 'message' => $outcome['message'] ?? 'Could not delete this Asset.']);
            exit;
        }

        echo json_encode([
            'success'     => true,
            'mode'        => 'removed',
            'refundCount' => 0,
            'refundTotal' => 0,
            'message'     => 'Asset deleted. Nobody had joined, so there is nothing to refund — the listing is closed and kept in the platform record.',
        ]);
        exit;
    }

    
    
    
    
    if ($action === 'extend_listing') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;

        if (!$ventureId || !isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can extend this listing.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT title, status, extension_count FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }
        if ($venture['status'] === 'cancelled') {
            echo json_encode(['success' => false, 'message' => 'This Asset has been cancelled and cannot be relisted.']);
            exit;
        }
        if ($venture['status'] !== 'expired') {
            echo json_encode(['success' => false, 'message' => 'This listing has not ended yet. You can change its duration from Edit Asset while it is still running.']);
            exit;
        }
        if ((int)$venture['extension_count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'This listing has already been extended once. The only remaining option is to cancel it.']);
            exit;
        }

        
        
        $maxDays = vh_max_extension_days($mysqli);
        $days = isset($input['days']) ? (int)$input['days'] : $maxDays;
        $days = max(VH_EXTENSION_MIN_DAYS, min($maxDays, $days));

        $stmt = $mysqli->prepare("
            UPDATE ventures
            SET status = 'active',
                listing_ends_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                days_left = ?,
                extension_count = 1,
                extended_at = NOW(),
                expired_at = NULL,
                expiry_reminders_sent = 0,
                last_expiry_reminder_at = NULL
            WHERE id = ? AND status = 'expired' AND extension_count = 0
        ");
        $stmt->bind_param("iii", $days, $days, $ventureId);
        $stmt->execute();
        $extended = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$extended) {
            echo json_encode(['success' => false, 'message' => 'This listing could not be extended — it may have just been extended or cancelled.']);
            exit;
        }

        
        $stmt = $mysqli->prepare("SELECT user_id FROM venture_members WHERE venture_id = ? AND user_id != ?");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $member) {
            vh_notify(
                $mysqli,
                (int)$member['user_id'],
                'venture',
                'Listing Extended 🔄',
                'The founder of "' . $venture['title'] . '" has extended its listing period by ' . $days . ' days to keep raising capital. '
                    . 'The Asset is live again and your partnership continues as normal.'
            );
        }
        $stmt->close();

        vh_notify(
            $mysqli,
            $userId,
            'venture',
            'Listing Extended 🔄',
            'Your Venture "' . $venture['title'] . '" is live again for another ' . $days . ' days. This was your one extension — '
                . 'if it is still not fully funded when this period ends, it will be cancelled automatically and partners refunded.'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Listing extended by ' . $days . ' days. This was the one extension available for this Asset.',
            'days' => $days,
        ]);
        exit;
    }

    
    
    
    
    
    if ($action === 'cancel_venture') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
        $reason = trim($input['reason'] ?? '');

        if (!$ventureId || !isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the founder can cancel this Asset.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT status FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }
        if (!in_array($venture['status'], ['active', 'expired'], true)) {
            echo json_encode([
                'success' => false,
                'message' => $venture['status'] === 'cancelled'
                    ? 'This Asset is already cancelled.'
                    : 'A suspended listing can only be cancelled by an administrator.',
            ]);
            exit;
        }

        $outcome = vh_cancel_venture(
            $mysqli,
            $ventureId,
            'founder',
            $reason !== '' ? $reason : 'Cancelled by the founder.'
        );

        if (empty($outcome['success'])) {
            echo json_encode(['success' => false, 'message' => $outcome['message'] ?? 'Could not cancel this Asset.']);
            exit;
        }

        $note = $outcome['refund_count'] > 0
            ? ' Commitment-fee refunds totalling ₹' . vh_inr($outcome['refund_total'])
                . ' have been opened automatically for ' . $outcome['refund_count'] . ' partner(s).'
            : ' No partner had paid a commitment fee, so there is nothing to refund.';

        echo json_encode([
            'success' => true,
            'message' => 'Asset cancelled.' . $note,
            'refundCount' => $outcome['refund_count'],
            'refundTotal' => $outcome['refund_total'],
        ]);
        exit;
    }

    if ($action === 'join') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
        $role = trim($input['role'] ?? 'silent');
        $investedAmount = isset($input['invested_amount']) ? (int)$input['invested_amount'] : 0;

        if (!$userId || !$ventureId) {
            echo json_encode(['success' => false, 'message' => 'Missing parameter inputs.']);
            exit;
        }
        
        
        if ($role !== 'active' && !$investedAmount) {
            echo json_encode(['success' => false, 'message' => 'Please enter an investment amount.']);
            exit;
        }

        // Fetch venture details
        $stmt = $mysqli->prepare("SELECT founder_user_id, title, status, is_showcase, raised_capital, target_capital, min_investment, silent_capital_limit, founder_contribution, members_count, max_members, founder_type, partner_types, application_deadline, required_skills FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        
        
        
        
        $venture = vh_capital_split($mysqli, $ventureId, $venture);

        
        
        
        
        if (!empty($venture['is_showcase'])) {
            echo json_encode(['success' => false, 'message' => vh_showcase_message()]);
            exit;
        }

        
        
        if ((int)$venture['founder_user_id'] === $userId) {
            echo json_encode(['success' => false, 'message' => 'You are the founder of this Asset — you cannot join it. Use "Edit Asset" to make changes.']);
            exit;
        }

        
        
        
        if ($venture['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => vh_closed_message($venture['status'])]);
            exit;
        }

        
        
        
        
        
        
        // Capacity that an exited partner freed is held for the waitlist, not for
        // whoever happens to open the page next — otherwise the people who waited
        // for exactly this lose it to a passer-by while they are still reading the
        // notification. The seat is claimed from admin/waitlist.php instead.
        $reservedSeats = vh_reserved_seat_capital($mysqli, $ventureId);
        if ($reservedSeats > 0) {
            $joinLimits = vh_pledge_limits($venture, $role);
            if ($joinLimits['remaining'] - $reservedSeats < $investedAmount) {
                http_response_code(409);
                echo json_encode([
                    'success'      => false,
                    'seatReserved' => true,
                    'message'      => 'The room left in this Asset belongs to its waitlist — a partner exited and '
                                    . 'everyone waiting was offered the seat. Join the waitlist to be in line for the next one.',
                ]);
                exit;
            }
        }

        $openness = vh_venture_openness($venture);
        if (($role === 'active' && !$openness['accepts_active'])
            || ($role !== 'active' && !$openness['accepts_silent'])) {
            echo json_encode([
                'success'   => false,
                'message'   => vh_role_closed_message($venture, $role),
                
                
                'tryActive' => $role !== 'active' && $openness['accepts_active'],
            ]);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT name, email, role, skills FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        if ($user['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Admin accounts cannot join Assets. Please use a regular user account.']);
            exit;
        }

        
        

        
        
        
        
        
        if ($role !== 'active') {
            
            
            $pledgeError = vh_validate_pledge($venture, $investedAmount, 'silent');
            if ($pledgeError !== null) {
                echo json_encode(['success' => false, 'message' => $pledgeError]);
                exit;
            }
        }

        // Check if user already joined
        $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You are already a member of this Asset.']);
            exit;
        }
        $stmt->close();

        
        
        
        
        $feeRate = 0.005;
        $feeAmount = (int) round($investedAmount * $feeRate);
        $chargedAmount = $feeAmount;

        
        
        
        
        
        if ($role === 'active') {
            $stmt = $mysqli->prepare("SELECT id FROM venture_applications WHERE venture_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $ventureId, $userId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'You have already applied to this Asset.']);
                exit;
            }
            $stmt->close();

            
            
            if (!isset($_FILES['resume']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
                echo json_encode(['success' => false, 'message' => 'Please attach your resume (PDF, DOC or DOCX) to apply.']);
                exit;
            }
            $resumeFile = $_FILES['resume'];
            if ($resumeFile['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Resume upload failed. Please try again.']);
                exit;
            }
            $resumeExt = strtolower(pathinfo($resumeFile['name'], PATHINFO_EXTENSION));
            if (!in_array($resumeExt, ['pdf', 'doc', 'docx'], true)) {
                echo json_encode(['success' => false, 'message' => 'Resume must be a PDF, DOC or DOCX file.']);
                exit;
            }
            if ($resumeFile['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Resume must be smaller than 5MB.']);
                exit;
            }
            $resumeDir = __DIR__ . '/../uploads/resumes';
            if (!is_dir($resumeDir) && !mkdir($resumeDir, 0755, true) && !is_dir($resumeDir)) {
                echo json_encode(['success' => false, 'message' => 'Server could not create the resumes folder. Check uploads permissions.']);
                exit;
            }
            $resumeNewName = 'resume_' . $ventureId . '_' . $userId . '_' . time() . '.' . $resumeExt;
            if (!move_uploaded_file($resumeFile['tmp_name'], $resumeDir . '/' . $resumeNewName)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded resume.']);
                exit;
            }
            $resumePath = 'uploads/resumes/' . $resumeNewName;
            $resumeName = substr((string) $resumeFile['name'], 0, 200);

            // Optional short note from the applicant to the founder.
            $applyMessage = isset($input['message']) ? trim($input['message']) : '';
            if ($applyMessage === '') {
                $applyMessage = null;
            }

            // Shared with the vacated-active-seat route, which the founder chooses
            // between the same way — see vh_skill_match_note().
            $skillMatchNote = vh_skill_match_note($venture['required_skills'] ?? null, $user['skills'] ?? null);

            // The amount the applicant commits. This used to be stored as 0 and
            // silently replaced with the venture's minimum ticket at payment
            // time, so an active partner never chose what they were investing
            // and the founder had nothing to weigh the application against.
            //
            // Validated against vh_pledge_limits() for the ACTIVE role — only
            // the silent side is capped, so an active partner may draw on the
            // whole remaining target including unused silent capacity. The
            // client checks the same bounds first; this is the one that counts.
            $applyLimits = vh_pledge_limits($venture, 'active');
            $investedAmount = isset($input['invested_amount']) ? (int)$input['invested_amount'] : 0;

            if ($applyLimits['is_full']) {
                @unlink($resumeDir . '/' . $resumeNewName);
                echo json_encode(['success' => false, 'message' => 'This Asset has reached its funding target and is not accepting further commitments.']);
                exit;
            }
            if ($investedAmount <= 0) {
                @unlink($resumeDir . '/' . $resumeNewName);
                echo json_encode(['success' => false, 'message' => 'Enter how much you want to invest in this Asset.']);
                exit;
            }
            if ($investedAmount < $applyLimits['min']) {
                @unlink($resumeDir . '/' . $resumeNewName);
                echo json_encode(['success' => false, 'message' => 'The minimum for this Asset is ₹' . vh_inr($applyLimits['min']) . '.']);
                exit;
            }
            if ($investedAmount > $applyLimits['max']) {
                @unlink($resumeDir . '/' . $resumeNewName);
                echo json_encode(['success' => false, 'message' => 'The most you can commit is ₹' . vh_inr($applyLimits['max']) . ' — that is all this venture still needs.']);
                exit;
            }

            /* The number the founder will use to ask a follow-up question. Required,
               and required HERE as well as in the form: the form is a courtesy, this
               is the rule. Normalised through the one helper both sides share, so
               what is stored is always something a wa.me link can be built from.
               The resume is unlinked on refusal, exactly as the amount checks above
               do — an orphan upload otherwise survives every failed apply. */
            $whatsapp = vh_normalize_whatsapp($_POST['whatsapp_number'] ?? ($input['whatsapp_number'] ?? null));
            if ($whatsapp === null) {
                @unlink($resumeDir . '/' . $resumeNewName);
                echo json_encode(['success' => false, 'message' => 'Enter a valid WhatsApp number so the founder can contact you about your application.']);
                exit;
            }

            $stmt = $mysqli->prepare("INSERT INTO venture_applications (venture_id, user_id, role, invested_amount, message, resume_path, resume_name, skill_match_note, whatsapp_number, status) VALUES (?, ?, 'active', ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iiisssss", $ventureId, $userId, $investedAmount, $applyMessage, $resumePath, $resumeName, $skillMatchNote, $whatsapp);
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'message' => 'Failed to submit application.']);
                exit;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'applied' => true,
                'message' => 'Application submitted! The founder will review your resume and you\'ll be notified if selected.'
            ]);
            exit;
        }

        
        
        
        
        // In live mode a membership is created by the verified PayU callback and
        // by nothing else. Reaching here with a fee owed means the browser took
        // the demo path — either hand-made, or because the live credentials are
        // missing and api/payments.php told it the gateway was unavailable. Both
        // used to end in a free membership and a transaction row marked
        // completed for money that never arrived.
        if ($feeAmount > 0) {
            $payuCfg = vh_payu_config($mysqli);
            if (!vh_payu_allows_manual_payment($payuCfg)) {
                http_response_code(402);
                echo json_encode([
                    'success'        => false,
                    'gatewayRequired' => true,
                    'message'        => vh_payu_manual_blocked_message($payuCfg),
                ]);
                exit;
            }
        }

        $txnId = 'VH' . rand(10000000, 99999999);
        $stmt = $mysqli->prepare("INSERT INTO transactions (user_id, venture_id, venture_name, amount, principal_amount, fee_amount, type, status, txn_id, payment_gateway) VALUES (?, ?, ?, ?, ?, ?, 'venture_investment', 'completed', ?, 'manual')");
        $stmt->bind_param("iisiiis", $userId, $ventureId, $venture['title'], $chargedAmount, $investedAmount, $feeAmount, $txnId);
        $stmt->execute();
        $stmt->close();



        $granted = vh_grant_membership($mysqli, [
            'user_id'         => $userId,
            'venture_id'      => $ventureId,
            'role'            => $role,
            'invested_amount' => $investedAmount,
            'fee_amount'      => $feeAmount,
            'txn_id'          => $txnId,
        ]);

        if (empty($granted['success'])) {
            echo json_encode(['success' => false, 'message' => $granted['message'] ?? 'Failed to join Asset.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Successfully joined the Asset!',
            'txnId' => $txnId,
            'feePaid' => $feeAmount,
            'totalPaid' => $feeAmount
        ]);
        exit;
    } elseif ($action === 'exit') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
        $payoutNote = trim($input['payout_note'] ?? '');
        
        
        
        
        
        
        $refundChoice = trim($input['refund_choice'] ?? 'refund');

        if (!$userId || !$ventureId) {
            echo json_encode(['success' => false, 'message' => 'Missing parameter inputs.']);
            exit;
        }

        if (!in_array($refundChoice, ['refund', 'no_refund'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid exit option.']);
            exit;
        }

        // Get user's investment amount from venture_members. The role comes with
        // it because the seat this person is about to vacate inherits it — a
        // silent seat must not reopen as an active one.
        $stmt = $mysqli->prepare("SELECT invested_amount, role, quit_window_opened_at AS member_window FROM venture_members WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        $mem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$mem) {
            echo json_encode(['success' => false, 'message' => 'You are not a member of this Asset.']);
            exit;
        }

        $investedAmount = $mem['invested_amount'];
        $leaverRole     = (string)($mem['role'] ?? 'silent');

        // Get venture details. The SELECT carries everything vh_venture_openness()
        // reads, because whether this Asset was FULL at the moment of departure is
        // what decides if a waitlist seat opens — and it can only be measured
        // before the member row is deleted a few lines below.
        $stmt = $mysqli->prepare("
            SELECT title, raised_capital, target_capital, members_count, quit_window_opened_at, status,
                   partner_types, founder_contribution, founder_user_id, min_investment,
                   silent_capital_limit, is_showcase, listing_ends_at
              FROM ventures WHERE id = ?
        ");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        /* A founder cannot exit their own Asset, and this guard is the only thing that
           said so. `create` writes them a venture_members row (role 'active', holding
           their own contribution), so every member check above passes for them — the
           DELETE below would have removed the founder from their own listing, dropped
           raised_capital by their contribution, and left a venture whose founder is not
           a member of it. vh_capital_split() excludes the founder by id, the meetup cap
           counts them, and the equity back-fill assumes the row exists; all three would
           then be reading a venture that cannot happen. Closing a listing is
           `action=delete`, which routes through vh_cancel_venture() and refunds the
           partners — that is the founder's exit, and it is a different thing. */
        if ((int)$venture['founder_user_id'] === (int)$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'You founded this Asset, so there is nothing to exit from. '
                           . 'To close it, use Delete Listing — every partner is refunded automatically.',
            ]);
            exit;
        }

        
        
        
        
        
        if ($venture['status'] === 'cancelled') {
            echo json_encode([
                'success' => false,
                'message' => 'This Asset has been cancelled and your refund was opened automatically — there is nothing to exit from. '
                           . 'Check your Payment Statement page for the refund status.',
            ]);
            exit;
        }

        
        
        $feeAmount = getPaidCommitmentFee($mysqli, $userId, $ventureId);

        // The member's own window wins over the venture's — see vh_member_quit_window().
        $openedAt = vh_member_quit_window($mem['member_window'] ?? null, $venture['quit_window_opened_at']);
        /* The authoritative refund decision, and it must be measured by the same
           clock that wrote the timestamp. Done in PHP this ran 3.5 hours long on
           this install — real refunds granted after the window had actually shut. */
        $quitSecondsLeft = vh_quit_window_seconds_left($mysqli, $openedAt);
        $windowOpen = $quitSecondsLeft !== null && $quitSecondsLeft > 0;

        /* Was the Asset full at the moment this person left? Measured HERE, before
           the member row goes, because a moment later it has room by definition and
           the answer would always be no. It answers two questions at once: whether
           the forfeit route is closed (below), and whether there is a seat worth
           offering the waitlist afterwards. One computation, so the two can never
           disagree about whether this Asset was full. */
        $wasFullRows = [$venture];
        vh_attach_capital_split($mysqli, $wasFullRows);
        /* vh_attach_reserved_seats() was missing here, and its absence meant the
           SECOND departure from a funded Asset opened no seat at all: the first
           exit had already dropped raised_capital, so without counting the seat it
           reserved the Asset read as "has room" and vh_waitlist_is_open() said no.
           The same omission that made the homepage show Co-Own on an Asset holding
           a seat for its waitlist. Several partners can leave and several seats can
           stand open at once — that is the documented intent. */
        vh_attach_reserved_seats($mysqli, $wasFullRows);
        $ventureBeforeExit = $wasFullRows[0];
        $wasFull = vh_waitlist_is_open($ventureBeforeExit);

        if ($refundChoice === 'no_refund' && vh_no_refund_exit_blocked($ventureBeforeExit, $openedAt)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'noRefundBlocked' => true,
                'message' => vh_no_refund_blocked_message(),
            ]);
            exit;
        }

        $bank = [
            'bank_account_name'   => null,
            'bank_account_number' => null,
            'bank_ifsc'           => null,
            'bank_name'           => null,
        ];

        if ($refundChoice === 'refund') {
            
            if (!$openedAt) {
                echo json_encode([
                    'success' => false,
                    'message' => 'The commitment fee only becomes refundable in the 24 hours after the founder marks a meetup as completed. You can still leave now using "Exit Without Refund".',
                    'canExitWithoutRefund' => true,
                ]);
                exit;
            }
            if (!$windowOpen) {
                echo json_encode([
                    'success' => false,
                    'message' => 'The 24-hour refund window for this Asset has closed. You can still leave using "Exit Without Refund".',
                    'canExitWithoutRefund' => true,
                ]);
                exit;
            }
            if ($feeAmount <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No commitment fee payment is on record for this Asset, so there is nothing to refund. You can still leave using "Exit Without Refund".',
                    'canExitWithoutRefund' => true,
                ]);
                exit;
            }

            [$bank, $bankError] = validateBankDetails($input);
            if ($bankError) {
                echo json_encode(['success' => false, 'message' => $bankError]);
                exit;
            }
        }

        $leaverName = '';
        $stmt = $mysqli->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $leaverRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($leaverRow) { $leaverName = (string)$leaverRow['name']; }

        // Delete member
        $stmt = $mysqli->prepare("DELETE FROM venture_members WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        $stmt->close();

        // Update venture financials
        $newRaised = max(0, $venture['raised_capital'] - $investedAmount);
        $newMembers = max(0, $venture['members_count'] - 1);
        $newProg = $venture['target_capital'] > 0 ? (int)(($newRaised / $venture['target_capital']) * 100) : 0;

        $stmt = $mysqli->prepare("UPDATE ventures SET raised_capital = ?, members_count = ?, progress_percent = ? WHERE id = ?");
        $stmt->bind_param("iiii", $newRaised, $newMembers, $newProg, $ventureId);
        $stmt->execute();
        $stmt->close();

        // The seat this person just vacated, offered to everyone waiting at once
        // with the reason they gave. Returns [0, 0] and costs nothing when the
        // Asset was not full, which is the ordinary case.
        [$openedSlotId, $waitlistNotified] = vh_open_slot_for_exit(
            $mysqli,
            $ventureId,
            $ventureBeforeExit,
            $userId,
            $leaverName,
            $leaverRole,
            (int)$investedAmount,
            $payoutNote,
            $wasFull
        );

        
        
        
        
        
        
        $txnId = 'VH' . rand(10000000, 99999999);

        if ($refundChoice === 'refund') {
            $stmt = $mysqli->prepare("
                INSERT INTO transactions
                    (user_id, venture_id, venture_name, amount, principal_amount, fee_amount,
                     type, status, refund_type, txn_id, payment_gateway,
                     bank_account_name, bank_account_number, bank_ifsc, bank_name, payout_note)
                VALUES (?, ?, ?, ?, ?, ?, 'refund_request', 'pending', 'exit', ?, 'manual', ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iisiiissssss",
                $userId, $ventureId, $venture['title'], $feeAmount, $investedAmount, $feeAmount, $txnId,
                $bank['bank_account_name'], $bank['bank_account_number'], $bank['bank_ifsc'], $bank['bank_name'], $payoutNote
            );
            $stmt->execute();
            $stmt->close();

            $notifTitle = 'Exit Refund Requested 💸';
            $notifMsg = "You have left the Asset: " . $venture['title'] . ". Because you exited within 24 hours of the meetup, your full commitment fee of ₹" . vh_inr($feeAmount) . " is refundable — the request has been submitted for admin review and will be transferred offline to the bank account you provided.";
            $responseMsg = 'You have exited the Asset. Your full commitment fee of ₹' . vh_inr($feeAmount) . ' has been submitted as a refund request for admin review.';
        } else {
            $zero = 0;
            $stmt = $mysqli->prepare("
                INSERT INTO transactions
                    (user_id, venture_id, venture_name, amount, principal_amount, fee_amount,
                     type, status, refund_type, txn_id, payment_gateway, payout_note)
                VALUES (?, ?, ?, ?, ?, ?, 'refund_request', 'waived', 'exit', ?, 'manual', ?)
            ");
            $stmt->bind_param(
                "iisiiiss",
                $userId, $ventureId, $venture['title'], $zero, $investedAmount, $feeAmount, $txnId, $payoutNote
            );
            $stmt->execute();
            $stmt->close();

            $notifTitle = 'Left Asset 🚪';
            $notifMsg = "You have left the Asset: " . $venture['title'] . ". You chose to exit without a refund, so the commitment fee of ₹" . vh_inr($feeAmount) . " has not been returned. No further action is needed.";
            $responseMsg = 'You have exited the Asset without a refund. The commitment fee of ₹' . vh_inr($feeAmount) . ' has not been returned.';
        }

        // Create notification
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', ?, ?)");
        $stmt->bind_param("iss", $userId, $notifTitle, $notifMsg);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => $responseMsg,
            'refundChoice' => $refundChoice,
            'refundRequested' => $refundChoice === 'refund' ? $feeAmount : 0,
            'feeForfeited' => $refundChoice === 'refund' ? 0 : $feeAmount,
        ]);
        exit;
    }

    if ($action === 'decide_application') {
        $userId = requireAuth();
        $applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
        $decision = trim($input['decision'] ?? '');

        if (!$applicationId || !in_array($decision, ['selected', 'rejected'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT a.venture_id, a.user_id, a.slot_id, v.title FROM venture_applications a JOIN ventures v ON a.venture_id = v.id WHERE a.id = ? AND a.status = 'pending'");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$application) {
            echo json_encode(['success' => false, 'message' => 'Application not found or already decided.']);
            exit;
        }

        /* A seat candidate is NOT an ordinary applicant, even though both are rows
           in this table. Selecting one here would mark them `selected` without ever
           moving the seat, and confirm_application would then grant them a plain
           membership — the Asset would gain a partner while the vacated seat sat
           open for somebody else, counting the same capital twice. Selection for a
           seat goes through vh_select_slot_candidate(), which moves both together. */
        if (!empty($application['slot_id'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'seatApplication' => true, 'message' =>
                'This is an application for a vacated seat. Choose from the seat\'s applicants on the '
                . 'Waitlist seat panel instead — that is what holds the seat for the person you pick.']);
            exit;
        }

        if (!isFounderOf($mysqli, (int)$application['venture_id'], $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the Asset founder can decide applications.']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE venture_applications SET status = ?, decided_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $decision, $applicationId);
        $stmt->execute();
        $stmt->close();

        $applicantId = (int)$application['user_id'];
        if ($decision === 'selected') {
            $notifMsg = "You've been selected as an active partner for " . $application['title'] . "! Complete your commitment fee payment to confirm your place.";
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Application Selected 🎉', ?)");
        } else {
            $notifMsg = "Your active-partner application for " . $application['title'] . " was not selected this time.";
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Application Update', ?)");
        }
        $stmt->bind_param("is", $applicantId, $notifMsg);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Application updated.']);
        exit;
    }

    
    
    
    
    
    
    if ($action === 'withdraw_application') {
        $userId = requireAuth();
        $applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;

        $stmt = $mysqli->prepare("
            SELECT a.id, a.user_id, a.status, a.resume_path, a.venture_id,
                   v.title, v.founder_user_id
            FROM venture_applications a JOIN ventures v ON a.venture_id = v.id
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$application || (int)$application['user_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            exit;
        }

        if (!in_array($application['status'], ['pending', 'selected'], true)) {
            echo json_encode(['success' => false, 'message' => 'This application can no longer be withdrawn.']);
            exit;
        }

        $wasSelected = $application['status'] === 'selected';

        $stmt = $mysqli->prepare("DELETE FROM venture_applications WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $applicationId, $userId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Could not withdraw your application. Please try again.']);
            exit;
        }
        $stmt->close();

        
        
        if (!empty($application['resume_path'])) {
            $resumeFsPath = __DIR__ . '/../' . $application['resume_path'];
            if (is_file($resumeFsPath)) {
                @unlink($resumeFsPath);
            }
        }

        
        
        if ($wasSelected && !empty($application['founder_user_id'])) {
            $founderId = (int)$application['founder_user_id'];
            $notifMsg = "An applicant you selected as an active partner for " . $application['title'] . " has withdrawn their application before paying the commitment fee.";
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Applicant Withdrew', ?)");
            $stmt->bind_param("is", $founderId, $notifMsg);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode([
            'success' => true,
            'message' => $wasSelected
                ? 'Your application has been withdrawn and the founder has been notified. Nothing was charged.'
                : 'Your application has been removed. Nothing was charged.',
        ]);
        exit;
    }

    if ($action === 'confirm_application') {
        $userId = requireAuth();
        $applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;

        $stmt = $mysqli->prepare("SELECT id, venture_id, user_id, role, invested_amount, status, slot_id FROM venture_applications WHERE id = ?");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$application || (int)$application['user_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            exit;
        }

        /* The seat-candidate twin of the guard in decide_application. Paying here
           would grant an ordinary membership and leave the seat unfilled — the seat
           has to be paid for through the Waitlist page, which routes to
           vh_fill_slot() and marks the seat taken under a guard. */
        if (!empty($application['slot_id'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'seatApplication' => true, 'message' =>
                'You were selected for a vacated seat — pay for it from your Waitlist page, '
                . 'which is what actually hands you the seat.']);
            exit;
        }

        if ($application['status'] !== 'selected') {
            echo json_encode(['success' => false, 'message' => 'This application is not ready for payment.']);
            exit;
        }

        $ventureId = (int)$application['venture_id'];
        $role = $application['role'];
        $investedAmount = (int)$application['invested_amount'];

        $stmt = $mysqli->prepare("SELECT title, status, raised_capital, target_capital, members_count, max_members, founder_type, min_investment, silent_capital_limit, founder_contribution FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venture) {
            echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            exit;
        }

        $venture = vh_capital_split($mysqli, $ventureId, $venture);

        if ($venture['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => vh_closed_message($venture['status'])]);
            exit;
        }

        
        
        // Legacy only. Applications now carry the amount the applicant entered
        // and validated at apply time; rows created before that was collected
        // hold 0, and fall back to the minimum ticket rather than charging a
        // 0.5% fee on nothing.
        if ($role === 'active' && $investedAmount <= 0) {
            $investedAmount = (int) $venture['min_investment'];
        }

        
        
        
        
        
        $limits = vh_pledge_limits($venture, $role === 'active' ? 'active' : 'silent');
        if ($role === 'active') {
            
            
            
            
            
            
            
            $investedAmount = vh_active_pledge_for($venture, $investedAmount);
        } elseif ($limits['is_full']) {
            $b = $limits['buckets'];
            echo json_encode([
                'success' => false,
                
                
                
                'message' => ($b['has_cap'] && $b['remaining'] > 0)
                    ? 'Silent partnership on this Asset filled up while your application was open — the ₹' . vh_inr($b['silent_cap'])
                      . ' opened to capital-only partners is now fully committed, and the remaining ₹' . vh_inr($b['remaining'])
                      . ' is reserved for active partners. Please contact the founder.'
                    : 'This Asset reached its funding target while your application was open, so no further commitments can be accepted. Please contact the founder.',
            ]);
            exit;
        } elseif ($investedAmount > $limits['max']) {
            $investedAmount = $limits['max'];
        }

        // No member cap — see the 'join' action above.

        $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $ventureId, $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You are already a member of this Asset.']);
            exit;
        }
        $stmt->close();

        
        
        $feeRate = 0.005;
        $feeAmount = (int) round($investedAmount * $feeRate);
        $chargedAmount = $feeAmount;

        // Same rule as the silent join above: in live mode only the verified
        // PayU callback may turn a selected applicant into a paying member.
        if ($feeAmount > 0) {
            $payuCfg = vh_payu_config($mysqli);
            if (!vh_payu_allows_manual_payment($payuCfg)) {
                http_response_code(402);
                echo json_encode([
                    'success'         => false,
                    'gatewayRequired' => true,
                    'message'         => vh_payu_manual_blocked_message($payuCfg),
                ]);
                exit;
            }
        }

        $txnId = 'VH' . rand(10000000, 99999999);
        $stmt = $mysqli->prepare("INSERT INTO transactions (user_id, venture_id, venture_name, amount, principal_amount, fee_amount, type, status, txn_id, payment_gateway) VALUES (?, ?, ?, ?, ?, ?, 'venture_investment', 'completed', ?, 'manual')");
        $stmt->bind_param("iisiiis", $userId, $ventureId, $venture['title'], $chargedAmount, $investedAmount, $feeAmount, $txnId);
        $stmt->execute();
        $stmt->close();

        $granted = vh_grant_membership($mysqli, [
            'user_id'         => $userId,
            'venture_id'      => $ventureId,
            'role'            => $role,
            'invested_amount' => $investedAmount,
            'fee_amount'      => $feeAmount,
            'txn_id'          => $txnId,
            'application_id'  => $applicationId,
        ]);

        if (empty($granted['success'])) {
            echo json_encode(['success' => false, 'message' => $granted['message'] ?? 'Failed to confirm membership.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Successfully joined the Asset!',
            'txnId' => $txnId,
            'feePaid' => $feeAmount,
            'totalPaid' => $feeAmount
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);

