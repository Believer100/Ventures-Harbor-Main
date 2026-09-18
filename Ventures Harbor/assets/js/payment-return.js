/* VENTURES HARBOR — PAYMENT RESULT PAGE (payment-return.js) */

document.addEventListener("DOMContentLoaded", async () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    const navAvatar = document.getElementById("navAvatar");
    if (navAvatar) VH.renderAvatarInto(navAvatar, user);

    const params = new URLSearchParams(window.location.search);
    const txnId = params.get("txnid") || "";

    if (!txnId) {
        renderState({ state: "invalid" });
        return;
    }

    try {
        const res = await fetch(`../api/payments.php?action=status&txnid=${encodeURIComponent(txnId)}`,
            { credentials: "same-origin" }).then(r => r.json());

        if (!res.success) {
            renderState({ state: "invalid", txnId });
            return;
        }
        renderState({ state: res.payment.status, payment: res.payment, txnId });
    } catch (err) {
        console.error(err);
        renderState({ state: "unknown", txnId });
    }
});

function rupees(n) {
    return "₹" + Number(n || 0).toLocaleString("en-IN");
}

function renderState({ state, payment, txnId }) {
    const card = document.getElementById("prCard");
    const icon = document.getElementById("prIcon");
    const title = document.getElementById("prTitle");
    const message = document.getElementById("prMessage");
    const details = document.getElementById("prDetails");
    const note = document.getElementById("prNote");
    const actions = document.getElementById("prActions");

    const dashboard = `<a href="../admin/dashboard.php" class="btn btn--primary">Go to Dashboard</a>`;
    const browse = `<a href="browse.php" class="btn btn--secondary">Browse Assets</a>`;
    const statement = `<a href="../users/wallet.php" class="btn btn--secondary">Payment Statement</a>`;

    let cls = "", noteText = "";

    if (state === "completed") {
        cls = "pr-card--success";
        icon.innerHTML = VH.icon("check", 34);
        title.textContent = "Payment successful";
        message.textContent = "Your commitment fee has been received and you're now a partner in this Asset.";
        actions.innerHTML = payment
            ? `<a href="venture-detail.php?id=${payment.ventureId}" class="btn btn--primary">View Asset</a>${dashboard}`
            : dashboard;

        noteText = payment
            ? `Only the commitment fee of ${rupees(payment.feeAmount)} was charged online. Your pledged investment of ${rupees(payment.investedAmount)} is recorded for tracking and is settled directly with the founder.`
            : "";
    } else if (state === "created") {
        cls = "pr-card--pending";
        icon.innerHTML = VH.icon("hourglass", 34);
        title.textContent = "Payment still processing";
        message.textContent = "Your bank hasn't confirmed this payment yet. It usually settles within a few minutes.";
        actions.innerHTML = statement + dashboard;
        noteText = "You have not been made a partner yet. Don't pay again — if the amount was debited it will either complete shortly or be returned by your bank. Check your Payment Statement in a few minutes.";
    } else if (state === "failed") {
        cls = "pr-card--failed";
        icon.textContent = "✕";
        title.textContent = "Payment not completed";
        message.textContent = (payment && payment.errorMessage)
            ? payment.errorMessage
            : "The payment was cancelled or declined, so nothing has been charged.";
        actions.innerHTML = payment
            ? `<a href="join-venture.php?id=${payment.ventureId}" class="btn btn--primary">Try Again</a>${browse}`
            : browse;
        noteText = "No commitment fee has been taken and you have not joined the Asset. If your bank shows a debit, it will be reversed automatically.";
    } else if (state === "invalid") {
        cls = "pr-card--failed";
        icon.textContent = "✕";
        title.textContent = "We couldn't verify this payment";
        message.textContent = "This payment reference doesn't match any payment on your account.";
        actions.innerHTML = statement + browse;
        noteText = "If money left your account, contact support with the transaction reference and it will be traced and refunded. Nothing has been charged by Ventures Harbor.";
    } else {
        cls = "pr-card--pending";
        icon.innerHTML = VH.icon("hourglass", 34);
        title.textContent = "Couldn't reach the server";
        message.textContent = "We couldn't load the result of this payment just now.";
        actions.innerHTML = statement + dashboard;
        noteText = "Your Payment Statement always shows the authoritative record — check there before retrying, so you don't pay twice.";
    }

    card.className = "pr-card " + cls;

    if (payment) {
        document.getElementById("prVenture").textContent = payment.ventureTitle || "—";
        document.getElementById("prAmount").textContent = rupees(payment.feeAmount);
        document.getElementById("prPledge").textContent = rupees(payment.investedAmount);
        document.getElementById("prTxn").textContent = payment.txnId;
        details.classList.remove("hidden");
    } else if (txnId) {
        document.getElementById("prVenture").textContent = "—";
        document.getElementById("prAmount").textContent = "—";
        document.getElementById("prPledge").textContent = "—";
        document.getElementById("prTxn").textContent = txnId;
        details.classList.remove("hidden");
    }

    if (noteText) {
        note.textContent = noteText;
        note.classList.remove("hidden");
    }
}
