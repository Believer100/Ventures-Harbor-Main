<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function formatUserPayload(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'avatar' => $user['avatar'] ?: strtoupper(substr($user['name'], 0, 2)),
        'avatarUrl' => $user['avatar_url'] ?? null,
        'coverUrl' => $user['cover_url'] ?? null,
        'role' => $user['role'] ?? 'user',
        'city' => $user['city'] ?? '',
        'occupation' => $user['occupation'] ?? '',
        'bio' => $user['bio'] ?? '',
        'skills' => $user['skills'] ?? '',
        'linkedinUrl' => $user['linkedin_url'] ?? '',
        'twitterUrl' => $user['twitter_url'] ?? '',
        'instagramUrl' => $user['instagram_url'] ?? '',
        'websiteUrl' => $user['website_url'] ?? '',
        'pastExperience' => $user['past_experience'] ?? '',
        'age' => isset($user['age']) ? (int)$user['age'] : null,

        'accountType' => $user['account_type'] ?? 'individual',
        'accountTypeConfirmed' => (int)($user['account_type_confirmed'] ?? 0),
        'companyRegistrationNo' => $user['company_registration_no'] ?? null,
        'companyFoundedYear' => $user['company_founded_year'] ?? null,
        'companySize' => $user['company_size'] ?? null,
        'companyIndustry' => $user['company_industry'] ?? null,

        // Name, bio and website are one relabelled input per account type. These
        // are the remembered value for each side, so switching type swaps the
        // right one back in instead of showing the other type's.
        'individualName' => $user['individual_name'] ?? null,
        'companyName' => $user['company_name'] ?? null,
        'individualBio' => $user['individual_bio'] ?? null,
        'companyBio' => $user['company_bio'] ?? null,
        'individualWebsiteUrl' => $user['individual_website_url'] ?? null,
        'companyWebsiteUrl' => $user['company_website_url'] ?? null,
    ];
}

function setUserSession(array $user): void
{
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user'] = formatUserPayload($user);
}

function getSessionUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function clearUserSession(): void
{
    unset($_SESSION['user_id'], $_SESSION['user']);
}

function requireAuth(): int
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in.']);
        exit;
    }
    return (int)$_SESSION['user_id'];
}

function requireAdmin(): int
{
    $userId = requireAuth();
    $user = getSessionUser();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required.']);
        exit;
    }
    return $userId;
}

function fetchUserById(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare('SELECT id, name, email, phone, avatar, avatar_url, cover_url, role, account_type, account_type_confirmed, company_registration_no, company_founded_year, company_size, city, occupation, bio, skills, linkedin_url, twitter_url, instagram_url, website_url, past_experience, age FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function isFounderOf(mysqli $mysqli, int $ventureId, int $userId): bool
{
    $stmt = $mysqli->prepare('SELECT id FROM ventures WHERE id = ? AND founder_user_id = ?');
    $stmt->bind_param('ii', $ventureId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $isFounder = $stmt->num_rows > 0;
    $stmt->close();
    return $isFounder;
}

/**
 * May this user change a venture's *content* — its logo, cover image or gallery?
 *
 * The founder always may. An admin may too, but only for a sample listing:
 * `is_showcase` rows are platform content rather than anyone's venture, so who
 * may edit them must not depend on which admin account happened to publish them.
 * That is the same rule api/ventures.php's `update` action already applies; this
 * is it in one place, because upload_logo, upload_cover and the three writing
 * actions in api/venture_media.php each carried a bare founder_user_id check and
 * so locked every admin except the original publisher out of the sample listings.
 * On a live site whose admin account had been replaced, that was every admin.
 *
 * Deliberately NOT widened to real ventures: adding a photo to somebody's actual
 * listing is putting words in their mouth, which is a different power from
 * moderation. Removing media stays broader on purpose — see vmCanModerate() in
 * api/venture_media.php, which lets an admin take down content anywhere.
 */
function canEditVentureContent(mysqli $mysqli, int $ventureId, int $userId): bool
{
    if (isFounderOf($mysqli, $ventureId, $userId)) return true;

    $stmt = $mysqli->prepare('SELECT is_showcase FROM ventures WHERE id = ?');
    $stmt->bind_param('i', $ventureId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (empty($row['is_showcase'])) return false;

    $user = getSessionUser();
    return $user && ($user['role'] ?? '') === 'admin';
}

function getViewerVentureStates(mysqli $mysqli, int $userId): array
{
    if ($userId <= 0) return [];

    $states = [];

    // Weakest first, so each stronger pass overwrites the last. Waitlisted leads
    // because it is the weakest relationship there is — somebody waiting for a seat
    // who then applies, joins or founds should read as the stronger thing.
    $stmt = $mysqli->prepare(
        "SELECT venture_id FROM venture_waitlist WHERE user_id = ? AND status = 'waiting'"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $states[(int)$row['venture_id']] = ['state' => 'waitlisted'];
    }
    $stmt->close();

    $stmt = $mysqli->prepare(
        "SELECT venture_id, id, status FROM venture_applications
          WHERE user_id = ? AND status IN ('pending', 'selected')"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $states[(int)$row['venture_id']] = [
            'state'          => $row['status'] === 'selected' ? 'selected' : 'applied',
            'application_id' => (int)$row['id'],
        ];
    }
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT venture_id FROM venture_members WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $states[(int)$row['venture_id']] = ['state' => 'member'];
    }
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT id FROM ventures WHERE founder_user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $states[(int)$row['id']] = ['state' => 'founder'];
    }
    $stmt->close();

    return $states;
}

function validateBankDetails(array $input): array
{
    $fields = [
        'bank_account_name'   => trim($input['bank_account_name'] ?? ''),
        'bank_account_number' => trim($input['bank_account_number'] ?? ''),
        'bank_ifsc'           => strtoupper(trim($input['bank_ifsc'] ?? '')),
        'bank_name'           => trim($input['bank_name'] ?? ''),
    ];

    if ($fields['bank_account_name'] === '' || $fields['bank_account_number'] === '' || $fields['bank_ifsc'] === '') {
        return [$fields, 'Please provide the account holder name, account number and IFSC code.'];
    }
    if (!preg_match('/^\d{6,20}$/', $fields['bank_account_number'])) {
        return [$fields, 'Account number must be 6-20 digits.'];
    }
    if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $fields['bank_ifsc'])) {
        return [$fields, 'Please enter a valid IFSC code (e.g. HDFC0001234).'];
    }
    return [$fields, null];
}

