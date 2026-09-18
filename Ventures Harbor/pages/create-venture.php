<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/lookups.php';
require_once __DIR__ . '/../config/venture-lifecycle.php';

$showcaseMode = isset($_GET['showcase']) && $_GET['showcase'] === '1';
$currentUser  = $showcaseMode ? requirePageAdmin() : requirePageUserOnly();

$maxListingDays = vh_max_listing_days($mysqli);
$maxExtensionDays = vh_max_extension_days($mysqli);

$showcaseSlotsLeft = $showcaseMode ? (VH_SHOWCASE_MAX - vh_showcase_count($mysqli)) : 0;
// Editing an existing sample consumes no slot, so the "N of 3 remaining" line
// would only confuse an admin who is not adding one.
$showcaseEditMode = $showcaseMode && isset($_GET['edit']) && (int)$_GET['edit'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="../assets/img/logo-mark.svg">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>List Your Venture – Ventures Harbor</title>
  <meta name="description" content="List a business or real-world Asset on Ventures Harbor. Create your listing in 4 simple steps."/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/main.css?v=35"/>
  <link rel="stylesheet" href="../assets/css/components.css?v=38"/>
  <link rel="stylesheet" href="../assets/css/theme.css?v=32"/>
  <link rel="stylesheet" href="../vendor/cropperjs/cropper.min.css?v=31"/>
  <link rel="stylesheet" href="../assets/css/create-venture.css?v=56"/>
    <link rel="stylesheet" href="../assets/css/vh-pages.css?v=43" />
  <link rel="stylesheet" href="../assets/css/vh-nav.css?v=3">
</head>
<body>
<div class="toast-container" id="toastContainer"></div>
<?php
$navSolid = true;
$navContext = '← <a href="' . ($showcaseMode ? '../admin/admin.php#showcase' : '../admin/dashboard.php') . '" style="color:#2563EB;text-decoration:none">' . ($showcaseMode ? 'Back to Admin Panel' : 'Back to Dashboard') . '</a>';
$navCta = false;
include __DIR__ . '/../partials/header.php';
?>

<div class="cv-page"<?php if ($showcaseMode): ?> data-showcase="1"<?php endif; ?>>
  <?php if ($showcaseMode): ?>

  <div class="cv-sample-note">
    <span class="cv-sample-note__tag">SAMPLE LISTING</span>
    <div>
      <?php if ($showcaseEditMode): ?>
      <strong>You're editing an existing sample listing.</strong>
      Changes go live immediately on Browse and the homepage. It still cannot be joined
      by anyone, never expires, and costs nothing — editing does not use a slot.
      <?php else: ?>
      <strong>You're publishing an example, not a Real Asset.</strong>
      It appears at the top of Browse and the homepage with a SAMPLE LISTING badge,
      cannot be joined by anyone, never expires, and costs nothing to publish.
      <?php echo (int)$showcaseSlotsLeft; ?> of <?php echo VH_SHOWCASE_MAX; ?> slots remaining.
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Step Progress -->
  <div class="cv-step-progress">
    <div class="cv-step active" data-step="1"><div class="cv-step-circle">1</div><div class="cv-step-label">Basic Info</div></div>
    <div class="cv-step-line"></div>
    <div class="cv-step" data-step="2"><div class="cv-step-circle">2</div><div class="cv-step-label">Capital</div></div>
    <div class="cv-step-line"></div>
    <div class="cv-step" data-step="3"><div class="cv-step-circle">3</div><div class="cv-step-label">Review</div></div>
  </div>

  <div class="cv-layout">
    <!-- Form -->
    <div class="cv-form-area">

      <!-- Step 1 -->
      <div class="cv-panel" id="cvStep1">
        <h2>Basic Information</h2>
        <p class="cv-subtitle">Tell the world about your Venture</p>
        <div class="form-group">
          <label class="form-label">Venture Title *</label>
          <input class="form-control" id="cvTitle" placeholder="e.g. Premium Car Detailing Franchise"/>
          <span class="form-error" id="cvTitleErr"></span>
        </div>
        <br>
        <?php if ($showcaseMode): ?>

        <div class="form-group">
          <label class="form-label">Founder Name Shown on the Card *</label>
          <input class="form-control" id="cvShowcaseFounder" placeholder="e.g. Rahul Sharma"/>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:0.9rem">
            <div>
              <label class="form-label">Example Silent Capital Raised (₹)</label>
              <input class="form-control" id="cvShowcaseSilent" type="number" min="0" step="10000" placeholder="e.g. 2500000"/>
            </div>
            <div>
              <label class="form-label">Example Active Capital Raised (₹)</label>
              <input class="form-control" id="cvShowcaseActive" type="number" min="0" step="10000" placeholder="e.g. 1200000"/>
            </div>
          </div>
          <span style="font-size:0.78rem;color:#5A6B85;display:block;margin-top:0.4rem">
            Sample listings have no real partners, so the silent/active capacity bars would
            sit at 0% without these. Illustrative only — clamped to the partner pool, and
            never used by a real listing.
          </span>
          <span style="font-size:0.78rem;color:#5A6B85">
            The name a visitor sees as the founder of this example. Not a real account —
            the SAMPLE LISTING badge makes clear it isn't a real person's listing.
          </span>
        </div>
        <br>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Short Description * <span class="char-counter" id="descCount">0/200</span></label>
          <textarea class="form-control form-textarea" id="cvDesc" rows="3" maxlength="200" placeholder="What is this Venture about? What problem does it solve?"></textarea>
          <span class="form-error" id="cvDescErr"></span>
        </div>
        <br>
        <div class="form-group">
          <label class="form-label">Full Description</label>
          <textarea class="form-control form-textarea" id="cvFullDesc" rows="5" placeholder="Detailed description, business model, market opportunity..."></textarea>
        </div>
        <br>
        <div class="form-group">
          <label class="form-label">Use of Funds</label>
          <textarea class="form-control form-textarea" id="cvUseOfFunds" rows="5" placeholder="How will the raised amount be spent? e.g. equipment, rent, inventory, staffing, marketing..."></textarea>
        </div>
        <br>
        <div class="form-group">
          <label class="form-label">Asset Class *</label>
          <div class="cv-class-picker" id="cvAssetClassPicker">
            <?php foreach ($VH_ASSET_CLASSES as $slug => $label): ?>
            <label class="cv-class-chip">
              <input type="checkbox" name="assetClass" value="<?php echo htmlspecialchars($slug); ?>"/>
              <span><?php echo htmlspecialchars($label); ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="cv-class-custom">
            <input class="form-control" id="cvAssetClassCustom" maxlength="40"
                   placeholder="Add your own &mdash; e.g. Vintage Cars"/>
            <button type="button" class="btn btn--outline btn--sm" id="cvAssetClassAdd">+ Add Custom</button>
          </div>
          <span class="form-hint">
            Which marketplace categories this listing is shopped under &mdash; pick up to
            <?php echo (int)VH_ASSET_CLASS_MAX; ?>, and it appears under each one.
            Picked for you from the industry below; change it if it belongs elsewhere.
            A class of your own shows on the listing and is searchable, but is not one of the
            marketplace filter buttons.
          </span>
          <span class="form-error" id="cvAssetClassErr"></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Industry *</label>
            <select class="form-control form-select" id="cvIndustry" onchange="toggleIndustryOther(); syncAssetClass()">
              <option value="">Select Industry</option>
              <?php foreach ($VH_INDUSTRIES as $ind): ?>
                <option value="<?php echo htmlspecialchars($ind); ?>"><?php echo htmlspecialchars($ind); ?></option>
              <?php endforeach; ?>
              <option value="__other__">Other (type your own)</option>
            </select>
            <input class="form-control hidden" id="cvIndustryOther" style="margin-top:0.5rem" placeholder="Type your industry"/>
            <span class="form-error" id="cvIndustryErr"></span>
          </div>
          <div class="form-group">
            <label class="form-label">State *</label>
            <select class="form-control form-select" id="cvState" onchange="syncPreviewCard()">
              <option value="">Select State</option>
              <?php foreach ($VH_STATES as $st): ?>
                <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($VH_STATE_LABELS[$st] ?? $st); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-error" id="cvStateErr"></span>
          </div>
        </div>
        <br>
        <div class="form-group">
          <label class="form-label">City *</label>
          <input class="form-control" id="cvCity" placeholder="Type your city (e.g. Rewari)" oninput="syncPreviewCard()"/>
          <span class="form-error" id="cvCityErr"></span>
        </div>
        <br>
        <div class="form-group">
          <label class="form-label">Asset Image</label>
          <div class="cv-logo-row">
            <div class="cv-logo-preview" id="cvLogoPreview" aria-hidden="true">?</div>
            <div class="cv-logo-actions">
              <input type="file" id="cvLogoInput" accept="image/png,image/jpeg,image/webp" hidden/>
              <button type="button" class="btn btn--secondary btn--sm" onclick="document.getElementById('cvLogoInput').click()">Choose Image</button>
              <button type="button" class="btn btn--ghost btn--sm hidden" id="cvLogoRemove" onclick="clearVentureLogo()">Remove</button>
              <span class="cv-logo-hint">JPG, PNG or WEBP, up to 5MB. If you don't upload one, your Venture's first letter is used instead.</span>
            </div>
          </div>
          <span class="form-error" id="cvLogoErr"></span>
        </div>
        <br>
        <?php /* Preferred Meeting Venue was withdrawn at the client's request: a
                 founder cannot honestly promise a venue before knowing how many
                 partners will turn up, and past VH_OFFLINE_MEETUP_MAX_MEMBERS the
                 Asset can only meet online anyway (vh_meetup_mode). The address is
                 now typed per-meetup in the schedule dialog, where the group size
                 is actually known. The `preferred_meeting_venue` column and its API
                 parameter are deliberately kept so older listings keep their value
                 and editing one does not wipe it. */ ?>
        <div class="cv-nav"><span></span><button class="btn btn--primary btn--lg" onclick="goStep(2)">Next: Capital Details →</button></div>
      </div>

      <!-- Step 2 -->
      <?php  ?>
      <div class="cv-panel hidden" id="cvStep2">
        <h2>Capital Details</h2>
        <p class="cv-subtitle">Define the financial structure</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Total Capital Required (₹) *</label>
            <input class="form-control" id="cvTarget" type="number" placeholder="1000000" oninput="updateCalc()"/>
            <span class="form-error" id="cvTargetErr"></span>
          </div>
          <div class="form-group">
            <label class="form-label">Your Contribution (₹)</label>
            <input class="form-control" id="cvContrib" type="number" placeholder="300000" oninput="updateCalc()"/>
          </div>
          <div class="form-group">
            <label class="form-label">Min. Investment per Member (₹) *</label>
            <input class="form-control" id="cvMinInv" type="number" placeholder="100000" oninput="updateCalc()"/>
            <span class="form-error" id="cvMinInvErr"></span>
            <?php /* Filled live by paintMemberLimit(): the headcount falls out of this
                     figure, so the founder is told the floor before they hit Next rather
                     than after. Turns red once the implied partner count exceeds the cap. */ ?>
            <span class="cv-field-hint hidden" id="cvMinInvHint"></span>
          </div>
          <?php  ?>
          <?php /* Compulsory since 5 Sep 2026, and only shown on a both-types listing —
                   a listing with one partner side has nothing to divide. The gap rule
                   lives in vh_resolve_silent_cap(); updateSilentCapHint() states the
                   allowed range live, so the founder is not guessing at it. */ ?>
          <div class="form-group" id="cvSilentCapWrap">
            <label class="form-label" for="cvSilentCap">Maximum from Silent Partners (₹) *</label>
            <input class="form-control" id="cvSilentCap" type="number" min="0" placeholder="Silent partners total contribution" oninput="updateSilentCapHint()"/>
            <span class="form-error" id="cvSilentCapErr"></span>
            <span id="cvSilentCapHint" style="display:block;margin-top:6px;font-size:0.78rem;color:#5A6B85">
              The total you will accept from silent partners. Whatever is left of your target
              stays reserved for active partners, and at least one member&rsquo;s minimum
              investment has to remain on that side.
            </span>
          </div>
          <?php 

                ?>
          <div class="form-group<?php echo $showcaseMode ? ' hidden' : ''; ?>">
            <label class="form-label">Listing Duration (days) *</label>
            <input class="form-control" id="cvDaysLeft" type="number" min="1"
                   max="<?php echo (int)$maxListingDays; ?>"
                   data-max-days="<?php echo (int)$maxListingDays; ?>"
                   value="15" list="cvDaysOptions"/>
            <datalist id="cvDaysOptions">
              <option value="5"></option><option value="6"></option><option value="7"></option>
              <option value="15"></option><option value="<?php echo (int)$maxListingDays; ?>"></option>
            </datalist>

            <span id="cvDaysHint" style="font-size:0.78rem;color:#5A6B85">
              How many days this listing stays open for partners &mdash; up to
              <strong><?php echo (int)$maxListingDays; ?> days</strong>.
              If it isn't fully funded by then you get one extension of up to
              <?php echo (int)$maxExtensionDays; ?> more days.
            </span>
          </div>
        </div>
        <div class="cv-calc-box" id="calcBox">
          <div class="cv-calc-row"><span>Capital from Partners</span><strong id="calcPartners">₹0</strong></div>
          <div class="cv-calc-row"><span>Est. Members Needed <small style="color:#7A8AA3">(incl. you)</small></span><strong id="calcMembers">0</strong></div>
          <div class="cv-calc-row cv-calc-highlight"><span>Commitment Fee per Member</span><strong id="calcFee">₹0</strong></div>

          <div class="cv-calc-row cv-calc-highlight"><span>Your Listing Fee (0.5% of your contribution)</span><strong id="calcListingFee">₹0</strong></div>
        </div>
        <p style="font-size:0.78rem;color:#5A6B85;line-height:1.6;margin:0.5rem 0 0;">
          Listing an Asset costs a one-time <strong>0.5% of your own contribution</strong> — the same rate
          every partner pays on theirs. It is charged when you publish, and it is not refunded if you later
          delete the listing.
        </p>

        <div class="cv-section-head">
          <h3>Investment &amp; Exit Details</h3>
          <p>How long a partner's money is committed, and how they get it out again. Partners see all of this on your listing page before they join — the clearer it is, the fewer questions you'll field later.</p>
        </div>

        <div class="form-group">
          <label class="form-label">Equity Distribution</label>
          <select class="form-control form-select" id="cvEquityDistribution">
            <option value="">Not specified</option>
            <option value="capital_based">Capital-Based Share</option>
            <option value="equal_split">Equal Split</option>
            <option value="negotiated">Negotiated</option>
          </select>
          <span style="font-size:0.78rem;color:#5A6B85">How ownership is split between you and your partners.</span>
        </div>

        <?php // Equity + salary per role. The amounts are read from the Capital step, never re-entered. ?>
        <div class="form-group cv-equity-table" id="cvEquityTable">
          <label class="form-label">Equity &amp; Salary per Role</label>
          <p class="cv-equity-intro">
            What each side actually gets. The amounts come from the Capital fields above — you only
            set the percentages and, where one is drawn, the monthly salary. Equity is split into the
            share earned by <strong>investment</strong> (capital put in) and the share earned by
            <strong>operations</strong> (work put in) — the total is worked out for you, and
            operations may be 0. Leave any box blank if it hasn't been agreed yet.
          </p>

          <div class="cv-equity-grid" role="table">
            <div class="cv-equity-head" role="row">
              <span role="columnheader">Role</span>
              <span role="columnheader">Investment</span>
              <span role="columnheader">Investment Equity %</span>
              <span role="columnheader">Operations Equity %</span>
              <span role="columnheader">Total Equity</span>
              <span role="columnheader">Monthly Salary (₹)</span>
            </div>

            <div class="cv-equity-row" role="row">
              <span class="cv-equity-role" role="cell">Founder <small>(you)</small></span>
              <span class="cv-equity-amt" role="cell" data-label="Investment" id="cvEqFounderAmt">—</span>
              <span role="cell" data-label="Investment Equity %"><input class="form-control" id="cvFounderEquity" type="number" min="0" max="100" step="0.5" placeholder="e.g. 30"/></span>
              <span role="cell" data-label="Operations Equity %"><input class="form-control" id="cvFounderOpsEquity" type="number" min="0" max="100" step="0.5" placeholder="e.g. 20"/></span>
              <span class="cv-equity-total" role="cell" data-label="Total Equity" id="cvEqFounderTotal">—</span>
              <span role="cell" data-label="Monthly Salary"><input class="form-control" id="cvFounderSalary" type="number" min="0" step="1000" placeholder="Optional"/></span>
            </div>

            <div class="cv-equity-row" role="row">
              <span class="cv-equity-role" role="cell">Active Partner</span>
              <span class="cv-equity-amt" role="cell" data-label="Investment" id="cvEqActiveAmt">—</span>
              <span role="cell" data-label="Investment Equity %"><input class="form-control" id="cvActiveEquity" type="number" min="0" max="100" step="0.5" placeholder="e.g. 5"/></span>
              <span role="cell" data-label="Operations Equity %"><input class="form-control" id="cvActiveOpsEquity" type="number" min="0" max="100" step="0.5" placeholder="e.g. 5"/></span>
              <span class="cv-equity-total" role="cell" data-label="Total Equity" id="cvEqActiveTotal">—</span>
              <span role="cell" data-label="Monthly Salary"><input class="form-control" id="cvActiveSalary" type="number" min="0" step="1000" placeholder="Optional"/></span>
            </div>

            <div class="cv-equity-row" role="row">
              <span class="cv-equity-role" role="cell">Silent Partner</span>
              <span class="cv-equity-amt" role="cell" data-label="Investment" id="cvEqSilentAmt">—</span>
              <span role="cell" data-label="Investment Equity %"><input class="form-control" id="cvSilentEquity" type="number" min="0" max="100" step="0.5" placeholder="e.g. 5"/></span>
              <span class="cv-equity-na" role="cell" data-label="Operations Equity %">No operations &mdash; capital only</span>
              <span class="cv-equity-total" role="cell" data-label="Total Equity" id="cvEqSilentTotal">—</span>
              <span class="cv-equity-na" role="cell" data-label="Monthly Salary">No salary &mdash; capital only</span>
            </div>
          </div>

          <span class="form-error" id="cvEquityErr"></span>
          <span style="font-size:0.78rem;color:#5A6B85;display:block;margin-top:0.4rem">
            The partner percentages are per member at the minimum investment. A silent partner
            takes no salary — that is what separates the two partner types.
          </span>
          <?php // The rule the numbers above don't state on their own. Partners
                // see the same explanation on the listing, worked through with
                // this venture's own figures. ?>
          <p class="cv-equity-rule">
            <strong>Above the minimum, an active partner becomes part silent.</strong>
            The active percentage is what one minimum investment buys, so that minimum is the active limit for a
            single partner. Anything they commit beyond it is extra capital: it is recognised as silent
            (capital-only) partnership and earns your silent partner percentage on top. With a ₹3,00,000 minimum,
            15% active and 6.7% silent, someone bringing ₹4,00,000 as an active partner receives
            15% + 2.23% = 17.23%.
          </p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Expected ROI (% per year) *</label>
            <div style="display:flex;align-items:center;gap:0.5rem">
              <input class="form-control" id="cvExpectedRoiMin" type="number" min="0" max="100" step="0.5" placeholder="14" style="flex:1"/>
              <span style="color:#5A6B85;font-size:0.85rem">to</span>
              <input class="form-control" id="cvExpectedRoiMax" type="number" min="0" max="100" step="0.5" placeholder="18" style="flex:1"/>
              <span style="color:#5A6B85;font-size:0.85rem">%</span>
            </div>
            <span class="form-error" id="cvExpectedRoiErr"></span>
            <span style="font-size:0.78rem;color:#5A6B85">The return a partner can expect each year. Enter the same figure twice if it isn't a range.</span>
          </div>
          <div class="form-group">
            <label class="form-label">ROI Conditions</label>
            <input class="form-control" id="cvExpectedRoi" maxlength="120"
                   placeholder="e.g. once occupancy passes 80%"/>
            <span style="font-size:0.78rem;color:#5A6B85">Optional. Anything the figures above depend on — shown on the listing page beside the range.</span>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Minimum Holding Period</label>
            <select class="form-control form-select" id="cvLockin">
              <option value="">Not specified</option>
              <option value="6 Months">6 Months</option>
              <option value="12 Months">12 Months</option>
              <option value="24 Months">24 Months</option>
              <option value="custom">Custom…</option>
            </select>
            <input class="form-control hidden" id="cvLockinCustom" style="margin-top:0.5rem" placeholder="e.g. 18 Months, until first profit cycle"/>
            <span style="font-size:0.78rem;color:#5A6B85">Minimum time to hold ownership before exit or transfer.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Expected Exit Timeline</label>
            <input class="form-control" id="cvExitTimeline" maxlength="120" placeholder="e.g. 3–5 years"/>
            <span style="font-size:0.78rem;color:#5A6B85">When you expect partners to be able to realise their return.</span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Exit Options Available</label>
          <div class="cv-check-grid" id="cvExitOptions">
            <label class="cv-check"><input type="checkbox" name="exitOption" value="Founder Buyback"/><span>Founder Buyback</span><em class="cv-rec">Recommended</em></label>
            <label class="cv-check"><input type="checkbox" name="exitOption" value="Existing Partner Buyout"/><span>Existing Partner Buyout</span><em class="cv-rec">Recommended</em></label>
            <label class="cv-check"><input type="checkbox" name="exitOption" value="External Ownership Transfer"/><span>External Ownership Transfer</span></label>
            <label class="cv-check"><input type="checkbox" name="exitOption" value="Strategic Sale / Acquisition"/><span>Strategic Sale / Acquisition</span></label>
            <label class="cv-check"><input type="checkbox" name="exitOption" value="Other" id="cvExitOther"/><span>Other</span></label>
          </div>
          <input class="form-control hidden" id="cvExitOtherText" style="margin-top:0.6rem" maxlength="150" placeholder="Describe the other exit route"/>
          <span style="font-size:0.78rem;color:#5A6B85">Select every route a partner could realistically use.</span>
        </div>

        <div class="form-group">
          <label class="form-label">Early Exit Policy</label>
          <div class="cv-inline-radios">
            <label class="cv-check"><input type="radio" name="earlyExit" value="1"/><span>Early exit allowed</span></label>
            <label class="cv-check"><input type="radio" name="earlyExit" value="0"/><span>Not allowed</span></label>
          </div>
          <!-- Only meaningful when early exit is actually allowed. -->
          <div class="cv-conditional hidden" id="cvEarlyExitFields">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
              <div>
                <label class="form-label">Notice Period</label>
                <input class="form-control" id="cvEarlyExitNotice" maxlength="120" placeholder="e.g. 30 days written notice"/>
              </div>
              <div>
                <label class="form-label">Conditions / Charges</label>
                <input class="form-control" id="cvEarlyExitConditions" maxlength="255" placeholder="e.g. 5% exit fee on the invested amount"/>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Ownership Transfer Rules</label>
          <div class="cv-inline-radios">
            <label class="cv-check"><input type="radio" name="transferAllowed" value="1"/><span>Transfer allowed</span><em class="cv-rec">Recommended</em></label>
            <label class="cv-check"><input type="radio" name="transferAllowed" value="0"/><span>Not allowed</span></label>
          </div>
          <div class="cv-conditional hidden" id="cvTransferFields">
            <?php /* A native <option> cannot carry a styled badge, so the pill sits on the
                     label and the option text repeats it in words for the open dropdown. */ ?>
            <label class="form-label">Approval Required From <em class="cv-rec">Founder recommended</em></label>
            <select class="form-control form-select" id="cvTransferApproval">
              <option value="">Not specified</option>
              <option value="founder">Founder (Recommended)</option>
              <option value="partners">Partners</option>
              <option value="both">Founder and Partners</option>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

          <div class="form-group">
            <label class="form-label">Profit Distribution Frequency *</label>
            <select class="form-control form-select" id="cvProfitFrequency">
              <option value="">Select frequency</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="yearly">Annually</option>
              <option value="none">No regular distribution</option>
            </select>
            <span class="form-error" id="cvProfitFrequencyErr"></span>
          </div>
          <div class="form-group">
            <label class="form-label">Exit Valuation Method</label>
            <select class="form-control form-select" id="cvExitValuation">
              <option value="">Not specified</option>
              <option value="fixed">Fixed valuation</option>
              <option value="business_valuation">Business valuation at exit</option>
              <option value="multiple">Revenue / Profit multiple</option>
              <option value="custom">Custom terms</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Valuation Terms <span style="font-weight:400;color:#5A6B85">(optional)</span></label>
          <textarea class="form-control form-textarea" id="cvExitValuationNotes" rows="2" maxlength="500" placeholder="e.g. Valued at 3× trailing twelve-month net profit, verified by an independent CA."></textarea>
        </div>

        <?php // Capital is step 2: back to Basic Info (1), forward to Review (3). ?>
        <div class="cv-nav"><button class="btn btn--secondary" onclick="goStep(1)">← Back</button><button class="btn btn--primary btn--lg" onclick="goStep(3)">Review &amp; Publish →</button></div>
      </div>

      <!-- Step 3 -->
      <?php  ?>
      <div class="cv-panel hidden" id="cvStep2">
        <h2>Partner Settings</h2>
        <p class="cv-subtitle">Define how partners will be involved</p>
        <div class="form-group">
          <label class="form-label">Partner Participation</label>
          <div class="cv-locked-field">
            <strong>None specified</strong>
            <span>Open to any partner. Capital details are defined in the next section.</span>
          </div>
          <input type="radio" name="partnerType" value="partner" checked hidden/>
        </div>

        <?php /* One "Partner Requirements" box asked two different questions at once —
                 what the founder wants from someone bringing money, and what he wants
                 from someone joining the team — so both got answered vaguely. Each
                 side now has its own box, and the skills field sits under the side it
                 belongs to. Whichever group the listing has no role for is hidden and
                 cleared, so a founder is never asked about partners they cannot take. */ ?>
        <div class="cv-role-req hidden" id="cvSilentReqGroup">
          <div class="cv-role-req-head">Silent Partner <small>Brings capital only</small></div>
          <div class="form-group">
            <label class="form-label" for="cvSilentRequirements">Silent Partner Requirements</label>
            <textarea class="form-control form-textarea" id="cvSilentRequirements" rows="3" placeholder="e.g. 3-year holding period, KYC documents, comfortable with quarterly payouts..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label" for="cvSilentRequiredSkills">Required Skills (Silent Partners)</label>
            <?php /* data-chip-input upgrades this into a tag editor at runtime
                     (setupChipInput in create-venture.js). It stays an ordinary
                     comma-separated text input in the markup on purpose: the value
                     format is unchanged, every existing reader still does
                     `getElementById(...).value`, and with JS broken the founder
                     still gets a usable field instead of nothing. */ ?>
            <input class="form-control" id="cvSilentRequiredSkills" data-chip-input
                   data-chip-placeholder="Type a skill and press Enter"
                   placeholder="e.g. Real estate, Finance (comma-separated)"/>
            <span class="form-hint" style="color:#5A6B85;font-size:0.8rem">Optional. A silent partner is judged on the capital they bring, so leave this blank unless a background genuinely matters to you.</span>
          </div>
        </div>

        <div class="cv-role-req hidden" id="cvActiveReqGroup">
          <div class="cv-role-req-head">Active Partner <small>Works in the business</small></div>
          <div class="form-group">
            <label class="form-label" for="cvRequirements">Active Partner Requirements</label>
            <textarea class="form-control form-textarea" id="cvRequirements" rows="3" placeholder="e.g. Minimum 2 years business experience, willingness to commit 5hrs/week..."></textarea>
          </div>
          <div class="form-group" id="cvSkillsGroup">
            <label class="form-label" for="cvRequiredSkills">Required Skills (Active Partners) *</label>
            <input class="form-control" id="cvRequiredSkills" data-chip-input
                   data-chip-placeholder="Type a skill and press Enter"
                   placeholder="e.g. Marketing, Sales, Excel (comma-separated)"/>
            <span class="form-error" id="cvRequiredSkillsErr"></span>
            <span class="form-hint" style="color:#5A6B85;font-size:0.8rem">Required. Every applicant is scored against this list and you are shown how many they match, so an empty list leaves you nothing to compare them on.</span>
          </div>
          <div class="form-group" id="cvDeadlineGroup">
            <label class="form-label">Active Partner Application Deadline</label>
            <input class="form-control" id="cvApplicationDeadline" type="date"/>
            <span class="form-hint" style="color:#5A6B85;font-size:0.8rem">Active-partner applicants will be reviewed after this date. Leave blank to accept active partners instantly, same as silent partners.</span>
          </div>
        </div>
        <?php // Partners is step 2: back to Basic Info (1), forward to Capital (3). ?>
        <div class="cv-nav"><button class="btn btn--secondary" onclick="goStep(1)">← Back</button><button class="btn btn--primary btn--lg" onclick="goStep(3)">Next: Capital Details →</button></div>
      </div>

      <!-- Step 4 -->
      <div class="cv-panel hidden" id="cvStep3">
        <h2>Review & Publish</h2>
        <p class="cv-subtitle">Confirm your listing details before going live</p>
        <div class="cv-review-card" id="reviewContent"></div>

        <div class="form-group" style="margin-top:1.25rem">
          <label class="form-label">Photos &amp; Video (optional)</label>
          <p style="font-size:0.78rem;color:#5A6B85;margin:0 0 0.5rem;">Show partners what the Asset actually looks like. The first image becomes the listing's cover. Images up to 8MB, video files up to 20MB, 10 items max — for longer videos paste a YouTube/Vimeo link from the listing page after publishing.</p>
          <input class="form-control" type="file" id="cvMediaInput" accept="image/png,image/jpeg,image/webp,image/gif,video/mp4,video/webm,video/quicktime" multiple/>
          <div id="cvMediaSelectedList" style="margin-top:0.5rem;display:flex;flex-direction:column;gap:0.3rem;"></div>
        </div>

        <div class="form-group" style="margin-top:1.25rem">
          <label class="form-label">Supporting Documents (PDF, optional)</label>
          <p style="font-size:0.78rem;color:#5A6B85;margin:0 0 0.5rem;">Upload a business plan, financial projections, or any PDF you'd like applicants to see on the listing's Documents section before they apply. Max 10MB per file.</p>
          <input class="form-control" type="file" id="cvDocsInput" accept="application/pdf" multiple/>
          <div id="cvDocsSelectedList" style="margin-top:0.5rem;display:flex;flex-direction:column;gap:0.3rem;"></div>
        </div>

        <div class="cv-consent">
          <label class="auth-checkbox"><input type="checkbox" id="cvTerms"/><span>I agree to Ventures Harbor's terms and confirm all information is accurate.</span></label>
          <label class="auth-checkbox"><input type="checkbox" id="cvPrivacy"/><span>I agree to Ventures Harbor's privacy policy.</span></label>
        </div>
        <div class="cv-nav"><button class="btn btn--secondary" onclick="goStep(2)">← Back</button><button class="btn btn--primary btn--xl" id="publishBtn" onclick="publishVenture()"> Publish Listing</button></div>
      </div>

    </div><!-- /cv-form-area -->

    <!-- Live Preview -->
    <div class="cv-preview-area vh-slim-scroll">
      <p class="cv-preview-label">Live Preview</p>
      <div class="vc" id="previewCard">
        <div class="vc-head">
          <div class="vc-icon" id="prevIcon" style="background:#EFF4FF;color:#2563EB"><svg class="vh-i" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/></svg></div>
          <div class="vc-meta">
            <div class="vc-name" id="prevTitle">Your Venture Title</div>
            <div class="vc-tags"><span class="vc-tag" id="prevIndustry">Industry</span><span class="vc-sep">|</span><span class="vc-tag" id="prevCity">City</span></div>
          </div>
        </div>
        <div class="vc-financials">
          <div class="vc-fin"><span class="vc-fin-val" id="prevTarget">₹0</span><span class="vc-fin-lbl">Target</span></div>
          <div class="vc-fin"><span class="vc-fin-val" id="prevMin">₹0</span><span class="vc-fin-lbl">Min. Ticket</span></div>
          <div class="vc-fin"><span class="vc-fin-val" id="prevContrib">₹0</span><span class="vc-fin-lbl">Founder</span></div>
        </div>
        <div class="vc-prog-bar"><div class="vc-prog-fill" style="width:0%;background:#2563EB"></div></div>
        <p class="vc-prog-label" style="color:#16a34a">Just Listed · 0% Funded</p>
        <div class="vc-stats"><span>0/25 Members</span><span id="prevPartner">Both Types</span></div>
        <div class="vc-actions">
          <button class="btn btn--primary btn--sm" disabled>Co-Own</button>
          <button class="btn btn--secondary btn--sm" disabled>View Details</button>
        </div>
      </div>
      <div class="cv-preview-tips">
        <p><svg class="vh-i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6M10 22h4"/></svg> <strong>Tips for a great listing:</strong></p>
        <ul>
          <li>Write a clear, compelling title</li>
          <li>Set realistic financial targets</li>
          <li>Be specific about partner requirements</li>
          <li>Add a detailed description</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
  <div class="modal">
    <div class="modal-header" style="flex-direction:column;text-align:center;padding:2rem">
      <div style="color:#2563EB;margin-bottom:0.5rem"><svg class="vh-i" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09"/><path d="M12 15 9 12a11 11 0 0 1 2-6.5C12.5 3.5 15 2 20 2c0 5-1.5 7.5-3.5 9A11 11 0 0 1 12 15Z"/><path d="M15 9h.01"/></svg></div>
      <h3 class="modal-title">Listing Published!</h3>
      <p style="color:#5A6B85;margin:0.5rem 0 0">Your listing is now live and visible across the marketplace.</p>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <a href="browse.php" class="btn btn--secondary">Browse Assets</a>
      <a href="../admin/dashboard.php" class="btn btn--primary">Go to Dashboard</a>
    </div>
  </div>
</div>

<script src="../assets/js/shared.js?v=70"></script>
<script src="../assets/js/vh-nav.js?v=1"></script>
<script src="../vendor/cropperjs/cropper.min.js?v=31"></script>
<script src="../assets/js/image-cropper.js?v=31"></script>
<script src="../assets/js/create-venture.js?v=74"></script>
</body>
</html>
