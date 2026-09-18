/* VENTURES HARBOR — PAYMENT STATEMENT PAGE (wallet.js) */

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    updateHeaderAndSidebar(user);
    VH.auth.applyRoleUI(user);
    loadStatementData();

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
            const pageWrapper = document.getElementById("pageWrapper");
            if (pageWrapper) pageWrapper.classList.toggle("sidebar-collapsed");
        });
    }

    const avatarBtn = document.getElementById("avatarBtn");
    const navDropdown = document.getElementById("navDropdown");
    if (avatarBtn && navDropdown) {
        avatarBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            navDropdown.classList.toggle("open");
        });
        document.addEventListener("click", () => navDropdown.classList.remove("open"));
    }
});

function updateHeaderAndSidebar(user) {
    const sidebarAvatar = document.getElementById("sidebarAvatar");
    const sidebarUserName = document.getElementById("sidebarUserName");
    const navAvatar = document.getElementById("navAvatar");
    const navAvatarName = document.getElementById("navAvatarName");

    if (sidebarAvatar) VH.renderAvatarInto(sidebarAvatar, user);
    if (sidebarUserName) sidebarUserName.textContent = user.name;
    if (navAvatar) VH.renderAvatarInto(navAvatar, user);
    if (navAvatarName) navAvatarName.textContent = user.name.split(" ")[0];
}

async function loadStatementData() {
    try {
        const txnRes = await fetch("../api/transactions.php?action=list", { credentials: 'same-origin' }).then(r => r.json());
        renderStatement(txnRes.success ? txnRes.data : []);
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error loading your payment statement.");
    }
}

let statementCache = [];

function escAttr(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

function renderStatement(txns) {
    const tbody = document.getElementById("statementTableBody");
    const emptyEl = document.getElementById("statementEmpty");
    if (!tbody) return;

    statementCache = txns || [];
    renderRefundActionBanner();

    if (!txns || txns.length === 0) {
        tbody.innerHTML = "";
        if (emptyEl) emptyEl.classList.remove("hidden");
        return;
    }
    if (emptyEl) emptyEl.classList.add("hidden");

    const typeLabels = {
        venture_investment: "Commitment Fee",
        venture_listing_fee: "Asset Listing Fee",
        commitment_fee: "Commitment Fee",
        refund_request: "Refund Request"
    };
    const refundTypeLabels = {
        exit: "Exit refund",
        account_deletion: "Account closure refund",
        venture_cancelled: "Refund — Asset cancelled"
    };
    const statusBadge = {
        completed: 'badge--success',
        pending: 'badge--warning',
        rejected: 'badge--danger',
        waived: 'badge--gray',
        refunded: 'badge--info'
    };

    const isOutflow = t => t.type === 'venture_investment' || t.type === 'commitment_fee' || t.type === 'venture_listing_fee';

    let html = "";
    txns.forEach(t => {
        const baseLabel = t.type === 'refund_request' && refundTypeLabels[t.refundType]
            ? refundTypeLabels[t.refundType]
            : (typeLabels[t.type] || t.type);
        const desc = baseLabel + (t.ventureName ? ' — ' + t.ventureName : '');
        const sign = isOutflow(t) ? '−' : '+';
        const color = isOutflow(t) ? '#dc2626' : '#16a34a';

        const note = t.needsBankDetails
            ? `<div style="margin-top:0.3rem;">
                   <button class="btn btn--primary btn--sm" onclick="openRefundBankModal('${escAttr(t.id)}')">Add bank details</button>
               </div>`
            : (t.type === 'refund_request' && t.bankAccountLast4
                ? `<div style="font-size:0.72rem;color:#94a3b8;margin-top:0.2rem;">To account ••••${escAttr(t.bankAccountLast4)}${t.status === 'pending' ? ' · <a href="#" onclick="openRefundBankModal(\'' + escAttr(t.id) + '\');return false;">change</a>' : ''}</div>`
                : '');

        html += `
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:0.6rem 0.4rem;color:#64748b;">${t.date}</td>
                <td style="padding:0.6rem 0.4rem;">${desc}${note}</td>
                <td style="padding:0.6rem 0.4rem;text-align:right;font-weight:700;color:${color};">${sign}₹${t.amount.toLocaleString('en-IN')}</td>
                <td style="padding:0.6rem 0.4rem;"><span class="badge ${statusBadge[t.status] || 'badge--gray'}" style="font-size:0.68rem;">${t.status.toUpperCase()}</span></td>
                <td style="padding:0.6rem 0.4rem;"><a href="invoice.php?txn=${t.id}" target="_blank" title="View Invoice">${VH.icon("receipt", 16)}</a></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function renderRefundActionBanner() {
    const banner = document.getElementById("refundActionBanner");
    const text = document.getElementById("refundActionText");
    if (!banner || !text) return;

    const pending = statementCache.filter(t => t.needsBankDetails);
    banner.classList.toggle("hidden", pending.length === 0);
    if (!pending.length) return;

    const total = pending.reduce((sum, t) => sum + Number(t.amount || 0), 0);
    text.textContent = pending.length === 1
        ? `A refund of ₹${total.toLocaleString('en-IN')} has been opened for you${pending[0].ventureName ? ' (' + pending[0].ventureName + ')' : ''}, but we don't have your bank details yet. Add them on the row below and the team will transfer it.`
        : `${pending.length} refunds totalling ₹${total.toLocaleString('en-IN')} have been opened for you, but we don't have bank details for them yet. Add them on the rows below and the team will transfer them.`;
}

// ── BANK DETAILS FOR AN AUTO-OPENED REFUND ──

let currentRefundTxnId = null;

window.openRefundBankModal = function(id) {
    const t = statementCache.find(row => String(row.id) === String(id));
    if (!t) return;

    currentRefundTxnId = t.id;
    document.getElementById("refundBankIntro").textContent =
        `We're refunding your commitment fee of ₹${Number(t.amount).toLocaleString('en-IN')}`
        + (t.ventureName ? ` for ${t.ventureName}` : '')
        + '. Tell us which account to transfer it to.';

    document.getElementById("rbHolder").value = '';
    document.getElementById("rbNumber").value = '';
    document.getElementById("rbIfsc").value = '';
    document.getElementById("rbBankName").value = '';
    document.getElementById("rbNote").value = '';

    VH.modal.open("refundBankModal");
};

document.addEventListener("DOMContentLoaded", () => {
    const saveBtn = document.getElementById("rbSaveBtn");
    if (!saveBtn) return;

    saveBtn.addEventListener("click", async () => {
        if (!currentRefundTxnId) return;

        const original = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = "Saving...";
        try {
            const res = await (await fetch("../api/transactions.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "submit_refund_bank_details",
                    id: currentRefundTxnId,
                    bank_account_name: document.getElementById("rbHolder").value.trim(),
                    bank_account_number: document.getElementById("rbNumber").value.trim(),
                    bank_ifsc: document.getElementById("rbIfsc").value.trim(),
                    bank_name: document.getElementById("rbBankName").value.trim(),
                    payout_note: document.getElementById("rbNote").value.trim()
                })
            })).json();

            if (res.success) {
                VH.toast.success(res.message);
                VH.modal.close("refundBankModal");
                loadStatementData();
            } else {
                VH.toast.error(res.message || "Could not save your bank details.");
            }
        } catch (err) {
            console.error(err);
            VH.toast.error("Network error saving your bank details.");
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = original;
        }
    });
});
