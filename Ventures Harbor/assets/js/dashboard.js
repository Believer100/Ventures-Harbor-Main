/* VENTURES HARBOR — DASHBOARD INTERACTION (dashboard.js) */

/* One glyph per notification type, so the two renderers below (the bell dropdown
   and the full list) cannot drift. Was an inline emoji ladder duplicated in both. */
function notifIcon(type) {
    const map = { meetup: "calendar", Asset: "briefcase", venture: "briefcase",
                  payment: "wallet", kyc: "clipboard" };
    return VH.icon(map[type] || "bell", 15);
}

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    // Initialize layout names and avatars
    updateHeaderAndSidebar(user);
    VH.auth.applyRoleUI(user);
    initSupportAndAccountActions();

    // Fetch Dashboard Data
    fetchDashboardData();

    // Toggle Sidebar Mobile
    const navHamburger = document.getElementById("navHamburger");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");
    const sidebarCollapseBtn = document.getElementById("sidebarCollapseBtn");

    if (navHamburger && sidebar) {
        navHamburger.addEventListener("click", () => {
            sidebar.classList.add("open");
            if (sidebarOverlay) sidebarOverlay.classList.add("active");
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", () => {
            sidebar.classList.remove("open");
            sidebarOverlay.classList.remove("active");
        });
    }

    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener("click", () => {
            sidebar.classList.toggle("sidebar--collapsed");
        });
    }

    // Avatar Dropdown Toggle
    const avatarBtn = document.getElementById("avatarBtn");
    const navDropdown = document.getElementById("navDropdown");
    if (avatarBtn && navDropdown) {
        avatarBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            navDropdown.classList.toggle("open");
        });
        document.addEventListener("click", () => {
            navDropdown.classList.remove("open");
        });
    }

    // Notification Bell Dropdown Toggle
    const notifBtn = document.getElementById("notifBtn");
    const notifDropdown = document.getElementById("notifDropdown");
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle("open");
        });
        document.addEventListener("click", () => {
            notifDropdown.classList.remove("open");
        });
    }
    const notifDropdownMarkAll = document.getElementById("notifDropdownMarkAll");
    if (notifDropdownMarkAll) {
        notifDropdownMarkAll.addEventListener("click", (e) => {
            e.stopPropagation();
            markNotificationsAsRead(user.id);
        });
    }
    const notifDropdownViewAll = document.getElementById("notifDropdownViewAll");
    if (notifDropdownViewAll) {
        notifDropdownViewAll.addEventListener("click", (e) => {
            e.preventDefault();
            notifDropdown.classList.remove("open");
            document.getElementById("notifList")?.scrollIntoView({ behavior: "smooth", block: "center" });
        });
    }

    // Notification mark all read
    const markAllReadBtn = document.getElementById("markAllRead");
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener("click", () => markNotificationsAsRead(user.id));
    }
});

function initSupportAndAccountActions() {
    const user = VH.auth.getUser();

    const supportBtn = document.getElementById("sidebarSupport");
    if (supportBtn) supportBtn.addEventListener("click", () => VH.modal.open("supportModal"));

    const sendSupportBtn = document.getElementById("sendSupportBtn");
    if (sendSupportBtn) {
        sendSupportBtn.addEventListener("click", async () => {
            const subject = document.getElementById("supportSubject").value.trim();
            const message = document.getElementById("supportMessage").value.trim();
            if (!message) { VH.toast.error("Please describe your issue before sending."); return; }

            sendSupportBtn.disabled = true;
            sendSupportBtn.textContent = "Sending...";
            try {
                const res = await (await fetch("../api/contact.php?action=submit", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        action: "submit",
                        name: user ? user.name : "Ventures Harbor user",
                        email: user ? user.email : "",
                        subject: subject || "Support request",
                        message
                    })
                })).json();
                if (res.success) {
                    VH.toast.success("Message sent to the admin team.");
                    document.getElementById("supportSubject").value = "";
                    document.getElementById("supportMessage").value = "";
                    VH.modal.close("supportModal");
                } else {
                    VH.toast.error(res.message || "Could not send your message.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error sending your message.");
            } finally {
                sendSupportBtn.disabled = false;
                sendSupportBtn.textContent = "Send to Admin";
            }
        });
    }

    const deleteBtn = document.getElementById("sidebarDeleteAccount");
    if (deleteBtn) deleteBtn.addEventListener("click", () => {
        VH.modal.open("deleteAccountModal");
        VH.deleteAccountRefund.gate();
    });

    VH.deleteAccountRefund.init();

    const confirmDeleteBtn = document.getElementById("confirmDeleteAccountBtn");
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", async () => {
            const typed = document.getElementById("deleteAccountConfirm").value.trim();
            if (typed !== "DELETE") { VH.toast.error('Please type DELETE to confirm.'); return; }

            // Optional refund-of-fees claim filed alongside the closure.
            const refund = VH.deleteAccountRefund.collect();
            if (refund.error) { VH.toast.error(refund.error); return; }

            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = "Deleting...";
            try {
                const res = await (await fetch("../api/auth.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(Object.assign({ action: "deactivate" }, refund.payload))
                })).json();
                if (res.success) {
                    VH.toast.success(res.message || "Your account has been deleted.");
                    localStorage.removeItem("vh_user");
                    setTimeout(() => { window.location.href = "../index.php"; }, 1800);
                } else {
                    VH.toast.error(res.message || "Could not delete your account.");
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.textContent = "Yes, delete permanently";
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error deleting your account.");
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = "Yes, delete permanently";
            }
        });
    }
}

