/* VENTURES HARBOR — JOIN VENTURE LOGIC (join-venture.js) */

let currentJoinStep = 1;
let ventureDetails = null;

let joinVentureId = 0;
let selectedRole = '';
let investmentAmount = 0;

let pledgeLimits = { remaining: 0, min: 0, max: 0, isFull: false };
// The active role's own ceiling, which on a capped venture is higher than the
// silent one above. Used by the application form on the resume step.
let activeLimits = { remaining: 0, min: 0, max: 0, isFull: false };
let commitmentFee = 0;
let totalPayable = 0;
let feeRate = 0.005;
let applicationId = null;
let selectedResumeFile = null;

let isSamplePreview = false;

let paymentGateway = { enabled: false, mode: 'test', name: 'PayU' };

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    loadPaymentGatewayConfig();

    // Set navbar avatar
    const navAvatar = document.getElementById("navAvatar");
    if (navAvatar) VH.renderAvatarInto(navAvatar, user);

    // Get venture ID from URL
    const params = new URLSearchParams(window.location.search);
    const ventureId = parseInt(params.get('id') || 0);
    joinVentureId = ventureId;
    applicationId = params.get('application_id') ? parseInt(params.get('application_id')) : null;

    if (!ventureId) {
        VH.toast.error("Asset ID is required.");
        setTimeout(() => {
            window.location.href = "browse.php";
        }, 1200);
        return;
    }

    // Fetch venture detail
    fetchVentureDetails(ventureId, user.id);

    if (applicationId) {
        loadSelectedApplication(applicationId);
    }

    // Step 1: Next click
    const step1Next = document.getElementById("step1Next");
    if (step1Next) {
        step1Next.addEventListener("click", () => {
            const roleRadio = document.querySelector("input[name='partnerRole']:checked");
            if (!roleRadio) {
                VH.toast.error("Please select a partner role.");
                return;
            }
            // A DISABLED RADIO STILL READS AS CHECKED — the same trap the exit
            // dialog's confirm button guards against. Without this, a role closed
            // after the page was drawn (or checked before it was disabled) walks
            // the visitor into a form api/ventures.php will refuse at the end.
            if (roleRadio.disabled) {
                VH.toast.error("This Asset is currently full for new partner commitments.");
                return;
            }
            selectedRole = roleRadio.value;
            updateStepperForRole(selectedRole);

            if (selectedRole === 'active') {
                joinGoStep('resume');
            } else {
                joinGoStep(2);
            }
        });
    }

    document.querySelectorAll("input[name='partnerRole']").forEach(radio => {
        radio.addEventListener("change", () => {
            if (radio.checked) {
                selectedRole = radio.value;
                updateStepperForRole(selectedRole);
            }
        });
    });

    // Active-partner résumé selection + application submit
    const resumeInput = document.getElementById("resumeInput");
    if (resumeInput) {
        resumeInput.addEventListener("change", () => {
            const errEl = document.getElementById("resumeErr");
            if (errEl) errEl.textContent = "";
            const nameEl = document.getElementById("resumeFileName");
            const f = resumeInput.files[0];
            if (!f) { selectedResumeFile = null; if (nameEl) nameEl.textContent = "No file selected"; return; }
            const ext = (f.name.split(".").pop() || "").toLowerCase();
            if (!["pdf", "doc", "docx"].includes(ext)) {
                if (errEl) errEl.textContent = "Please choose a PDF, DOC or DOCX file.";
                resumeInput.value = ""; selectedResumeFile = null;
                if (nameEl) nameEl.textContent = "No file selected";
                return;
            }
            if (f.size > 5 * 1024 * 1024) {
                if (errEl) errEl.textContent = "File must be smaller than 5MB.";
                resumeInput.value = ""; selectedResumeFile = null;
                if (nameEl) nameEl.textContent = "No file selected";
                return;
            }
            selectedResumeFile = f;
            if (nameEl) nameEl.textContent = f.name;
        });
    }
    const applyAmount = document.getElementById("applyAmount");
    if (applyAmount) {
        applyAmount.addEventListener("input", () => {
            validateApplyAmount();
            paintApplySummary(parseFloat(applyAmount.value || 0));
        });
    }

    /* Clear the complaint as they correct it, but stay quiet while the box is still
       empty — nagging somebody who has not finished typing their first digit is noise. */
    const applyWhatsapp = document.getElementById("applyWhatsapp");
    if (applyWhatsapp) {
        applyWhatsapp.addEventListener("input", () => {
            const err = document.getElementById("applyWhatsappErr");
            if (String(applyWhatsapp.value || '').trim() === '') {
                if (err) err.textContent = '';
                applyWhatsapp.classList.remove('is-invalid');
                return;
            }
            validateApplyWhatsapp();
        });
    }

    const resumeSubmit = document.getElementById("resumeSubmit");
    if (resumeSubmit) {
        resumeSubmit.addEventListener("click", async () => {
            const errEl = document.getElementById("resumeErr");
            if (!selectedResumeFile) {
                if (errEl) errEl.textContent = "Resume is required to apply.";
                VH.toast.error("Please attach your resume to apply.");
                return;
            }
            if (validateApplyWhatsapp(true) === null) return;
            const amt = validateApplyAmount(true);
            if (amt === null) return;
            investmentAmount = amt;
            commitmentFee = Math.round(amt * feeRate);
            totalPayable = commitmentFee;
            await submitApplication();
        });
    }

    // Step 2: Next click
    const step2Next = document.getElementById("step2Next");
    if (step2Next) {
        step2Next.addEventListener("click", async () => {
            const amt = parseFloat(document.getElementById("invAmount").value || 0);

            if (pledgeLimits.isFull) {
                VH.toast.error("This Asset has reached its funding target and is not accepting further commitments.");
                return;
            }
            if (!amt || amt < pledgeLimits.min) {
                VH.toast.error(`Minimum investment amount is ₹${pledgeLimits.min.toLocaleString('en-IN')}`);
                return;
            }
            if (amt > pledgeLimits.max) {
                VH.toast.error(`This Asset only needs ₹${pledgeLimits.remaining.toLocaleString('en-IN')} more, so that is the most you can commit.`);
                return;
            }
            investmentAmount = amt;
            commitmentFee = Math.round(amt * feeRate);
            totalPayable = commitmentFee;

            // Sync values to Step 3 and Step 4
            const roleLabel = 'Partner';
            setJoinText("agrRole", roleLabel);
            setJoinText("agrAmount", '₹' + amt.toLocaleString('en-IN'));

            setJoinText("paySumRole", roleLabel);
            setJoinText("paySumInv", '₹' + amt.toLocaleString('en-IN'));
            setJoinText("paySumFee", '₹' + commitmentFee.toLocaleString('en-IN'));
            setJoinText("paySumTotal", '₹' + totalPayable.toLocaleString('en-IN'));
            setJoinText("payBtnAmt", totalPayable.toLocaleString('en-IN'));
            paintCommitmentEquity();

            if (isActiveApplicationFlow()) {
                await submitApplication();
                return;
            }

            joinGoStep(3);
        });
    }

    // Step 3: Next click
    const step3Next = document.getElementById("step3Next");
    if (step3Next) {
        step3Next.addEventListener("click", () => {
            const c1 = document.getElementById("agrCheck1").checked;
            const c2 = document.getElementById("agrCheck2").checked;
            const c3 = document.getElementById("agrCheck3").checked;

            if (!c1 || !c2 || !c3) {
                VH.toast.error("You must agree to all legal terms and conditions.");
                return;
            }

            const orderIdEl = document.getElementById("rzpOrderId");
            if (orderIdEl) {
                orderIdEl.textContent = paymentGateway.enabled
                    ? 'Generated at payment'
                    : 'Not applicable (demo mode)';
            }

            joinGoStep(4);
        });
    }

    const payNowBtn = document.getElementById("payNowBtn");
    if (payNowBtn) {
        payNowBtn.addEventListener("click", async () => {
            if (isSamplePreview) {
                VH.toast.info('Sample listing — payment is locked on a preview.');
                return;
            }

            const resetBtn = () => {
                payNowBtn.innerHTML = payButtonLabel();
                payNowBtn.disabled = false;
            };

            payNowBtn.disabled = true;
            payNowBtn.textContent = totalPayable <= 0
                ? "Confirming..."
                : (paymentGateway.enabled ? "Redirecting to PayU..." : "Processing Payment...");

            if (paymentGateway.enabled && totalPayable > 0) {
                try {
                    const body = applicationId
                        ? { application_id: applicationId }
                        : { venture_id: ventureId, role: selectedRole, invested_amount: investmentAmount };

                    const res = await (await fetch("../api/payments.php?action=initiate", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        credentials: "same-origin",
                        body: JSON.stringify(body)
                    })).json();

                    if (!res.success) {
                        if (res.noPaymentNeeded) {
                            totalPayable = 0;
                            commitmentFee = 0;
                            await completeWithoutGateway(resetBtn);
                            return;
                        }
                        VH.toast.error(res.message || "Could not start the payment.");
                        resetBtn();
                        return;
                    }

                    postToGateway(res.endpoint, res.fields);
                } catch (err) {
                    console.error(err);
                    VH.toast.error("Network error starting the payment.");
                    resetBtn();
                }
                return;
            }

            await completeWithoutGateway(resetBtn);
        });
    }

    // Input tracker for Step 2 sums
    const invAmountInput = document.getElementById("invAmount");
    if (invAmountInput) {
        invAmountInput.addEventListener("input", (e) => {
            const val = parseFloat(e.target.value || 0);
            const fee = Math.round(val * feeRate);
            document.getElementById("invSumAmt").textContent = '₹' + val.toLocaleString('en-IN');
            document.getElementById("invSumFee").textContent = '₹' + fee.toLocaleString('en-IN');
            document.getElementById("invSumTotal").textContent = '₹' + fee.toLocaleString('en-IN');

            const over = pledgeLimits.max > 0 && val > pledgeLimits.max;
            e.target.classList.toggle('is-over-limit', over);
            VH.equity.paintInto("invEquity", ventureDetails, selectedRole || 'silent', val);

            const warn = document.getElementById("invLimitWarning");
            if (warn) {
                warn.textContent = over
                    ? `That's more than this Asset still needs. The maximum you can commit is ₹${pledgeLimits.max.toLocaleString('en-IN')}.`
                    : '';
                warn.classList.toggle('hidden', !over);
            }
        });
    }
});

