/* VENTURES HARBOR — WAITLIST (waitlist.js)
 *
 * Two lists: seats open right now, and the Assets this person is waiting on.
 *
 * Everything decisive happens on the server. The claim is a guarded UPDATE in
 * vh_claim_slot(), so this file never decides who won a seat — it asks, and
 * renders the answer. In particular the countdown here is cosmetic: when it
 * reaches zero the page re-reads rather than assuming the hold has lapsed, because
 * the browser's clock and the database's are not the same clock.
 */

let wlUser = null;
let wlTimer = null;
let wlSlots = [];
// Venture id whose rule line is owed a flash on the next load — set by wlJoin().
let wlFlashFor = 0;

document.addEventListener("DOMContentLoaded", () => {
    wlUser = VH.auth.getUser();
    if (!wlUser) {
        VH.auth.redirectToLogin();
        return;
    }

    wlBindChrome();
    wlLoad();

    // Arriving from a card's "Join Waitlist" button: ?join=<ventureId>.
    const joinId = new URLSearchParams(location.search).get("join");
    if (joinId) {
        wlJoin(parseInt(joinId, 10));
        history.replaceState({}, "", location.pathname);
    }
});

/* ------------------------------------------------------------------ data -- */

async function wlLoad() {
    try {
        const res = await (await fetch("../api/waitlist.php?action=mine")).json();
        if (!res.success) {
            VH.toast.error(res.message || "Could not load your waitlist.");
            return;
        }

        wlSlots = res.slots || [];
        const entries = (res.entries || []).filter(e => e.status !== "left");

        wlRenderSlots(wlSlots);
        wlRenderEntries(entries);

        // Flash the rule for an Asset just added. Done here rather than in wlJoin()
        // because partner_types arrives with THIS response, and reading it here means
        // a join that somehow did not land shows no line at all — the honest failure.
        if (wlFlashFor) {
            const added = entries.find(e => String(e.venture_id) === String(wlFlashFor));
            if (added) wlFlashNote(added.partner_types);
            wlFlashFor = 0;
        }

        const sub = document.getElementById("wlSubtitle");
        const waiting = entries.filter(e => e.status === "waiting").length;
        if (wlSlots.length) {
            sub.textContent = `${wlSlots.length} seat${wlSlots.length === 1 ? "" : "s"} open right now · waiting on ${waiting} Asset${waiting === 1 ? "" : "s"}`;
        } else {
            sub.textContent = waiting
                ? `Waiting on ${waiting} Asset${waiting === 1 ? "" : "s"} — no seat is open at the moment.`
                : "You are not waiting on anything yet.";
        }

        document.getElementById("wlEmpty").classList.toggle("hidden", entries.length > 0);
        document.querySelector(".wl-entries").classList.toggle("hidden", entries.length === 0);

        wlStartTicker();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error loading your waitlist.");
    }
}

/* --------------------------------------------------------------- render --- */