function updateHeaderAndSidebar(user) {
    const initials = user.avatar || user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
    const firstName = user.name.split(" ")[0];

    const sidebarAvatar = document.getElementById("sidebarAvatar");
    const sidebarUserName = document.getElementById("sidebarUserName");
    const navAvatar = document.getElementById("navAvatar");
    const navAvatarName = document.getElementById("navAvatarName");
    const welcomeName = document.getElementById("welcomeName");
    const welcomeDate = document.getElementById("welcomeDate");

    if (sidebarAvatar) VH.renderAvatarInto(sidebarAvatar, user);
    if (sidebarUserName) sidebarUserName.textContent = user.name;
    if (navAvatar) VH.renderAvatarInto(navAvatar, user);
    if (navAvatarName) navAvatarName.textContent = firstName;
    if (welcomeName) welcomeName.textContent = firstName;

    // Display formatted current date
    if (welcomeDate) {
        const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const dateStr = new Date().toLocaleDateString('en-IN', options);
        welcomeDate.textContent = `${dateStr} · ${user.city || 'India'}`;
    }
}

async function fetchDashboardData() {
    try {
        const response = await fetch(`../api/dashboard_data.php`, {
            credentials: 'same-origin'
        });
        const res = await response.json();

        if (res.success) {
            // Update stats
            document.getElementById("statMyVentures").textContent = res.stats.myVenturesCount;
            document.getElementById("statJoined").textContent = res.stats.joinedCount;
            document.getElementById("statInvested").textContent = '₹' + parseInt(res.stats.investedTotal).toLocaleString('en-IN');

            // Update sidebar badges
            const sidebarBadges = document.querySelectorAll(".sidebar-badge");
            if (sidebarBadges[0]) sidebarBadges[0].textContent = res.stats.myVenturesCount;
            if (sidebarBadges[1]) sidebarBadges[1].textContent = res.stats.joinedCount;

            // Render open applications (incl. the "pay your fee" action)
            renderMyApplications(res.myApplications);

            // Render My Ventures
            renderMyVentures(res.myVentures);

            // Render Joined Ventures
            renderJoinedVentures(res.joinedVentures);

            // Render Meetups
            renderMeetups(res.upcomingMeetups);

            // Render Notifications
            renderNotifications(res.notifications, res.unreadCount);

            // Render Activity
            renderActivities(res.activities);
        } else {
            VH.toast.error("Failed to load dashboard statistics.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error loading dashboard statistics.");
    }
}

function renderMyApplications(apps) {
    const card = document.getElementById("my-applications");
    const list = document.getElementById("myApplicationsList");
    if (!card || !list) return;

    if (!apps || apps.length === 0) {
        card.classList.add("hidden");
        return;
    }
    card.classList.remove("hidden");

    list.innerHTML = '<div class="dash-ventures-list">' + apps.map(a => {
        const isSelected = a.status === 'selected';
        const fee = Math.round(Number(a.invested_amount || 0) * 0.005);

        const right = isSelected
            ? `<a href="../pages/join-venture.php?id=${a.venture_id}&application_id=${a.id}"
                  class="btn btn--primary btn--sm">Pay ₹${fee.toLocaleString('en-IN')} Fee →</a>`
            : `<span class="badge badge--gray">Awaiting founder review</span>`;
        const withdrawLabel = isSelected ? 'Decline &amp; withdraw' : 'Remove request';
        return `
            <div class="dash-venture-item card">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;">
                        <span class="badge ${isSelected ? 'badge--success' : 'badge--gray'}">${isSelected ? 'SELECTED' : 'PENDING'}</span>
                        <span class="badge badge--gray">Partner</span>
                    </div>
                    <h4 style="margin:0 0 0.4rem;font-size:1rem;">
                        <a href="../pages/venture-detail.php?id=${a.venture_id}" class="auth-link" style="color:#0f172a">${a.venture_title}</a>
                    </h4>
                    <p style="margin:0;font-size:0.8rem;color:#64748b;">
                        Pledged investment: ₹${Number(a.invested_amount).toLocaleString('en-IN')}
                        ${isSelected ? ' · The founder selected you — pay the commitment fee to confirm your place.' : ''}
                    </p>
                    <p style="margin:0.35rem 0 0;font-size:0.74rem;color:#94a3b8;">
                        You haven't been charged anything yet — withdrawing now costs nothing.
                    </p>
                </div>
                <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:0.4rem;">
                    ${right}
                    <button class="btn btn--ghost btn--sm" style="color:#ef4444;padding:2px 8px;font-size:0.75rem;"
                            onclick="withdrawApplication(${a.id}, ${isSelected})">${withdrawLabel}</button>
                </div>
            </div>`;
    }).join('') + '</div>';
}

window.withdrawApplication = async function(applicationId, wasSelected) {
    const message = wasSelected
        ? "Withdraw your application? The founder selected you, and they'll be notified that you've backed out. You haven't been charged, so there's nothing to refund."
        : "Remove your application? You haven't been charged anything, so there's nothing to refund. You can apply again later.";
    if (!confirm(message)) return;

    try {
        const res = await (await fetch("../api/ventures.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action: "withdraw_application", application_id: applicationId })
        })).json();

        if (res.success) {
            VH.toast.success(res.message || "Application withdrawn.");
            const user = VH.auth.getUser();
            if (user) fetchDashboardData(user.id);
        } else {
            VH.toast.error(res.message || "Could not withdraw your application.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error withdrawing your application.");
    }
};

function renderMyVentures(ventures) {
    const list = document.getElementById("myVenturesList");
    if (!list) return;

    if (!ventures || ventures.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">${VH.icon("briefcase", 30)}</div>
                <h4>No Ventures Listed Yet</h4>
                <p>List your Venture to find co-investors.</p>
                <a href="../pages/create-venture.php" class="btn btn--primary btn--sm" style="margin-top:1rem">+ List Your Venture</a>
            </div>
        `;
        return;
    }

    myVenturesCache = {};

    let html = '<div class="dash-ventures-list">';
    ventures.forEach(v => {
        myVenturesCache[String(v.id)] = v;

        const status = v.status || 'active';
        const isExpired = status === 'expired';
        const isCancelled = status === 'cancelled';

        const isUnpaid = status === 'pending_payment';

        const statusCls = isCancelled ? 'badge--danger'
            : (isExpired ? 'badge--warning'
            : (isUnpaid ? 'badge--warning'
            : (status === 'suspended' ? 'badge--danger' : 'badge--success')));

        const banner = isUnpaid
            ? `<div class="dash-listing-alert">
                   <strong>Not published yet.</strong> This Asset is waiting for its listing fee, so nobody else can see it.
                   Pay the fee to put it live in the gallery.
                   <button class="btn btn--primary btn--sm" style="margin-top:0.6rem;display:block"
                           onclick="payListingFee('${escAttr(v.id)}')">Pay listing fee &amp; publish</button>
               </div>`
            : isExpired
            ? `<div class="dash-listing-alert">
                   <strong>Your listing period has ended.</strong> This venture wasn't fully funded in time.
                   ${Number(v.extension_count) > 0
                        ? 'It has already used its one extension, so the only remaining option is to delete it.'
                        : 'You can extend the listing once, or delete it.'}
                   If you do neither it will be closed automatically and your partners refunded.
                   <button class="btn btn--primary btn--sm" style="margin-top:0.6rem;display:block"
                           onclick="openDeleteVentureModal('${escAttr(v.id)}')">Choose what happens next</button>
               </div>`
            : (isCancelled
                ? `<div class="dash-listing-alert dash-listing-alert--dead">
                       <strong>This Asset was deleted${v.cancelled_at ? ' on ' + String(v.cancelled_at).split(' ')[0] : ''}.</strong>
                       ${v.cancel_reason ? escHtmlDash(v.cancel_reason) : ''}
                       Commitment fees paid by partners have been queued for refund by the Ventures Harbor team.
                       It stays here until those refunds are paid out.
                   </div>`
                : '');

        const actions = isCancelled
            ? `<a href="../pages/venture-detail.php?id=${encodeURIComponent(v.id)}" class="btn btn--secondary btn--sm">View</a>`
            : isUnpaid
            ? `<a href="../pages/create-venture.php?edit=${encodeURIComponent(v.id)}" class="btn btn--secondary btn--sm">Edit</a>
               <button class="btn btn--sm" style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca"
                       onclick="openDeleteVentureModal('${escAttr(v.id)}')">Delete</button>`
            : `<a href="../pages/create-venture.php?edit=${encodeURIComponent(v.id)}" class="btn btn--secondary btn--sm">Edit</a>
               <button class="btn btn--sm" style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca"
                       onclick="openDeleteVentureModal('${escAttr(v.id)}')">Delete</button>`;

        const daysNote = (!isCancelled && !isUnpaid && v.listing_ends_at)
            ? `<span style="color:${isExpired ? '#b45309' : '#94a3b8'};"> · ${isExpired ? 'Listing ended' : parseInt(v.days_left, 10) + ' days left'}</span>`
            : '';

        html += `
            <div class="dash-venture-item card">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;">
                        <span class="badge" style="background:${v.icon_bg || '#f1f5f9'};color:${v.icon_color || '#1e293b'}">${v.industry}</span>
                        <span class="badge ${statusCls}">${isCancelled ? 'DELETED' : (isUnpaid ? 'UNPAID' : status.toUpperCase())}</span>
                    </div>
                    <h4 style="margin:0 0 0.4rem;font-size:1rem;"><a href="../pages/venture-detail.php?id=${v.id}" class="auth-link" style="color:#0f172a">${v.title}</a></h4>
                    <p style="margin:0;font-size:0.8rem;color:#64748b;">Target: ₹${parseInt(v.target_capital).toLocaleString('en-IN')} · Raised: ₹${parseInt(v.raised_capital).toLocaleString('en-IN')}${daysNote}</p>
                    ${ventureActivityHTML(v)}
                    ${banner}
                </div>
                <div style="text-align:right">
                    <div style="font-weight:700;font-size:0.95rem;color:#3983F6;">${v.progress_percent}%</div>
                    <div style="font-size:0.75rem;color:#94a3b8;">Funded</div>
                    <div style="display:flex;gap:0.4rem;margin-top:0.6rem;justify-content:flex-end;flex-wrap:wrap">
                        ${actions}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

/**
 * The activity chips under each of the founder's own listings.
 *
 * A founder with three Assets could not tell which one wanted him without opening each
 * one, because an application, a question and a chat message all landed only in the bell
 * dropdown. These put it on the card itself.
 *
 * No partner headcount here on purpose — the client cut it on review, since the card
 * already states Raised and % Funded and a third figure said the same thing again.
 *
 * Two kinds, and the colour is the difference:
 *   ACTION  (amber, first)  something is waiting on the founder and will not clear itself
 *   INFO    (grey)          what is going on, for context
 *
 * Every chip is a link straight into the tab that answers it — venture-detail.php reads
 * ?tab= already. A zero count renders nothing rather than a "0", so a quiet listing shows
 * a clean card instead of a row of empty badges.
 */
function ventureActivityHTML(v) {
    const a = v.activity;
    if (!a) return '';

    const detail = (tab) => `../pages/venture-detail.php?id=${encodeURIComponent(v.id)}&tab=${tab}`;
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    const chips = [
        // Named "active partner application" rather than just "application": only an
        // active partner ever files one. A silent partner joins and pays in a single
        // step, so there is nothing for the founder to review on that side at all.
        { n: a.pending_applications, tab: 'applications', icon: 'clipboard',
          text: plural(a.pending_applications, 'active partner application') + ' for review', action: true },
        { n: a.unanswered_questions, tab: 'qna', icon: 'question',
          text: plural(a.unanswered_questions, 'question') + ' to answer', action: true },
        { n: a.chat_messages, tab: 'chat', icon: 'chat',
          text: plural(a.chat_messages, 'message'), action: false }
    ].filter(c => Number(c.n) > 0);

    if (!chips.length) return '';

    return `<div class="dash-activity">` + chips.map(c =>
        `<a class="dash-activity-chip${c.action ? ' dash-activity-chip--action' : ''}" href="${detail(c.tab)}">`
        + `${VH.icon(c.icon, 13)}<span>${c.text}</span></a>`
    ).join('') + `</div>`;
}

let myVenturesCache = {};

let deletePreview = null;
let currentListingVentureId = null;

function escAttr(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}
function escHtmlDash(value) {
    return escAttr(value);
}

window.openDeleteVentureModal = async function(id) {
    const v = myVenturesCache[String(id)];
    if (!v) return;

    currentListingVentureId = v.id;
    document.getElementById("delVentureName").textContent = v.title;
    document.getElementById("delReason").value = "";
    document.getElementById("delExtendWrap").classList.add("hidden");

    const box = document.getElementById("delImpactBox");
    const text = document.getElementById("delImpactText");
    const confirmBtn = document.getElementById("delConfirmBtn");

    box.style.background = "#F1F5F9";
    box.style.border = "1px solid #E2E8F0";
    box.style.color = "#475569";
    text.textContent = "Checking what this will affect...";
    confirmBtn.disabled = true;
    deletePreview = null;

    VH.modal.open("deleteVentureModal");

    let res;
    try {
        res = await (await fetch(
            "../api/ventures.php?action=delete_preview&venture_id=" + encodeURIComponent(v.id),
            { credentials: "same-origin" }
        )).json();
    } catch (err) {
        console.error(err);
        text.textContent = "Could not check what this would affect. Please try again.";
        return;
    }

    if (!res.success) {
        text.textContent = res.message || "Could not check what this would affect.";
        return;
    }

    deletePreview = res;
    confirmBtn.disabled = false;

    const reasonLabel = document.getElementById("delReasonLabel");
    const reasonHelp = document.getElementById("delReasonHelp");
    const money = n => Number(n).toLocaleString("en-IN");

    if (res.mode === "closed") {
        box.style.background = "#FEF2F2";
        box.style.border = "1px solid #FECACA";
        box.style.color = "#991B1B";
        text.textContent = res.refund_count > 0
            ? res.partners + " partner(s) have joined this venture. Deleting it refunds their commitment fees in full \u2014 \u20b9"
              + money(res.refund_total) + " across " + res.refund_count + " partner(s) \u2014 closes every open application, and notifies everyone. "
              + "The refunds are paid out by the Ventures Harbor team, so this Asset stays on your dashboard until that is done. This cannot be undone."
            : res.partners + " partner(s) are attached to this Asset. Deleting it closes every open application and notifies them. "
              + "No commitment fee is outstanding, so there is nothing to refund. This cannot be undone.";

        reasonLabel.textContent = "Reason for deleting (required)";
        reasonHelp.textContent = "Shown to the partners who joined, and included in their refund notification.";
    } else {
        text.textContent = "Nobody has joined this Asset and no commitment fee has been paid, so it will be removed completely along with its photos. This cannot be undone.";
        reasonLabel.textContent = "Reason for deleting (optional)";
        reasonHelp.textContent = "Nobody is affected by this deletion, so a reason isn't required.";
    }

    document.getElementById("delExtendWrap").classList.toggle("hidden", !res.can_extend);
};

document.addEventListener("DOMContentLoaded", () => {
    const extendBtn = document.getElementById("delExtendBtn");
    if (extendBtn) {
        extendBtn.addEventListener("click", async () => {
            if (!currentListingVentureId) return;
            const days = parseInt(document.getElementById("delExtendDays").value, 10) || 15;

            extendBtn.disabled = true;
            const original = extendBtn.textContent;
            extendBtn.textContent = "Extending...";
            try {
                const res = await (await fetch("../api/ventures.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({ action: "extend_listing", venture_id: currentListingVentureId, days })
                })).json();

                if (res.success) {
                    VH.toast.success(res.message);
                    VH.modal.close("deleteVentureModal");
                    fetchDashboardData();
                } else {
                    VH.toast.error(res.message || "Could not extend this listing.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error extending the listing.");
            } finally {
                extendBtn.disabled = false;
                extendBtn.textContent = original;
            }
        });
    }

    const confirmBtn = document.getElementById("delConfirmBtn");
    if (confirmBtn) {
        confirmBtn.addEventListener("click", async () => {
            if (!currentListingVentureId || !deletePreview) return;

            const reason = document.getElementById("delReason").value.trim();

            if (deletePreview.reason_required && reason === "") {
                VH.toast.error("Please give a reason - it is shown to the partners being refunded.");
                document.getElementById("delReason").focus();
                return;
            }

            const v = myVenturesCache[String(currentListingVentureId)] || {};

            const warning = deletePreview.mode === "closed"
                ? "Delete \"" + v.title + "\"?\n\nThis is permanent. " + deletePreview.refund_count
                  + " partner(s) will be refunded \u20b9" + Number(deletePreview.refund_total).toLocaleString("en-IN")
                  + " in commitment fees, and every open application will be closed."

                : "Delete \"" + v.title + "\"?\n\nThe listing closes and is removed from your dashboard. Nobody has joined, so there is nothing to refund. A closure record is kept by Ventures Harbor.";
            if (!confirm(warning)) return;

            confirmBtn.disabled = true;
            const original = confirmBtn.textContent;
            confirmBtn.textContent = "Deleting...";
            try {
                const res = await (await fetch("../api/ventures.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action: "delete",
                        venture_id: currentListingVentureId,
                        reason: reason
                    })
                })).json();

                if (res.success) {
                    VH.toast.success(res.message || "Asset deleted.");
                    VH.modal.close("deleteVentureModal");
                    fetchDashboardData();
                } else {
                    VH.toast.error(res.message || "Could not delete this Asset.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error deleting the Asset.");
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = original;
            }
        });
    }
});

function renderJoinedVentures(ventures) {
    const list = document.getElementById("joinedVenturesList");
    if (!list) return;

    if (!ventures || ventures.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">${VH.icon("handshake", 30)}</div>
                <h4>No Co-Owned Assets Yet</h4>
                <p>Explore fractional opportunities and start building your portfolio.</p>
                <a href="../pages/browse.php" class="btn btn--outline btn--sm" style="margin-top:1rem">Browse Assets</a>
            </div>
        `;
        return;
    }

    let html = '<div class="dash-ventures-list">';
    ventures.forEach(v => {
        const state = joinedVentureState(v);

        html += `
            <div class="dash-venture-item card"${state.dimmed ? ' style="opacity:0.82"' : ''}>
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;flex-wrap:wrap;">
                        <span class="badge" style="background:${v.icon_bg || '#f1f5f9'};color:${v.icon_color || '#1e293b'}">${v.industry}</span>
                        <span class="badge badge--info">${v.partner_type.toUpperCase()} PARTNER</span>
                        ${state.badge ? `<span class="badge ${state.badgeClass}">${state.badge}</span>` : ''}
                    </div>
                    <h4 style="margin:0 0 0.4rem;font-size:1rem;"><a href="../pages/venture-detail.php?id=${v.id}" class="auth-link" style="color:#0f172a">${v.title}</a></h4>
                    <p style="margin:0;font-size:0.8rem;color:#64748b;">My Investment Commitment: ₹${parseInt(v.my_investment).toLocaleString('en-IN')}</p>
                    ${myEquityHTML(v)}
                    ${state.note ? `<p style="margin:0.5rem 0 0;font-size:0.78rem;line-height:1.45;color:${state.noteColor};">${state.note}</p>` : ''}
                </div>
                <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:0.4rem;">
                    <div style="text-align:right">
                        <div style="font-weight:700;font-size:0.95rem;color:${state.dimmed ? '#94a3b8' : '#3983F6'};">${v.progress_percent}%</div>
                        <div style="font-size:0.75rem;color:#94a3b8;">Funded</div>
                    </div>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;justify-content:flex-end;">
                        <a href="../pages/venture-detail.php?id=${v.id}" class="btn btn--outline btn--sm" style="padding:2px 8px;font-size:0.75rem;">${VH.icon("chat", 13)} Chat</a>
                        ${state.action}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

/**
 * "You hold 6%" on a Co-Owned Asset card.
 *
 * Read off the snapshot the server stamped when the partner paid, not off the listing —
 * the founder can still edit the minimum ticket and their own contribution, and before
 * the snapshot existed those edits silently moved this number. `equity_drifted` says the
 * listing has changed since; the figure is unaffected, and saying so is the whole point.
 */
function myEquityHTML(v) {
    const pct = VH.equity.heldPct(v, null);
    if (!pct) return '';
    const held = `<strong style="color:#166534">${pct}</strong> equity — agreed when you joined`;
    const note = v.equity_drifted
        ? `<span style="color:#8A6D00">${VH.icon("lock", 12)} The founder has edited this listing since. Your ${pct} is unchanged.</span>`
        : '';
    return `<p style="margin:0.3rem 0 0;font-size:0.78rem;color:#64748b;">${held}</p>`
         + (note ? `<p style="margin:0.2rem 0 0;font-size:0.74rem;line-height:1.4;">${note}</p>` : '');
}

function joinedVentureState(v) {
    const exitBtn = `<button class="btn btn--ghost btn--sm" style="color:#ef4444;padding:2px 8px;font-size:0.75rem;" onclick="openExitModal(${v.id}, '${v.title.replace(/'/g, "\\'")}')">Exit Asset</button>`;

    if (v.status === 'cancelled') {
        const refundNote = v.refund_state === 'needs_bank'
            ? 'Your commitment fee is being refunded automatically — you don\'t need to request it. Add your bank details on the Payment Statement page so the team can transfer it.'
            : v.refund_state === 'processing'
                ? 'Your commitment fee refund is with the team for payout — no action needed from you.'
                : 'No commitment fee refund is pending against this Asset.';

        return {
            badge: 'CANCELLED',
            badgeClass: 'badge--danger',
            note: 'This Asset was cancelled — it did not raise enough capital in its listing period. ' + refundNote,
            noteColor: '#b91c1c',
            dimmed: true,

            action: v.refund_state === 'needs_bank'
                ? '<a href="../users/wallet.php" class="btn btn--primary btn--sm" style="padding:2px 8px;font-size:0.75rem;">Add Bank Details</a>'
                : '<a href="../users/wallet.php" class="btn btn--outline btn--sm" style="padding:2px 8px;font-size:0.75rem;">Refund Status</a>',
        };
    }

    if (v.status === 'expired') {
        return {
            badge: 'LISTING ENDED',
            badgeClass: 'badge--warning',
            note: 'The listing period ended without reaching the funding target. The founder can extend it once or cancel it — you stay a partner either way until then.',
            noteColor: '#92400e',
            dimmed: false,
            action: exitBtn,
        };
    }

    if (v.status === 'suspended') {
        return {
            badge: 'SUSPENDED',
            badgeClass: 'badge--gray',
            note: 'This listing has been suspended by the administrator. Your partnership is unaffected while it is reviewed.',
            noteColor: '#475569',
            dimmed: true,
            action: exitBtn,
        };
    }

    return { badge: '', badgeClass: '', note: '', noteColor: '', dimmed: false, action: exitBtn };
}

let currentExitVentureId = null;

function setExitRefundEnabled(enabled) {
    const wrap = document.getElementById("exitOptionRefundWrap");
    const radio = document.getElementById("exitOptionRefund");
    if (!wrap || !radio) return;
    radio.disabled = !enabled;
    wrap.classList.toggle("exit-option--disabled", !enabled);
}

/* The twin of setExitRefundEnabled, for the case the client added on 6 Sep 2026:
   a funded Asset whose meetup has not happened yet closes the forfeit route too,
   so that leaving cannot hand a seat to the waitlist before anyone has met the
   team. Both routes can therefore be shut at once, which is why the confirm
   button below now checks that the chosen radio is actually enabled. */
function setExitNoRefundEnabled(enabled) {
    const wrap = document.getElementById("exitOptionNoRefundWrap");
    const radio = document.getElementById("exitOptionNoRefund");
    if (!wrap || !radio) return;
    radio.disabled = !enabled;
    wrap.classList.toggle("exit-option--disabled", !enabled);
    if (!enabled) radio.checked = false;
}

function syncExitBankFields() {
    const refundRadio = document.getElementById("exitOptionRefund");
    const noRefundRadio = document.getElementById("exitOptionNoRefund");
    const refundChosen = refundRadio?.checked;
    document.getElementById("exitBankFields")?.classList.toggle("hidden", !refundChosen);
    const btn = document.getElementById("confirmExitBtn");
    // A disabled radio can still read as checked if it was checked before being
    // disabled, so "chosen" has to mean chosen AND still available.
    const anyChosen = (refundChosen && !refundRadio.disabled)
                   || (noRefundRadio?.checked && !noRefundRadio.disabled);
    if (btn) {
        btn.disabled = !anyChosen;
        btn.textContent = refundChosen ? "Exit & Request Refund" : "Exit Without Refund";
    }
}

window.openExitModal = async function(ventureId, title) {
    currentExitVentureId = ventureId;
    document.getElementById("exitVentureName").textContent = title;

    // Reset every field — the modal is reused across ventures.
    ["exitBankHolder", "exitBankNumber", "exitBankIfsc", "exitBankName", "exitPayoutNote"]
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ""; });
    document.querySelectorAll('input[name="exitRefundChoice"]').forEach(r => { r.checked = false; });
    document.getElementById("exitBankFields")?.classList.add("hidden");
    document.getElementById("exitOptionsWrap")?.classList.add("hidden");
    document.getElementById("confirmExitBtn").disabled = true;

    const banner = document.getElementById("exitStatusBanner");
    banner.className = "exit-status-banner";
    banner.textContent = "Checking your refund eligibility…";
    VH.modal.open("exitModal");

    try {
        const res = await (await fetch(
            `../api/ventures.php?action=exit_eligibility&venture_id=${ventureId}`,
            { credentials: "same-origin" }
        )).json();

        if (!res.success) {
            banner.classList.add("exit-status-banner--bad");
            banner.textContent = res.message || "Could not check your refund eligibility.";
            return;
        }

        const feeText = "₹" + Number(res.fee_paid).toLocaleString("en-IN");
        document.getElementById("exitOptionsWrap").classList.remove("hidden");

        setExitNoRefundEnabled(!res.no_refund_blocked);
        if (res.no_refund_blocked) {
            document.getElementById("exitOptionNoRefundDesc").textContent =
                "Not available — this Asset is fully funded and its meetup has not been completed yet.";
        }

        if (res.refundable) {
            const hoursLeft = Math.floor(res.seconds_left / 3600);
            const minsLeft = Math.floor((res.seconds_left % 3600) / 60);
            banner.classList.add("exit-status-banner--good");
            /* innerHTML, not textContent: VH.icon() returns SVG source. Only numbers this
               file computed are interpolated in — never a server string. */
            banner.innerHTML =
                `${VH.icon("check", 14)} You're inside the 24-hour window after the meetup — ` +
                `${hoursLeft}h ${minsLeft}m left. Your full commitment fee of ${feeText} is refundable.`;
            document.getElementById("exitRefundPill").textContent = feeText + " back";
            setExitRefundEnabled(true);
            document.getElementById("exitOptionRefund").checked = true;
        } else {
            setExitRefundEnabled(false);
            banner.classList.add("exit-status-banner--warn");
            if (res.no_refund_blocked) {
                /* Both routes shut. Say so plainly and say when it lifts, rather
                   than leaving the founder-dependent part of it a mystery — the
                   server sends the wording so the two cannot drift. */
                banner.innerHTML = VH.icon("hourglass", 14) + " " +
                    VH.card.esc(res.no_refund_blocked_reason ||
                        "This Asset is fully funded and its meetup has not been completed yet.");
                document.getElementById("exitOptionRefundDesc").textContent =
                    "Not available yet — this unlocks for 24 hours once the founder marks the meetup completed.";
            } else if (res.window_state === "not_opened") {
                banner.innerHTML =
                    VH.icon("hourglass", 14) + " The meetup for this Asset hasn't been marked completed yet. " +
                    "The commitment fee only becomes refundable in the 24 hours after it is — " +
                    "so right now the only way out is to exit without a refund.";
                document.getElementById("exitOptionRefundDesc").textContent =
                    "Not available yet — this unlocks for 24 hours once the founder marks the meetup completed.";
            } else if (res.window_state === "closed") {
                banner.innerHTML =
                    VH.icon("hourglass", 14) + " The 24-hour refund window after the meetup has closed, so the " +
                    "commitment fee is no longer refundable. You can still leave the Asset.";
                document.getElementById("exitOptionRefundDesc").textContent =
                    "Not available — the 24-hour window after the meetup has already closed.";
            } else {
                banner.textContent =
                    "No commitment fee payment is on record for this Asset, so there is nothing to refund.";
                document.getElementById("exitOptionRefundDesc").textContent =
                    "Not available — no commitment fee payment is on record for this Asset.";
            }
            // Only preselect it when it is actually available — otherwise the
            // dialog would arrive with a disabled option ticked and a live button.
            if (!res.no_refund_blocked) {
                document.getElementById("exitOptionNoRefund").checked = true;
            }
        }
        syncExitBankFields();
    } catch (err) {
        console.error(err);
        banner.classList.add("exit-status-banner--bad");
        banner.textContent = "Network error checking your refund eligibility.";
    }
};

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('input[name="exitRefundChoice"]')
        .forEach(r => r.addEventListener("change", syncExitBankFields));
});

