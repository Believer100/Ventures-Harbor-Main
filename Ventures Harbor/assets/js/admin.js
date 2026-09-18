/* VENTURES HARBOR — ADMINISTRATOR CONTROL LOGIC (admin.js) */

let adminUsersList = [];
let adminVenturesList = [];

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    if (user.role !== 'admin') {
        VH.toast.error("Access denied. Admin permissions required.");
        setTimeout(() => {
            VH.auth.redirectToDashboard(user);
        }, 1200);
        return;
    }

    updateHeaderAndSidebar(user);
    setupAdminTabs();
    setupSidebarTabLinks();
    fetchAdminData();
    fetchRefundRequests();
    fetchCancelledVentures();
    fetchShowcaseVentures();
    fetchContactMessages();
    loadSettings();
    const settingsForm = document.getElementById("settingsForm");
    if (settingsForm) {
        settingsForm.addEventListener("submit", saveSettings);
    }

    const sidebarCollapseBtn = document.getElementById("sidebarCollapseBtn");
    const sidebar = document.getElementById("sidebar");
    if (sidebarCollapseBtn && sidebar) {
        sidebarCollapseBtn.addEventListener("click", () => {
            sidebar.classList.toggle("sidebar--collapsed");
        });
    }

    const avatarBtnEl = document.getElementById("avatarBtn");
    const navDropdown = document.getElementById("navDropdown");
    if (avatarBtnEl && navDropdown) {
        avatarBtnEl.addEventListener("click", (e) => {
            e.stopPropagation();
        return;
    }

    ventureModCache = {};
    let html = '';
    ventures.forEach(v => {
        ventureModCache[v.id] = v;
        const isActive = v.status === 'active';

        const isExpired = v.status === 'expired';
        const pendingBadge = v.pendingApplications > 0
            ? ` <span class="badge badge--warning" style="font-size:0.6rem;" title="${v.pendingApplications} application(s) awaiting the founder">${v.pendingApplications} pending</span>`
            : '';
        const statusCls = isActive ? 'badge--success' : (isExpired ? 'badge--warning' : 'badge--danger');
        const statusTitle = isActive
            ? 'Live and visible on Browse Assets'
            : (isExpired
                ? 'Listing period ended without full funding — waiting for the founder to extend it once or cancel it. It will be cancelled automatically if they do neither.'
                : 'Hidden from Browse Assets; new members cannot join');
        const actionBtn = isExpired
            ? `<button class="btn btn--sm btn--outline" disabled title="This listing has expired. Only the founder can extend or cancel it, and it will be cancelled automatically if they do neither.">Expired</button>`
            : `<button class="btn btn--sm ${isActive ? 'btn--outline' : 'btn--primary'}" onclick="toggleVentureStatus(${v.id}, '${v.status}')" title="${isActive ? 'Hide this listing from Browse Assets and block new joins' : 'Make this listing live again'}">
                            ${isActive ? 'Suspend' : 'Activate'}
                        </button>`;
        html += `
            <tr>
                <td style="font-weight:600;color:#0f172a;">${v.title}${pendingBadge}</td>
                <td style="color:#475569;">${v.founderName}</td>
                <td style="color:#475569;">${v.location || '—'}</td>
                <td style="font-weight:700;color:#3983F6;">₹${v.targetCapital.toLocaleString('en-IN')}</td>
                <td style="font-weight:700;color:#16a34a;">₹${v.raisedCapital.toLocaleString('en-IN')}</td>
                <td style="color:#475569;text-align:center;">${v.activePartners}</td>
                <td><span class="badge ${statusCls}" style="font-size:0.68rem;" title="${statusTitle}">${v.status.toUpperCase()}</span></td>
                <td>
                    <div style="display:flex;gap:0.4rem;">
                        <button class="btn btn--sm btn--secondary" onclick="viewVentureDetails('${v.id}')" title="See full details for this Asset">View</button>
                        ${actionBtn}
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

let showcaseCache = [];

async function fetchShowcaseVentures() {
    try {
        const response = await fetch(`../api/admin_actions.php?action=showcase_ventures`, { credentials: 'same-origin' });
        const res = await response.json();
        if (res.success) {
            showcaseCache = res.ventures || [];
            renderShowcaseVentures(res.max, res.slotsLeft);
            const badge = document.getElementById("showcaseBadge");
            if (badge) badge.textContent = showcaseCache.length;
            const max = document.getElementById("showcaseMax");
            if (max) max.textContent = res.max;
        }
    } catch (err) {
        console.error(err);
    }
}

function renderShowcaseVentures(max, slotsLeft) {
    const tbody = document.getElementById("showcaseTableBody");
    if (!tbody) return;

    const createBtn = document.getElementById("showcaseCreateBtn");
    if (createBtn) {
        const full = slotsLeft <= 0;
        createBtn.classList.toggle("btn--disabled", full);
        createBtn.style.pointerEvents = full ? "none" : "";
        createBtn.style.opacity = full ? "0.55" : "";
        createBtn.title = full
            ? `You already have ${max} sample listings. Delete one to free a slot.`
            : `${slotsLeft} of ${max} slots remaining`;
        createBtn.textContent = full
            ? `All ${max} slots used`
            : `+ Create Sample Listing (${slotsLeft} left)`;
    }

    if (!showcaseCache.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#94a3b8;">
            No sample listings yet. Create one to show visitors what a finished Asset looks like.</td></tr>`;
        return;
    }

    const esc = VH.card.esc;
    tbody.innerHTML = showcaseCache.map(v => `
        <tr>
            <td style="font-weight:600;color:#0f172a;">
                ${esc(v.title)}
                <span class="badge" style="font-size:0.6rem;background:#F5C518;color:#0B1B2B;margin-left:0.35rem;">SAMPLE</span>
            </td>
            <td style="color:#475569;">${esc(v.founderName || '—')}</td>
            <td style="color:#475569;">${esc(v.industry || '—')}</td>
            <td style="color:#475569;">₹${Number(v.targetCapital || 0).toLocaleString('en-IN')}</td>
            <td style="color:#475569;">${esc((v.createdAt || '').split(' ')[0] || '—')}</td>
            <td>
                <a class="btn btn--sm btn--outline" href="../pages/venture-detail.php?id=${v.id}" target="_blank" rel="noopener">View</a>
                <a class="btn btn--sm btn--secondary" href="../pages/create-venture.php?showcase=1&edit=${v.id}">Edit</a>
                <button class="btn btn--sm btn--danger" onclick="deleteShowcaseVenture(${v.id})">Delete</button>
            </td>
        </tr>`).join('');
}

window.deleteShowcaseVenture = async function(id) {
    const row = showcaseCache.find(v => String(v.id) === String(id));
    const title = row ? row.title : 'this sample listing';

    if (!confirm(`Delete the sample listing "${title}"?\n\nIt will be removed from Browse and the homepage. No refunds are involved — nobody can join a sample listing.`)) {
        return;
    }
    try {
        const response = await fetch(`../api/admin_actions.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete_showcase', id })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success(res.message || 'Sample listing deleted.');
            fetchShowcaseVentures();
            fetchAdminData();
        } else {
            VH.toast.error(res.message || 'Could not delete that sample listing.');
        }
    } catch (err) {
        console.error(err);
        VH.toast.error('Network error deleting the sample listing.');
    }
};

let cancelledVenturesCache = [];

async function fetchCancelledVentures() {
    try {
        const response = await fetch(`../api/admin_actions.php?action=cancelled_ventures`, { credentials: 'same-origin' });
        const res = await response.json();
        if (res.success) {
            cancelledVenturesCache = res.ventures || [];
            renderCancelledVentures();
            const badge = document.getElementById("cancelledBadge");
            if (badge) badge.textContent = cancelledVenturesCache.length;
        }
    } catch (err) {
        console.error(err);
    }
}

function renderCancelledVentures() {
    const tbody = document.getElementById("cancelledTableBody");
    if (!tbody) return;

    if (!cancelledVenturesCache.length) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:1.5rem;color:#94a3b8;">No Assets have been cancelled.</td></tr>`;
        return;
    }

    const byLabel = {
        founder: '<span class="badge badge--gray" style="font-size:0.62rem;" title="The founder deleted this Asset themselves">Deleted by founder</span>',
        system:  '<span class="badge badge--warning" style="font-size:0.62rem;" title="Closed automatically when the listing period ran out without full funding">Closed automatically</span>',
        admin:   '<span class="badge badge--danger" style="font-size:0.62rem;">Closed by admin</span>'
    };

    tbody.innerHTML = cancelledVenturesCache.map(v => {
        let refundCell;
        if (v.refundCount === 0) {
            refundCell = '<span style="color:#94a3b8;font-size:0.75rem;">No fees paid — nothing to refund</span>';
        } else {
            const parts = [];
            if (v.refundAwaitingBank > 0) {
                parts.push(`<span class="badge badge--warning" style="font-size:0.6rem;" title="Partner has not supplied bank details yet">${v.refundAwaitingBank} awaiting bank details</span>`);
            }
            const readyToPay = v.refundPending - v.refundAwaitingBank;
            if (readyToPay > 0) {
                parts.push(`<span class="badge badge--info" style="font-size:0.6rem;" title="Bank details on file — ready to transfer">${readyToPay} ready to pay</span>`);
            }
            if (v.refundCompleted > 0) {
                parts.push(`<span class="badge badge--success" style="font-size:0.6rem;">${v.refundCompleted} paid</span>`);
            }
            if (v.refundRejected > 0) {
                parts.push(`<span class="badge badge--danger" style="font-size:0.6rem;">${v.refundRejected} rejected</span>`);
            }
            refundCell = `<div style="display:flex;flex-wrap:wrap;gap:0.25rem;margin-bottom:0.25rem;">${parts.join('')}</div>
                          <div style="font-size:0.72rem;color:#64748b;">₹${v.refundTotal.toLocaleString('en-IN')} across ${v.refundCount} partner(s)</div>`;
        }

        const extendedNote = v.extensionCount > 0
            ? '<div style="font-size:0.68rem;color:#94a3b8;margin-top:0.15rem;">Was extended once</div>'
            : '';

        return `
            <tr>
                <td style="font-weight:600;color:#0f172a;">${escHtml(v.title)}
                    <div style="font-size:0.72rem;color:#94a3b8;font-weight:400;">${escHtml(v.industry || '—')} · ${escHtml(v.location || '—')}</div>${extendedNote}
                </td>
                <td style="color:#475569;">${escHtml(v.founderName || '—')}</td>
                <td style="color:#475569;">₹${v.raisedCapital.toLocaleString('en-IN')} of ₹${v.targetCapital.toLocaleString('en-IN')}
                    <div style="font-size:0.72rem;color:#94a3b8;">${v.progressPercent}% funded</div>
                </td>
                <td style="color:#475569;text-align:center;">${v.totalMembers}</td>
                <td style="color:#64748b;font-size:0.8rem;">${escHtml(v.cancelledAt || '—')}<div style="margin-top:0.2rem;">${byLabel[v.cancelledBy] || ''}</div></td>
                <td style="color:#64748b;font-size:0.78rem;max-width:220px;">${escHtml(v.cancelReason || '—')}</td>
                <td style="max-width:230px;">${refundCell}</td>
                <td>
                    <div style="display:flex;gap:0.4rem;">
                        <a class="btn btn--sm btn--secondary" href="../pages/venture-detail.php?id=${encodeURIComponent(v.id)}" target="_blank" title="Open the Asset page">View</a>
                        ${v.refundCount > 0
                            ? `<button class="btn btn--sm btn--outline" onclick="showCancelledRefunds('${escHtml(v.id)}')" title="Jump to this Asset's refund rows">Refunds</button>`
                            : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

window.showCancelledRefunds = function(ventureId) {
    activateTab('refundRequests');
    document.querySelectorAll('.sidebar-nav-item[data-tab-link]').forEach(l => {
        l.classList.toggle('active', l.dataset.tabLink === 'refundRequests');
    });

    currentRefundFilter = 'venture_cancelled';
    document.querySelectorAll("[data-refund-filter]").forEach(b => {
        b.classList.toggle("is-active", b.dataset.refundFilter === 'venture_cancelled');
    });
    renderRefundRequests();

    const venture = cancelledVenturesCache.find(v => String(v.id) === String(ventureId));
    if (venture) {
        VH.toast.info
            ? VH.toast.info(`Showing cancellation refunds — ${venture.title} has ${venture.refundCount} of them.`)
            : VH.toast.success(`Showing cancellation refunds — ${venture.title} has ${venture.refundCount} of them.`);
    }
};

let ventureModCache = {};

window.viewVentureDetails = function(id) {
    const v = ventureModCache[id];
    if (!v) return;

    const money = n => '₹' + Number(n || 0).toLocaleString('en-IN');
    const partnerLabel = { active: 'Active partners only', silent: 'Silent partners only', both: 'Active & Silent' }[v.partnerTypes] || (v.partnerTypes || '—');
    const created = v.createdAt ? new Date(v.createdAt.replace(' ', 'T')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
    const deadline = v.applicationDeadline ? new Date(v.applicationDeadline).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : 'None';
    const statusBadge = v.status === 'active'
        ? '<span class="badge badge--success" style="font-size:0.68rem;">ACTIVE</span>'
        : '<span class="badge badge--danger" style="font-size:0.68rem;">SUSPENDED</span>';

    const row = (label, value) =>
        `<div style="display:flex;justify-content:space-between;gap:1rem;padding:0.55rem 0;border-bottom:1px solid #f1f5f9;">
            <span style="color:#64748b;font-size:0.85rem;">${label}</span>
            <span style="color:#0f172a;font-size:0.88rem;font-weight:600;text-align:right;">${value}</span>
         </div>`;

    const body = document.getElementById("ventureDetailBody");
    if (body) {
        body.innerHTML = `
            <div style="margin-bottom:1rem;">
                <div style="font-family:'Outfit',sans-serif;font-size:1.15rem;font-weight:800;color:#0f172a;">${v.title}</div>
                <div style="color:#64748b;font-size:0.85rem;margin-top:0.2rem;">${v.industry || '—'} · ${v.location || '—'} &nbsp; ${statusBadge}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1.5rem;">
                <div>
                    <p style="font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;margin:0.5rem 0 0.2rem;">Partners</p>
                    ${row('Total members', v.totalMembers)}
                    ${row('Active partners', v.activePartners)}
                    ${row('Silent partners', v.silentPartners)}
                    ${row('Pending applications', v.pendingApplications)}
                    ${row('Accepts', partnerLabel)}
                </div>
                <div>
                    <p style="font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;margin:0.5rem 0 0.2rem;">Funding & timeline</p>
                    ${row('Funding goal', money(v.targetCapital))}
                    ${row('Current funding', money(v.raisedCapital))}
                    ${row('Progress', (v.progressPercent || 0) + '%')}
                    ${row('Min. investment', money(v.minInvestment))}
                    ${row('Days left', v.daysLeft)}
                </div>
            </div>
            <p style="font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;margin:1rem 0 0.2rem;">Founder & listing</p>
            ${row('Founder', v.founderName)}
            ${row('Listing created', created)}
            ${row('Application deadline', deadline)}
        `;
    }
    VH.modal.open("ventureDetailModal");
};

window.toggleVentureStatus = async function(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    try {
        const response = await fetch("../api/admin_actions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "venture_status",
                id: id,
                status: newStatus
            })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success(`Asset status updated to ${newStatus.toUpperCase()}.`);
            fetchAdminData();
        } else {
            VH.toast.error(res.message || "Failed to update Asset status.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error toggle Asset listing.");
    }
};

function renderUserTable(users) {
    const body = document.getElementById("userTableBody");
    if (!body) return;

    if (!users || users.length === 0) {
        body.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#94a3b8;">No members registered yet.</td></tr>`;
        return;
    }

    let html = "";
    users.forEach(u => {
        // A deleted account is still listed — it holds ventures, memberships and
        // money records. Nothing else in this table distinguished it, so an
        // admin could not tell why the person "cannot log in".
        const deletedTag = u.isDeleted
            ? ` <span class="badge badge--danger" style="font-size:0.6rem;vertical-align:middle;">DELETED</span>`
            : '';

        // The email shown is the real one (api/admin_actions.php reads
        // previous_email in preference to the placeholder), so this note is the
        // only thing saying the address itself now belongs to somebody else.
        const releasedNote = u.emailReleased
            ? `<br/><span style="font-size:0.68rem;color:#94a3b8;">address since taken by a new signup</span>`
            : '';

        html += `
            <tr${u.isDeleted ? ' style="background:#FEF6F6;"' : ''}>
                <td>
                    <div style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;" onclick="openAdminUserProfile('${u.id}')" title="View full profile">
                        <div class="nav-avatar" style="width:28px;height:28px;font-size:0.68rem;">${u.avatar}</div>
                        <div style="font-weight:600;color:#3983F6;">${u.name}${deletedTag}</div>
                    </div>
                </td>
                <td style="color:#475569;">${u.occupation || 'N/A'} <br/><span style="font-size:0.72rem;color:#94a3b8;">${u.city || 'India'}</span></td>
                <td style="color:#64748b;font-size:0.8rem;">${u.email}${releasedNote}</td>
                <td style="color:#475569;text-align:center;">${u.venturesJoined}</td>
                <td style="font-weight:700;color:#3983F6;">₹${u.totalInvested.toLocaleString('en-IN')}</td>
                <td>${u.isDeleted
                    ? `<button class="btn btn--secondary btn--sm" onclick="restoreAccount('${u.id}', ${JSON.stringify(u.name)})">Restore</button>`
                    : '<span style="color:#CBD5E1;">—</span>'}</td>
            </tr>
        `;
    });
    body.innerHTML = html;
}

/**
 * Undo an account deletion. The only route back: nothing a visitor does can
 * restore a deleted account, because signing up on its address now creates a
 * separate empty one.
 *
 * When the address has already been claimed the server refuses and says so, and
 * the admin is asked for a different one — restoring an account to an email
 * somebody else owns would just make an account nobody can log into.
 */
window.restoreAccount = async function (userId, name, emailOverride) {
    if (!emailOverride && !confirm(`Restore ${name}'s account?\n\nThey will be able to sign in again, and everything they were part of comes back with them.`)) {
        return;
    }

    try {
        const res = await fetch("../api/admin_actions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action: "restore_account", user_id: userId, email: emailOverride || "" })
        }).then(r => r.json());

        if (res.success) {
            VH.toast.success(res.message || "Account restored.");
            fetchAdminData();
            return;
        }

        if (res.emailTaken) {
            const wanted = prompt(`${res.message}\n\nNew email address for ${name}:`, "");
            if (wanted && wanted.trim()) {
                window.restoreAccount(userId, name, wanted.trim());
            }
            return;
        }

        VH.toast.error(res.message || "Could not restore the account.");
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error restoring the account.");
    }
};

window.openAdminUserProfile = async function (userId) {
    if (!userId) return;
    buildAdminUserModal();
    const body = document.getElementById("adminUserBody");
    if (body) body.innerHTML = `<p style="text-align:center;color:#94a3b8;padding:2rem 0;">Loading profile…</p>`;
    VH.modal.open("adminUserModal");

    try {
        const res = await fetch(`../api/profile.php?action=admin_profile&id=${userId}`, { credentials: 'same-origin' }).then(r => r.json());
        if (!res.success || !res.user) {
            if (body) body.innerHTML = `<p style="text-align:center;color:#ef4444;padding:2rem 0;">${res.message || 'Could not load this profile.'}</p>`;
            return;
        }
        renderAdminUserModal(res.user);
    } catch (err) {
        console.error(err);
        if (body) body.innerHTML = `<p style="text-align:center;color:#ef4444;padding:2rem 0;">Network error loading profile.</p>`;
    }
};

function buildAdminUserModal() {
    if (document.getElementById("adminUserModal")) return;
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'adminUserModal';
    modal.innerHTML = `
        <div class="modal" style="max-width:480px">
            <button class="modal-close" onclick="VH.modal.close('adminUserModal')">×</button>
            <div class="modal-header"><h3 class="modal-title">User Profile</h3></div>
            <div id="adminUserBody" style="padding:0 1.5rem 1.5rem"></div>
        </div>`;
    document.body.appendChild(modal);
}

function renderAdminUserModal(u) {
    const body = document.getElementById("adminUserBody");
    if (!body) return;
    const base = VH.getBasePath();
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const avatarHtml = u.avatarUrl
        ? `<img src="${u.avatarUrl.startsWith('http') ? u.avatarUrl : base + u.avatarUrl}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
        : (u.avatar || (u.name || '?').slice(0, 2).toUpperCase());

    const skills = (u.skills || '').split(',').map(s => s.trim()).filter(Boolean);
    const roleLabel = u.role ? (u.role.charAt(0).toUpperCase() + u.role.slice(1)) : '';

    const row = (label, value) => value
        ? `<div style="display:flex;justify-content:space-between;gap:1rem;padding:0.5rem 0;border-bottom:1px solid #f1f5f9;">
               <span style="color:#64748b;font-size:0.85rem;">${label}</span>
               <span style="color:#0f172a;font-size:0.88rem;font-weight:600;text-align:right;word-break:break-word;">${esc(value)}</span>
           </div>`
        : '';

    const socials = [
        u.linkedinUrl ? `<a href="${esc(u.linkedinUrl)}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("link", 14)} LinkedIn</a>` : '',
        u.twitterUrl ? `<a href="${esc(u.twitterUrl)}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("globe", 14)} Twitter</a>` : '',
        u.instagramUrl ? `<a href="${esc(u.instagramUrl)}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("image", 14)} Instagram</a>` : '',
        u.websiteUrl ? `<a href="${esc(u.websiteUrl)}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("globe", 14)} Website</a>` : ''
    ].filter(Boolean).join(' &middot; ');

    const rowRaw = (label, htmlValue) =>
        `<div style="display:flex;justify-content:space-between;gap:1rem;padding:0.5rem 0;border-bottom:1px solid #f1f5f9;">
             <span style="color:#64748b;font-size:0.85rem;">${label}</span>
             <span style="color:#0f172a;font-size:0.88rem;font-weight:600;text-align:right;">${htmlValue}</span>
         </div>`;
    const money = n => '₹' + Number(n || 0).toLocaleString('en-IN');
    const kyc = u.kycStatus || 'pending';
    const kycBadge = `<span class="badge ${kyc === 'verified' ? 'badge--success' : 'badge--warning'}" style="font-size:0.68rem;">${esc(kyc).toUpperCase()}</span>`;
    const acctBadge = u.isDeactivated
        ? '<span class="badge badge--danger" style="font-size:0.68rem;">DEACTIVATED</span>'
        : '<span class="badge badge--success" style="font-size:0.68rem;">ACTIVE</span>';
    const joined = u.createdAt ? new Date(String(u.createdAt).replace(' ', 'T')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

    body.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:0.3rem;margin-bottom:1rem;">
            <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#173463,#3983F6);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.4rem;">${avatarHtml}</div>
            <h3 style="margin:0.4rem 0 0;">${esc(u.name || '')}</h3>
            <div style="color:#64748b;font-size:0.85rem;">${esc(u.occupation) || (roleLabel ? roleLabel + ' Member' : 'Member')}${u.city ? ' · ' + VH.icon('pin', 12) + ' ' + esc(u.city) : ''}</div>
        </div>
        ${u.bio ? `<p style="color:#334155;font-size:0.88rem;line-height:1.5;margin:0 0 1rem;">${esc(u.bio)}</p>` : ''}
        <p style="font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;margin:0.5rem 0 0.2rem;">Contact</p>
        ${row('Email', u.email)}
        ${row('Phone', u.phone)}
        ${row('Age', u.age)}
        ${row('City', u.city)}
        <p style="font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;margin:1rem 0 0.2rem;">Account</p>
        ${rowRaw('KYC status', kycBadge)}
        ${rowRaw('Account status', acctBadge)}
        ${row('Wallet balance', money(u.walletBalance))}
        ${row('Joined', joined)}
        ${row('Assets joined', String(u.venturesJoined))}
        ${row('Capital invested', money(u.totalInvested))}
        ${skills.length ? `<div style="margin-top:1rem;"><div style="font-weight:600;font-size:0.8rem;color:#0f172a;margin-bottom:0.4rem;">Skills</div><div style="display:flex;flex-wrap:wrap;gap:0.35rem;">${skills.map(s => `<span class="badge badge--info" style="font-size:0.72rem;">${esc(s)}</span>`).join('')}</div></div>` : ''}
        ${u.pastExperience ? `<div style="margin-top:1rem;"><div style="font-weight:600;font-size:0.8rem;color:#0f172a;margin-bottom:0.4rem;">Past Experience</div><p style="color:#64748b;font-size:0.85rem;line-height:1.5;margin:0;">${esc(u.pastExperience)}</p></div>` : ''}
        ${socials ? `<div style="margin-top:1rem;color:#3983F6;font-size:0.82rem;text-align:center;">${socials}</div>` : ''}
    `;
}

window.filterUserTable = function() {
    const query = document.getElementById("userSearchInput").value.toLowerCase().trim();
    if (!query) {
        renderUserTable(adminUsersList);
        return;
    }
    const filtered = adminUsersList.filter(u =>
        u.name.toLowerCase().includes(query) ||
        u.email.toLowerCase().includes(query) ||
        (u.city && u.city.toLowerCase().includes(query)) ||
        (u.occupation && u.occupation.toLowerCase().includes(query))
    );
    renderUserTable(filtered);
};

let refundRequestsCache = [];
let currentRefundFilter = 'all';

function escHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

const REFUND_TYPE_META = {
    account_deletion:  { label: 'Account Deletion',  icon: VH.icon('trash', 14), cls: 'refund-type--deletion' },
    exit:              { label: 'Asset Exit',      icon: VH.icon('door', 14), cls: 'refund-type--exit' },
    venture_cancelled: { label: 'Asset Closed', icon: VH.icon('ban', 14), cls: 'refund-type--cancelled' }
};

async function fetchRefundRequests() {
    try {
        const response = await fetch(`../api/admin_actions.php?action=refund_requests`, { credentials: 'same-origin' });
        const res = await response.json();
        if (res.success) {
            refundRequestsCache = res.refunds || [];
            renderRefundRequests();

            const badge = document.getElementById("refundsBadge");
            if (badge) badge.textContent = refundRequestsCache.filter(r => r.status === 'pending').length;

            const countAll = document.getElementById("refundCountAll");
            const countDeletion = document.getElementById("refundCountDeletion");
            const countExit = document.getElementById("refundCountExit");
            const countCancelled = document.getElementById("refundCountCancelled");
            const deletionCount = refundRequestsCache.filter(r => r.refund_type === 'account_deletion').length;
            if (countAll) countAll.textContent = refundRequestsCache.length;
            if (countDeletion) countDeletion.textContent = deletionCount;
            if (countExit) countExit.textContent = refundRequestsCache.filter(r => r.refund_type === 'exit').length;
            if (countCancelled) countCancelled.textContent = refundRequestsCache.filter(r => r.refund_type === 'venture_cancelled').length;

            const deletionChip = countDeletion
                ? countDeletion.closest('.refund-filter')
                : null;
            if (deletionChip) {
                deletionChip.classList.toggle('hidden', deletionCount === 0);

                if (deletionCount === 0 && deletionChip.classList.contains('is-active')) {
                    const allChip = document.querySelector('.refund-filter[data-refund-filter="all"]');
                    if (allChip) allChip.click();
                }
            }
        }
    } catch (err) {
        console.error(err);
    }
}

function renderRefundRequests() {
    const tbody = document.getElementById("refundsTableBody");
    if (!tbody) return;

    const hint = document.getElementById("refundFilterHint");
    if (hint) {
        const hints = {
            account_deletion: 'Users who deleted their account and asked for their paid commitment fees back. Pay out to the bank account shown — there is no Asset or meeting involved.',
            exit: 'Users who left an Asset. PENDING means they quit within 24 hours of the meetup, so the full commitment fee is refundable. WAIVED means they chose to exit without a refund — nothing to pay.',
            venture_cancelled: 'Opened automatically when an Asset was closed — because the founder deleted it, or because its listing period ran out. The partner never had to ask. Rows marked AWAITING BANK DETAILS have nowhere to pay yet; the partner has been asked to add them and they cannot be completed until they do.',
            all: 'All refund requests across every flow.'
        };
        hint.textContent = hints[currentRefundFilter] || hints.all;
    }

    const refunds = currentRefundFilter === 'all'
        ? refundRequestsCache
        : refundRequestsCache.filter(r => r.refund_type === currentRefundFilter);

    if (!refunds || refunds.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:1.5rem;color:#94a3b8;">No refund requests in this category.</td></tr>`;
        return;
    }

    const statusBadge = {
        pending: 'badge--warning',
        completed: 'badge--success',
        rejected: 'badge--danger',
        waived: 'badge--gray'
    };

    tbody.innerHTML = refunds.map(r => {
        const meta = REFUND_TYPE_META[r.refund_type] || REFUND_TYPE_META.exit;
        const isWaived = r.status === 'waived';

        const context = r.refund_type === 'account_deletion'
            ? '<span style="color:#94a3b8;">Account closure</span>'
            : escHtml(r.venture_name || '—');

        const bank = r.bank_account_number
            ? `<div style="font-size:0.75rem;line-height:1.45;">
                   <div style="color:#0f172a;font-weight:600;">${escHtml(r.bank_account_name)}</div>
                   <div style="color:#64748b;">A/C ${escHtml(r.bank_account_number)}</div>
                   <div style="color:#94a3b8;">${escHtml(r.bank_ifsc)}${r.bank_name ? ' · ' + escHtml(r.bank_name) : ''}</div>
               </div>`
            : (r.awaiting_bank_details
                ? `<span class="badge badge--warning" style="font-size:0.62rem;" title="The partner has been asked to add their bank details from their Payment Statement page. This refund cannot be marked paid until they do.">Awaiting bank details</span>`
                : (r.payout_note
                    ? `<span style="color:#64748b;font-size:0.75rem;">${escHtml(r.payout_note)}</span>`
                    : '<span style="color:#cbd5e1;">— not required —</span>'));

        const amountCell = isWaived
            ? `<span style="color:#94a3b8;font-size:0.78rem;">₹${Number(r.fee_amount).toLocaleString('en-IN')} forfeited</span>`
            : `<span style="font-weight:700;color:#3983F6;">₹${Number(r.amount).toLocaleString('en-IN')}</span>`;

        return `
            <tr>
                <td style="font-weight:600;color:#0f172a;">${escHtml(r.userName)}<div style="font-size:0.72rem;color:#94a3b8;font-weight:400;">${escHtml(r.userEmail)}</div></td>
                <td><span class="refund-type-badge ${meta.cls}">${meta.icon} ${meta.label}</span></td>
                <td style="color:#475569;font-size:0.8rem;">${context}</td>
                <td>${amountCell}</td>
                <td style="color:#64748b;font-size:0.8rem;">${escHtml(r.created_at)}</td>
                <td style="max-width:220px;">${bank}</td>
                <td><span class="badge ${statusBadge[r.status] || 'badge--gray'}" style="font-size:0.68rem;">${escHtml(r.status.toUpperCase())}</span></td>
                <td><button class="btn btn--outline btn--sm" onclick="openRefundModal('${escHtml(r.id)}')">${isWaived ? 'View' : 'Manage'}</button></td>
            </tr>
        `;
    }).join('');
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-refund-filter]").forEach(btn => {
        btn.addEventListener("click", () => {
            currentRefundFilter = btn.dataset.refundFilter;
            document.querySelectorAll("[data-refund-filter]").forEach(b => b.classList.toggle("is-active", b === btn));
            renderRefundRequests();
        });
    });
});