function wlRenderSlots(slots) {
    const section = document.getElementById("wlSlotsSection");
    const list = document.getElementById("wlSlotList");
    section.classList.toggle("hidden", slots.length === 0);
    if (!slots.length) { list.innerHTML = ""; return; }

    list.innerHTML = slots.map(s => {
        const role = s.role === "active" ? "Active partner" : "Silent partner";
        const reason = s.exit_reason
            ? `<div class="wl-reason"><span class="wl-reason-label">Reason given for leaving</span><p>${VH.card.esc(s.exit_reason)}</p></div>`
            : `<div class="wl-reason wl-reason--none"><p>No reason was given for leaving.</p></div>`;

        /* The seat is being held for me — identical for both roles, because by this
           point an active candidate has been chosen and is simply paying. The only
           difference is how long the hold runs, and the server sends that. */
        const holding = `<div class="wl-hold">
                   <span class="wl-hold-timer" data-countdown="${s.id}">${wlFormatLeft(s.seconds_left)}</span>
                   <span class="wl-hold-label">left to pay</span>
               </div>
               <div class="wl-slot-actions">
                   <button class="btn btn--primary btn--md" data-pay="${s.id}">Pay ${VH.card.money(s.fee_amount)} &amp; take the seat</button>
                   <button class="btn btn--secondary btn--md" data-release="${s.id}">Give it back</button>
               </div>`;

        let action;
        let note;

        if (s.is_mine) {
            action = holding;
            note = s.is_founder_choice
                ? `The founder chose you for this seat. Pay the commitment fee to take it — if it is not paid in time the seat goes back to the other applicants.`
                : `Everyone on this waitlist was told at the same moment. No meeting is required — whoever claims it first pays and takes it.`;
        } else if (s.is_founder_choice) {
            /* An active seat is not a race, so it must not look like one. No timer,
               no "claim", and the button says what actually happens. */
            note = `This is an active partner seat, so it is not first-come. Everyone waiting can apply, and the `
                 + `founder chooses. Whoever is selected then has ${s.pay_days} days to pay.`;
            if (s.my_application === "pending") {
                action = `<div class="wl-applied">${VH.icon("check", 15)}<span>Application sent — the founder is reviewing it${
                    s.applicant_count > 1 ? ` alongside ${s.applicant_count - 1} other${s.applicant_count === 2 ? "" : "s"}` : ""
                }.</span></div>
                <div class="wl-slot-actions">
                    <button class="btn btn--secondary btn--sm" data-apply="${s.id}">Update my application</button>
                </div>`;
            } else if (s.my_application === "rejected") {
                action = `<div class="wl-applied wl-applied--closed">${VH.icon("info", 15)}<span>The founder reopened this seat for new applications. You can apply again.</span></div>
                <div class="wl-slot-actions">
                    <button class="btn btn--primary btn--md" data-apply="${s.id}">Apply again</button>
                </div>`;
            } else {
                action = `<div class="wl-slot-actions">
                    <button class="btn btn--primary btn--md" data-apply="${s.id}">Apply for this seat</button>
                </div>`;
            }
        } else {
            action = `<div class="wl-slot-actions">
                   <button class="btn btn--primary btn--md" data-claim="${s.id}">Claim this seat</button>
               </div>`;
            note = `Everyone on this waitlist was told at the same moment. No meeting is required — whoever claims it first pays and takes it.`;
        }

        return `
        <article class="wl-slot${s.is_mine ? " wl-slot--mine" : ""}">
            <div class="wl-slot-top">
                <div>
                    <span class="wl-badge wl-badge--${s.role === "active" ? "active" : "silent"}">${role} seat</span>
                    <h3>${VH.card.esc(s.title)}</h3>
                    <p class="wl-slot-meta">${VH.card.esc(s.industry || "")}${s.location ? " · " + VH.card.esc(s.location) : ""}</p>
                </div>
                <div class="wl-slot-figures">
                    <div><strong>${VH.card.money(s.invested_amount)}</strong><span>seat value</span></div>
                    <div><strong>${VH.card.money(s.fee_amount)}</strong><span>commitment fee</span></div>
                </div>
            </div>
            ${reason}
            <p class="wl-slot-note">${note}</p>
            ${action}
        </article>`;
    }).join("");

    list.querySelectorAll("[data-claim]").forEach(b =>
        b.addEventListener("click", () => wlConfirmClaim(parseInt(b.dataset.claim, 10))));
    list.querySelectorAll("[data-apply]").forEach(b =>
        b.addEventListener("click", () => wlOpenApplyForm(parseInt(b.dataset.apply, 10))));
    list.querySelectorAll("[data-pay]").forEach(b =>
        b.addEventListener("click", () => wlPay(parseInt(b.dataset.pay, 10), b)));
    list.querySelectorAll("[data-release]").forEach(b =>
        b.addEventListener("click", () => wlRelease(parseInt(b.dataset.release, 10))));
}