// Setup exit confirmation listener
const confirmExitBtn = document.getElementById("confirmExitBtn");
if (confirmExitBtn) {
    confirmExitBtn.addEventListener("click", async () => {
        if (!currentExitVentureId) return;
        const user = VH.auth.getUser();
        if (!user) return;

        const wantsRefund = document.getElementById("exitOptionRefund").checked;
        const payload = {
            action: "exit",
            user_id: user.id,
            venture_id: currentExitVentureId,
            refund_choice: wantsRefund ? "refund" : "no_refund",
            payout_note: document.getElementById("exitPayoutNote").value.trim()
        };

        if (wantsRefund) {
            const holder = document.getElementById("exitBankHolder").value.trim();
            const number = document.getElementById("exitBankNumber").value.trim();
            const ifsc = document.getElementById("exitBankIfsc").value.trim().toUpperCase();

            if (!holder || !number || !ifsc) {
                VH.toast.error("Please fill in the account holder name, account number and IFSC code.");
                return;
            }
            if (!/^\d{6,20}$/.test(number)) {
                VH.toast.error("Account number must be 6-20 digits.");
                return;
            }
            if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc)) {
                VH.toast.error("Please enter a valid IFSC code (e.g. HDFC0001234).");
                return;
            }

            payload.bank_account_name = holder;
            payload.bank_account_number = number;
            payload.bank_ifsc = ifsc;
            payload.bank_name = document.getElementById("exitBankName").value.trim();
        } else if (!confirm("Exit without a refund? Your commitment fee will not be returned. This cannot be undone.")) {
            return;
        }

        const originalLabel = confirmExitBtn.textContent;
        confirmExitBtn.textContent = "Exiting...";
        confirmExitBtn.disabled = true;

        try {
            const response = await fetch("../api/ventures.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify(payload)
            });
            const res = await response.json();
            if (res.success) {
                VH.toast.success(res.message || "Successfully exited the Asset.");
                VH.modal.close("exitModal");
                // Reload dashboard data
                fetchDashboardData(user.id);
            } else {
                VH.toast.error(res.message || "Failed to exit Asset.");
            }
        } catch (err) {
            console.error(err);
            VH.toast.error("Network error exiting Asset.");
        } finally {
            confirmExitBtn.textContent = originalLabel;
            confirmExitBtn.disabled = false;
        }
    });
}

