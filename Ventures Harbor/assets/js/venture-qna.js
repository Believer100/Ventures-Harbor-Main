/* VENTURES HARBOR — VENTURE Q&A (venture-qna.js)
 *
 * The tab reads as a comment thread: anyone asks, only the founder answers,
 * anyone replies under either of those two, and all three can be liked.
 *
 * Three rules the markup below keeps to:
 *   - One heart per thing, and it is a toggle. Red and filled means "you liked
 *     this", hollow means you didn't. The count sits under it. There is no
 *     dislike anywhere — see migration step 48 for why the data cannot hold one.
 *   - Replies are flat. A reply to a reply is an @mention in the same list, not
 *     a deeper level, so the thread can never out-indent the column it sits in.
 *   - Handlers are delegated from #qnaList once, not re-bound per render. The
 *     list is replaced wholesale on every reload, and the old per-element pass
 *     re-ran after each vote, stacking a second listener on every surviving
 *     button — one tap then fired two requests.
 */

let qnaState = { isFounder: false, canAsk: false, canReply: false, viewerId: 0, bound: false };

function initQnaComposer() {
    const form = document.getElementById("qnaAskForm");
    const input = document.getElementById("qnaInput");
    const counter = document.getElementById("qnaCounter");
    const btn = document.getElementById("qnaAskBtn");
    if (!form || !input) return;

    input.addEventListener("input", () => {
        if (counter) counter.textContent = `${input.value.length} / 1000`;
        if (btn) btn.disabled = input.value.trim() === "";
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const question = input.value.trim();
        if (!question || !currentVentureId) return;

        btn.disabled = true;
        btn.textContent = "Posting…";
        try {
            const res = await fetch("../api/questions.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({ action: "ask", venture_id: currentVentureId, question })
            });
            const data = await res.json();
            if (res.status === 401) { VH.auth.redirectToLogin(); return; }
            if (!data.success) { VH.toast.error(data.message || "Could not post your question."); return; }

            input.value = "";
            if (counter) counter.textContent = "0 / 1000";
            VH.toast.success(data.message);
            loadQuestions();
        } catch (err) {
            VH.toast.error("Network error posting your question.");
        } finally {
            btn.textContent = "Post Question";
            btn.disabled = input.value.trim() === "";
        }
    });
}

async function loadQuestions() {
    const params = new URLSearchParams(window.location.search);
    const ventureId = currentVentureId || parseInt(params.get("id") || 0, 10);
    const list = document.getElementById("qnaList");
    if (!ventureId || !list) return;

    try {
        const res = await fetch(`../api/questions.php?action=list&venture_id=${ventureId}`, {
            credentials: "same-origin"
        });
        const data = await res.json();
        if (!data.success) {
            list.innerHTML = `<p class="vd-qna-empty">${escapeHtml(data.message || "Could not load questions.")}</p>`;
            return;
        }

        qnaState.isFounder = !!data.is_founder;
        qnaState.canAsk = !!data.can_ask;
        qnaState.canReply = !!data.can_reply;
        qnaState.viewerId = parseInt(data.viewer_id, 10) || 0;

        const signedIn = !!VH.auth.getUser();
        qnaToggle("qnaAskForm", !data.can_ask);
        qnaToggle("qnaSignedOut", signedIn);
        qnaToggle("qnaFounderNote", !data.is_founder);

        const badge = document.getElementById("qnaTabCount");
        if (badge) {
            badge.textContent = data.unanswered;
            badge.classList.toggle("hidden", data.unanswered === 0);
        }

        renderQuestions(data.questions || []);
    } catch (err) {
        list.innerHTML = `<p class="vd-qna-empty">Could not load questions.</p>`;
    }
}

function qnaToggle(id, hide) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle("hidden", hide);
}

/* The one heart path, drawn hollow and filled red by CSS alone — see
   .qna-like.is-liked in venture-detail.css. */
const QNA_HEART = '<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true" focusable="false">'
    + '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';

/**
 * The heart and its tally, for a question, an answer or a reply.
 *
 * `mine` is what turns it red, and tapping sends no direction — the endpoint
 * toggles. A signed-out visitor still sees the count; the tap tells them to sign
 * in rather than failing silently.
 */
function qnaHeartHTML(targetType, targetId, likes) {
    const l = likes || { count: 0, mine: false };
    const liked = !!l.mine;
    const noun = targetType === "reply" ? "reply" : targetType;
    return `<button type="button" class="qna-like${liked ? " is-liked" : ""}"
        data-like-type="${targetType}" data-like-id="${targetId}"
        aria-pressed="${liked}" aria-label="${liked ? "Unlike" : "Like"} this ${noun}"
        title="${liked ? "Unlike" : "Like"} this ${noun}">
        ${QNA_HEART}<span class="qna-like-n">${l.count > 0 ? l.count : ""}</span></button>`;
}