async function loadPaymentGatewayConfig() {
    try {
        const res = await (await fetch("../api/payments.php?action=config", { credentials: "same-origin" })).json();
        if (res.success && res.gateway) paymentGateway = res.gateway;
    } catch (err) {
        console.error(err);
    }
    applyGatewayUI();
}

function applyGatewayUI() {
    const badge = document.getElementById("payGatewayName");
    if (badge) badge.textContent = paymentGateway.name || "PayU";

    const note = document.getElementById("razorpayDemoNote");
    if (!note) return;

    if (!paymentGateway.enabled) {
        note.innerHTML = VH.icon("alert", 14) + " <strong>Demo Mode:</strong> no payment gateway is active on this platform yet. "
            + "Clicking \"Pay Securely\" will simulate a successful payment for testing — no real money is charged.";
        note.classList.remove("hidden");
        return;
    }

    if (paymentGateway.mode === "test") {
        note.innerHTML = VH.icon("flask", 14) + " <strong>Test Mode:</strong> you'll be taken to PayU's test gateway. "
            + "Use a PayU test card — no real money is charged. Switch to Live mode in Admin Settings when testing is complete.";
        note.classList.remove("hidden");
    } else {
        note.classList.add("hidden");
    }
}

function postToGateway(endpoint, fields) {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = endpoint;
    form.style.display = "none";

    Object.keys(fields).forEach(name => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

function isActiveApplicationFlow() {
    if (applicationId) return false; // already past the application stage
    return selectedRole === 'active';
}

/**
 * Validate the active-partner application amount against the same limits the
 * silent path uses — the venture's minimum ticket and what is still open to the
 * active role. api/ventures.php re-checks both; this is so the applicant is told
 * before they submit rather than after.
 *
 * Returns the amount, or null when it isn't usable. Pass true to also surface a
 * toast, for the submit path.
 */
/* The twin of vh_normalize_whatsapp() in config/membership.php — same rules, so the
   form and the server cannot disagree about what counts as a number. The server is the
   authority and re-normalises whatever arrives; this exists so an applicant is told
   before they submit rather than after.

   India is assumed only where the input is ambiguous: a bare 10-digit number opening
   6-9. Anything already carrying a country code is left alone, so somebody applying
   from abroad is not rejected. */
function normalizeWhatsapp(raw) {
    let d = String(raw ?? '').replace(/\D+/g, '');
    if (!d) return null;
    if (d.startsWith('00')) d = d.slice(2);
    if (d.length === 11 && d[0] === '0') d = d.slice(1);
    if (/^[6-9]\d{9}$/.test(d)) d = '91' + d;
    return (d.length >= 10 && d.length <= 15) ? d : null;
}

/* Returns the normalised number, or null when it isn't usable. Pass true to also
   surface a toast, for the submit path — same shape as validateApplyAmount(). */
function validateApplyWhatsapp(loud) {
    const el = document.getElementById("applyWhatsapp");
    const err = document.getElementById("applyWhatsappErr");
    if (!el) return '';

    const raw = String(el.value || '').trim();
    const fail = (msg) => {
        if (err) err.textContent = msg;
        el.classList.add('is-invalid');
        if (loud) VH.toast.error(msg);
        return null;
    };

    if (raw === '') return fail("Enter a WhatsApp number so the founder can reach you.");
    const n = normalizeWhatsapp(raw);
    if (!n) return fail("That doesn't look like a valid mobile number.");

    if (err) err.textContent = '';
    el.classList.remove('is-invalid');
    return n;
}

function validateApplyAmount(loud) {
    const el = document.getElementById("applyAmount");
    const err = document.getElementById("applyAmountErr");
    if (!el) return 0;

    const raw = String(el.value || '').trim();
    const amt = Math.round(parseFloat(raw) || 0);
    const inr = (n) => '₹' + Number(n).toLocaleString('en-IN');
    const fail = (msg) => {
        if (err) err.textContent = msg;
        el.classList.add('is-invalid');
        if (loud) VH.toast.error(msg);
        return null;
    };

    if (activeLimits.isFull) {
        return fail("This Asset has reached its funding target and is not accepting further commitments.");
    }
    if (raw === '' || amt <= 0) {
        return fail("Enter how much you want to invest in this Asset.");
    }
    if (amt < activeLimits.min) {
        return fail(`The minimum for this Asset is ${inr(activeLimits.min)}.`);
    }
    if (amt > activeLimits.max) {
        return fail(`The most you can commit is ${inr(activeLimits.max)} — that is all this Asset still needs.`);
    }

    if (err) err.textContent = '';
    el.classList.remove('is-invalid');
    return amt;
}

/** Live figures under the application amount, so the fee is never a surprise. */
function paintApplySummary(amt) {
    const valid = Number(amt) > 0 ? Math.round(amt) : 0;
    const fee = Math.round(valid * feeRate);
    const set = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = '₹' + Number(v).toLocaleString('en-IN');
    };
    set("applySumAmt", valid);
    set("applySumFee", fee);

    // An active partner's equity is not simply their share of the pool: the
    // operations half is flat, and anything beyond the active reserve is
    // recognised as silent capital. VH.equity owns both rules.
    VH.equity.paintInto("applyEquity", ventureDetails, 'active', valid);
}

/**
 * Equity lines on the agreement and payment summaries. They restate what the
 * amount step already showed, so the partner sees the same figure on the screen
 * where they agree to it and on the one where they pay — never a new number.
 */
function paintCommitmentEquity() {
    const role = selectedRole || 'silent';
    const label = ventureDetails ? VH.equity.totalLabel(ventureDetails, role, investmentAmount) : '';

    const line = document.getElementById("agrEquityLine");
    if (line) line.classList.toggle("hidden", label === '');
    setJoinText("agrEquity", label || '—');

    const detail = document.getElementById("agrEquityDetail");
    if (detail && ventureDetails) {
        const e = VH.equity.forPledge(ventureDetails, role, investmentAmount);
        detail.textContent = (label && e.role === 'active' && e.silentCapital > 0)
            ? `(${VH.equity.inr(e.silentCapital)} of it is recognised as silent capital)`
            : '';
    }

    const payRow = document.getElementById("paySumEquityRow");
    if (payRow) payRow.classList.toggle("hidden", label === '');
    setJoinText("paySumEquity", label || '—');
}

/**
 * The sample-preview lock rewrites the payment nav, so #payBtnAmt and the pay
 * summary nodes can be absent. Writing straight to them threw a TypeError that
 * stranded silent partners on step 2 of a sample listing.
 */
function setJoinText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

/**
 * An active-partner application ends at "Application Submitted", not at payment
 * — the fee is only charged once the founder selects you. A sample preview must
 * end the same way or it teaches the wrong flow.
 */
function showAppliedPanel() {
    const deadlineWrap = document.getElementById("appliedDeadlineWrap");
    const deadline = ventureDetails && ventureDetails.application_deadline;
    if (deadline) {
        setJoinText("appliedDeadline", deadline);
        if (deadlineWrap) deadlineWrap.classList.remove("hidden");
    } else if (deadlineWrap) {
        deadlineWrap.classList.add("hidden");
    }
    joinGoStep('applied');
}

async function submitApplication() {
    if (isSamplePreview) {
        showAppliedPanel();
        VH.toast.info("Sample listing — nothing was submitted. This is where a real application ends.");
        return;
    }

    const btn = document.getElementById("resumeSubmit");
    if (btn) { btn.textContent = "Submitting..."; btn.disabled = true; }

    try {
        const fd = new FormData();
        fd.append("action", "join");
        fd.append("venture_id", ventureDetails.id);
        fd.append("role", selectedRole);
        fd.append("invested_amount", String(investmentAmount || 0));
        if (selectedResumeFile) fd.append("resume", selectedResumeFile);
        const msgEl = document.getElementById("applyMessage");
        if (msgEl && msgEl.value.trim()) fd.append("message", msgEl.value.trim());
        const waEl = document.getElementById("applyWhatsapp");
        if (waEl && waEl.value.trim()) fd.append("whatsapp_number", waEl.value.trim());

        const response = await fetch("../api/ventures.php", {
            method: "POST",
            credentials: "same-origin",
            body: fd
        });
        const res = await response.json();
        if (res.success) {
            showAppliedPanel();
            VH.toast.success(res.message || "Application submitted!");
        } else {
            VH.toast.error(res.message || "Failed to submit application.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error submitting application.");
    } finally {
        if (btn) { btn.textContent = "Submit Application →"; btn.disabled = false; }
    }
}

async function loadSelectedApplication(appId) {
    try {
        const response = await fetch(`../api/ventures.php?action=application_detail&application_id=${appId}`, { credentials: 'same-origin' });
        const res = await response.json();
        if (!res.success || !res.application) {
            VH.toast.error(res.message || "Application not found.");
            return;
        }
        const app = res.application;
        selectedRole = app.role;
        investmentAmount = parseFloat(app.invested_amount);
        commitmentFee = Math.round(investmentAmount * feeRate);
        totalPayable = commitmentFee;

        const roleLabel = selectedRole === 'active' ? 'Active Partner' : 'Silent Partner';
        setJoinText("agrRole", roleLabel);
        setJoinText("agrAmount", '₹' + investmentAmount.toLocaleString('en-IN'));
        setJoinText("paySumRole", roleLabel);
        setJoinText("paySumInv", '₹' + investmentAmount.toLocaleString('en-IN'));
        setJoinText("paySumFee", '₹' + commitmentFee.toLocaleString('en-IN'));
        setJoinText("paySumTotal", '₹' + totalPayable.toLocaleString('en-IN'));
        setJoinText("payBtnAmt", totalPayable.toLocaleString('en-IN'));
        // The venture fetch runs in parallel with this one; whichever finishes
        // last paints the equity, so the lines are filled in either order.
        paintCommitmentEquity();

        if (totalPayable <= 0) {
            const payBtn = document.getElementById("payNowBtn");
            if (payBtn) payBtn.innerHTML = payButtonLabel();
            const paySumFee = document.getElementById("paySumFee");
            if (paySumFee) paySumFee.textContent = '₹0 (Asset already fully funded)';
        }

        updateStepperForRole(selectedRole);
        if (selectedRole === 'active') {
            setActiveStepper(4); // selected → now at Agreement/Payment
        }
        joinGoStep(3);
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error loading your application.");
    }
}

function enterSamplePreviewMode() {
    document.querySelectorAll('.join-panel').forEach(panel => {
        if (panel.querySelector('[data-sample-banner]')) return;
        const banner = document.createElement('div');
        banner.setAttribute('data-sample-banner', '1');
        banner.className = 'join-sample-banner';
        banner.innerHTML =
            '<strong>Sample listing — preview only.</strong> '
            + 'This walks through exactly what joining a real Asset involves. '
            + 'Nothing is submitted and no payment can be taken.';
        panel.insertBefore(banner, panel.firstChild);
    });

    const payPanel = document.getElementById('joinStep4');
    if (payPanel && !payPanel.querySelector('[data-sample-lock]')) {
        const nav = payPanel.querySelector('.join-nav');
        const box = document.getElementById('razorpayBox');
        if (box) box.classList.add('hidden');
        if (nav) {
            nav.innerHTML =
                '<button class="btn btn--secondary" onclick="joinGoStep(3)">← Back</button>'
                + '<span data-sample-lock class="join-sample-lock">' + VH.icon('lock', 13) + ' Payment is locked on a sample listing</span>';
        }
        const cta = document.createElement('div');
        cta.setAttribute('data-sample-lock', '1');
        cta.className = 'join-sample-cta';
        cta.innerHTML =
            '<p>This is where you would pay the commitment fee on a real Asset. '
            + 'Sample listings exist to show the flow, so the step stops here.</p>'
            + '<div class="join-sample-cta__actions">'
            + '<a class="btn btn--primary" href="browse.php">Browse real Assets</a>'
            + '<a class="btn btn--secondary" href="create-venture.php">List your own Asset</a>'
            + '</div>';
        payPanel.insertBefore(cta, payPanel.querySelector('.join-nav'));
    }
}

function renderAgreementExitRights(v) {
    const el = document.getElementById('agrExitRights');
    if (!el) return;

    const allowed = v.early_exit_allowed;
    let text = '';
    if (String(allowed) === '1') {
        const notice = (v.early_exit_notice_period || '').trim();
        text = notice
            ? `Exit rights: early exit allowed, ${notice} notice required`
            : 'Exit rights: early exit allowed';
    } else if (String(allowed) === '0') {
        text = 'Exit rights: the founder does not allow early exit before the agreed minimum holding period';
    }

    el.textContent = text;
    el.classList.toggle('hidden', text === '');
}

function renderRoleCapacity(v) {
    const b = v.capital_buckets || {};
    const truthy = (x) => x === true || x === 1 || x === '1';

    const slots = {
        silent: document.getElementById('rcSilentAvail'),
        active: document.getElementById('rcActiveAvail')
    };
    Object.values(slots).forEach(el => {
        if (el) el.querySelectorAll('.role-cap').forEach(n => n.remove());
    });

    if (!truthy(b.split_known)) return;

    const num = (x) => Number(x) || 0;
    const hasCap = truthy(b.has_cap);

    const pool = Math.max(0, num(v.target_capital) - num(v.founder_contribution));
    const totals = hasCap
        ? { silent: num(b.silent_cap), active: num(b.active_reserve) }
        : { silent: pool, active: pool };
    const raised = { silent: num(b.raised_silent), active: num(b.raised_active) };

    const types = String(v.partner_types || 'both');
    const show = {
        silent: types === 'silent' || types === 'both',
        active: types === 'active' || types === 'both'
    };

    ['silent', 'active'].forEach(role => {
        const el = slots[role];
        if (!el || !show[role] || totals[role] <= 0) return;
        const pct = Math.min(100, Math.round((raised[role] / totals[role]) * 100));
        const wrap = document.createElement('div');
        wrap.className = 'role-cap';
        wrap.innerHTML =
            `<div class="role-cap-head">
                <span class="role-cap-label">${role === 'silent' ? 'Silent' : 'Active'} capital</span>
                <span class="role-cap-figs">${VH.card.money(raised[role])} / ${VH.card.money(totals[role])}</span>
             </div>
             <span class="role-cap-track"><span class="role-cap-fill role-cap-fill--${role}" style="width:${pct}%"></span></span>`;
        el.appendChild(wrap);
    });
}

async function fetchVentureDetails(id, userId) {
    try {
        const response = await fetch(`../api/ventures.php?action=detail&id=${id}`);
        const res = await response.json();

        if (res.success && res.venture) {
            if (String(res.venture.is_showcase ?? '0') === '1') {
                isSamplePreview = true;
                enterSamplePreviewMode();
            }

            const blocked = { member: 'You have already joined this Asset.',
                              founder: 'This is your own Asset — you cannot join it as a partner.' };
            if (blocked[res.venture.viewer_state]) {
                VH.toast.error(blocked[res.venture.viewer_state]);
                setTimeout(() => { window.location.href = `venture-detail.php?id=${id}`; }, 1600);
                return;
            }

            ventureDetails = res.venture;
            feeRate = 0.005;

            renderAgreementExitRights(res.venture);

            // Populate venture header details
            document.getElementById("jTitle").textContent = res.venture.title;
            document.getElementById("paySumVenture").textContent = res.venture.title;

            VH.renderVentureIconInto(document.getElementById("jIcon"), res.venture);

            document.getElementById("jMeta").textContent = `${res.venture.industry} · ${res.venture.location} · ${res.venture.days_left} days left`;
            document.getElementById("jProgFill").style.width = `${res.venture.progress_percent}%`;
            document.getElementById("jPct").textContent = `${res.venture.progress_percent}% funded · ${res.venture.members_count} members`;

            const statusEl = document.getElementById("jStatus");
            if (statusEl) {
                statusEl.textContent = res.venture.status.toUpperCase();
                statusEl.className = `badge ${res.venture.status === 'active' ? 'badge--success' : 'badge--danger'}`;
            }

            pledgeLimits = computePledgeLimits(res.venture, 'silent');
            activeLimits = computePledgeLimits(res.venture, 'active');
            const minInv = pledgeLimits.min;

            // The application form on the resume step reads the active role's
            // limits, which on a capped venture are wider than the silent ones.
            const applyMin = document.getElementById("applyMinNote");
            const applyMax = document.getElementById("applyMaxNote");
            if (applyMin) applyMin.textContent = '₹' + activeLimits.min.toLocaleString('en-IN');
            if (applyMax) applyMax.textContent = '₹' + activeLimits.max.toLocaleString('en-IN');
            const applyInput = document.getElementById("applyAmount");
            if (applyInput) {
                applyInput.min = activeLimits.min;
                applyInput.max = activeLimits.max;
                applyInput.step = 1000;
            }
            renderInvestmentPresets(activeLimits.min, activeLimits.max, "applyPresets", "applyAmount");

            document.getElementById("invMinNote").textContent = '₹' + minInv.toLocaleString('en-IN');
            const invMaxNote = document.getElementById("invMaxNote");
            if (invMaxNote) {
                invMaxNote.textContent = '₹' + pledgeLimits.max.toLocaleString('en-IN');
            }
            const invLimitNote = document.getElementById("invLimitNote");
            if (invLimitNote) {
                const buckets = res.venture.capital_buckets;
                const capped = !!buckets && (buckets.has_cap === true || buckets.has_cap === 1 || buckets.has_cap === '1');
                const scope = capped ? 'is open to silent partners' : 'of this Asset is still available';

                invLimitNote.textContent = pledgeLimits.remaining < parseFloat(res.venture.min_investment)
                    ? `Only ₹${pledgeLimits.remaining.toLocaleString('en-IN')} ${scope} — that is the exact amount left to commit.`
                    : (capped
                        ? `₹${pledgeLimits.remaining.toLocaleString('en-IN')} of this Asset is still open to silent partners. You can commit between ₹${minInv.toLocaleString('en-IN')} and ₹${pledgeLimits.max.toLocaleString('en-IN')}.`
                        : `This Asset still needs ₹${pledgeLimits.remaining.toLocaleString('en-IN')}. You can commit between ₹${minInv.toLocaleString('en-IN')} and ₹${pledgeLimits.max.toLocaleString('en-IN')}.`);
                invLimitNote.classList.remove('hidden');
            }

            const invAmount = document.getElementById("invAmount");
            if (invAmount) {
                invAmount.min = minInv;
                invAmount.max = pledgeLimits.max;
                invAmount.value = minInv;
                // trigger change
                invAmount.dispatchEvent(new Event('input'));
            }

            // Role constraints setup
            applyRoleAvailability(res.venture);

            renderRoleCapacity(res.venture);

            applyRoleFromUrl();

            // Investment presets setup
            renderInvestmentPresets(minInv, pledgeLimits.max);

            // The application form starts blank, so its equity box only appears
            // once an amount is typed; a returning selected applicant already
            // has one, and gets the agreement/payment lines filled here.
            paintApplySummary(parseFloat((document.getElementById("applyAmount") || {}).value || 0));
            if (investmentAmount > 0) paintCommitmentEquity();
        } else {
            VH.toast.error("Failed to load Asset configuration details.");
        }
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error reading Asset parameters.");
    }
}

// role defaults to 'silent' because that is what every existing caller means.
// BOTH sides are bounded by their own bucket on a capped listing — the active one
// since 6 Sep 2026, because the role card said "ACTIVE CAPITAL ₹0 / ₹8L" while
// this form offered ₹90,00,000. Same rule as vh_pledge_limits() in
// config/membership.php, which is the authority — pass the role explicitly.
function computePledgeLimits(v, role) {
    const target = parseFloat(v.target_capital) || 0;
    const raised = parseFloat(v.raised_capital) || 0;
    const ticket = parseFloat(v.min_investment) || 0;
    let remaining = Math.max(0, target - raised);

    const b = v.capital_buckets;
    const hasCap = !!b && (b.has_cap === true || b.has_cap === 1 || b.has_cap === '1');
    if (hasCap) {
        const bucket = role === "active"
            ? parseFloat(b.active_remaining)
            : parseFloat(b.silent_remaining);
        remaining = Math.max(0, Math.min(remaining, bucket || 0));
    }

    return {
        remaining: remaining,
        min: remaining > 0 ? Math.min(Math.max(ticket, 1), remaining) : 0,
        max: remaining,
        isFull: remaining <= 0,

        // This role is out of room while the Asset as a whole is not — true of
        // either side now, so the message can name the one the visitor picked.
        silentCapped: hasCap && remaining <= 0 && (parseFloat(b.remaining) || 0) > 0
    };
}

function payButtonLabel() {
    return totalPayable > 0
        ? `${VH.icon("lock", 15)} Pay ₹<span id="payBtnAmt">${totalPayable.toLocaleString('en-IN')}</span> Securely`
        : `✓ Confirm My Place<span id="payBtnAmt" hidden>0</span>`;
}

async function completeWithoutGateway(resetBtn) {
    if (isSamplePreview) {
        VH.toast.info('Sample listing — nothing is charged and no membership is created.');
        if (resetBtn) resetBtn.disabled = false;
        return;
    }

    try {
        const body = applicationId
            ? { action: "confirm_application", application_id: applicationId }
            : { action: "join", venture_id: joinVentureId, role: selectedRole, invested_amount: investmentAmount, fee_amount: commitmentFee };

        const response = await fetch("../api/ventures.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body)
        });
        const res = await response.json();
        if (res.success) {
            const totalPaid = typeof res.totalPaid === 'number' ? res.totalPaid : totalPayable;
            document.getElementById("joinedVentureName").textContent = ventureDetails.title;
            document.getElementById("joinTxnId").textContent = res.txnId;
            document.getElementById("joinTxnAmt").textContent = '₹' + totalPaid.toLocaleString('en-IN');

            VH.toast.success(totalPaid > 0 ? "Payment successful!" : "You're in — no commitment fee was due.");
            VH.modal.open("joinSuccessModal");
        } else {
            VH.toast.error(res.message || "Failed to join Asset.");
            resetBtn();
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error processing payment.");
        resetBtn();
    }
}

/* A SAMPLE LISTING IS WALKED THROUGH, SO ITS ROLES MUST READ AS OPEN.
 *
 * `vh_venture_openness()` answers false to both `accepts_*` for a showcase, because
 * $live is false there — which is what makes the server refuse a join or a payment on
 * one. Those gates stay. But this page read the same two flags, so it disabled BOTH
 * role cards on every sample and the preview could not leave step 1: the client's own
 * active-only example listing showed "NOT ACCEPTING" on the one role it lists, under a
 * banner promising to walk through exactly what joining involves (9 Sep 2026).
 *
 * `openness_preview` is that same server function's answer computed as if the listing
 * were live. It is read ONLY in preview mode, and only when the server sent it, so an
 * older cached payload behaves exactly as it did before.
 */
function joinOpenness(v) {
    if (isSamplePreview && v && v.openness_preview) return v.openness_preview;
    return (v && v.openness) || {};
}

/* A CLOSED ROLE MUST NOT BE SELECTABLE, AND BOTH SIDES CLOSE BY THE SAME RULE.
 *
 * This used to be a ladder over `partner_types` plus a single `capitalClosed` flag
 * derived from the SILENT bucket alone — so it could only ever close the silent card.
 * An Asset whose ACTIVE reserve had filled while silent capital was still wanted left
 * "Active Partner" enabled on a card that read ₹8L / ₹8L: the visitor selected it,
 * went through to the résumé step and filled the whole application in, and only then
 * did api/ventures.php refuse it. `accepts_active` has been false in that state ever
 * since the active side was bucketed; nothing on this page ever asked.
 *
 * The client's report is that state exactly — "founder ne uski request accept karli
 * phir bhi ye active partner open kyo ha": the seat had already been taken, and the
 * form still offered it.
 *
 * So the answer is now read off `venture.openness` rather than recomputed here — the
 * same reader relationship VH.card.buckets() has with `capital_buckets`. The browser
 * cannot see the `venture_members` rows fullness is derived from, and a second
 * implementation of the rule is precisely what drifted.
 */
function applyRoleAvailability(v) {
    const o = joinOpenness(v);
    const pTypes = v.partner_types;

    const lists = {
        active: pTypes === 'active' || pTypes === 'both',
        silent: pTypes === 'silent' || pTypes === 'both',
    };

    // The server is the authority. The local derivation is only a fallback for a
    // payload that predates `openness`, and it is deliberately SYMMETRIC — falling
    // back to the old silent-only logic would reinstate the bug.
    const limits = { active: computePledgeLimits(v, 'active'), silent: computePledgeLimits(v, 'silent') };
    const targeted = (parseFloat(v.target_capital) || 0) > 0;
    const open = {
        active: 'accepts_active' in o ? !!o.accepts_active : (lists.active && !(targeted && limits.active.isFull)),
        silent: 'accepts_silent' in o ? !!o.accepts_silent : (lists.silent && !(targeted && limits.silent.isFull)),
    };

    ['active', 'silent'].forEach(role => {
        const card = document.getElementById(role === 'active' ? 'rcActive' : 'rcSilent');
        const slot = document.getElementById(role === 'active' ? 'rcActiveAvail' : 'rcSilentAvail');

        /* An available role draws NOTHING — the client cut the green "Role Available"
           chip. An enabled, clickable card already says the role is open, so the badge
           only restated it. The refusals are the ones that carry information. */
        if (open[role]) {
            if (card) card.classList.remove('disabled');
            const input = card && card.querySelector('input');
            if (input) input.disabled = false;
            if (slot) slot.innerHTML = '';
            return;
        }

        if (card) card.classList.add('disabled');
        const input = card && card.querySelector('input');
        // Uncheck as well as disable: a disabled radio still answers to `:checked`,
        // which is the same trap the exit dialog's confirm button guards against.
        if (input) { input.disabled = true; input.checked = false; }
        if (slot) slot.innerHTML = closedBadgeHTML(role, lists[role], o, limits[role]);
    });

    showRoleClosedNote(v, open, lists);

    // Land the visitor on the one role still open, rather than on a dead card they
    // have to work out is dead. Only ever selects a role that is genuinely open.
    const openRoles = ['active', 'silent'].filter(r => open[r]);
    if (openRoles.length === 1) {
        const card = document.getElementById(openRoles[0] === 'active' ? 'rcActive' : 'rcSilent');
        const input = card && card.querySelector('input');
        if (input && !input.disabled && !input.checked) {
            input.checked = true;
            input.dispatchEvent(new Event('change'));
        }
    }
}

/** Why this role is shut, in three words on the card itself. */
function closedBadgeHTML(role, listed, o, limit) {
    const badge = text =>
        `<span class="badge badge--danger" style="font-size:0.68rem;margin-top:0.5rem;display:inline-block;">${text}</span>`;

    if (!listed) return badge('Not Accepted');
    if (o.is_full) return badge('Funding Closed — 100% Raised');

    const capped = role === 'active'
        ? ('active_capped' in o ? o.active_capped : limit.silentCapped)
        : ('silent_capped' in o ? o.silent_capped : limit.silentCapped);

    return badge(capped
        ? (role === 'active' ? 'Active Limit Reached' : 'Silent Limit Reached')
        : 'Not Accepting');
}

/* The paragraph under the cards. It mirrors vh_role_closed_message() in
 * config/membership.php — same figures, same "here is the route that IS open"
 * shape — so the page cannot promise something the server would refuse. */
function showRoleClosedNote(v, open, lists) {
    const host = document.getElementById("roleCards");
    if (!host) return;

    let note = document.getElementById("capitalClosedNote");
    if (!note) {
        note = document.createElement("div");
        note.id = "capitalClosedNote";
        note.className = "join-funded-note";
        host.parentNode.insertBefore(note, host.nextSibling);
    }

    // Nothing is shut that the visitor could have taken — say nothing.
    if ((open.active || !lists.active) && (open.silent || !lists.silent)) {
        note.innerHTML = '';
        note.classList.add('hidden');
        return;
    }
    note.classList.remove('hidden');

    // `venture`, not `Asset`. A bulk "venture" -> "Asset" rename once spanned live code
    // and renamed this identifier; nothing declares `Asset`, so every call threw a
    // ReferenceError and the fully-funded note never appeared.
    const b = v.capital_buckets || {};
    const inr = n => '₹' + (parseFloat(n) || 0).toLocaleString('en-IN');
    const target = inr(v.target_capital);

    if (!open.active && !open.silent) {
        note.innerHTML = `<strong>This Asset is not accepting new partners.</strong> Every role it listed is
           full — there is no place left to commit to. Join the waitlist on the Asset page to be in line if
           somebody exits.`;
        return;
    }

    // The active reserve filled while silent capital is still wanted. This is the case
    // that had no note at all, because nothing here could close the active card.
    if (!open.active && lists.active) {
        note.innerHTML = `<strong>The active-partner side is full.</strong> This Asset has taken the full
           <strong>${inr(b.active_reserve)}</strong> the founder reserved for partners who work in the business.
           The remaining <strong>${inr(b.silent_remaining)}</strong> is open to <strong>silent partners</strong>,
           who invest capital only — join as one of those instead.`;
        return;
    }

    // Silent is shut: either its own cap closed it, or the Asset is fully funded.
    const o = joinOpenness(v);
    const silentCapped = 'silent_capped' in o
        ? o.silent_capped
        : computePledgeLimits(v, 'silent').silentCapped;

    if (silentCapped) {
        note.innerHTML = `<strong>Silent partnership is full.</strong> This Asset has taken the full
           <strong>${inr(b.silent_cap)}</strong> it opened to silent (capital-only) partners. The remaining
           <strong>${inr(b.remaining)}</strong> is reserved for <strong>active partners</strong>, who join the
           team and work in the business — apply for that role below.`;
        return;
    }

    note.innerHTML = `<strong>This Asset is fully funded.</strong> It has raised its full ${target} target, so the
       silent (capital) partner role is closed — there is no investment left to commit, and no commitment fee to
       pay. It is still looking for <strong>active partners</strong> who join the team and work in the business.
       Apply for that role below.`;
}

function applyRoleFromUrl() {
    const wanted = new URLSearchParams(window.location.search).get('role');
    if (wanted !== 'active' && wanted !== 'silent') return;

    const input = document.querySelector(`input[name='partnerRole'][value='${wanted}']`);
    if (!input || input.disabled) return;

    input.checked = true;
    input.dispatchEvent(new Event('change'));
}

// containerId/inputId default to the silent-partner step, so existing calls are
// unchanged; the active application form passes its own pair.
function renderInvestmentPresets(minVal, maxVal, containerId, inputId) {
    const container = document.getElementById(containerId || "invPresets");
    if (!container) return;

    const fmt = n => VH.card.money(n);
    const values = [...new Set(
        [minVal, Math.round(minVal * 1.5), minVal * 2]
            .map(n => Math.min(n, maxVal))
            .filter(n => n > 0)
    )];

    const target = inputId || "invAmount";
    container.innerHTML = values.map((v, i) =>
        `<button type="button" class="preset-btn${i === 0 && !inputId ? ' active' : ''}" `
        + `onclick="applyPreset(${v}, this, '${target}')">${fmt(v)}</button>`
    ).join('');
}

window.applyPreset = function(value, button, inputId) {
    const input = document.getElementById(inputId || "invAmount");
    if (input) {
        input.value = value;
        input.dispatchEvent(new Event('input'));
    }

    const group = button.parentElement;
    if (group) group.querySelectorAll(".preset-btn").forEach(btn => btn.classList.remove("active"));
    button.classList.add("active");
};

function updateStepperForRole(role) {
    const isActive = role === 'active';
    const stepsSilent = document.getElementById("joinStepsSilent");
    const stepsActive = document.getElementById("joinStepsActive");
    const stepsNeutral = document.getElementById("joinStepsNeutral");

    if (stepsNeutral) stepsNeutral.classList.add("hidden");
    if (stepsSilent) stepsSilent.classList.toggle("hidden", isActive);
    if (stepsActive) stepsActive.classList.toggle("hidden", !isActive);
    if (isActive) setActiveStepper(1);

    const nextSilent = document.getElementById("nextStepsSilent");
    const nextActive = document.getElementById("nextStepsActive");
    if (nextSilent) nextSilent.classList.toggle("hidden", isActive);
    if (nextActive) nextActive.classList.toggle("hidden", !isActive);

    const step2Next = document.getElementById("step2Next");
    if (step2Next) step2Next.textContent = isActive ? "Submit Application →" : "Next: Legal Agreement →";

    // Deadline only matters to active-partner applicants.
    const deadlineCard = document.getElementById("deadlineCard");
    if (deadlineCard) {
        const deadline = ventureDetails && ventureDetails.application_deadline;
        deadlineCard.classList.toggle("hidden", !(isActive && deadline));
        if (isActive && deadline) {
            const dateEl = document.getElementById("deadlineCardDate");
            if (dateEl) dateEl.textContent = deadline;
        }
    }
}

function setActiveStepper(n) {
    for (let i = 1; i <= 4; i++) {
        const step = document.getElementById(`ja${i}`);
        if (step) {
            step.classList.remove("active", "done");
            if (i < n) step.classList.add("done");
            else if (i === n) step.classList.add("active");
        }
        const line = document.getElementById(`jal${i}`);
        if (line) line.classList.toggle("done", i < n);
    }
}

// Every joinGoStep path ends here: the visitor should start each step at its
// first field, not at the scroll position the previous step's buttons left.
function scrollToJoinStepTop() {
    const stepper = document.querySelector(".join-steps:not(.hidden)")
        || document.querySelector(".join-steps");
    VH.scroll.toElementTop(stepper);
}

window.joinGoStep = function(stepNum) {
    currentJoinStep = stepNum;
    const resumePanel = document.getElementById("joinStepResume");

    const hideNumericPanels = () => {
        for (let i = 1; i <= 4; i++) {
            const panel = document.getElementById(`joinStep${i}`);
            if (panel) panel.classList.add("hidden");
        }
    };

    if (stepNum === 'applied') {
        hideNumericPanels();
        if (resumePanel) resumePanel.classList.add("hidden");
        const appliedPanel = document.getElementById("joinStepApplied");
        if (appliedPanel) appliedPanel.classList.remove("hidden");
        setActiveStepper(3); // In Selection
        scrollToJoinStepTop();
        return;
    }

    if (stepNum === 'resume') {
        hideNumericPanels();
        if (resumePanel) resumePanel.classList.remove("hidden");
        setActiveStepper(2); // Upload Resume
        scrollToJoinStepTop();
        return;
    }

    if (resumePanel) resumePanel.classList.add("hidden");
    const appliedPanel = document.getElementById("joinStepApplied");
    if (appliedPanel) appliedPanel.classList.add("hidden");

    for (let i = 1; i <= 4; i++) {
        const panel = document.getElementById(`joinStep${i}`);
        const indicator = document.getElementById(`js${i}`);

        if (i === stepNum) {
            if (panel) panel.classList.remove("hidden");
            if (indicator) indicator.classList.add("active");
        } else {
            if (panel) panel.classList.add("hidden");
            if (indicator) indicator.classList.remove("active");
        }
    }

    if (stepNum === 1) setActiveStepper(1);

    // Painted on arrival, not only on keystroke. Where the founder stated no
    // percentages VH.equity shows the rule instead of a figure, and that has to
    // be readable before anything is typed — an empty amount box would
    // otherwise hide the explanation the visitor most needs.
    if (stepNum === 2) {
        VH.equity.paintInto("invEquity", ventureDetails, selectedRole || 'silent',
            document.getElementById("invAmount")?.value || 0);
    }

    scrollToJoinStepTop();
};

