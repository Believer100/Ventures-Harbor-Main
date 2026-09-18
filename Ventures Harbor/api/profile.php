<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That file is larger than this server allows (check upload_max_filesize / post_max_size in php.ini).';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was selected.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server is missing a temporary folder for uploads. Contact your host/admin.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server failed to write the uploaded file to disk (check folder permissions).';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension blocked this upload.';
        default:
            return 'Unknown upload error (code ' . $code . ').';
    }
}

$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
} else {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    if (!$input) {
        $input = $_POST;
    }
}

$action = $input['action'] ?? 'load';

if ($action === 'public') {
    $pid = (int) ($input['id'] ?? 0);
    if ($pid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
        exit;
    }
    $stmt = $mysqli->prepare('SELECT id, name, avatar, avatar_url, role, account_type, city, occupation, skills, bio, age, linkedin_url, twitter_url, instagram_url, website_url, past_experience, company_registration_no, company_founded_year, company_size, company_industry FROM users WHERE id = ?');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // Ventures this person founded or joined, for the profile popup.
    //
    // Scoped to the same four statuses the public Browse list whitelists, so a
    // profile can never surface a listing that is not already public — a
    // pending_payment venture has never been published and stays invisible here.
    // Sample listings are excluded: their founder_user_id is an admin, and a
    // sample is the platform's example rather than that person's own venture.
    //
    // Note what this endpoint does NOT select: `phone` and `email` are absent
    // from the query entirely. It is a public endpoint and neither is a detail
    // a stranger tapping a name should receive.
    $publicStatuses = ['active', 'expired', 'cancelled', 'suspended'];
    $inList = implode(',', array_fill(0, count($publicStatuses), '?'));
    $ventures = [];

    $stmt = $mysqli->prepare("
        SELECT id, title, status
        FROM ventures
        WHERE founder_user_id = ? AND is_showcase = 0 AND status IN ($inList)
        ORDER BY created_at DESC
        LIMIT 25
    ");
    $stmt->bind_param('i' . str_repeat('s', count($publicStatuses)), $pid, ...$publicStatuses);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $ventures[] = [
            'id'       => (int)$row['id'],
            'title'    => $row['title'],
            'status'   => $row['status'],
            'relation' => 'founder',
        ];
    }
    $stmt->close();

    // The founder also holds a venture_members row for their own venture, so it
    // is excluded here — otherwise every founder's listing appears twice.
    $stmt = $mysqli->prepare("
        SELECT v.id, v.title, v.status, vm.role
        FROM venture_members vm
        JOIN ventures v ON v.id = vm.venture_id
        WHERE vm.user_id = ? AND v.founder_user_id <> ?
          AND v.is_showcase = 0 AND v.status IN ($inList)
        ORDER BY vm.joined_at DESC
        LIMIT 25
    ");
    $stmt->bind_param('ii' . str_repeat('s', count($publicStatuses)), $pid, $pid, ...$publicStatuses);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $ventures[] = [
            'id'       => (int)$row['id'],
            'title'    => $row['title'],
            'status'   => $row['status'],
            'relation' => $row['role'] === 'silent' ? 'silent' : 'active',
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'ventures' => $ventures, 'user' => [
        'id' => $u['id'],
        'name' => $u['name'],
        'avatar' => $u['avatar'] ?: strtoupper(substr($u['name'], 0, 2)),
        'avatarUrl' => $u['avatar_url'],
        'role' => $u['role'],
        'city' => $u['city'],
        'occupation' => $u['occupation'],
        'skills' => $u['skills'],
        'bio' => $u['bio'],
        'age' => $u['age'],
        'linkedinUrl' => $u['linkedin_url'],
        'twitterUrl' => $u['twitter_url'],
        'instagramUrl' => $u['instagram_url'],
        'websiteUrl' => $u['website_url'],
        'pastExperience' => $u['past_experience'],
        
        
        
        'accountType' => $u['account_type'] ?? 'individual',
        'companyRegistrationNo' => $u['company_registration_no'] ?? null,
        'companyFoundedYear' => $u['company_founded_year'] ?? null,
        'companySize' => $u['company_size'] ?? null,
        'companyIndustry' => $u['company_industry'] ?? null
    ]]);
    exit;
}