function qnaAvatarHTML(name, avatar, avatarUrl, userId, extraClass) {
    const initials = escapeHtml(
        avatar || (name || "?").split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase()
    );
    const inner = avatarUrl ? `<img src="../${escapeHtml(avatarUrl)}" alt="">` : initials;
    const cls = `qna-av${extraClass ? " " + extraClass : ""}`;
    // Without an id the avatar is not a button: a deleted account renders as
    // "Former member" and must not link to a profile that no longer exists.
    return userId
        ? `<div class="${cls} vd-qna-who" role="button" tabindex="-1" aria-hidden="true" data-profile-user="${userId}">${inner}</div>`
        : `<div class="${cls}">${inner}</div>`;
}

function qnaNameHTML(name, userId) {
    const safe = escapeHtml(name || "Former member");
    return userId
        ? `<span class="qna-name vd-qna-who" role="button" tabindex="0" data-profile-user="${userId}" title="View ${safe}'s profile">${safe}</span>`
        : `<span class="qna-name">${safe}</span>`;
}

/**
 * One row of the thread. Question, answer and reply are the same shape — avatar,
 * name and text, a meta line, and the heart on the right — because they are the
 * same thing to a reader; only the badge and the indent differ.
 */
function qnaCommentHTML(opts) {
    const {
        kind, likeType, likeId, likes, name, userId, avatar, avatarUrl,
        text, time, badge, canDelete, deleteAttr, replyTarget, questionId
    } = opts;

    const replyBtn = (qnaState.canReply && replyTarget)
        ? `<button type="button" class="qna-act" data-reply-open="${questionId}" data-reply-target="${replyTarget}" data-reply-to="${escapeHtml(name || "")}">Reply</button>`
        : "";
    const delBtn = canDelete
        ? `<button type="button" class="qna-act qna-act--del" ${deleteAttr} title="Delete">Delete</button>`
        : "";

    return `
    <div class="qna-c qna-c--${kind}">
        ${qnaAvatarHTML(name, avatar, avatarUrl, userId, kind === "reply" ? "qna-av--sm" : "")}
        <div class="qna-c-body">
            <div class="qna-c-text">
                ${qnaNameHTML(name, userId)}${badge || ""}
                <span class="qna-c-msg">${escapeHtml(text || "")}</span>
            </div>
            <div class="qna-c-meta">
                <span class="qna-time">${fmtQnaDate(time)}</span>
                ${replyBtn}
                ${delBtn}
            </div>
        </div>
        ${qnaHeartHTML(likeType, likeId, likes)}
    </div>`;
}

/**
 * The replies under one anchor, behind Instagram's "View replies (n)" line.
 *
 * Collapsed by default so a long argument under one question cannot bury the
 * next one; the composer this opens into is rendered by the Reply button.
 */
function qnaRepliesHTML(questionId, target, replies) {
    const key = `${questionId}:${target}`;
    const rows = (replies || []).map(r => qnaCommentHTML({
        kind: "reply",
        likeType: "reply", likeId: r.id, likes: r.likes,
        name: r.author_name, userId: r.author_user_id,
        avatar: r.author_avatar, avatarUrl: r.author_avatar_url,
        text: r.body, time: r.created_at,
        canDelete: r.can_delete,
        deleteAttr: `data-delete-reply="${r.id}"`,
        replyTarget: target, questionId
    })).join("");

    const toggle = (replies || []).length
        ? `<button type="button" class="qna-more" data-replies-toggle="${key}" aria-expanded="false">
               <span class="qna-more-line" aria-hidden="true"></span>
               <span class="qna-more-txt">View replies (${replies.length})</span>
           </button>`
        : "";

    return `
    <div class="qna-thread" data-replies-key="${key}">
        ${toggle}
        <div class="qna-thread-list hidden" data-replies-list="${key}">${rows}</div>
        <div class="qna-thread-composer" data-composer-slot="${key}"></div>
    </div>`;
}