/*
 * The line under "Assets you are waiting on", written from the Assets that are
 * actually in the list.
 *
 * "Ye active only ha, isme ye nahi aana chahiye — faraaz isme ye likh sakte ha
 * 'if any active partner exits'. You add resume details to founder, be selected
 * and claim seat after selection." — the client, 7 Sep 2026.
 *
 * A fixed sentence cannot be right for everyone: an active-only listing has no
 * silent role, so telling its waiters about a first-come silent seat describes
 * something that Asset can never offer.
 *
 * THE NOTE NOW SITS ON EACH ASSET'S OWN ROW, and that is what finally settles it.
 *
 *   "Jese hi mene dusra venture add kiya why this line show 'a seat may be silent
 *    or Active' — vo seat active thi toh uski line show karo na, then disappear
 *    that line" — the client, 7 Sep 2026.
 *
 * It was one shared line above the list, so it had to describe every Asset at once
 * and could only fall back to the general form the moment two rows disagreed. Adding
 * a second Asset of a different kind is all it took: he had one silent listing and
 * one active-only listing, each with a sentence of its own already written, and got
 * the generic sentence instead of either. A line above a mixed list CANNOT be true
 * of every row beneath it, which is why this had been reworded three times in three
 * days without ever being right.
 *
 * Per-row, each Asset states its own rule and the shared line is gone. `both` is no
 * longer a fallback but an answer: such an Asset really can open either kind of seat.
 *
 * BOTH SINGLE-KIND SENTENCES ARE THE CLIENT'S OWN WORDS, given 8 Sep 2026 as two
 * screenshots — one per variant, which is itself the sign-off on this staying
 * adaptive rather than collapsing back to one sentence:
 *
 *   silent — "If a Partner Exits after Physical Meeting the Available seat is
 *             offered to Everyone waiting at once. Whoever claims it first gets
 *             the seat"
 *   active — "If an Active Partner Exits, the seat becomes Available. Submit your
 *             Resume and Details to the Founder. If selected, the Seat is Yours
 *             to Claim."
 *
 * Only the capitalisation was normalised to sentence case, to match every other
 * string on the page; the words and their order are his. Each variant carries ONE
 * <strong>, and it is on the decision rule — first-come for silent, the founder's
 * selection for active — because that is the half a waiter needs to act on.
 *
 * "physical meeting" is his word and is kept deliberately, but note it is narrower
 * than the code: vh_meetup_mode() has been OFF since 5 Sep 2026, so a meetup may be
 * online or physical, and ANY completed meetup opens the quit window that lets a
 * partner exit. Widen this to "the meeting" if he ever meets online.
 */
function wlNoteHTML(partnerTypes) {
    const t = String(partnerTypes || "both");

    if (t === "active") {
        return `If an Active Partner Exits, the seat becomes Available. Submit your Resume and Details to `
             + `the Founder. <strong>If selected, the Seat is Yours to Claim.</strong>`;
    }
    if (t === "silent") {
        return `If a Partner Exits after Physical Meeting the Available seat is offered to Everyone waiting `
             + `at once. <strong>Whoever claims it first gets the seat.</strong>`;
    }
    // A `both` Asset genuinely may open either kind of seat, so this is not a
    // fallback here the way it was when one line had to cover a whole list — it is
    // the honest answer FOR THIS ASSET, and the only one of the three that has to
    // describe two routes.
    return `A seat here may be Silent or Active. A Silent seat goes to whoever claims it first; `
         + `<strong>an Active seat is chosen by the Founder</strong> from the people who apply.`;
}

/* HOW LONG THE LINE STAYS. "Few seconds" is the brief, but it has to be long enough
   to READ — the active sentence is three clauses — so this is deliberately at the
   generous end of that, and it is a named constant precisely so it can be tuned
   without going hunting through the show/hide code. */
const WL_FLASH_MS = 9000;
let wlFlashTimer = null;

/* THE NOTE IS SHOWN ONCE, WHEN AN ASSET IS ADDED, AND THEN GOES AWAY.
 *
 *   "Ek baar show kardo when add any asset, then disappear in few seconds kardo line
 *    ko. Line show hogi jo asset add hoga — latest active plus silent wala asset hota
 *    ha toh 'this seat maybe active or silent' ye wali. Only active ha toh 'If an
 *    Active Partner Exits...' ye wali." — the client, 7 Sep 2026.
 *
 * This is what his earlier "uski line show karo na THEN DISAPPEAR that line" meant,
 * and it was first read here as "delete the generic fallback" — hence a spell where
 * the note sat permanently on every row. It is a confirmation of the rule at the
 * moment somebody commits to waiting, not standing documentation: it says the rule
 * for the Asset just added, then gets out of the way.
 *
 * Keyed to ONE Asset, so the sentence is always exactly right for it — which is also
 * why the three-way branch in wlNoteHTML() survives untouched. */