if ($action === 'admin_profile') {
    requireAdmin();
    $pid = (int) ($input['id'] ?? 0);
    if ($pid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
        exit;
    }
    $stmt = $mysqli->prepare('SELECT id, name, email, phone, avatar, avatar_url, role, account_type, account_type_confirmed, company_registration_no, company_founded_year, company_size, company_industry, city, occupation, skills, bio, age, linkedin_url, twitter_url, instagram_url, website_url, past_experience, kyc_status, is_deactivated, wallet_balance, created_at,
        (SELECT COUNT(*) FROM venture_members WHERE user_id = users.id) AS venturesJoined,
        (SELECT COALESCE(SUM(invested_amount), 0) FROM venture_members WHERE user_id = users.id) AS totalInvested
        FROM users WHERE id = ?');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'user' => [
        'id' => $u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
        'phone' => $u['phone'],
        'avatar' => $u['avatar'] ?: strtoupper(substr($u['name'], 0, 2)),
        'avatarUrl' => $u['avatar_url'],
        'role' => $u['role'],
        'city' => $u['city'],
        'occupation' => $u['occupation'],
        'skills' => $u['skills'],
        'bio' => $u['bio'],
        'age' => $u['age'],
        'linkedinUrl' => $u['linkedin_url'],
        'twitterUrl' => $u['twitter_url'],
        'instagramUrl' => $u['instagram_url'],
        'websiteUrl' => $u['website_url'],
        'pastExperience' => $u['past_experience'],
        
        
        'accountType' => $u['account_type'] ?? 'individual',
        'accountTypeConfirmed' => (int)($u['account_type_confirmed'] ?? 0),
        'companyRegistrationNo' => $u['company_registration_no'] ?? null,
        'companyFoundedYear' => $u['company_founded_year'] ?? null,
        'companySize' => $u['company_size'] ?? null,
        'companyIndustry' => $u['company_industry'] ?? null,
        'kycStatus' => $u['kyc_status'],
        'isDeactivated' => (int) $u['is_deactivated'],
        'walletBalance' => (int) $u['wallet_balance'],
        'createdAt' => $u['created_at'],
        'venturesJoined' => (int) $u['venturesJoined'],
        'totalInvested' => (int) $u['totalInvested']
    ]]);
    exit;
}

$userId = requireAuth();

if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'No image file was received. Please choose a photo and try again.']);
        exit;
    }

    $file = $_FILES['avatar'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => uploadErrorMessage($file['error'])]);
        exit;
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.']);
        exit;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image must be smaller than 3MB.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Server could not create the uploads/avatars folder. Check that the uploads directory is writable.']);
        exit;
    }
    if (!is_writable($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'The uploads/avatars folder is not writable by the server. Check its permissions.']);
        exit;
    }

    $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded image to disk.']);
        exit;
    }

    $relativePath = 'uploads/avatars/' . $newFileName;

    
    $stmt = $mysqli->prepare('SELECT avatar_url FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($old['avatar_url'])) {
        $oldFile = __DIR__ . '/../' . $old['avatar_url'];
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $stmt = $mysqli->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
    $stmt->bind_param('si', $relativePath, $userId);
    $stmt->execute();
    $stmt->close();

    $updated = fetchUserById($mysqli, $userId);
    if ($updated) {
        setUserSession($updated);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated successfully.',
        'avatarUrl' => $relativePath
    ]);
    exit;
}

if ($action === 'upload_cover') {
    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'No image file was received. Please choose a photo and try again.']);
        exit;
    }

    $file = $_FILES['cover'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => uploadErrorMessage($file['error'])]);
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

    $uploadDir = __DIR__ . '/../uploads/covers';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Server could not create the uploads/covers folder. Check that the uploads directory is writable.']);
        exit;
    }
    if (!is_writable($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'The uploads/covers folder is not writable by the server. Check its permissions.']);
        exit;
    }

    $newFileName = 'cover_' . $userId . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded image to disk.']);
        exit;
    }

    $relativePath = 'uploads/covers/' . $newFileName;

    $stmt = $mysqli->prepare('SELECT cover_url FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($old['cover_url'])) {
        $oldFile = __DIR__ . '/../' . $old['cover_url'];
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $stmt = $mysqli->prepare('UPDATE users SET cover_url = ? WHERE id = ?');
    $stmt->bind_param('si', $relativePath, $userId);
    $stmt->execute();
    $stmt->close();

    $updated = fetchUserById($mysqli, $userId);
    if ($updated) {
        setUserSession($updated);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cover photo updated successfully.',
        'coverUrl' => $relativePath
    ]);
    exit;
}

