<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
if (!$action) {
    $action = $input['action'] ?? '';
}

const VH_QUESTION_MAX = 1000;
const VH_ANSWER_MAX   = 2000;
const VH_REPLY_MAX    = 1000;

/**
 * The three things in a Q&A thread that can be liked.
 *
 * 'question' and 'answer' both key off the question row's id — the founder's
 * answer lives on that row rather than in a table of its own — while 'reply'
 * keys off venture_question_replies.id. Everything below funnels through this
 * list so an unknown target_type can never reach a query.
 */
const VH_LIKE_TARGETS = ['question', 'answer', 'reply'];

function qnaNoLikes(): array
{
    return ['count' => 0, 'mine' => false];
}

/**
 * Like tallies for a whole page of the thread, plus whether the viewer is in them.
 *
 * Two queries for the entire tab rather than one per heart: the Q&A panel renders
 * every question, answer and reply at once, so per-row lookups would be dozens of
 * round trips. `mine` is false throughout for a signed-out visitor, which is what
 * renders every heart hollow and read-only for them.
 */
function qnaLoadLikes(mysqli $mysqli, array $questionIds, array $replyIds, int $viewerId): array
{
    $qIds = array_values(array_unique(array_map('intval', array_filter($questionIds))));
    $rIds = array_values(array_unique(array_map('intval', array_filter($replyIds))));

    $out = ['question' => [], 'answer' => [], 'reply' => []];
    foreach ($qIds as $id) {
        $out['question'][$id] = qnaNoLikes();
        $out['answer'][$id]   = qnaNoLikes();
    }
    foreach ($rIds as $id) $out['reply'][$id] = qnaNoLikes();

    if (!$qIds && !$rIds) return $out;

    // Built from counted placeholders, never from request values.
    $where = [];
    $params = [];
    $types = '';
    if ($qIds) {
        $where[] = "(l.target_type IN ('question','answer') AND l.target_id IN ("
                   . implode(',', array_fill(0, count($qIds), '?')) . "))";
        $params = array_merge($params, $qIds);
        $types .= str_repeat('i', count($qIds));
    }
    if ($rIds) {
        $where[] = "(l.target_type = 'reply' AND l.target_id IN ("
                   . implode(',', array_fill(0, count($rIds), '?')) . "))";
        $params = array_merge($params, $rIds);
        $types .= str_repeat('i', count($rIds));
    }
    $scope = implode(' OR ', $where);

    $stmt = $mysqli->prepare("
        SELECT l.target_type, l.target_id, COUNT(*) AS c
        FROM venture_qna_likes l
        WHERE $scope
        GROUP BY l.target_type, l.target_id
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $out[$r['target_type']][(int)$r['target_id']]['count'] = (int)$r['c'];
    }
    $stmt->close();

    if ($viewerId > 0) {
        $stmt = $mysqli->prepare("
            SELECT l.target_type, l.target_id
            FROM venture_qna_likes l
            WHERE ($scope) AND l.user_id = ?
        ");
        $stmt->bind_param($types . 'i', ...array_merge($params, [$viewerId]));
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $out[$r['target_type']][(int)$r['target_id']]['mine'] = true;
        }
        $stmt->close();
    }

    return $out;
}

/** One like tally, for the response to a heart tap. */
function qnaOneLike(mysqli $mysqli, string $targetType, int $targetId, int $viewerId): array
{
    $likes = $targetType === 'reply'
        ? qnaLoadLikes($mysqli, [], [$targetId], $viewerId)
        : qnaLoadLikes($mysqli, [$targetId], [], $viewerId);
    return $likes[$targetType][$targetId] ?? qnaNoLikes();
}

/**
 * Every reply on a set of questions, grouped by question and by which of the two
 * anchors it hangs under. Replies are flat by design (see migration step 48).
 */