function renderMeetups(meetups) {
    const list = document.getElementById("upcomingMeetupsList");
    if (!list) return;

    if (!meetups || meetups.length === 0) {
        list.innerHTML = `
            <div class="empty-state" style="padding:1.5rem 1rem;">
                <div style="font-size:1.5rem;margin-bottom:0.5rem;">${VH.icon("calendar", 26)}</div>
                <h4 style="font-size:0.85rem;margin:0 0 0.2rem;">No Upcoming Meetups</h4>
                <p style="font-size:0.75rem;margin:0;">Schedule a session with co-partners to review business plans.</p>
            </div>
        `;
        return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:0.75rem;">';
    meetups.forEach(m => {
        const dateObj = new Date(m.date);
        const day = dateObj.getDate();
        const month = dateObj.toLocaleDateString('en-IN', { month: 'short' }).toUpperCase();

        html += `
            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;background:#3983F6;color:white;width:40px;height:40px;border-radius:8px;font-size:0.75rem;font-weight:700;">
                    <div>${day}</div>
                    <div style="font-size:0.55rem;opacity:0.8;">${month}</div>
                </div>
                <div style="flex:1">
                    <h5 style="margin:0 0 0.15rem;font-size:0.82rem;font-weight:700;color:#1e293b;">${m.title}</h5>
                    <p style="margin:0;font-size:0.72rem;color:#64748b;">Asset: ${m.ventureName} · ${m.time}</p>
                </div>
                <div>
                    <span class="badge ${m.rsvpStatus === 'accepted' ? 'badge--success' : m.rsvpStatus === 'declined' ? 'badge--danger' : 'badge--warning'}" style="font-size:0.65rem;">
                        ${m.rsvpStatus.toUpperCase()}
                    </span>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

function renderNotifications(notifs, unreadCount) {
    const list = document.getElementById("notifList");
    const badge1 = document.getElementById("notifBadge");
    const badge2 = document.getElementById("unreadCount");
    const dropdownList = document.getElementById("notifDropdownList");

    if (badge1) badge1.textContent = unreadCount;
    if (badge2) badge2.textContent = unreadCount;

    if (badge1) {
        badge1.style.display = unreadCount > 0 ? 'flex' : 'none';
    }

    if (!notifs || notifs.length === 0) {
        const emptyHtml = `
            <div class="empty-state" style="padding:1.5rem 1rem;">
                <div style="font-size:1.5rem;margin-bottom:0.5rem;">${VH.icon("bell", 26)}</div>
                <h4 style="font-size:0.85rem;margin:0 0 0.2rem;">All Caught Up!</h4>
                <p style="font-size:0.75rem;margin:0;">No new notifications right now.</p>
            </div>
        `;
        if (list) list.innerHTML = emptyHtml;
        if (dropdownList) dropdownList.innerHTML = `<p style="text-align:center;color:#94a3b8;font-size:0.78rem;padding:1rem 0.5rem;">No new notifications right now.</p>`;
        return;
    }

    if (list) {
        let html = '<div style="display:flex;flex-direction:column;gap:0.6rem;">';
        notifs.forEach(n => {
            const timeDiff = getRelativeTime(n.time, n.seconds_ago);
            html += `
                <div style="padding:0.6rem;background:${n.read ? '#fff' : '#f0f7ff'};border-radius:8px;border:1px solid ${n.read ? '#f1f5f9' : '#dbeafe'};display:flex;gap:0.6rem;align-items:flex-start;position:relative;" onclick="markSingleRead(${n.id}, this)">
                    <div style="font-size:1rem;margin-top:2px;">
                        ${notifIcon(n.type)}
                    </div>
                    <div style="flex:1">
                        <h5 style="margin:0 0 0.15rem;font-size:0.82rem;font-weight:700;color:#1e293b;">${n.title}</h5>
                        <p style="margin:0 0 0.2rem;font-size:0.75rem;color:#475569;line-height:1.35;">${n.message}</p>
                        <span style="font-size:0.65rem;color:#94a3b8;">${timeDiff}</span>
                    </div>
                    ${n.read ? '' : '<div style="width:6px;height:6px;background:#3983F6;border-radius:50%;position:absolute;top:10px;right:10px;"></div>'}
                </div>
            `;
        });
        html += '</div>';
        list.innerHTML = html;
    }

    if (dropdownList) {
        let dHtml = '';
        notifs.slice(0, 5).forEach(n => {
            const timeDiff = getRelativeTime(n.time, n.seconds_ago);
            dHtml += `
                <div class="nav-dropdown-item" style="align-items:flex-start;white-space:normal;" onclick="markSingleRead(${n.id}, this)">
                    <span style="font-size:0.95rem;">${notifIcon(n.type)}</span>
                    <span style="flex:1;min-width:0;">
                        <span style="display:block;font-weight:${n.read ? '500' : '700'};color:#1e293b;font-size:0.8rem;">${n.title}</span>
                        <span style="display:block;font-size:0.75rem;color:#475569;margin-top:2px;line-height:1.4;">${n.message}</span>
                        <span style="display:block;font-size:0.7rem;color:#94a3b8;margin-top:3px;">${timeDiff}</span>
                    </span>
                    ${n.read ? '' : '<span style="width:6px;height:6px;background:#3983F6;border-radius:50%;flex-shrink:0;margin-top:4px;"></span>'}
                </div>
            `;
        });
        dropdownList.innerHTML = dHtml;
    }
}

window.markSingleRead = async function(id, element) {
    const user = VH.auth.getUser();
    if (!user) return;
    try {
        const response = await fetch("../api/notifications.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "mark_read",
                user_id: user.id,
                id: id
            })
        });
        const res = await response.json();
        if (res.success) {
            // Re-fetch data to update counts
            fetchDashboardData(user.id);
        }
    } catch (e) {
        console.error(e);
    }
};

async function markNotificationsAsRead(userId) {
    try {
        const response = await fetch("../api/notifications.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "mark_read",
                user_id: userId
            })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success("All notifications marked as read.");
            fetchDashboardData(userId);
        } else {
            VH.toast.error("Failed to update notifications.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error updating notifications.");
    }
}

function renderActivities(activities) {
    const list = document.getElementById("activityFeed");
    if (!list) return;

    if (!activities || activities.length === 0) {
        list.innerHTML = `<p style="font-size:0.78rem;color:#94a3b8;text-align:center;margin:1rem 0;">No recent activity.</p>`;
        return;
    }

    let html = '<div class="activity-feed-list" style="display:flex;flex-direction:column;gap:0.75rem;position:relative;">';
    activities.forEach((a, idx) => {
        const rel = getRelativeTime(a.time, a.seconds_ago);
        html += `
            <div style="display:flex;gap:0.75rem;font-size:0.78rem;position:relative;">
                <div style="display:flex;flex-direction:column;align-items:center;width:12px;">
                    <div style="width:8px;height:8px;background:#3983F6;border-radius:50%;z-index:2;"></div>
                    ${idx < activities.length - 1 ? '<div style="width:1px;flex:1;background:#e2e8f0;margin-top:4px;"></div>' : ''}
                </div>
                <div style="flex:1;padding-bottom:0.6rem;">
                    <div style="color:#334155;line-height:1.35">${a.text}</div>
                    <div style="color:#94a3b8;font-size:0.7rem;margin-top:2px;">${rel}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

/*
 * "No exact time" — the client, 7 Sep 2026, on notifications minutes old that read
 * "5 hours ago". 5.5 hours is exactly IST's offset from UTC, which is the whole
 * story: `created_at` is a bare "Y-m-d H:i:s" with no timezone in it, and
 * `new Date(...)` parses that as the BROWSER's local time. Compare it to the
 * browser's "now" and you have measured the gap between MySQL's clock and the
 * viewer's, not the age of the notification.
 *
 * So the age comes from the server, measured by TIMESTAMPDIFF inside SQL where
 * both sides are the same clock. secondsAgo is authoritative; the string is only a
 * fallback for a caller that has not been given one, and it is still wrong in the
 * same way — it exists so an un-migrated payload degrades instead of throwing.
 */
function getRelativeTime(timeStr, secondsAgo) {
    let diffSecs;

    if (secondsAgo !== undefined && secondsAgo !== null && !isNaN(secondsAgo)) {
        diffSecs = Math.max(0, Math.floor(Number(secondsAgo)));
    } else {
        const prev = new Date(String(timeStr || "").replace(/-/g, "/"));
        if (isNaN(prev.getTime())) return "";
        diffSecs = Math.max(0, Math.floor((Date.now() - prev.getTime()) / 1000));
    }

    const diffMins = Math.floor(diffSecs / 60);
    const diffHrs  = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHrs / 24);

    // Singulars, because "1 days ago" was on the client's screenshot too.
    const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"} ago`;

    if (diffSecs < 60)  return "just now";
    if (diffMins < 60)  return plural(diffMins, "minute");
    if (diffHrs  < 24)  return plural(diffHrs, "hour");
    if (diffDays < 30)  return plural(diffDays, "day");
    if (diffDays < 365) return plural(Math.floor(diffDays / 30), "month");
    return plural(Math.floor(diffDays / 365), "year");
}

window.payListingFee = async function(ventureId) {
    try {
        const res = await (await fetch("../api/payments.php?action=initiate", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ purpose: "venture_listing", venture_id: ventureId })
        })).json();

        if (!res.success || !res.endpoint || !res.fields) {
            VH.toast.error(res.message || "Could not start the listing-fee payment.");
            if (res.needsProfile) {
                setTimeout(() => { window.location.href = "../users/profile.php"; }, 1800);
            }
            return;
        }

        const form = document.createElement("form");
        form.method = "POST";
        form.action = res.endpoint;
        form.style.display = "none";
        Object.keys(res.fields).forEach(name => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = res.fields[name];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error starting the listing-fee payment.");
    }
};