if ($action === 'update') {
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $age = $input['age'] ?? null;
    $occupation = trim($input['occupation'] ?? '');
    $skills = trim($input['skills'] ?? '');
    $city = trim($input['city'] ?? '');
    $bio = trim($input['bio'] ?? '');
    $avatar = trim($input['avatar'] ?? '');
    $linkedinUrl = trim($input['linkedin_url'] ?? '');
    $twitterUrl = trim($input['twitter_url'] ?? '');
    $instagramUrl = trim($input['instagram_url'] ?? '');
    $websiteUrl = trim($input['website_url'] ?? '');
    $pastExperience = trim($input['past_experience'] ?? '');
    $companyRegNo = trim($input['company_registration_no'] ?? '');
    $companyFounded = $input['company_founded_year'] ?? null;
    $companySize = trim($input['company_size'] ?? '');
    $companyIndustry = mb_substr(trim($input['company_industry'] ?? ''), 0, 80);

    if ($age !== null && $age !== '') {
        $age = (int) $age;
    } else {
        $age = null;
    }
    $companyFounded = ($companyFounded !== null && $companyFounded !== '')
        ? (int) $companyFounded
        : null;

    $stmt = $mysqli->prepare('SELECT id, account_type FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    
    
    
    
    $accountType = in_array($input['account_type'] ?? '', ['individual', 'company'], true)
        ? $input['account_type']
        : (string)($existing['account_type'] ?? 'individual');

    $isCompany = $accountType === 'company';

    
    
    
    if ($isCompany) {
        $age = null;
        $occupation = '';
        $pastExperience = '';
    } else {
        $companyRegNo = '';
        $companyFounded = null;
        $companySize = '';
        $companyIndustry = '';
    }

    // Name, bio and website are ONE input relabelled per account type, so saving
    // as a company used to overwrite the individual's own name. Each side is
    // remembered separately; only the active type's copy is written, while
    // name/bio/website_url stay the live values the rest of the app reads.
    $individualName = $isCompany ? null : $name;
    $companyName    = $isCompany ? $name : null;
    $individualBio  = $isCompany ? null : $bio;
    $companyBio     = $isCompany ? $bio : null;
    $individualSite = $isCompany ? null : $websiteUrl;
    $companySite    = $isCompany ? $websiteUrl : null;

    $stmt = $mysqli->prepare('UPDATE users SET name = ?, phone = ?, age = ?, occupation = ?, skills = ?, city = ?, bio = ?, avatar = ?, linkedin_url = ?, twitter_url = ?, instagram_url = ?, website_url = ?, past_experience = ?, account_type = ?, account_type_confirmed = 1, company_registration_no = ?, company_founded_year = ?, company_size = ?, company_industry = ?,
            individual_name = COALESCE(?, individual_name),
            company_name = COALESCE(?, company_name),
            individual_bio = COALESCE(?, individual_bio),
            company_bio = COALESCE(?, company_bio),
            individual_website_url = COALESCE(?, individual_website_url),
            company_website_url = COALESCE(?, company_website_url)
        WHERE id = ?');



    $stmt->bind_param('ssissssssssssssiss' . 'ssssss' . 'i', $name, $phone, $age, $occupation, $skills, $city, $bio, $avatar, $linkedinUrl, $twitterUrl, $instagramUrl, $websiteUrl, $pastExperience, $accountType, $companyRegNo, $companyFounded, $companySize, $companyIndustry,
        $individualName, $companyName, $individualBio, $companyBio, $individualSite, $companySite, $userId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Profile update failed.']);
        exit;
    }
    $stmt->close();

    $updated = fetchUserById($mysqli, $userId);
    if ($updated) {
        setUserSession($updated);
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => formatUserPayload($updated)
        ]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    }
    exit;
}

$stmt = $mysqli->prepare('SELECT id, name, email, phone, avatar, avatar_url, cover_url, role, city, occupation, skills, bio, age, linkedin_url, twitter_url, instagram_url, website_url, past_experience, account_type, account_type_confirmed, individual_name, company_name, individual_bio, company_bio, individual_website_url, company_website_url, company_registration_no, company_founded_year, company_size, company_industry FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

echo json_encode(['success' => true, 'user' => [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'avatar' => $user['avatar'] ?: strtoupper(substr($user['name'], 0, 2)),
    'avatarUrl' => $user['avatar_url'],
    'coverUrl' => $user['cover_url'],
    'role' => $user['role'],
    'city' => $user['city'],
    'occupation' => $user['occupation'],
    'skills' => $user['skills'],
    'bio' => $user['bio'],
    'age' => $user['age'],
    'linkedinUrl' => $user['linkedin_url'],
    'twitterUrl' => $user['twitter_url'],
    'instagramUrl' => $user['instagram_url'],
    'websiteUrl' => $user['website_url'],
    'pastExperience' => $user['past_experience'],
    
    
    'accountType' => $user['account_type'] ?? 'individual',
    'accountTypeConfirmed' => (int)($user['account_type_confirmed'] ?? 0),
    'companyRegistrationNo' => $user['company_registration_no'] ?? null,
    'companyFoundedYear' => $user['company_founded_year'] ?? null,
    'companySize' => $user['company_size'] ?? null,
    'companyIndustry' => $user['company_industry'] ?? null,

    // Remembered per account type, so switching type on the form restores that
    // side's own name/bio/website instead of the other side's.
    'individualName' => $user['individual_name'] ?? null,
    'companyName' => $user['company_name'] ?? null,
    'individualBio' => $user['individual_bio'] ?? null,
    'companyBio' => $user['company_bio'] ?? null,
    'individualWebsiteUrl' => $user['individual_website_url'] ?? null,
    'companyWebsiteUrl' => $user['company_website_url'] ?? null
]]);
