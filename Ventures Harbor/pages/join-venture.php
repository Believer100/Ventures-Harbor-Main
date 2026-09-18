<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$currentUser = requirePageUserOnly();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title id="joinPageTitle">Join Asset – Ventures Harbor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../assets/css/join-venture.css?v=41"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body>
<div class="toast-container" id="toastContainer"></div>
<?php
$navSolid = true;
$navContext = '← <a href="browse.php" style="color:#2563EB;text-decoration:none">Back to Browse</a>';
include __DIR__ . '/../partials/header.php';
?>

<div class="join-page">

  <div class="join-steps" id="joinStepsNeutral">
    <div class="join-step active"><span>1</span> Select Role</div>
    <div class="join-step-line"></div>
    <div class="join-step"><span>2</span> Details</div>
    <div class="join-step-line"></div>
    <div class="join-step"><span>3</span> Agreement</div>
    <div class="join-step-line"></div>
    <div class="join-step"><span>4</span> Payment</div>
  </div>
  <div class="join-steps hidden" id="joinStepsSilent">
    <div class="join-step active" id="js1"><span>1</span> Select Role</div>
    <div class="join-step-line"></div>
    <div class="join-step" id="js2"><span>2</span> Investment</div>
    <div class="join-step-line"></div>
    <div class="join-step" id="js3"><span>3</span> Agreement</div>
    <div class="join-step-line"></div>
    <div class="join-step" id="js4"><span>4</span> Payment</div>
  </div>
  <div class="join-steps hidden" id="joinStepsActive">
    <div class="join-step active" id="ja1"><span>1</span> Select Role</div>
    <div class="join-step-line" id="jal1"></div>
    <div class="join-step" id="ja2"><span>2</span> Upload Resume</div>
    <div class="join-step-line" id="jal2"></div>
    <div class="join-step" id="ja3"><span>3</span> In Selection</div>
    <div class="join-step-line" id="jal3"></div>
    <div class="join-step" id="ja4"><span>4</span> Payment</div>
  </div>

  <div class="join-layout">
    <!-- Main form area -->
    <div class="join-form-area">

      <!-- Venture Summary -->
      <div class="join-venture-summary" id="joinSummary">
        <div class="join-vs-icon" id="jIcon"><svg class="vh-i" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></div>
        <div class="join-vs-info">
          <h3 id="jTitle">Car Detailing Franchise</h3>
          <p id="jMeta">Automotive · Mumbai · 24 days left</p>
          <div class="join-vs-prog">
            <div class="join-prog-bar"><div class="join-prog-fill" id="jProgFill" style="width:21%"></div></div>
            <span id="jPct">21% funded · 16/25 members</span>
          </div>
        </div>
        <span class="badge badge--success" id="jStatus">Active</span>
      </div>

      <!-- Step 1: Role Selection -->
      <div class="join-panel" id="joinStep1">
        <h2>Join as a Partner</h2>
        <p class="join-subtitle">Choose the partner flow for this Asset.</p>
        <div class="role-cards" id="roleCards">
          <label class="role-card" id="rcPartner">
            <input type="radio" name="partnerRole" value="partner" checked/>
            <div class="role-card-inner">
              <div class="role-icon"><svg class="vh-i" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
              <h4>Partner</h4>
              <p>Become a partner in this Asset with a single investment flow, simple agreement, and one payment step.</p>
              <ul class="role-perks">
                <li><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg> One join flow</li>
                <li><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg> Clear investment details</li>
                <li><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg> Simple commitment fee</li>
              </ul>
              <div id="rcPartnerAvail"></div>
            </div>
          </label>
        </div>
        <div class="join-nav"><span></span><button class="btn btn--primary btn--lg" id="step1Next">Next →</button></div>
      </div>

      <!-- Step 2: Investment -->
      <div class="join-panel hidden" id="joinStep2">
        <h2>Investment Amount</h2>
        <p class="join-subtitle">Enter how much you'd like to invest in this Asset</p>
        <div class="investment-input-wrap">
          <span class="inv-currency">₹</span>
          <input type="number" class="form-control inv-input" id="invAmount" placeholder="0"/>
        </div>
        <p class="inv-min-note">Minimum: <strong id="invMinNote">₹2,00,000</strong> &nbsp;·&nbsp; Maximum: <strong id="invMaxNote">—</strong></p>

        <p class="inv-limit-note hidden" id="invLimitNote"></p>
        <p class="inv-limit-warning hidden" id="invLimitWarning"></p>
        <div class="inv-presets" id="invPresets"></div>
        <div class="inv-summary-box">
          <div class="inv-sum-row"><span>Investment Amount <small style="color:#7A8AA3;font-weight:400">(settled offline)</small></span><strong id="invSumAmt">₹0</strong></div>
          <div class="inv-sum-row"><span id="invFeeLabel">Commitment Fee (0.5%)</span><strong id="invSumFee">₹0</strong></div>
          <div class="inv-sum-row inv-sum-total"><span>Commitment Fee Payable Today</span><strong id="invSumTotal">₹0</strong></div>
          <p style="font-size:0.75rem;color:#5A6B85;margin:0.5rem 0 0;font-style:italic">* Only the commitment fee (0.5%) is charged online now. Your investment amount is recorded for tracking but is not charged through this platform — it is settled offline directly with the founder after the partner meetup.</p>
        </div>
        <?php // What this amount buys — VH.equity, painted live as the figure changes. ?>
        <div id="invEquity" class="hidden"></div>
        <div class="join-nav"><button class="btn btn--secondary" onclick="joinGoStep(1)">← Back</button><button class="btn btn--primary btn--lg" id="step2Next">Next: Legal Agreement →</button></div>
      </div>

      <div class="join-panel hidden" id="joinStepResume">
        <h2>Upload Your Resume</h2>
        <p class="join-subtitle">Partners apply with a resume. The founder reviews it and, if you're selected, you'll then complete the agreement and pay the commitment fee. No payment is taken now.</p>
        <div class="form-group">
          <label class="form-label">Resume / CV * <small style="color:#7A8AA3;font-weight:400">(PDF, DOC or DOCX, up to 5MB)</small></label>
          <input type="file" id="resumeInput" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" hidden/>
          <div class="cv-logo-row" style="align-items:center;gap:0.75rem;">
            <button type="button" class="btn btn--secondary" onclick="document.getElementById('resumeInput').click()"><svg class="vh-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> Choose File</button>
            <span id="resumeFileName" style="font-size:0.85rem;color:#5A6B85;">No file selected</span>
          </div>
          <span class="form-error" id="resumeErr"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Message to the founder <small style="color:#7A8AA3;font-weight:400">(optional)</small></label>
          <textarea class="form-control form-textarea" id="applyMessage" rows="3" maxlength="500" placeholder="Briefly tell the founder why you're a good fit for this Asset."></textarea>
        </div>

        <?php /* The founder decides on a resume and a short message alone — there is no
                 messaging on this platform until somebody is a member, so until now the
                 only way to ask an applicant a question was to select them first. The
                 number is per-application, not the account's phone: it is offered for
                 this listing and goes when the application does. */ ?>
        <div class="form-group">
          <label class="form-label" for="applyWhatsapp">WhatsApp Number *</label>
          <input class="form-control" id="applyWhatsapp" type="tel" inputmode="tel" maxlength="20"
                 autocomplete="tel" placeholder="e.g. 98765 43210"/>
          <span class="form-error" id="applyWhatsappErr"></span>
          <span class="form-hint" style="color:#5A6B85;font-size:0.8rem">If the founder needs more information about your application, they will contact you on this number.</span>
        </div>

        <!-- An active partner commits capital as well as effort, so the amount is
             stated here rather than being assumed. It used to be stored as 0 and
             quietly replaced with the minimum ticket at payment time, which meant
             the applicant never chose it and the founder never saw it. -->
        <div class="form-group">
          <label class="form-label">Investment Amount *</label>
          <div class="investment-input-wrap">
            <span class="inv-currency">₹</span>
            <input type="number" class="form-control inv-input" id="applyAmount" placeholder="0" inputmode="numeric"/>
          </div>
          <p class="inv-min-note">Minimum: <strong id="applyMinNote">—</strong> &nbsp;·&nbsp; Maximum: <strong id="applyMaxNote">—</strong></p>
          <div class="inv-presets" id="applyPresets"></div>
          <span class="form-error" id="applyAmountErr"></span>
          <div class="inv-summary-box" style="margin-top:0.75rem">
            <div class="inv-sum-row"><span>Investment Amount <small style="color:#7A8AA3;font-weight:400">(settled offline)</small></span><strong id="applySumAmt">₹0</strong></div>
            <div class="inv-sum-row"><span>Commitment Fee (0.5%)</span><strong id="applySumFee">₹0</strong></div>
            <p style="font-size:0.75rem;color:#5A6B85;margin:0.5rem 0 0;font-style:italic">* Nothing is charged now. The commitment fee only becomes payable if the founder selects you.</p>
          </div>
          <?php // Active partners see the same breakdown, including the part of a
                // large commitment that is recognised as silent capital. ?>
          <div id="applyEquity" class="hidden"></div>
        </div>
        <div class="join-nav"><button class="btn btn--secondary" onclick="joinGoStep(1)">← Back</button><button class="btn btn--primary btn--lg" id="resumeSubmit">Submit Application →</button></div>
      </div>

      <!-- Step 3: Agreement -->
      <div class="join-panel hidden" id="joinStep3">
        <h2>Legal Agreement</h2>
        <p class="join-subtitle">Review the partnership terms before proceeding</p>
        <div class="agreement-box">
          <h4>Partnership Agreement Summary</h4>
          <ul class="agreement-points">
            <li>You are joining as a <strong id="agrRole">Partner</strong></li>
            <li>Investment commitment: <strong id="agrAmount">₹0</strong></li>
            <li id="agrEquityLine" class="hidden">Equity this commitment buys: <strong id="agrEquity">—</strong> <span id="agrEquityDetail" style="color:#5A6B85"></span></li>
            <li>Profit sharing based on capital contribution ratio</li>
            <li>All formal legal agreements are executed offline, under proper legal/accounting supervision</li>

            <li id="agrExitRights" class="hidden"></li>
            <li><strong>Commitment fee refund policy.</strong> The <span id="agrFeeRate">0.5%</span> commitment fee is refunded in full if the Asset does not raise the required funds within its listed period, or if the founder deletes or cancels the Asset</li>
            <li>You also receive your complete commitment fee back if you exit after the partner meeting — within the 24-hour window that opens when the founder marks the meetup completed. Submit the request with your bank/UPI details and it is transferred offline after admin review</li>
            <li><strong>No refund</strong> if you choose to exit before the partner meeting</li>
            <li>Disputes resolved via arbitration per Indian Arbitration Act</li>
          </ul>
          <?php /* "Download Agreement Template" was here and is deliberately GONE
                   (client, 8 Sep 2026). It downloaded nothing — the click only raised
                   a toast saying the PDF "will be available after joining" — so on the
                   one screen where somebody is being asked to accept terms, the single
                   control that looked like it would hand them those terms did not.
                   That is worse than an absent button. The terms themselves are stated
                   in full in the list above, which is what the checkboxes below refer
                   to. Do not restore it without a real file behind it. */ ?>
        </div>
        <div class="agr-checkboxes">
          <label class="auth-checkbox"><input type="checkbox" id="agrCheck1"/><span>I have read and understood the partnership terms</span></label>
          <label class="auth-checkbox"><input type="checkbox" id="agrCheck2"/><span>I agree that this platform facilitates introductions only; final agreements are offline</span></label>
          <label class="auth-checkbox"><input type="checkbox" id="agrCheck3"/><span>I confirm my investment intent and agree to the commitment fee policy</span></label>
        </div>
        <div class="join-nav"><button class="btn btn--secondary" onclick="joinGoStep(2)">← Back</button><button class="btn btn--primary btn--lg" id="step3Next">Next: Payment →</button></div>
      </div>

      <!-- Step 4: Payment -->
      <div class="join-panel hidden" id="joinStep4">
        <h2>Complete Payment</h2>
        <p class="join-subtitle">Pay your commitment fee online to confirm your place; your investment is settled offline</p>
        <div class="payment-summary-box">
          <div class="pay-sum-row"><span>Asset</span><strong id="paySumVenture">Car Detailing Franchise</strong></div>
          <div class="pay-sum-row"><span>Role</span><strong id="paySumRole">Partner</strong></div>
          <div class="pay-sum-row"><span>Investment <small style="color:#7A8AA3;font-weight:400">(settled offline)</small></span><strong id="paySumInv">₹0</strong></div>
          <div class="pay-sum-row hidden" id="paySumEquityRow"><span>Equity</span><strong id="paySumEquity">—</strong></div>
          <div class="pay-sum-row"><span>Commitment Fee</span><strong id="paySumFee">₹0</strong></div>
          <div class="pay-sum-row pay-total"><span>Commitment Fee Payable Now</span><strong id="paySumTotal">₹0</strong></div>
        </div>

        <div class="razorpay-checkout-box" id="razorpayBox">
          <div class="razorpay-badge">
            <span class="razorpay-lock"><svg class="vh-i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
            <span>Payments secured by <strong id="payGatewayName">PayU</strong></span>
          </div>
          <div class="pay-sum-row"><span>Reference</span><strong id="rzpOrderId">Generated at payment</strong></div>
          <div class="pay-sum-row"><span>Payment Methods</span><strong>UPI · Cards · Netbanking · Wallets</strong></div>
          <p class="razorpay-demo-note" id="razorpayDemoNote"></p>
        </div>

        <div class="join-nav"><button class="btn btn--secondary" onclick="joinGoStep(3)">← Back</button><button class="btn btn--primary btn--xl" id="payNowBtn">🔒 Pay ₹<span id="payBtnAmt">0</span> Securely</button></div>
      </div>

      <!-- Application Submitted (Active Partner, deadline-gated) -->
      <div class="join-panel hidden" id="joinStepApplied">
        <div style="text-align:center;padding:2rem 0">
          <div style="color:#2563EB;margin-bottom:0.5rem"><svg class="vh-i" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></div>
          <h2>Application Submitted</h2>
          <p class="join-subtitle">The founder reviews partner applications before accepting new partners<span id="appliedDeadlineWrap" class="hidden"> (deadline: <strong id="appliedDeadline"></strong>)</span>. No fee is charged now — you'll be notified if the founder selects you, and can complete payment then.</p>
          <div class="join-nav" style="justify-content:center;margin-top:1.5rem">
            <a href="../admin/dashboard.php" class="btn btn--primary">Go to Dashboard</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Info sidebar -->
    <div class="join-info-sidebar">
      <div class="join-info-card">
        <h4><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Safe &amp; Secure</h4>
        <p>Ventures Harbor uses bank-grade encryption. Your payment information is never stored.</p>
      </div>

      <div class="join-info-card">
        <h4><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg> What Happens Next?</h4>
        <ol class="join-next-steps" id="nextStepsSilent">
          <li>Pay your commitment fee here to secure your spot. This fee is fully refundable if you choose to exit within 24 hours after the venture meeting.</li>
          <li>Get instant group chat access to communicate directly with the founder and partners.</li>
          <li>Once the Asset is fully funded, a meeting will be hosted within a few days by founder to clear up all details and provide absolute clarity.</li>
          <li>If the concept doesn't align with your expectations, you can exit the venture directly through the dashboard within 24 hours after the meeting for a 100% commitment fee refund.</li>
          <li>Once you submit your exit reason, your seat will immediately be released to all waitlisted members.</li>
        </ol>
        <?php /* The client's own wording, 6 Sep 2026 — the previous five were the
                 generic originals and said nothing about the fee being refundable,
                 the 24-hour window or the waitlist, all of which the silent list
                 beside them already explained. Keep the two lists in step: they
                 describe the same platform from two sides. */ ?>
        <ol class="join-next-steps hidden" id="nextStepsActive">
          <li>Fill out the initial form with your details. <strong>(Nothing is charged at this stage)</strong></li>
          <li>The project founder reviews your application to determine whether you're a suitable fit for an active role.</li>
          <li>If selected, you'll be notified to pay the commitment fee directly through your dashboard.</li>
          <li>Get instant group chat access to communicate directly with the founder and other partners. Once the Asset is fully funded, the founder will host a meeting within a few days to clarify all details and provide complete transparency.</li>
          <li>If the concept doesn't align with your expectations, you can exit the venture directly through your dashboard within 24 hours of the meeting for a 100% refund of your commitment fee.</li>
          <li>Once you submit your exit reason, your seat will immediately be released to waitlisted members.</li>
        </ol>
      </div>
      <div class="join-info-card hidden" id="deadlineCard">
        <h4><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 22h14M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg> Application Deadline</h4>
        <p>Active-partner applications for this Asset close on
          <strong id="deadlineCardDate">—</strong>. The founder reviews all applications after this date.</p>
      </div>
      <div class="join-info-card">
        <h4><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg> Need Help?</h4>
        <p>Contact our support team at <a href="contact.php" style="color:#2563EB;text-decoration:none;font-weight:600;">support@venturesharbor.com</a></p>
        <a href="contact.php" class="btn btn--primary btn--sm" style="margin-top:0.75rem">Contact Support →</a>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="joinSuccessModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header" style="flex-direction:column;text-align:center;padding:2rem">
      <div style="color:#15803D;margin-bottom:0.5rem"><svg class="vh-i" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="M22 2 20 4l2 2-2 2"/><path d="M11 13a9 9 0 0 1 9 9"/><path d="M2 22a9 9 0 0 1 9-9"/></svg></div>
      <h3 class="modal-title">Welcome Aboard!</h3>
      <p style="color:#5A6B85;margin:0.5rem 0 0">You have successfully joined <strong id="joinedVentureName">this Asset</strong>. You'll be added to the WhatsApp group shortly.</p>
    </div>
    <div class="modal-body" style="text-align:center">
      <div class="join-success-txn">
        <p>Transaction ID: <strong id="joinTxnId">VH000000</strong></p>
        <p>Amount Paid: <strong id="joinTxnAmt">₹0</strong></p>
      </div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <a href="browse.php" class="btn btn--secondary">Browse More</a>
      <a href="../admin/dashboard.php" class="btn btn--primary">Go to Dashboard</a>
    </div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/vh-nav.js?v=1"></script>
<script src="../assets/js/join-venture.js?v=60"></script>
</body>
</html>