function renderQuestions(questions) {
    const list = document.getElementById("qnaList");
    if (!list) return;

    if (questions.length === 0) {
        list.innerHTML = `<p class="vd-qna-empty">No questions yet. ${
            qnaState.isFounder
                ? "When someone asks, it will appear here."
                : "Be the first to ask the founder something."
        }</p>`;
        qnaBindOnce();
        return;
    }

    list.innerHTML = questions.map(q => {
        const questionRow = qnaCommentHTML({
            kind: "q",
            likeType: "question", likeId: q.id, likes: q.likes && q.likes.question,
            name: q.asker_name, userId: q.asker_user_id,
            avatar: q.asker_avatar, avatarUrl: q.asker_avatar_url,
            text: q.question, time: q.created_at,
            canDelete: q.can_delete,
            deleteAttr: `data-delete-question="${q.id}"`,
            replyTarget: "question", questionId: q.id
        });

        let answerBlock;
        if (q.answer) {
            answerBlock = qnaCommentHTML({
                kind: "a",
                likeType: "answer", likeId: q.id, likes: q.likes && q.likes.answer,
                name: q.answerer_name, userId: q.answerer_user_id,
                avatar: q.answerer_avatar, avatarUrl: q.answerer_avatar_url,
                text: q.answer, time: q.answered_at,
                badge: `<span class="qna-badge">Founder</span>`,
                canDelete: false, deleteAttr: "",
                replyTarget: "answer", questionId: q.id
            }) + qnaRepliesHTML(q.id, "answer", q.replies && q.replies.answer);
        } else if (qnaState.isFounder) {
            // Only the founder is offered the answer box — everyone else is told
            // the founder owes an answer, and can still reply under the question.
            answerBlock = `
                <form class="qna-answer-form" data-answer-form data-question-id="${q.id}">
                    <textarea class="form-control form-textarea" rows="2" maxlength="2000" placeholder="Write your answer…" required></textarea>
                    <button type="submit" class="btn btn--primary btn--sm">Post Answer</button>
                </form>`;
        } else {
            answerBlock = `<p class="vd-qna-pending">Awaiting the founder's reply</p>`;
        }

        return `
        <div class="vd-qna-item" data-question-id="${q.id}">
            ${questionRow}
            ${qnaRepliesHTML(q.id, "question", q.replies && q.replies.question)}
            ${answerBlock}
        </div>`;
    }).join("");

    qnaBindOnce();
}

/* ---------- one delegated listener set, installed on first render ---------- */

function qnaBindOnce() {
    const list = document.getElementById("qnaList");
    if (!list || qnaState.bound) return;
    qnaState.bound = true;

    list.addEventListener("click", qnaOnClick);
    list.addEventListener("keydown", (e) => {
        const who = e.target.closest("[data-profile-user]");
        if (who && (e.key === "Enter" || e.key === " ")) {
            e.preventDefault();
            qnaOpenProfile(who);
        }
    });
    list.addEventListener("submit", qnaOnSubmit);
}

function qnaOpenProfile(el) {
    const uid = parseInt(el.dataset.profileUser, 10);
    if (uid && typeof openUserProfile === "function") openUserProfile(uid);
}

async function qnaOnClick(e) {
    const heart = e.target.closest("[data-like-type]");
    if (heart) return qnaToggleLike(heart);

    const toggle = e.target.closest("[data-replies-toggle]");
    if (toggle) return qnaToggleReplies(toggle);

    const replyOpen = e.target.closest("[data-reply-open]");
    if (replyOpen) return qnaOpenComposer(replyOpen);

    const delQ = e.target.closest("[data-delete-question]");
    if (delQ) return qnaDelete("delete", { question_id: parseInt(delQ.dataset.deleteQuestion, 10) },
        "Delete this question? Its replies go with it. This cannot be undone.");

    const delR = e.target.closest("[data-delete-reply]");
    if (delR) return qnaDelete("delete_reply", { reply_id: parseInt(delR.dataset.deleteReply, 10) },
        "Delete this reply? This cannot be undone.");

    const who = e.target.closest("[data-profile-user]");
    if (who) return qnaOpenProfile(who);
}

/**
 * Toggling updates the one heart it belongs to and nothing else — reloading the
 * list would scroll the reader away from the comment they just tapped.
 */
async function qnaToggleLike(btn) {
    if (!VH.auth.getUser()) { VH.toast.error("Sign in to like questions and replies."); return; }
    if (btn.disabled) return;

    const targetType = btn.dataset.likeType;
    const targetId = parseInt(btn.dataset.likeId, 10);

    btn.disabled = true;
    try {
        const res = await fetch("../api/questions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action: "like", target_type: targetType, target_id: targetId })
        });
        if (res.status === 401) { VH.auth.redirectToLogin(); return; }
        const data = await res.json();
        if (!data.success) { VH.toast.error(data.message || "Could not save your like."); return; }
        btn.outerHTML = qnaHeartHTML(targetType, targetId, data.likes);
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error saving your like.");
    } finally {
        if (btn.isConnected) btn.disabled = false;
    }
}

