<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

const VM_MAX_ITEMS      = 25;                  // per venture, agreed product cap
                                              // Raised 10 -> 25 at the client's request: a property
                                              // listing needs more slots than a business one. This is
                                              // the ONLY place the number lives — the browser reads it
                                              // back off ?action=list ('maxItems'), so the picker, the
                                              // "slots left" note and both server gates all follow it.
const VM_MAX_IMAGE_SIZE = 8 * 1024 * 1024;     // 8MB
const VM_MAX_VIDEO_SIZE = 20 * 1024 * 1024;    // 20MB — served raw, so kept small
const VM_IMAGE_EXT      = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const VM_VIDEO_EXT      = ['mp4', 'webm', 'mov'];

function vmBytesFromIni(string $key): int
{
    $raw = trim((string)ini_get($key));
    if ($raw === '') return 0;
    $unit = strtolower(substr($raw, -1));
    $value = (int)$raw;
    switch ($unit) {
        case 'g': return $value * 1024 * 1024 * 1024;
        case 'm': return $value * 1024 * 1024;
        case 'k': return $value * 1024;
        default:  return $value;
    }
}

function vmServerUploadLimit(): int
{
    $limits = array_filter([vmBytesFromIni('upload_max_filesize'), vmBytesFromIni('post_max_size')]);
    return $limits ? min($limits) : 0;
}

function vmUploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That file is larger than this server allows (' . round(vmServerUploadLimit() / 1048576) . 'MB).';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was selected.';
        default:
            return 'Upload failed (code ' . $code . ').';
    }
}

/**
 * The *removal* rule, deliberately broader than the add/reorder one: an admin may
 * take media down from any venture, because that is moderation. Putting media in
 * (upload, add_embed, reorder) goes through canEditVentureContent() instead, which
 * only lets an admin write to a sample listing.
 */
function vmCanModerate(mysqli $mysqli, int $ventureId, int $userId): bool
{
    if (isFounderOf($mysqli, $ventureId, $userId)) return true;
    $user = getSessionUser();
    return $user && ($user['role'] ?? '') === 'admin';
}

function vmParseEmbed(string $url): ?array
{
    $url = trim($url);
    if ($url === '') return null;

    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~i', $url, $m)) {
        return [
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $m[1],
            'thumbnail' => 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg'
        ];
    }

    // Instagram post / reel / IGTV — these have a real embed player.
    if (preg_match('~instagram\.com/(?:p|reel|reels|tv)/([A-Za-z0-9_-]{5,20})~i', $url, $m)) {
        return [
            'embed_url' => 'https://www.instagram.com/p/' . $m[1] . '/embed',
            'thumbnail' => null
        ];
    }

    // A channel or profile has no player — YouTube and Instagram both refuse to
    // be framed that way, so an iframe would render a blank box. Stored as a
    // link the gallery opens in a new tab instead of pretending to embed it.
    // (The client pasted an account link and found it "not supported".)
    if (preg_match('~youtube\.com/(?:channel/|c/|user/|@)([A-Za-z0-9_.-]{2,60})~i', $url, $m)) {
        return ['link_url' => $url, 'link_kind' => 'youtube', 'link_label' => '@' . ltrim($m[1], '@')];
    }
    if (preg_match('~instagram\.com/([A-Za-z0-9_.]{1,40})/?(?:\?.*)?$~i', $url, $m)
        && !in_array(strtolower($m[1]), ['p', 'reel', 'reels', 'tv', 'explore', 'accounts'], true)) {
        return ['link_url' => $url, 'link_kind' => 'instagram', 'link_label' => '@' . $m[1]];
    }

    // vimeo.com/ID  |  player.vimeo.com/video/ID
    if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~i', $url, $m)) {
        $thumb = null;
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $json = @file_get_contents('https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . $m[1], false, $ctx);
        if ($json) {
            $data = json_decode($json, true);
            if (!empty($data['thumbnail_url'])) $thumb = $data['thumbnail_url'];
        }
        return [
            'embed_url' => 'https://player.vimeo.com/video/' . $m[1],
            'thumbnail' => $thumb
        ];
    }

    return null;
}