function wlFlashNote(partnerTypes) {
    const el = document.getElementById("wlFlash");
    if (!el) return;

    const span = el.querySelector("span");
    if (span) span.innerHTML = wlNoteHTML(partnerTypes);

    // A second join while the first line is still up must not leave the older timer
    // running, or it would hide the new line early.
    if (wlFlashTimer) { clearTimeout(wlFlashTimer); wlFlashTimer = null; }

    el.hidden = false;
    // Next frame, so the element is laid out before the class that transitions it —
    // setting both in one go makes the browser skip the animation entirely.
    requestAnimationFrame(() => el.classList.add("is-in"));

    wlFlashTimer = setTimeout(() => {
        el.classList.remove("is-in");
        // Stay out of the layout once faded, so it leaves no gap above the list.
        wlFlashTimer = setTimeout(() => { el.hidden = true; }, 400);
    }, WL_FLASH_MS);
}

function wlRenderEntries(entries) {
    const list = document.getElementById("wlEntryList");
    list.innerHTML = entries.map(e => {
        const joined = e.status === "joined";
        const pct = parseInt(e.progress_percent, 10) || 0;
        return `
        <article class="wl-entry${joined ? " wl-entry--joined" : ""}">
            <div class="wl-entry-main">
                <h3><a href="../pages/venture-detail.php?id=${e.venture_id}">${VH.card.esc(e.title)}</a></h3>
                <p class="wl-entry-meta">${VH.card.esc(e.founder_name || "")}${e.location ? " · " + VH.card.esc(e.location) : ""}</p>
                <div class="wl-entry-stats">
                    <span>${VH.card.money(e.raised_capital)} of ${VH.card.money(e.target_capital)}</span>
                    <span>${e.members_count} partners</span>
                    <span>${e.days_left} day${e.days_left === 1 ? "" : "s"} left</span>
                    <span>${e.waiting_count} waiting</span>
                </div>
                <div class="wl-entry-bar"><i style="width:${Math.min(100, pct)}%"></i></div>
            </div>
            <div class="wl-entry-side">
                ${joined
                    ? `<span class="wl-status wl-status--joined">You took a seat</span>`
                    : `<span class="wl-status">Waiting</span>
                       <button class="btn btn--secondary btn--sm" data-leave="${e.venture_id}">Leave waitlist</button>`}
            </div>
        </article>`;
    }).join("");

    list.querySelectorAll("[data-leave]").forEach(b =>
        b.addEventListener("click", () => wlLeave(parseInt(b.dataset.leave, 10))));
}

/* --------------------------------------------------------------- actions -- */

async function wlJoin(ventureId) {
    if (!ventureId) return;
    try {
        const res = await (await fetch("../api/waitlist.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "join", venture_id: ventureId })
        })).json();
        res.success ? VH.toast.success(res.message) : VH.toast.error(res.message || "Could not join the waitlist.");
        // The rule for THIS Asset is flashed once the reload brings its row back —
        // that response is where partner_types comes from. See wlFlashNote().
        if (res.success) wlFlashFor = ventureId;
        wlLoad();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error joining the waitlist.");
    }
}

async function wlLeave(ventureId) {
    if (!confirm("Leave this waitlist?\n\nYou will stop being notified when a seat opens on this Asset.")) return;
    try {
        const res = await (await fetch("../api/waitlist.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "leave", venture_id: ventureId })
        })).json();
        res.success ? VH.toast.success(res.message) : VH.toast.error(res.message || "Could not leave.");
        wlLoad();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error.");
    }
}

/* The seat inherits the ROLE of the partner who left, so a waitlister may be
 * offered either kind — and the two are different commitments: a silent seat is
 * capital, an active seat is work. The notification already says which; this is
 * the second telling, at the one moment it costs something to be wrong.
 *
 * It matters here specifically because claiming is not browsing. It starts a
 * VH_SLOT_CLAIM_MINUTES hold that locks everybody else out of the seat, so a
 * mis-click both commits this person and stalls the queue. One click used to do
 * it. (Client, 6 Sep 2026: "when he is claiming the seat he also gets one
 * confirmation pop up saying this seat is for silent/active partner.")
 */