function qnaToggleReplies(btn) {
    const key = btn.dataset.repliesToggle;
    const list = document.querySelector(`[data-replies-list="${CSS.escape(key)}"]`);
    if (!list) return;
    const open = !list.classList.contains("hidden");
    list.classList.toggle("hidden", open);
    btn.setAttribute("aria-expanded", String(!open));
    const count = list.children.length;
    const label = btn.querySelector(".qna-more-txt");
    if (label) label.textContent = open ? `View replies (${count})` : "Hide replies";
}

/**
 * The reply box is created on demand, one at a time, and prefilled with the
 * @name being answered — which is the whole of the "reply to a reply" model.
 */
function qnaOpenComposer(btn) {
    if (!VH.auth.getUser()) { VH.toast.error("Sign in to reply."); return; }

    const questionId = parseInt(btn.dataset.replyOpen, 10);
    const target = btn.dataset.replyTarget;
    const key = `${questionId}:${target}`;
    const slot = document.querySelector(`[data-composer-slot="${CSS.escape(key)}"]`);
    if (!slot) return;

    // A second tap on the same Reply closes it again.
    if (slot.dataset.openFor === String(btn.dataset.replyTo) && slot.firstChild) {
        slot.innerHTML = "";
        slot.removeAttribute("data-open-for");
        return;
    }

    const to = btn.dataset.replyTo || "";
    const mention = to ? `@${to} ` : "";
    slot.dataset.openFor = to;
    slot.innerHTML = `
        <form class="qna-reply-form" data-reply-form data-question-id="${questionId}" data-target="${target}">
            <input type="text" class="qna-reply-input" maxlength="1000"
                   placeholder="Reply${to ? " to " + escapeHtml(to) : ""}…" value="${escapeHtml(mention)}">
            <button type="submit" class="btn btn--primary btn--sm">Post</button>
            <button type="button" class="qna-act" data-reply-cancel>Cancel</button>
        </form>`;

    const input = slot.querySelector("input");
    if (input) {
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }
    slot.querySelector("[data-reply-cancel]").addEventListener("click", () => {
        slot.innerHTML = "";
        slot.removeAttribute("data-open-for");
    });
}

async function qnaOnSubmit(e) {
    const answerForm = e.target.closest("[data-answer-form]");
    if (answerForm) { e.preventDefault(); return qnaPostAnswer(answerForm); }

    const replyForm = e.target.closest("[data-reply-form]");
    if (replyForm) { e.preventDefault(); return qnaPostReply(replyForm); }
}

async function qnaPostAnswer(form) {
    const textarea = form.querySelector("textarea");
    const submitBtn = form.querySelector("button[type=submit]");
    const answer = textarea.value.trim();
    if (!answer) return;

    submitBtn.disabled = true;
    submitBtn.textContent = "Posting…";
    try {
        const res = await fetch("../api/questions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "answer",
                question_id: parseInt(form.dataset.questionId, 10),
                answer: answer
            })
        });
        const data = await res.json();
        if (!data.success) { VH.toast.error(data.message || "Could not post your answer."); return; }
        VH.toast.success(data.message);
        loadQuestions();
    } catch (err) {
        VH.toast.error("Network error posting your answer.");
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "Post Answer";
    }
}

async function qnaPostReply(form) {
    const input = form.querySelector("input");
    const submitBtn = form.querySelector("button[type=submit]");
    const body = input.value.trim();
    if (!body) return;

    submitBtn.disabled = true;
    submitBtn.textContent = "Posting…";
    try {
        const res = await fetch("../api/questions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "reply",
                question_id: parseInt(form.dataset.questionId, 10),
                target: form.dataset.target,
                body: body
            })
        });
        if (res.status === 401) { VH.auth.redirectToLogin(); return; }
        const data = await res.json();
        if (!data.success) { VH.toast.error(data.message || "Could not post your reply."); return; }
        VH.toast.success(data.message);
        loadQuestions();
    } catch (err) {
        VH.toast.error("Network error posting your reply.");
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "Post";
    }
}

async function qnaDelete(action, payload, confirmText) {
    if (!confirm(confirmText)) return;
    try {
        const res = await fetch("../api/questions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(Object.assign({ action }, payload))
        });
        const data = await res.json();
        if (!data.success) { VH.toast.error(data.message || "Could not delete."); return; }
        VH.toast.success(data.message);
        loadQuestions();
    } catch (err) {
        VH.toast.error("Network error.");
    }
}

function fmtQnaDate(value) {
    if (!value) return "";
    const then = new Date(String(value).replace(" ", "T"));
    if (isNaN(then.getTime())) return "";
    const mins = Math.floor((Date.now() - then.getTime()) / 60000);
    if (mins < 1) return "just now";
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return then.toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
}
