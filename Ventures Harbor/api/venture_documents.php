<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

function docUploadErrorMessage(int $code): string
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    requireAuth();
    $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;
    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id, venture_id, title, file_name, file_path, created_at FROM venture_documents WHERE venture_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($docs as &$d) {
        $d['id'] = (string)$d['id'];
    }
    unset($d);

    echo json_encode(['success' => true, 'documents' => $docs]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    $userId = requireAuth();
    $ventureId = isset($_POST['venture_id']) ? (int)$_POST['venture_id'] : 0;
    $title = trim($_POST['title'] ?? '');

    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }
    // Founder, or any admin on a sample listing — the Supporting Documents field
    // sits on the same sample-edit form as the logo. See canEditVentureContent().
    if (!canEditVentureContent($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Asset founder can upload documents for this Asset.']);
        exit;
    }
    if (!isset($_FILES['doc']) || $_FILES['doc']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'No file was selected.']);
        exit;
    }
    if ($_FILES['doc']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => docUploadErrorMessage($_FILES['doc']['error'])]);
        exit;
    }

    $file = $_FILES['doc'];
    $fileName = basename($file['name']);
    if (!$title) {
        $title = pathinfo($fileName, PATHINFO_FILENAME);
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File must be smaller than 10MB.']);
        exit;
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/venture_docs';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Server could not create the uploads folder.']);
        exit;
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
    $newFileName = 'venture_' . $ventureId . '_' . $safeName . '_' . time() . '.pdf';
    $destPath = $uploadDir . '/' . $newFileName;
    $relativePath = 'uploads/venture_docs/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save the file to disk on the server.']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO venture_documents (venture_id, title, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $ventureId, $title, $fileName, $relativePath, $userId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save document record.']);
        exit;
    }
    $docId = $mysqli->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully.',
        'id' => $docId,
        'filePath' => $relativePath,
        'fileName' => $fileName,
        'title' => $title
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $userId = requireAuth();
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
    $docId = isset($input['id']) ? (int)$input['id'] : 0;

    if (!$docId) {
        echo json_encode(['success' => false, 'message' => 'Document ID is required.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT venture_id, file_path FROM venture_documents WHERE id = ?");
    $stmt->bind_param("i", $docId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found.']);
        exit;
    }
    if (!canEditVentureContent($mysqli, (int)$doc['venture_id'], $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Asset founder can delete this document.']);
        exit;
    }

    $fullPath = __DIR__ . '/../' . $doc['file_path'];
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    $stmt = $mysqli->prepare("DELETE FROM venture_documents WHERE id = ?");
    $stmt->bind_param("i", $docId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Document removed.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request']);