function wlConfirmClaim(slotId) {
    const s = wlSlots.find(x => parseInt(x.id, 10) === slotId);
    if (!s) { wlLoad(); return; }   // list went stale; re-read rather than guess

    const isActive = s.role === "active";
    const role = isActive ? "Active partner" : "Silent partner";
    // Server's VH_SLOT_CLAIM_MINUTES, sent per slot. The fallback is only for a
    // slot row that predates the field; keep it equal to the constant.
    const mins = parseInt(s.claim_minutes, 10) || 10;

    /* What the role actually costs the person taking it, in their words, not in
       ours — "active" and "silent" mean nothing to somebody reading it cold. */
    const meaning = isActive
        ? `The partner who left was an <strong>active partner</strong> — they worked in the business. Taking this seat means taking on that operational role, not just the capital.`
        : `This is a <strong>silent partner</strong> seat — capital only. You are not expected to work in the business; you hold your share and take your part of the returns.`;

    document.getElementById("claimTitle").textContent = `Claim this ${role} seat`;
    document.getElementById("claimBody").innerHTML = `
        <div class="wl-confirm">
            <span class="wl-badge wl-badge--${isActive ? "active" : "silent"}">${role} seat</span>
            <p class="wl-confirm-lead">${meaning}</p>
            <div class="wl-confirm-figures">
                <div><strong>${VH.card.money(s.invested_amount)}</strong><span>seat value</span></div>
                <div><strong>${VH.card.money(s.fee_amount)}</strong><span>commitment fee, payable now</span></div>
            </div>
            <p class="wl-confirm-hold">Claiming holds the seat for you for <strong>${mins} minutes</strong> while you pay. If the fee is not paid in that time the seat goes back to everyone on the waitlist.</p>
            <p class="wl-confirm-ask">Do you wish to continue?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn--secondary btn--md" id="claimCancel">Cancel</button>
            <button class="btn btn--primary btn--md" id="claimGo">Yes, claim this seat</button>
        </div>`;

    document.getElementById("claimCancel").onclick = () => VH.modal.close("claimModal");
    document.getElementById("claimGo").onclick = () => {
        VH.modal.close("claimModal");
        wlClaim(slotId);
    };

    VH.modal.open("claimModal");
}

/* ------------------------------------------------- applying for a seat --- */
/*
 * An ACTIVE seat is applied for, not claimed. The founder reads a resume, a short
 * message and a WhatsApp number and picks somebody — the same three things an
 * ordinary active application carries, collected here at the seat rather than when
 * joining the waitlist, so waiting stays free and one click.
 *
 * Reuses #claimModal: it is the same dialog slot, and having two modals for "decide
 * about this seat" would be two things to keep in step.
 */