function qnaLoadReplies(mysqli $mysqli, array $questionIds): array
{
    $ids = array_values(array_unique(array_map('intval', array_filter($questionIds))));
    if (!$ids) return [];

    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $mysqli->prepare("
        SELECT r.id, r.question_id, r.parent_target, r.body, r.created_at, r.user_id,
               u.name AS author_name, u.avatar AS author_avatar, u.avatar_url AS author_avatar_url
        FROM venture_question_replies r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.question_id IN ($in)
        ORDER BY r.created_at ASC, r.id ASC
    ");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $ventureId = isset($_GET['venture_id']) ? (int)$_GET['venture_id'] : 0;
    if (!$ventureId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid Asset ID.']);
        exit;
    }

    $viewerId = (int)($_SESSION['user_id'] ?? 0);
    $viewerIsAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');
    $viewerIsFounder = $viewerId > 0 && isFounderOf($mysqli, $ventureId, $viewerId);

    $stmt = $mysqli->prepare("
        SELECT q.id, q.question, q.answer, q.created_at, q.answered_at,
               q.user_id, u.name AS asker_name, u.avatar AS asker_avatar, u.avatar_url AS asker_avatar_url,
               q.answered_by, a.name AS answerer_name, a.avatar AS answerer_avatar, a.avatar_url AS answerer_avatar_url
        FROM venture_questions q
        LEFT JOIN users u ON u.id = q.user_id
        LEFT JOIN users a ON a.id = q.answered_by
        WHERE q.venture_id = ?
        ORDER BY (q.answer IS NULL) DESC, q.created_at DESC
    ");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $questionIds = array_map('intval', array_column($rows, 'id'));
    $replyRows = qnaLoadReplies($mysqli, $questionIds);
    $likes = qnaLoadLikes($mysqli, $questionIds, array_map('intval', array_column($replyRows, 'id')), $viewerId);

    // A reply can be removed by whoever wrote it, the founder, or an admin — the
    // same three who can remove a question.
    $canModerate = $viewerIsAdmin || $viewerIsFounder;
    $repliesByQuestion = [];
    foreach ($replyRows as $r) {
        $rid = (int)$r['id'];
        $repliesByQuestion[(int)$r['question_id']][$r['parent_target']][] = [
            'id'          => $rid,
            'body'        => $r['body'],
            'author_name' => $r['author_name'] ?? 'Former member',
            'author_avatar' => $r['author_avatar'],
            'author_avatar_url' => $r['author_avatar_url'],
            'author_user_id' => $r['author_name'] === null ? null : (int)$r['user_id'],
            'created_at'  => $r['created_at'],
            'likes'       => $likes['reply'][$rid] ?? qnaNoLikes(),
            'can_delete'  => $canModerate || ($viewerId > 0 && (int)$r['user_id'] === $viewerId),
        ];
    }

    $questions = [];
    $unanswered = 0;
    foreach ($rows as $row) {
        $qid = (int)$row['id'];
        if ($row['answer'] === null) $unanswered++;
        $questions[] = [
            'id'            => $qid,
            'question'      => $row['question'],
            'answer'        => $row['answer'],

            'asker_name'    => $row['asker_name'] ?? 'Former member',
            'asker_avatar'  => $row['asker_avatar'],
            'asker_avatar_url' => $row['asker_avatar_url'],
            // Ids so the name and avatar can open the public profile popup.
            // NULL for a deleted account, which is what keeps "Former member"
            // unclickable rather than linking to a profile that no longer exists.
            'asker_user_id' => $row['asker_name'] === null ? null : (int)$row['user_id'],
            'answerer_user_id' => $row['answered_by'] === null ? null : (int)$row['answered_by'],
            'answerer_name' => $row['answerer_name'],
            'answerer_avatar' => $row['answerer_avatar'],
            'answerer_avatar_url' => $row['answerer_avatar_url'],
            'likes'         => [
                'question' => $likes['question'][$qid] ?? qnaNoLikes(),
                'answer'   => $likes['answer'][$qid] ?? qnaNoLikes(),
            ],
            'replies'       => [
                'question' => $repliesByQuestion[$qid]['question'] ?? [],
                'answer'   => $repliesByQuestion[$qid]['answer'] ?? [],
            ],
            'created_at'    => $row['created_at'],
            'answered_at'   => $row['answered_at'],
            'can_delete'    => $canModerate
                               || ($viewerId > 0 && (int)$row['user_id'] === $viewerId),
        ];
    }

    echo json_encode([
        'success'      => true,
        'questions'    => $questions,
        'unanswered'   => $unanswered,
        'is_founder'   => $viewerIsFounder,
        'can_ask'      => $viewerId > 0 && !$viewerIsAdmin && !$viewerIsFounder,
        // The founder joins the conversation under their own answer, so replying
        // is open to every signed-in account except an admin — admins are kept
        // out of venture participation everywhere else too.
        'can_reply'    => $viewerId > 0 && !$viewerIsAdmin,
        'viewer_id'    => $viewerId,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'ask') {
    $userId = requireAuth();
    $ventureId = isset($input['venture_id']) ? (int)$input['venture_id'] : 0;
    $question = trim((string)($input['question'] ?? ''));

    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin accounts cannot post questions on Assets.']);
        exit;
    }
    if (!$ventureId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid Asset ID.']);
        exit;
    }
    if ($question === '') {
        echo json_encode(['success' => false, 'message' => 'Please type a question first.']);
        exit;
    }
    if (mb_strlen($question) > VH_QUESTION_MAX) {
        echo json_encode(['success' => false, 'message' => 'Questions are limited to ' . VH_QUESTION_MAX . ' characters.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT title, founder_user_id FROM ventures WHERE id = ?");
    $stmt->bind_param("i", $ventureId);
    $stmt->execute();
    $venture = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$venture) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asset not found.']);
        exit;
    }
    // The founder answers questions, they don't ask their own.
    if ((int)$venture['founder_user_id'] === $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You founded this Asset — you answer questions here.']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO venture_questions (venture_id, user_id, question) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $ventureId, $userId, $question);
    $stmt->execute();
    $questionId = $stmt->insert_id;
    $stmt->close();

    if (!empty($venture['founder_user_id'])) {
        $founderId = (int)$venture['founder_user_id'];
        $notifMsg = "Someone asked a question about '" . $venture['title'] . "'. Open the Asset's Q&A tab to reply.";
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'New Question ❓', ?)");
        $stmt->bind_param("is", $founderId, $notifMsg);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'id' => $questionId, 'message' => 'Question posted. The founder will be notified.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'answer') {
    $userId = requireAuth();
    $questionId = isset($input['question_id']) ? (int)$input['question_id'] : 0;
    $answer = trim((string)($input['answer'] ?? ''));

    if (!$questionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid question.']);
        exit;
    }
    if ($answer === '') {
        echo json_encode(['success' => false, 'message' => 'Please type an answer first.']);
        exit;
    }
    if (mb_strlen($answer) > VH_ANSWER_MAX) {
        echo json_encode(['success' => false, 'message' => 'Answers are limited to ' . VH_ANSWER_MAX . ' characters.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT q.venture_id, q.user_id, v.title
        FROM venture_questions q JOIN ventures v ON v.id = q.venture_id
        WHERE q.id = ?
    ");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Question not found.']);
        exit;
    }
    if (!isFounderOf($mysqli, (int)$row['venture_id'], $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the founder of this Asset can answer its questions.']);
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE venture_questions SET answer = ?, answered_by = ?, answered_at = NOW() WHERE id = ?");
    $stmt->bind_param("sii", $answer, $userId, $questionId);
    $stmt->execute();
    $stmt->close();

    if (!empty($row['user_id'])) {
        $askerId = (int)$row['user_id'];
        $notifMsg = "The founder answered your question about '" . $row['title'] . "'.";
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'Question Answered 💬', ?)");
        $stmt->bind_param("is", $askerId, $notifMsg);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Answer posted.']);
    exit;
}

/* ---------- reply: anyone signed in, under a question or under the answer ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reply') {
    $userId = requireAuth();
    $questionId = isset($input['question_id']) ? (int)$input['question_id'] : 0;
    $target = ($input['target'] ?? '') === 'answer' ? 'answer' : 'question';
    $body = trim((string)($input['body'] ?? ''));

    // Answering is the founder's alone; replying is not. An admin stays out of
    // venture conversations, same as they cannot ask or join.
    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin accounts cannot post in Asset Q&A.']);
        exit;
    }
    if (!$questionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid question.']);
        exit;
    }
    if ($body === '') {
        echo json_encode(['success' => false, 'message' => 'Please type a reply first.']);
        exit;
    }
    if (mb_strlen($body) > VH_REPLY_MAX) {
        echo json_encode(['success' => false, 'message' => 'Replies are limited to ' . VH_REPLY_MAX . ' characters.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT q.id, q.venture_id, q.user_id, q.answer, q.answered_by, v.title
        FROM venture_questions q JOIN ventures v ON v.id = q.venture_id
        WHERE q.id = ?
    ");
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'That question no longer exists.']);
        exit;
    }
    // Nothing to reply under until the founder has actually answered.
    if ($target === 'answer' && ($row['answer'] === null || $row['answer'] === '')) {
        echo json_encode(['success' => false, 'message' => 'This question has not been answered yet.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        INSERT INTO venture_question_replies (question_id, parent_target, user_id, body)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('isis', $questionId, $target, $userId, $body);
    $stmt->execute();
    $replyId = $stmt->insert_id;
    $stmt->close();

    // Tell whoever is being replied to — the asker under a question, the founder
    // under their answer — unless that is the person doing the replying.
    $notifyId = $target === 'answer' ? (int)($row['answered_by'] ?? 0) : (int)($row['user_id'] ?? 0);
    if ($notifyId > 0 && $notifyId !== $userId) {
        $what = $target === 'answer' ? 'your answer' : 'your question';
        $notifMsg = "Someone replied to $what about '" . $row['title'] . "'.";
        $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'venture', 'New Reply 💬', ?)");
        $stmt->bind_param('is', $notifyId, $notifMsg);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'id' => $replyId, 'message' => 'Reply posted.']);
    exit;
}

/* ---------- like: one heart, on a question, an answer or a reply ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'like') {
    $userId = requireAuth();
    $targetType = (string)($input['target_type'] ?? '');
    $targetId = isset($input['target_id']) ? (int)$input['target_id'] : 0;

    if (!in_array($targetType, VH_LIKE_TARGETS, true) || $targetId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid like target.']);
        exit;
    }

    // The thing being liked has to exist. Without this a like could be parked on
    // a deleted row and reappear as a phantom count if the id were ever reused.
    if ($targetType === 'reply') {
        $stmt = $mysqli->prepare("SELECT id FROM venture_question_replies WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'That reply no longer exists.']);
            exit;
        }
    } else {
        $stmt = $mysqli->prepare("SELECT id, answer FROM venture_questions WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'That question no longer exists.']);
            exit;
        }
        if ($targetType === 'answer' && ($row['answer'] === null || $row['answer'] === '')) {
            echo json_encode(['success' => false, 'message' => 'This question has not been answered yet.']);
            exit;
        }
    }

    // The heart is a toggle, and one statement each way says so: delete the row
    // if it is there, insert it if it is not. The PRIMARY KEY means a double tap
    // can never leave two likes behind.
    $stmt = $mysqli->prepare("
        DELETE FROM venture_qna_likes
        WHERE target_type = ? AND target_id = ? AND user_id = ?
    ");
    $stmt->bind_param('sii', $targetType, $targetId, $userId);
    $stmt->execute();
    $removed = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$removed) {
        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO venture_qna_likes (target_type, target_id, user_id)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('sii', $targetType, $targetId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'liked'   => !$removed,
        'likes'   => qnaOneLike($mysqli, $targetType, $targetId, $userId),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $userId = requireAuth();
    $questionId = isset($input['question_id']) ? (int)$input['question_id'] : 0;
    if (!$questionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid question.']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT venture_id, user_id FROM venture_questions WHERE id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Question not found.']);
        exit;
    }

    $isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');
    $isAsker = (int)$row['user_id'] === $userId;
    $isFounder = isFounderOf($mysqli, (int)$row['venture_id'], $userId);
    if (!$isAdmin && !$isAsker && !$isFounder) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot delete this question.']);
        exit;
    }

    // venture_qna_likes.target_id points at two different tables, so it carries no
    // foreign key and nothing cascades into it — the likes have to be cleared by
    // hand, and the replies' likes must go *before* the question does, since
    // deleting the question cascades the replies out from under this lookup.
    $stmt = $mysqli->prepare("
        DELETE FROM venture_qna_likes
        WHERE target_type = 'reply'
          AND target_id IN (SELECT id FROM venture_question_replies WHERE question_id = ?)
    ");
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("
        DELETE FROM venture_qna_likes
        WHERE target_type IN ('question','answer') AND target_id = ?
    ");
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $stmt->close();

    // The pre-step-48 table has no foreign key either; clear it so a deleted
    // question leaves nothing behind anywhere.
    $stmt = $mysqli->prepare("DELETE FROM venture_question_votes WHERE question_id = ?");
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $stmt->close();

    // Replies cascade with the question (FK, migration step 48).
    $stmt = $mysqli->prepare("DELETE FROM venture_questions WHERE id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Question removed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_reply') {
    $userId = requireAuth();
    $replyId = isset($input['reply_id']) ? (int)$input['reply_id'] : 0;
    if (!$replyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reply.']);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT r.user_id, q.venture_id
        FROM venture_question_replies r
        JOIN venture_questions q ON q.id = r.question_id
        WHERE r.id = ?
    ");
    $stmt->bind_param('i', $replyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reply not found.']);
        exit;
    }

    $isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');
    $isAuthor = (int)$row['user_id'] === $userId;
    $isFounder = isFounderOf($mysqli, (int)$row['venture_id'], $userId);
    if (!$isAdmin && !$isAuthor && !$isFounder) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot delete this reply.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM venture_qna_likes WHERE target_type = 'reply' AND target_id = ?");
    $stmt->bind_param('i', $replyId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("DELETE FROM venture_question_replies WHERE id = ?");
    $stmt->bind_param('i', $replyId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Reply removed.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action.']);