function vmSyncCover(mysqli $mysqli, int $ventureId): void
{
    $stmt = $mysqli->prepare(
        "SELECT file_path FROM venture_media
         WHERE venture_id = ? AND media_type = 'image' AND file_path IS NOT NULL
         ORDER BY sort_order ASC, id ASC LIMIT 1"
    );
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cover = $row['file_path'] ?? null;
    $stmt = $mysqli->prepare('UPDATE ventures SET cover_image = ? WHERE id = ?');
    $stmt->bind_param('si', $cover, $ventureId);
    $stmt->execute();
    $stmt->close();
}

function vmList(mysqli $mysqli, int $ventureId): array
{
    $stmt = $mysqli->prepare(
        "SELECT id, venture_id, media_type, file_path, embed_url, thumbnail_path, sort_order
         FROM venture_media WHERE venture_id = ? ORDER BY sort_order ASC, id ASC"
    );
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
    }
    return $rows;
}

function vmCount(mysqli $mysqli, int $ventureId): int
{
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM venture_media WHERE venture_id = ?');
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $n;
}

function vmNextSort(mysqli $mysqli, int $ventureId): int
{
    $stmt = $mysqli->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM venture_media WHERE venture_id = ?');
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    return $n;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;
    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'media' => vmList($mysqli, $ventureId),
        'maxItems' => VM_MAX_ITEMS,
        'maxImageSize' => VM_MAX_IMAGE_SIZE,
        'maxVideoSize' => min(VM_MAX_VIDEO_SIZE, vmServerUploadLimit() ?: VM_MAX_VIDEO_SIZE),
        'serverLimit' => vmServerUploadLimit()
    ]);
    exit;
}