function wlOpenApplyForm(slotId) {
    const s = wlSlots.find(x => parseInt(x.id, 10) === slotId);
    if (!s) { wlLoad(); return; }

    const updating = s.my_application === "pending";

    document.getElementById("claimTitle").textContent =
        updating ? "Update your application" : "Apply for this Active partner seat";
    document.getElementById("claimBody").innerHTML = `
        <div class="wl-confirm">
            <span class="wl-badge wl-badge--active">Active partner seat</span>
            <p class="wl-confirm-lead">The partner who left was an <strong>active partner</strong> — they worked in the
                business. The founder chooses who takes the seat, so give them enough to choose on.</p>
            <div class="wl-confirm-figures">
                <div><strong>${VH.card.money(s.invested_amount)}</strong><span>seat value</span></div>
                <div><strong>${VH.card.money(s.fee_amount)}</strong><span>commitment fee, only if chosen</span></div>
            </div>

            <div class="form-group" style="margin-top:1rem">
                <label class="form-label" for="wlApplyResume">Your resume *</label>
                <input class="form-control" type="file" id="wlApplyResume" accept=".pdf,.doc,.docx"/>
                <span class="form-hint" style="font-size:0.78rem;color:#5A6B85">PDF, DOC or DOCX, up to 5MB.${
                    updating ? " Leave empty to keep the one you already sent." : ""}</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="wlApplyWhatsapp">WhatsApp number *</label>
                <input class="form-control" type="tel" id="wlApplyWhatsapp" placeholder="98765 43210"/>
                <span class="form-hint" style="font-size:0.78rem;color:#5A6B85">If the founder needs more information they will contact you on this.</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="wlApplyMessage">A short message to the founder</label>
                <textarea class="form-control form-textarea" id="wlApplyMessage" rows="3"
                          placeholder="Why you, and what you would bring to the day-to-day."></textarea>
            </div>

            <p class="wl-confirm-hold">Nothing is charged now, and applying does not hold the seat. If the founder
                selects you, you will have <strong>${s.pay_days} days</strong> to pay the commitment fee.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn--secondary btn--md" id="wlApplyCancel">Cancel</button>
            <button class="btn btn--primary btn--md" id="wlApplyGo">${updating ? "Update application" : "Send application"}</button>
        </div>`;

    document.getElementById("wlApplyCancel").onclick = () => VH.modal.close("claimModal");
    document.getElementById("wlApplyGo").onclick = () => wlSubmitApplication(slotId, updating);

    VH.modal.open("claimModal");
}

async function wlSubmitApplication(slotId, updating) {
    const fileEl = document.getElementById("wlApplyResume");
    const waEl   = document.getElementById("wlApplyWhatsapp");
    const msgEl  = document.getElementById("wlApplyMessage");
    const go     = document.getElementById("wlApplyGo");

    const file = fileEl.files && fileEl.files[0];
    if (!file && !updating) {
        VH.toast.error("Please attach your resume — the founder chooses from these.");
        return;
    }
    if (file && file.size > 5 * 1024 * 1024) {
        VH.toast.error("Resume must be smaller than 5MB.");
        return;
    }
    // Same shape the server insists on (vh_normalize_whatsapp): digits, and a bare
    // 10-digit Indian mobile is assumed to be +91. The server is the authority.
    const digits = (waEl.value || "").replace(/\D/g, "");
    if (digits.length < 10) {
        VH.toast.error("Please give a WhatsApp number the founder can reach you on.");
        return;
    }

    const fd = new FormData();
    fd.append("action", "apply_slot");
    fd.append("slot_id", slotId);
    fd.append("whatsapp_number", waEl.value || "");
    fd.append("message", msgEl.value || "");
    if (file) fd.append("resume", file);

    go.disabled = true;
    go.textContent = "Sending…";
    try {
        const res = await (await fetch("../api/waitlist.php", { method: "POST", body: fd })).json();
        if (!res.success) {
            VH.toast.error(res.message || "Could not send your application.");
            go.disabled = false;
            go.textContent = updating ? "Update application" : "Send application";
            return;
        }
        VH.modal.close("claimModal");
        VH.toast.success(res.message);
        wlLoad();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error sending your application.");
        go.disabled = false;
        go.textContent = updating ? "Update application" : "Send application";
    }
}

async function wlClaim(slotId) {
    try {
        const response = await fetch("../api/waitlist.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "claim", slot_id: slotId })
        });
        const res = await response.json();

        if (!res.success) {
            // Somebody was a moment quicker. Re-read rather than leaving a button
            // that will only fail again — the seat may already be gone.
            VH.toast.error(res.message || "Could not claim that seat.");
            wlLoad();
            return;
        }

        VH.toast.success(res.message);
        wlLoad();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error claiming the seat.");
    }
}

async function wlRelease(slotId) {
    if (!confirm("Give this seat back?\n\nIt returns to everyone else on the waitlist immediately, and you may not get it again.")) return;
    try {
        const res = await (await fetch("../api/waitlist.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "release", slot_id: slotId })
        })).json();
        res.success ? VH.toast.success(res.message) : VH.toast.error(res.message);
        wlLoad();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error.");
    }
}

