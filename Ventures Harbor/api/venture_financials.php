<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function reportUploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That file is larger than this server allows (check upload_max_filesize / post_max_size in php.ini).';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was selected.';
        default:
            return 'Upload failed (code ' . $code . ').';
    }
}

function isVentureMember(mysqli $mysqli, int $ventureId, int $userId): bool
{
    $stmt = $mysqli->prepare("SELECT id FROM venture_members WHERE venture_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ventureId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $isMember = $stmt->num_rows > 0;
    $stmt->close();
    return $isMember;
}

function attachReportDetails(mysqli $mysqli, array $report, ?array $venture = null): array
{
    $reportId = (int)$report['id'];
    $ventureId = (int)$report['venture_id'];

    $stmt = $mysqli->prepare("SELECT category, amount FROM venture_financial_expenses WHERE report_id = ?");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $report['expenses'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$venture) {
        $stmt = $mysqli->prepare("SELECT raised_capital FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $partnerShares = [];
    $raisedCapital = (int)($venture['raised_capital'] ?? 0);
    if ($raisedCapital > 0) {
        $stmt = $mysqli->prepare("SELECT u.name, vm.invested_amount FROM venture_members vm JOIN users u ON vm.user_id = u.id WHERE vm.venture_id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($members as $m) {
            $share = (int) round($report['profit_loss'] * ($m['invested_amount'] / $raisedCapital));
            $partnerShares[] = ['name' => $m['name'], 'share' => $share];
        }
    }
    $report['partner_shares'] = $partnerShares;

    return $report;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;

        $stmt = $mysqli->prepare("SELECT raised_capital, is_showcase FROM ventures WHERE id = ?");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $venture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // A sample listing is the platform's worked example — its whole purpose
        // is to show a visitor what being a partner looks like, and the monthly
        // report is the most convincing part of that. It has no real members and
        // can never gain one, so there is no private figure here to protect.
        // Every other venture stays members-only, unchanged.
        $isShowcase = $venture && (int)$venture['is_showcase'] === 1;
        if (!$isShowcase) {
            $userId = requireAuth();
            if (!$ventureId || !isVentureMember($mysqli, $ventureId, $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only Asset members can view financial reports.']);
                exit;
            }
        }
        if (!$venture) {
            echo json_encode(['success' => true, 'reports' => []]);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT * FROM venture_financial_reports WHERE venture_id = ? ORDER BY report_month DESC");
        $stmt->bind_param("i", $ventureId);
        $stmt->execute();
        $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $reports = array_map(function ($r) use ($mysqli, $venture) {
            return attachReportDetails($mysqli, $r, $venture);
        }, $reports);

        echo json_encode(['success' => true, 'reports' => $reports]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A multipart upload has no JSON body — PHP has already consumed the stream
    // into $_POST/$_FILES by this point, so php://input reads empty and the
    // json_decode falls through to $_POST.
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $action = $input['action'] ?? '';

    // The founder attaches the month's statement as a PDF. Kept separate from
    // create/update because a file cannot ride along in a JSON body: the form
    // saves the report first, then posts the file against the id it gets back.
    if ($action === 'upload_pdf') {
        $userId = requireAuth();
        $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

        $stmt = $mysqli->prepare("SELECT id, venture_id, pdf_path FROM venture_financial_reports WHERE id = ?");
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$report) {
            echo json_encode(['success' => false, 'message' => 'That report no longer exists.']);
            exit;
        }
        if (!isFounderOf($mysqli, (int)$report['venture_id'], $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the Asset founder can attach a report PDF.']);
            exit;
        }
        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] === UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'No file was selected.']);
            exit;
        }
        if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => reportUploadErrorMessage($_FILES['pdf']['error'])]);
            exit;
        }

        $file = $_FILES['pdf'];
        $fileName = basename($file['name']);

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File must be smaller than 10MB.']);
            exit;
        }
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') {
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/financial_reports';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Server could not create the uploads folder.']);
            exit;
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
        $newFileName = 'report_' . $reportId . '_' . $safeName . '_' . time() . '.pdf';
        $relativePath = 'uploads/financial_reports/' . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $newFileName)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the file to disk on the server.']);
            exit;
        }

        // Replacing the attachment: drop the file the row used to point at, so
        // superseded PDFs don't accumulate on disk unreferenced.
        $previous = $report['pdf_path'] ?? '';
        if ($previous && $previous !== $relativePath) {
            $previousFull = __DIR__ . '/../' . $previous;
            if (is_file($previousFull)) @unlink($previousFull);
        }

        $stmt = $mysqli->prepare("UPDATE venture_financial_reports SET pdf_path = ?, pdf_name = ? WHERE id = ?");
        $stmt->bind_param("ssi", $relativePath, $fileName, $reportId);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success'  => true,
            'message'  => 'Report PDF uploaded.',
            'pdf_path' => $relativePath,
            'pdf_name' => $fileName
        ]);
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $userId = requireAuth();
        $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
        $reportMonth = trim($input['report_month'] ?? '');
        $totalRevenue = isset($input['total_revenue']) ? (int)$input['total_revenue'] : 0;
        $expensesInput = is_array($input['expenses'] ?? null) ? $input['expenses'] : [];
        $cashFlowSummary = trim($input['cash_flow_summary'] ?? '') ?: null;
        $balanceSheetSummary = trim($input['balance_sheet_summary'] ?? '') ?: null;

        if (!$ventureId || !preg_match('/^\d{4}-\d{2}$/', $reportMonth)) {
            echo json_encode(['success' => false, 'message' => 'Asset and a valid month (YYYY-MM) are required.']);
            exit;
        }

        if (!isFounderOf($mysqli, $ventureId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the Asset founder can publish financial reports.']);
            exit;
        }

        // Total expenses is now typed in directly. It used to be derived by
        // summing the line items, which is what made the field readonly in the
        // form. Line items are still honoured when a caller sends them, so an
        // older report being re-saved does not silently lose its breakdown, but
        // a posted total always wins.
        $totalExpenses = 0;
        $cleanExpenses = [];
        foreach ($expensesInput as $item) {
            $category = trim($item['category'] ?? '');
            $amount = (int)($item['amount'] ?? 0);
            if ($category === '' || $amount <= 0) continue;
            $cleanExpenses[] = ['category' => $category, 'amount' => $amount];
            $totalExpenses += $amount;
        }
        if (array_key_exists('total_expenses', $input)) {
            $totalExpenses = max(0, (int)$input['total_expenses']);
        }
        $profitLoss = $totalRevenue - $totalExpenses;

        $stmt = $mysqli->prepare("
            INSERT INTO venture_financial_reports (venture_id, report_month, total_revenue, total_expenses, profit_loss, cash_flow_summary, balance_sheet_summary, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE total_revenue = VALUES(total_revenue), total_expenses = VALUES(total_expenses),
                profit_loss = VALUES(profit_loss), cash_flow_summary = VALUES(cash_flow_summary),
                balance_sheet_summary = VALUES(balance_sheet_summary)
        ");
        $stmt->bind_param("isiiissi", $ventureId, $reportMonth, $totalRevenue, $totalExpenses, $profitLoss, $cashFlowSummary, $balanceSheetSummary, $userId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to save financial report.']);
            exit;
        }
        $stmt->close();

        $stmt = $mysqli->prepare("SELECT id FROM venture_financial_reports WHERE venture_id = ? AND report_month = ?");
        $stmt->bind_param("is", $ventureId, $reportMonth);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $reportId = (int)$report['id'];

        $stmt = $mysqli->prepare("DELETE FROM venture_financial_expenses WHERE report_id = ?");
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $stmt->close();

        foreach ($cleanExpenses as $item) {
            $stmt = $mysqli->prepare("INSERT INTO venture_financial_expenses (report_id, category, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $reportId, $item['category'], $item['amount']);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Financial report published.', 'id' => $reportId]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