/* ---------- upload: one image or video file ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    $userId = requireAuth();
    $ventureId = isset($_POST['venture_id']) ? (int)$_POST['venture_id'] : 0;

    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }

    if (!canEditVentureContent($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Asset founder can add media to this Asset.']);
        exit;
    }
    if (vmCount($mysqli, $ventureId) >= VM_MAX_ITEMS) {
        echo json_encode(['success' => false, 'message' => 'This Asset already has the maximum of ' . VM_MAX_ITEMS . ' gallery items. Remove one to add another.']);
        exit;
    }
    if (!isset($_FILES['media']) || $_FILES['media']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'No file was selected.']);
        exit;
    }
    if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => vmUploadErrorMessage($_FILES['media']['error'])]);
        exit;
    }

    $file = $_FILES['media'];
    $fileName = basename($file['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (in_array($ext, VM_IMAGE_EXT, true)) {
        $type = 'image';
        $maxSize = VM_MAX_IMAGE_SIZE;
    } elseif (in_array($ext, VM_VIDEO_EXT, true)) {
        $type = 'video';
        $maxSize = VM_MAX_VIDEO_SIZE;
    } else {
        echo json_encode(['success' => false, 'message' => 'Allowed file types: ' . implode(', ', VM_IMAGE_EXT) . ', ' . implode(', ', VM_VIDEO_EXT) . '.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => ucfirst($type) . ' files must be smaller than ' . round($maxSize / 1048576) . 'MB.']);
        exit;
    }

    $mime = null;
    if (function_exists('finfo_open') && ($finfo = finfo_open(FILEINFO_MIME_TYPE))) {
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    if ($mime !== null) {
        $prefix = $type === 'image' ? 'image/' : 'video/';
        if (strpos($mime, $prefix) !== 0) {
            echo json_encode(['success' => false, 'message' => 'That file does not look like a valid ' . $type . ' (detected ' . $mime . ').']);
            exit;
        }
    }

    $uploadDir = __DIR__ . '/../uploads/venture_media';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Server could not create the uploads folder.']);
        exit;
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
    $newFileName = 'venture_' . $ventureId . '_' . substr($safeName, 0, 40) . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destPath = $uploadDir . '/' . $newFileName;
    $relativePath = 'uploads/venture_media/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save the file to disk on the server.']);
        exit;
    }

    $sort = vmNextSort($mysqli, $ventureId);
    $stmt = $mysqli->prepare('INSERT INTO venture_media (venture_id, media_type, file_path, sort_order, uploaded_by) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issii', $ventureId, $type, $relativePath, $sort, $userId);
    if (!$stmt->execute()) {
        @unlink($destPath);
        echo json_encode(['success' => false, 'message' => 'Failed to save the media record.']);
        exit;
    }
    $stmt->close();

    vmSyncCover($mysqli, $ventureId);

    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' added to the gallery.',
        'media' => vmList($mysqli, $ventureId)
    ]);
    exit;
}

/* ---------- add_embed: YouTube / Vimeo link ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_embed') {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
    $url = trim((string)($input['url'] ?? ''));

    if (!$ventureId) {
        echo json_encode(['success' => false, 'message' => 'Asset ID is required.']);
        exit;
    }
    if (!canEditVentureContent($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Asset founder can add media to this Asset.']);
        exit;
    }
    if (vmCount($mysqli, $ventureId) >= VM_MAX_ITEMS) {
        echo json_encode(['success' => false, 'message' => 'This Asset already has the maximum of ' . VM_MAX_ITEMS . ' gallery items. Remove one to add another.']);
        exit;
    }

    $parsed = vmParseEmbed($url);
    if (!$parsed) {
        echo json_encode(['success' => false, 'message' => 'Please paste a YouTube video or channel link, an Instagram post, reel or profile link, or a Vimeo link.']);
        exit;
    }

    // A channel/profile is stored as 'link' and opened in a new tab; anything
    // with a real player stays 'embed'.
    $isLink = isset($parsed['link_url']);
    $type = $isLink ? 'link' : 'embed';
    $storedUrl = $isLink ? $parsed['link_url'] : $parsed['embed_url'];
    $thumb = $isLink ? null : $parsed['thumbnail'];

    $sort = vmNextSort($mysqli, $ventureId);
    $stmt = $mysqli->prepare("INSERT INTO venture_media (venture_id, media_type, embed_url, thumbnail_path, sort_order, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssii', $ventureId, $type, $storedUrl, $thumb, $sort, $userId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save the link.']);
        exit;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => $isLink ? 'Link added to the gallery.' : 'Video added to the gallery.',
        'media' => vmList($mysqli, $ventureId)
    ]);
    exit;
}

/* ---------- delete: founder or admin ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $mediaId = isset($input['id']) ? (int)$input['id'] : 0;

    if (!$mediaId) {
        echo json_encode(['success' => false, 'message' => 'Media ID is required.']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT venture_id, file_path FROM venture_media WHERE id = ?');
    $stmt->bind_param('i', $mediaId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'That gallery item no longer exists.']);
        exit;
    }
    $ventureId = (int)$row['venture_id'];
    if (!vmCanModerate($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Asset founder or an admin can remove this media.']);
        exit;
    }

    $stmt = $mysqli->prepare('DELETE FROM venture_media WHERE id = ?');
    $stmt->bind_param('i', $mediaId);
    $stmt->execute();
    $stmt->close();

    if (!empty($row['file_path'])) {
        $path = __DIR__ . '/../' . $row['file_path'];
        if (is_file($path)) @unlink($path);
    }

    vmSyncCover($mysqli, $ventureId);

    echo json_encode([
        'success' => true,
        'message' => 'Removed from the gallery.',
        'media' => vmList($mysqli, $ventureId)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reorder') {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
    $order = $input['order'] ?? [];

    if (!$ventureId || !is_array($order) || !$order) {
        echo json_encode(['success' => false, 'message' => 'An Asset ID and an ordered list of media IDs are required.']);
        exit;
    }
    if (!canEditVentureContent($mysqli, $ventureId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the Asset founder can reorder this gallery.']);
        exit;
    }

    $stmt = $mysqli->prepare('UPDATE venture_media SET sort_order = ? WHERE id = ? AND venture_id = ?');
    foreach (array_values($order) as $position => $mediaId) {
        $mediaId = (int)$mediaId;
        if (!$mediaId) continue;
        $stmt->bind_param('iii', $position, $mediaId, $ventureId);
        $stmt->execute();
    }
    $stmt->close();

    vmSyncCover($mysqli, $ventureId);

    echo json_encode([
        'success' => true,
        'message' => 'Gallery order saved.',
        'media' => vmList($mysqli, $ventureId)
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