let contactMessagesCache = [];
let currentContactMsgId = null;

async function fetchContactMessages() {
    try {
        const response = await fetch(`../api/admin_actions.php?action=contact_messages`, { credentials: 'same-origin' });
        const res = await response.json();
        if (res.success) {
            contactMessagesCache = res.messages;
            renderContactMessages(res.messages);
            const badge = document.getElementById("contactMsgBadge");
            if (badge) badge.textContent = res.messages.filter(m => m.status === 'new').length;
        }
    } catch (err) {
        console.error(err);
    }
}

function renderContactMessages(messages) {
    const tbody = document.getElementById("contactMsgTableBody");
    if (!tbody) return;

    if (!messages || messages.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#94a3b8;">No contact messages yet.</td></tr>`;
        return;
    }

    const statusBadge = { new: 'badge--danger', read: 'badge--warning', replied: 'badge--success' };

    let html = '';
    messages.forEach(m => {
        html += `
            <tr>
                <td style="font-weight:600;color:#0f172a;">${m.name}<div style="font-size:0.72rem;color:#94a3b8;font-weight:400;">${m.email}</div></td>
                <td style="color:#475569;font-size:0.82rem;">${m.subject || '—'}</td>
                <td style="color:#64748b;font-size:0.78rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${m.message}</td>
                <td style="color:#64748b;font-size:0.8rem;">${m.created_at}</td>
                <td><span class="badge ${statusBadge[m.status] || 'badge--gray'}" style="font-size:0.68rem;">${m.status.toUpperCase()}</span></td>
                <td><button class="btn btn--outline btn--sm" onclick="openContactMsgModal(${m.id})">View / Manage</button></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

window.openContactMsgModal = function(id) {
    const msg = contactMessagesCache.find(m => String(m.id) === String(id));
    if (!msg) return;
    currentContactMsgId = id;

    document.getElementById("cmModalName").textContent = msg.name;
    document.getElementById("cmModalEmail").textContent = msg.email;
    document.getElementById("cmModalSubject").textContent = msg.subject || '(no subject)';
    document.getElementById("cmModalDate").textContent = msg.created_at;
    document.getElementById("cmModalMessage").textContent = msg.message;
    document.getElementById("cmModalStatus").value = msg.status;
    document.getElementById("cmModalNotes").value = msg.admin_notes || '';
    VH.modal.open("contactMsgModal");
};

document.addEventListener("DOMContentLoaded", () => {
    const cmSaveBtn = document.getElementById("cmModalSaveBtn");
    if (cmSaveBtn) {
        cmSaveBtn.addEventListener("click", async () => {
            const originalLabel = cmSaveBtn.textContent;
            cmSaveBtn.textContent = "Saving...";
            cmSaveBtn.disabled = true;

            try {
                const response = await fetch("../api/admin_actions.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action: "update_contact_message",
                        id: currentContactMsgId,
                        status: document.getElementById("cmModalStatus").value,
                        admin_notes: document.getElementById("cmModalNotes").value.trim()
                    })
                });
                const res = await response.json();
                if (res.success) {
                    VH.toast.success("Message updated.");
                    VH.modal.close("contactMsgModal");
                    fetchContactMessages();
                } else {
                    VH.toast.error(res.message || "Failed to update message.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error updating message.");
            } finally {
                cmSaveBtn.textContent = originalLabel;
                cmSaveBtn.disabled = false;
            }
        });
    }
});

let currentRefundRequestId = null;

window.openRefundModal = function(id) {
    const r = refundRequestsCache.find(row => String(row.id) === String(id));
    if (!r) return;

    currentRefundRequestId = r.id;
    const meta = REFUND_TYPE_META[r.refund_type] || REFUND_TYPE_META.exit;
    const isDeletion = r.refund_type === 'account_deletion';
    const isWaived = r.status === 'waived';
    const isAwaitingBank = !!r.awaiting_bank_details;

    const banner = document.getElementById("refundModalTypeBanner");
    banner.textContent = `${meta.icon} ${meta.label}`;
    banner.className = `refund-modal-type ${meta.cls}`;

    document.getElementById("refundModalUser").textContent = r.userName + ' · ' + r.userEmail;
    document.getElementById("refundModalContext").textContent = isDeletion
        ? 'Account closure — all commitment fees paid'
        : (r.refund_type === 'venture_cancelled'
            ? (r.venture_name || '—') + ' (closed venture)'
            : (r.venture_name || '—'));
    document.getElementById("refundModalAmount").textContent = isWaived
        ? '₹0 — fee of ₹' + Number(r.fee_amount).toLocaleString('en-IN') + ' forfeited by the user'
        : '₹' + Number(r.amount).toLocaleString('en-IN');

    document.getElementById("refundModalBankBlock").classList.toggle("hidden", isWaived || isAwaitingBank);
    document.getElementById("refundModalAwaitingBlock").classList.toggle("hidden", isWaived || !isAwaitingBank);
    document.getElementById("refundModalWaivedBlock").classList.toggle("hidden", !isWaived);
    document.getElementById("refundModalActionFields").classList.toggle("hidden", isWaived);
    document.getElementById("refundModalSaveBtn").classList.toggle("hidden", isWaived);

    const statusSelect = document.getElementById("refundModalStatus");
    const completedOption = statusSelect ? statusSelect.querySelector('option[value="completed"]') : null;
    if (completedOption) {
        completedOption.disabled = isAwaitingBank;
        completedOption.textContent = isAwaitingBank
            ? 'Completed — unavailable until bank details are provided'
            : 'Completed — paid offline';
    }

    if (!isWaived) {
        document.getElementById("refundModalBankHolder").textContent = r.bank_account_name || '—';
        document.getElementById("refundModalBankNumber").textContent = r.bank_account_number || '—';
        document.getElementById("refundModalBankIfsc").textContent = r.bank_ifsc || '—';
        document.getElementById("refundModalBankName").textContent = r.bank_name || '—';

        const noteRow = document.getElementById("refundModalPayoutNoteRow");
        noteRow.classList.toggle("hidden", !r.payout_note);
        document.getElementById("refundModalPayoutNote").textContent = r.payout_note || '';

        document.getElementById("refundModalStatus").value = r.status;
        document.getElementById("refundModalNotes").value = r.admin_notes || '';
    }

    VH.modal.open("refundModal");
};

document.addEventListener("DOMContentLoaded", () => {
    const saveBtn = document.getElementById("refundModalSaveBtn");
    if (saveBtn) {
        saveBtn.addEventListener("click", async () => {
            const originalLabel = saveBtn.textContent;
            saveBtn.textContent = "Saving...";
            saveBtn.disabled = true;

            try {
                const response = await fetch("../api/admin_actions.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action: "update_refund_request",
                        id: currentRefundRequestId,
                        status: document.getElementById("refundModalStatus").value,
                        admin_notes: document.getElementById("refundModalNotes").value.trim()
                    })
                });
                const res = await response.json();
                if (res.success) {
                    VH.toast.success("Refund request updated and user notified.");
                    VH.modal.close("refundModal");
                    fetchRefundRequests();
                    fetchAdminData();
                } else {
                    VH.toast.error(res.message || "Failed to update refund request.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error updating refund request.");
            } finally {
                saveBtn.textContent = originalLabel;
                saveBtn.disabled = false;
            }
        });
    }
});

// ── SETTINGS ──

async function loadSettings() {
    try {
        const response = await fetch(`../api/admin_actions.php?action=settings`, { credentials: 'same-origin' });
        const res = await response.json();
        if (!res.success) return;

        const s = res.settings || {};
        setVal("setSiteName", s.site_name);
        setVal("setSupportEmail", s.support_email);
        setVal("setFeePercent", s.commitment_fee_percent);
        setVal("setPayuEnabled", s.payu_enabled);
        setVal("setPayuMode", s.payu_mode);
    } catch (err) {
        console.error(err);
    }
}

function setVal(id, value) {
    const el = document.getElementById(id);
    if (el && value !== undefined && value !== null) el.value = value;
}

async function saveSettings(e) {
    e.preventDefault();
    const btn = e.target.querySelector("button[type='submit']");
    const originalLabel = btn.textContent;
    btn.textContent = "Saving...";
    btn.disabled = true;

    const settings = {
        site_name: document.getElementById("setSiteName").value.trim(),
        support_email: document.getElementById("setSupportEmail").value.trim(),
        commitment_fee_percent: document.getElementById("setFeePercent").value,
        payu_enabled: document.getElementById("setPayuEnabled").value,
        payu_mode: document.getElementById("setPayuMode").value
    };

    if (settings.payu_enabled === "1" && settings.payu_mode === "live") {
        if (!confirm("Enable PayU in LIVE mode?\n\nPartners will be charged real money for their commitment fee from now on. Make sure the live Key and Salt are configured in config/payu.php on this server first.")) {
            btn.textContent = originalLabel;
            btn.disabled = false;
            return;
        }
    }

    try {
        const response = await fetch("../api/admin_actions.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action: "update_settings", settings })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success("Settings saved successfully.");
        } else {
            VH.toast.error(res.message || "Failed to save settings.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error saving settings.");
    } finally {
        btn.textContent = originalLabel;
        btn.disabled = false;
    }
}