async function wlPay(slotId, btn) {
    const slot = wlSlots.find(s => parseInt(s.id, 10) === slotId);
    if (!slot) return;

    if (!confirm(`Take this seat in ${slot.title}?\n\nYou pay the commitment fee of ${VH.card.money(slot.fee_amount)} now. `
               + `The pledged ${VH.card.money(slot.invested_amount)} is recorded for tracking and settled offline with the founder.\n\n`
               + `You are joining without a meeting, so your 24-hour refund window starts the moment this goes through.`)) return;

    btn.disabled = true;
    btn.textContent = "Confirming…";

    try {
        const response = await fetch("../api/waitlist.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "pay", slot_id: slotId })
        });
        const res = await response.json();

        if (res.success) {
            VH.toast.success(res.message);
            setTimeout(() => { location.href = "dashboard.php"; }, 1600);
            return;
        }

        // 402 gatewayRequired: PayU is live, so the seat has to be paid for through
        // the gateway. Hand the claim over to the ordinary payment flow, which
        // already knows how to redirect and come back.
        if (res.gatewayRequired) {
            location.href = `../pages/join-venture.php?id=${slot.venture_id}&slot_id=${slotId}`;
            return;
        }

        VH.toast.error(res.message || "Could not confirm the seat.");
        wlLoad();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error confirming the seat.");
    } finally {
        btn.disabled = false;
        btn.textContent = `Pay ${VH.card.money(slot.fee_amount)} & take the seat`;
    }
}

/* -------------------------------------------------------------- countdown -- */

function wlFormatLeft(seconds) {
    const s = Math.max(0, seconds | 0);
    const m = Math.floor(s / 60);
    return `${m}:${String(s % 60).padStart(2, "0")}`;
}

function wlStartTicker() {
    if (wlTimer) clearInterval(wlTimer);
    if (!wlSlots.some(s => s.is_mine)) return;

    wlTimer = setInterval(() => {
        let expired = false;
        wlSlots.forEach(s => {
            if (!s.is_mine) return;
            s.seconds_left = Math.max(0, s.seconds_left - 1);
            const el = document.querySelector(`[data-countdown="${s.id}"]`);
            if (el) el.textContent = wlFormatLeft(s.seconds_left);
            if (s.seconds_left <= 0) expired = true;
        });
        if (expired) {
            clearInterval(wlTimer);
            // The server owns the clock — ask it, do not conclude.
            wlLoad();
        }
    }, 1000);
}

/* ----------------------------------------------------------------- chrome -- */

function wlBindChrome() {
    const initials = wlUser.avatar || wlUser.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
    const sidebarAvatar = document.getElementById("sidebarAvatar");
    const sidebarUserName = document.getElementById("sidebarUserName");
    const navAvatar = document.getElementById("navAvatar");
    const navAvatarName = document.getElementById("navAvatarName");

    if (sidebarAvatar) VH.renderAvatarInto(sidebarAvatar, wlUser);
    if (sidebarUserName) sidebarUserName.textContent = wlUser.name;
    if (navAvatar) VH.renderAvatarInto(navAvatar, wlUser);
    if (navAvatarName) navAvatarName.textContent = wlUser.name.split(" ")[0];

    const hamburger = document.getElementById("navHamburger");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const collapse = document.getElementById("sidebarCollapseBtn");

    if (hamburger && sidebar) {
        hamburger.addEventListener("click", () => {
            sidebar.classList.add("open");
            if (overlay) overlay.classList.add("active");
        });
    }
    if (overlay) {
        overlay.addEventListener("click", () => {
            sidebar.classList.remove("open");
            overlay.classList.remove("active");
        });
    }
    if (collapse) {
        collapse.addEventListener("click", () => {
            sidebar.classList.toggle("sidebar--collapsed");
            const wrapper = document.querySelector(".page-wrapper");
            if (wrapper) wrapper.classList.toggle("sidebar-collapsed");
        });
    }

    const avatarBtn = document.getElementById("avatarBtn");
    const dropdown = document.getElementById("navDropdown");
    if (avatarBtn && dropdown) {
        avatarBtn.addEventListener("click", e => {
            e.stopPropagation();
            dropdown.classList.toggle("open");
        });
        document.addEventListener("click", () => dropdown.classList.remove("open"));
    }
    // Logout is bound centrally by VH.auth.bindLogout() in shared.js — never here.
}