/**
 * Where a deleted account's address goes when somebody else claims it.
 *
 * `.invalid` is reserved by RFC 2606 and can never resolve, so no mailer can
 * ever send to one of these by accident. The row id makes it unique for free
 * and makes the whole thing repeatable — the same person may sign up, delete,
 * and sign up again as often as they like, each abandoned row getting its own.
 */
const VH_DELETED_EMAIL_DOMAIN = 'deleted.venturesharbor.invalid';

function vh_deleted_email_placeholder(int $userId): string
{
    return 'deleted+' . $userId . '@' . VH_DELETED_EMAIL_DOMAIN;
}

/**
 * Release a deleted account's email so a new signup can take it.
 *
 * The real address is kept in `previous_email` — an admin paying out that
 * person's pending refund still needs somewhere to write to, and the admin panel
 * reads it in preference to the placeholder. COALESCE keeps the FIRST address
 * archived, which is the real one, if a row is ever put through this twice.
 *
 * `is_deactivated = 1` in the WHERE is the load-bearing part: a live account can
 * never lose its address through this path, whatever the caller believes. Same
 * concurrency shape as vh_cancel_venture()'s status guard.
 *
 * Returns false when nothing was archived — the row was live, already archived,
 * or gone — and the caller must not then hand the address to anybody.
 */
function vh_archive_deleted_email(mysqli $mysqli, int $userId, string $email): bool
{
    $placeholder = vh_deleted_email_placeholder($userId);

    $stmt = $mysqli->prepare(
        'UPDATE users
            SET previous_email = COALESCE(previous_email, ?),
                email = ?
          WHERE id = ? AND is_deactivated = 1 AND email = ?'
    );
    $stmt->bind_param('ssis', $email, $placeholder, $userId, $email);
    $stmt->execute();
    $archived = $stmt->affected_rows > 0;
    $stmt->close();

    return $archived;
}

function pageRedirectPrefix(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return preg_match('#/(pages|admin|users|api)/[^/]+$#', $script) ? '../' : '';
}

function requirePageAuth(): array
{
    $user = getSessionUser();
    if (!$user) {
        header('Location: ' . pageRedirectPrefix() . 'users/auth.php');
        exit;
    }
    return $user;
}

function requirePageAdmin(): array
{
    $user = requirePageAuth();
    if (($user['role'] ?? '') !== 'admin') {
        header('Location: ' . pageRedirectPrefix() . 'admin/dashboard.php');
        exit;
    }
    return $user;
}

function requirePageUserOnly(): array
{
    $user = requirePageAuth();
    if (($user['role'] ?? '') === 'admin') {
        header('Location: ' . pageRedirectPrefix() . 'admin/admin.php');
        exit;
    }
    return $user;
}

/**
 * A rupee amount in Indian digit grouping: 8,00,000 rather than 800,000.
 *
 * PHP's number_format() groups in threes, which is correct for most of the
 * world and wrong here — every figure this platform shows is in rupees, to an
 * Indian audience. The last three digits stay together and everything above
 * them groups in twos.
 *
 * The JS twin is VH.card.money() (compact K/L/Cr, for cards); this one is the
 * full-precision form used in notifications, emails and validation messages.
 */
function vh_inr($amount): string
{
    $n = (int) round((float) $amount);
    $sign = $n < 0 ? '-' : '';
    $s = (string) abs($n);
    if (strlen($s) <= 3) {
        return $sign . $s;
    }
    $last3 = substr($s, -3);
    $rest  = substr($s, 0, -3);
    $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $sign . $rest . ',' . $last3;
}
