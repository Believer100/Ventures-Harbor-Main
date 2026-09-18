/* VENTURES HARBOR — CREATE VENTURE LOGIC (create-venture.js) */

let selectedVentureDocFiles = [];

let selectedVentureMediaFiles = [];

let selectedVentureLogoFile = null;

let existingVentureLogoUrl = null;

let editVentureId = null;

let loadedFounderType = null;

let gatewayLive = false;

const isShowcaseMode = !!document.querySelector('.cv-page[data-showcase="1"]');

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    // Before loadVentureForEdit: its setVal() announces the value with `change`,
    // and nothing is listening until the chip inputs are built.
    initChipInputs();

    const editParam = new URLSearchParams(window.location.search).get("edit");
    if (editParam) loadVentureForEdit(parseInt(editParam, 10));

    fetch("../api/payments.php?action=config")
        .then(r => r.json())
        .then(res => { gatewayLive = !!(res.success && res.gateway && res.gateway.enabled); })
        .catch(() => { gatewayLive = false; })
        .finally(syncPublishButton);

    const cvLogoInput = document.getElementById("cvLogoInput");
    if (cvLogoInput) {
        cvLogoInput.addEventListener("change", async () => {
            const errEl = document.getElementById("cvLogoErr");
            if (errEl) errEl.textContent = "";
            const picked = cvLogoInput.files[0];
            cvLogoInput.value = ""; // allow re-picking the same file
            if (!picked) return;

            const okTypes = ["image/jpeg", "image/png", "image/webp"];
            if (!okTypes.includes(picked.type)) {
                if (errEl) errEl.textContent = "Please choose a JPG, PNG or WEBP image.";
                return;
            }
            if (picked.size > 5 * 1024 * 1024) {
                if (errEl) errEl.textContent = "Image must be smaller than 5MB.";
                return;
            }

            const file = await VH.cropImage(picked, { aspectRatio: 1, title: "Adjust Asset image", maxWidth: 800, maxHeight: 800 });
            if (!file) return; // cancelled

            selectedVentureLogoFile = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.getElementById("cvLogoPreview");
                if (preview) preview.innerHTML = `<img src="${e.target.result}" alt=""/>`;
                const prevIcon = document.getElementById("prevIcon");
                if (prevIcon) prevIcon.innerHTML = `<img src="${e.target.result}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;"/>`;
            };
            reader.readAsDataURL(file);

            const removeBtn = document.getElementById("cvLogoRemove");
            if (removeBtn) removeBtn.classList.remove("hidden");
        });
    }

    const cvMediaInput = document.getElementById("cvMediaInput");
    if (cvMediaInput) {
        cvMediaInput.addEventListener("change", () => {
            selectedVentureMediaFiles = Array.from(cvMediaInput.files || []).slice(0, 10);
            const listEl = document.getElementById("cvMediaSelectedList");
            if (listEl) {
                listEl.innerHTML = selectedVentureMediaFiles.map(f =>
                    `<div style="font-size:0.8rem;color:#334155;">${f.type.startsWith("video/") ? VH.icon("video", 13) : VH.icon("image", 13)} ${f.name} (${(f.size / 1024).toFixed(0)} KB)</div>`
                ).join('');
            }
        });
    }

    const cvDocsInput = document.getElementById("cvDocsInput");
    if (cvDocsInput) {
        cvDocsInput.addEventListener("change", () => {
            selectedVentureDocFiles = Array.from(cvDocsInput.files || []);
            const listEl = document.getElementById("cvDocsSelectedList");
            if (listEl) {
                listEl.innerHTML = selectedVentureDocFiles.map(f =>
                    `<div style="font-size:0.8rem;color:#334155;">${VH.icon("file", 13)} ${f.name} (${(f.size / 1024).toFixed(0)} KB)</div>`
                ).join('');
            }
        });
    }

    // Set sidebars/avatars
    updateHeaderAndSidebar(user);

    // Initial character counter
    const cvDesc = document.getElementById("cvDesc");
    const descCount = document.getElementById("descCount");
    if (cvDesc && descCount) {
        cvDesc.addEventListener("input", () => {
            descCount.textContent = `${cvDesc.value.length}/200`;
        });
    }

    // Avatar Dropdown
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

    document.querySelectorAll("input[name='partnerType']").forEach(radio => {
        radio.addEventListener("change", () => {
            toggleSkillsField();
            toggleSilentCap();
            syncEquityAmounts();

            syncPreviewCard();
        });
    });
    toggleSkillsField();
    toggleSilentCap();
    syncEquityAmounts();

    // Keep the derived Total Equity cells in step with either half.
    ["cvFounderEquity", "cvFounderOpsEquity", "cvActiveEquity", "cvActiveOpsEquity", "cvSilentEquity"]
        .forEach(id => document.getElementById(id)?.addEventListener("input", syncEquityTotals));
    document.getElementById("cvFounderEquity")
        ?.addEventListener("input", setFounderEquityBaseline);
    ["cvActiveEquity", "cvSilentEquity"].forEach(id =>
        document.getElementById(id)?.addEventListener("input", setPartnerEquityBaseline));
});

function roiRangeLabel(exit) {
    const min = parseFloat(exit.expected_roi_min);
    const max = parseFloat(exit.expected_roi_max);
    if (isNaN(min) || isNaN(max)) return 'Not specified';
    const n = (v) => (v % 1 === 0 ? v.toFixed(0) : v.toFixed(1));
    return min === max ? `${n(min)}% per year` : `${n(min)}–${n(max)}% per year`;
}

function describeSilentCap(target, contrib) {
    const inr = (n) => '₹' + Math.round(n).toLocaleString('en-IN');
    const cap = parseFloat(document.getElementById("cvSilentCap")?.value || 0) || 0;
    const pool = Math.max(0, (target || 0) - Math.min(contrib || 0, target || 0));

    if (cap <= 0 || pool <= 0 || cap >= pool) {
        return 'No limit — silent partners may fill the whole target';
    }
    return `${inr(cap)} from silent partners · ${inr(pool - cap)} reserved for active partners`;
}

function toggleSilentCap() {
    const radio = document.querySelector("input[name='partnerType']:checked");
    const isBoth = (radio ? radio.value : 'both') === 'both';

    const wrap = document.getElementById("cvSilentCapWrap");
    if (wrap) wrap.classList.toggle("hidden", !isBoth);

    const input = document.getElementById("cvSilentCap");
    if (input && !isBoth) input.value = "";

    updateSilentCapHint();
}

function updateSilentCapHint() {
    const hint = document.getElementById("cvSilentCapHint");
    const input = document.getElementById("cvSilentCap");
    if (!hint || !input) return;

    const inr = (n) => '₹' + Math.round(n).toLocaleString('en-IN');
    const target = parseFloat(document.getElementById("cvTarget")?.value || 0) || 0;
    const contrib = parseFloat(document.getElementById("cvContrib")?.value || 0) || 0;
    const cap = parseFloat(input.value || 0) || 0;

    const pool = Math.max(0, target - Math.min(contrib, target));

    clearFieldError("cvSilentCap", "cvSilentCapErr");

    /* The gap rule, mirroring vh_resolve_silent_cap(): the active reserve (pool − cap)
       must be at least one whole ticket, and the cap itself must be at least one, or one
       side or the other advertises a role nobody could take. The server is the
       authority; this is so the founder sees the range while typing. */
    const ticket = parseFloat(document.getElementById("cvMinInv")?.value || 0) || 0;
    const maxCap = pool - ticket;

    if (target <= 0) {
        hint.innerHTML = 'Enter your total capital required above first — the limit is measured against it.';
        return;
    }
    if (ticket <= 0) {
        hint.innerHTML = 'Enter the minimum investment per member first — the limit is measured against it.';
        return;
    }
    if (pool < ticket * 2) {
        hint.innerHTML = `An Asset taking both partner types needs room for at least one of each, so partners `
                       + `must be able to bring at least <strong>${inr(ticket * 2)}</strong> between them. `
                       + `Right now they can bring ${inr(pool)}.`;
        return;
    }
    if (cap <= 0) {
        hint.innerHTML = `The total you will accept from silent partners — between <strong>${inr(ticket)}</strong> `
                       + `and <strong>${inr(maxCap)}</strong>. Whatever is left of the ${inr(pool)} from partners `
                       + `stays reserved for active partners.`;
        return;
    }
    if (cap > maxCap) {
        hint.innerHTML = `Too high. Partners bring ${inr(pool)} in total, and at least ${inr(ticket)} — one `
                       + `member's minimum investment — has to stay reserved for active partners, so the most `
                       + `silent partners can bring is <strong>${inr(maxCap)}</strong>.`;
        return;
    }
    if (cap < ticket) {
        hint.innerHTML = `Too low. Below <strong>${inr(ticket)}</strong> no silent partner could ever join, `
                       + `because their smallest allowed pledge would already exceed the limit.`;
        return;
    }
    hint.innerHTML = `Silent partners can bring up to <strong>${inr(cap)}</strong>. The remaining `
                   + `<strong>${inr(pool - cap)}</strong> is reserved for active partners — once silent reaches `
                   + `${inr(cap)}, only active partners can join.`;
}

function toggleSkillsField() {
    const partnerTypeRadio = document.querySelector("input[name='partnerType']:checked");
    const partnerType = partnerTypeRadio ? partnerTypeRadio.value : 'both';
    const silentOnly = partnerType === 'silent';
    const activeOnly = partnerType === 'active';

    // Each side's whole group goes, not just its skills box — asking a
    // silent-only listing what it wants from an active partner is a question it
    // has no role to answer.
    const activeGroup = document.getElementById("cvActiveReqGroup");
    if (activeGroup) activeGroup.classList.toggle("hidden", silentOnly);

    const silentGroup = document.getElementById("cvSilentReqGroup");
    if (silentGroup) silentGroup.classList.toggle("hidden", activeOnly);

    // Clear what is now hidden, so a founder who switches type cannot publish
    // requirements for partners the listing does not accept.
    const clear = (ids) => ids.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.value = "";
        // A chip input renders from its store, so clearing the store has to say so
        // — otherwise the switch to silent-only leaves the active chips on screen.
        el.dispatchEvent(new Event("change", { bubbles: true }));
    });
    if (silentOnly) clear(["cvRequirements", "cvRequiredSkills", "cvApplicationDeadline"]);
    if (activeOnly) clear(["cvSilentRequirements", "cvSilentRequiredSkills"]);

    if (silentOnly) clearFieldError("cvRequiredSkills", "cvRequiredSkillsErr");
}

/**
 * Step 2. The only thing this step insists on is the active side's skills —
 * every applicant is scored against that list, and the founder is shown the
 * match, so an empty list quietly removes the one comparison they were given.
 * A silent-only listing has no active role, so it is not asked.
 *
 * Requirements themselves stay optional on both sides: "none in particular" is
 * an honest answer, and the placeholder already shows what a good one looks
 * like. Mirrors the check in api/ventures.php's `create`, which — as with the
 * equity percentages — does NOT enforce it on `update`, so listings published
 * before the rule stay editable.
 */
function validatePartners() {
    const partnerTypeRadio = document.querySelector("input[name='partnerType']:checked");
    const partnerType = partnerTypeRadio ? partnerTypeRadio.value : 'both';

    clearFieldError("cvRequiredSkills", "cvRequiredSkillsErr");
    if (partnerType === 'silent') return null;

    const skills = document.getElementById("cvRequiredSkills");
    if (!skills || skills.value.trim() !== "") return null;

    return markFieldError("cvRequiredSkills", "cvRequiredSkillsErr",
        "List at least one skill you need from an active partner");
}

function updateHeaderAndSidebar(user) {
    const initials = user.avatar || user.name.split(" ").map(n => n[0]).join("").slice(0, 2).toUpperCase();
    const navAvatar = document.getElementById("navAvatar");
    const navAvatarName = document.getElementById("navAvatarName");

    if (navAvatar) VH.renderAvatarInto(navAvatar, user);
    if (navAvatarName) navAvatarName.textContent = user.name.split(" ")[0];
}

/* Required-field feedback */

const REDUCED_MOTION = window.matchMedia
    && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

// The easing itself lives in VH.scroll (shared.js) so this page and the join
// wizard cannot drift apart.
function smoothScrollTo(targetY, duration) {
    return VH.scroll.toY(targetY, duration);
}

/* ------------------------------------------------------------ chip input --- */
/*
 * "isko commas ma na deke single line mei" — the client, 6 Sep 2026. A skill
 * list typed as one comma-separated line gives no feedback that a skill was
 * actually registered, and a stray or missing comma silently merges two skills
 * into one that no applicant can ever match.
 *
 * THE STORED FORMAT DOES NOT CHANGE. The original <input> stays in the DOM as the
 * canonical value holder — still comma-joined, still `getElementById(id).value`,
 * still `explode(',', …)` server-side — and the chips are a view of it. That is
 * what keeps this a pure UI change: collectPayload(), the review panel, the edit
 * prefill, validatePartners() and the skill-match scoring all read exactly what
 * they read before, and nothing on the server knows this happened.
 *
 * It is also why the markup ships as a plain text input and is upgraded here
 * rather than being written as chips in the PHP: with JS broken the founder gets
 * a working comma field instead of an empty box.
 */
function initChipInputs() {
    document.querySelectorAll("input[data-chip-input]").forEach(setupChipInput);
}

function setupChipInput(store) {
    if (store.dataset.chipReady) return;
    store.dataset.chipReady = "1";

    const box = document.createElement("div");
    box.className = "chip-box";

    const entry = document.createElement("input");
    entry.type = "text";
    entry.className = "chip-entry";
    entry.placeholder = store.dataset.chipPlaceholder || store.placeholder || "";
    const label = document.querySelector(`label[for='${store.id}']`);
    entry.setAttribute("aria-label", label ? label.textContent.trim() : "Add an item");

    store.parentNode.insertBefore(box, store);
    box.appendChild(entry);
    store.type = "hidden";

    // The visible stand-ins for a now-hidden input, so the error styling, the
    // scroll-to-first-error and the focus all land somewhere a person can see.
    store.chipBox = box;
    store.chipEntry = entry;

    const read  = () => store.value.split(",").map(s => s.trim()).filter(Boolean);
    const write = (list) => { store.value = list.join(", "); };

    const render = () => {
        box.querySelectorAll(".chip").forEach(c => c.remove());
        read().forEach((name, i) => {
            const chip = document.createElement("span");
            chip.className = "chip";
            // textContent, never innerHTML: this is text the founder typed.
            chip.appendChild(document.createTextNode(name));

            const x = document.createElement("button");
            x.type = "button";
            x.className = "chip-x";
            x.setAttribute("aria-label", "Remove " + name);
            x.textContent = "×";
            x.addEventListener("click", () => {
                const list = read();
                list.splice(i, 1);
                write(list);
                render();
                entry.focus();
            });

            chip.appendChild(x);
            box.insertBefore(chip, entry);
        });
    };

    const commit = () => {
        const raw = entry.value;
        entry.value = "";
        if (!raw.trim()) return;

        const list = read();
        // Split on commas here too, so pasting "Marketing, Sales" still gives two
        // chips rather than one skill with a comma in the middle of it.
        raw.split(",").map(s => s.trim()).filter(Boolean).forEach((token) => {
            if (!list.some(existing => existing.toLowerCase() === token.toLowerCase())) {
                list.push(token);
            }
        });
        write(list);
        render();
        // What markFieldError() listens on to clear the error it set.
        store.dispatchEvent(new Event("input", { bubbles: true }));
    };

    entry.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === ",") {
            e.preventDefault();
            commit();
        } else if (e.key === "Backspace" && entry.value === "") {
            const list = read();
            if (list.length) { list.pop(); write(list); render(); }
        }
    });
    // Committing on blur is what stops a half-typed skill being silently dropped
    // when the founder clicks Next instead of pressing Enter.
    entry.addEventListener("blur", commit);
    box.addEventListener("click", (e) => { if (e.target === box) entry.focus(); });

    /* The two places that write to the store directly — the edit prefill's setVal()
       and toggleSkillsField()'s clear() — both fire `change`, so the chips follow
       the value rather than the component keeping a second copy of the truth. */
    store.addEventListener("change", render);

    render();
}

function clearFieldError(inputId, errId) {
    const input = document.getElementById(inputId);
    const err = document.getElementById(errId);
    // A chip input's store is hidden, so the outline has to go on its visible box.
    if (input) (input.chipBox || input).classList.remove("is-invalid");
    if (err) err.textContent = "";
}

function markFieldError(inputId, errId, message) {
    const input = document.getElementById(inputId);
    const err = document.getElementById(errId);
    if (err) err.textContent = message;
    if (input) {
        (input.chipBox || input).classList.add("is-invalid");

        if (!input.dataset.errBound) {
            input.dataset.errBound = "1";
            const clear = function () { clearFieldError(inputId, errId); };
            input.addEventListener("input", clear);
            input.addEventListener("change", clear);
        }
    }
    return input;
}

function revealFieldError(input) {
    if (!input) return;
    /* A chip input's store is type=hidden: it has a zero-size rect at the top of
       the document and cannot take focus, so scrolling and focusing it would send
       the founder to the top of the page instead of to the field. */
    const seen = input.chipBox || input;
    const focusable = input.chipEntry || input;
    const rect = seen.getBoundingClientRect();

    const targetY = Math.max(0, window.scrollY + rect.top - (window.innerHeight / 2) + (rect.height / 2));
    smoothScrollTo(targetY, 350).then(function () {
        try { focusable.focus({ preventScroll: true }); } catch (e) { focusable.focus(); }
    });
}

function validateBasicInfo() {
    const title = document.getElementById("cvTitle").value.trim();
    const desc = document.getElementById("cvDesc").value.trim();
    const ind = getIndustryValue();
    const city = document.getElementById("cvCity").value.trim();
    const state = document.getElementById("cvState").value;

    let firstInvalid = null;
    const fail = function (input) { if (input && !firstInvalid) firstInvalid = input; };

    clearFieldError("cvTitle", "cvTitleErr");
    clearFieldError("cvDesc", "cvDescErr");
    clearFieldError("cvIndustry", "cvIndustryErr");
    clearFieldError("cvIndustryOther", "cvIndustryErr");
    clearFieldError("cvCity", "cvCityErr");
    clearFieldError("cvState", "cvStateErr");
    const acErr = document.getElementById("cvAssetClassErr");
    if (acErr) acErr.textContent = "";

    if (!title) fail(markFieldError("cvTitle", "cvTitleErr", "Asset title is required"));
    if (!desc) fail(markFieldError("cvDesc", "cvDescErr", "Short description is required"));
    if (!ind) {
        const usingOther = document.getElementById("cvIndustry").value === "__other__";
        fail(markFieldError(
            usingOther ? "cvIndustryOther" : "cvIndustry",
            "cvIndustryErr",
            usingOther ? "Please type your industry" : "Please select an industry"
        ));
    }
    if (!selectedAssetClasses().length) {
        const err = document.getElementById("cvAssetClassErr");
        if (err) err.textContent = "Pick at least one Asset Class";
        fail(document.getElementById("cvAssetClassPicker"));
    }
    if (!state) fail(markFieldError("cvState", "cvStateErr", "Please select a state"));
    if (!city) fail(markFieldError("cvCity", "cvCityErr", "Please enter your city"));

    return firstInvalid;
}

/** Rupees for an inline error — short, and the same formatter the rest of the form uses. */
function inrShort(n) {
    return '₹' + Math.round(Number(n) || 0).toLocaleString('en-IN');
}

/* True when this is an EDIT of a listing that was published without a silent limit.
   Those are grandfathered by the server (see vh_resolve_silent_cap), so the form must
   not demand one either — the trap the equity percentages already taught us. Set by
   populateForEdit() from what the listing actually holds, never guessed. */
let editingSilentCapWasNull = false;
function editingVentureHadNoSilentCap() { return editingSilentCapWasNull; }

function validateCapital() {
    const target = document.getElementById("cvTarget").value;
    const minInv = document.getElementById("cvMinInv").value;

    const profitFreqEl = document.getElementById("cvProfitFrequency");
    const profitFreq = profitFreqEl ? profitFreqEl.value : "";

    let firstInvalid = null;
    const fail = function (input) { if (input && !firstInvalid) firstInvalid = input; };

    clearFieldError("cvTarget", "cvTargetErr");
    clearFieldError("cvMinInv", "cvMinInvErr");
    clearFieldError("cvProfitFrequency", "cvProfitFrequencyErr");
    clearFieldError("cvExpectedRoiMin", "cvExpectedRoiErr");
    clearFieldError("cvExpectedRoiMax", "cvExpectedRoiErr");

    if (!target || parseInt(target) <= 0) {
        fail(markFieldError("cvTarget", "cvTargetErr", "Total capital required is required"));
    }
    if (!minInv || parseInt(minInv) <= 0) {
        fail(markFieldError("cvMinInv", "cvMinInvErr", "Min. investment per member is required"));
    } else {
        // The headcount falls out of the ticket, so the cap is enforced as a floor under
        // it. Mirrors vh_min_investment_floor() on the server, which is the authority —
        // this only lets the founder see the number before submitting.
        const floor = minInvestmentFloor();
        if (!capitalStepLocked && floor > 0 && parseInt(minInv) < floor) {
            fail(markFieldError("cvMinInv", "cvMinInvErr", memberLimitMessage(floor)));
        }
    }
    if (!profitFreq) {
        fail(markFieldError("cvProfitFrequency", "cvProfitFrequencyErr", "Choose how often profit will be distributed"));
    }

    // The neutral flow does not ask for a silent capital cap. Keep the legacy
    // field in the payload, but never block the founder on this hidden setting.

    const roiMinRaw = (document.getElementById("cvExpectedRoiMin")?.value ?? "").trim();
    const roiMaxRaw = (document.getElementById("cvExpectedRoiMax")?.value ?? "").trim();
    const roiMin = parseFloat(roiMinRaw);
    const roiMax = parseFloat(roiMaxRaw);

    if (roiMinRaw === "" || roiMaxRaw === "" || isNaN(roiMin) || isNaN(roiMax)) {
        fail(markFieldError("cvExpectedRoiMin", "cvExpectedRoiErr", "Enter the expected ROI range (both figures)"));
    } else if (roiMin < 0 || roiMax < 0 || roiMin > 100 || roiMax > 100) {
        fail(markFieldError("cvExpectedRoiMin", "cvExpectedRoiErr", "ROI must be between 0% and 100%"));
    } else if (roiMin > roiMax) {
        fail(markFieldError("cvExpectedRoiMin", "cvExpectedRoiErr", "The lower ROI figure must come first"));
    }

    // The founder's own percentages stay optional — they are not used to work
    // anything out for a visitor — but a figure that IS entered has to be a real
    // percentage.
    clearFieldError("cvFounderEquity", "cvEquityErr");
    const equityFields = [
        ["cvFounderEquity", "Founder investment equity"],
        ["cvFounderOpsEquity", "Founder operations equity"],
        ["cvActiveEquity", "Active partner investment equity"],
        ["cvActiveOpsEquity", "Active partner operations equity"],
        ["cvSilentEquity", "Silent partner equity"]
    ];
    for (const [id, label] of equityFields) {
        const el = document.getElementById(id);
        const raw = (el?.value ?? "").trim();
        if (raw === "") continue;
        const pct = parseFloat(raw);
        if (isNaN(pct) || pct < 0 || pct > 100) {
            fail(markFieldError(id, "cvEquityErr", `${label} must be between 0% and 100%`));
            break;
        }
    }

    // The partner percentages, unlike the founder's, are REQUIRED on a new
    // listing — and only the investment half, since operations may genuinely be
    // 0. VH.equity quotes a visitor what their money buys by measuring it
    // against these, so a listing published without them shows no equity figure
    // anywhere: not in the join flow, not on the venture page. Almost every
    // early listing was published blank, which is why the calculator looked
    // missing rather than empty.
    //
    // Asked for on CREATE only. Existing listings are deliberately left alone —
    // forcing a percentage before an unrelated edit could be saved would be a
    // trap, and the ones already blank were published under the old rule.
    // Each half is within 0-100 by here, but the two together still have to
    // describe a real stake — 70 + 60 would otherwise publish as 130%.
    if (!firstInvalid) {
        for (const [investId, opsId, label] of EQUITY_PAIRS) {
            const invest = parseFloat((document.getElementById(investId)?.value ?? "").trim());
            const ops = parseFloat((document.getElementById(opsId)?.value ?? "").trim());
            if (isNaN(invest) || isNaN(ops)) continue;
            if (invest + ops > 100) {
                fail(markFieldError(investId, "cvEquityErr",
                    `${label} totals ${formatEquityPct(invest + ops)} — investment and operations together cannot exceed 100%`));
                break;
            }
        }
    }

    const salaryFields = [["cvFounderSalary", "Founder salary"], ["cvActiveSalary", "Active partner salary"]];
    for (const [id, label] of salaryFields) {
        const el = document.getElementById(id);
        const raw = (el?.value ?? "").trim();
        if (raw === "") continue;
        const amt = parseFloat(raw);
        if (isNaN(amt) || amt < 0) {
            fail(markFieldError(id, "cvEquityErr", `${label} cannot be negative`));
            break;
        }
    }

    return firstInvalid;
}

// Wizard Step Navigation
window.goStep = function(stepNum) {
    if (stepNum === 2) {
        const firstInvalid = validateBasicInfo();
        if (firstInvalid) { revealFieldError(firstInvalid); return; }
    }

    if (stepNum === 3) {
        const firstInvalid = validateCapital();
        if (firstInvalid) { revealFieldError(firstInvalid); return; }
    }

    // Toggle panels
    for (let i = 1; i <= 3; i++) {
        const panel = document.getElementById(`cvStep${i}`);
        const indicator = document.querySelector(`.cv-step[data-step="${i}"]`);

        if (i === stepNum) {
            if (panel) panel.classList.remove("hidden");
            if (indicator) indicator.classList.add("active");
        } else {
            if (panel) panel.classList.add("hidden");
            if (indicator) indicator.classList.remove("active");
        }
    }

    // Synchronize review card details in Step 4
    if (stepNum === 3) {
        syncPreviewCard();
        populateReviewCard();
        syncPublishButton();
    }

    // Land on the step's first field, not wherever the previous step's buttons
    // happened to leave the page.
    VH.scroll.toElementTop(document.querySelector(".cv-step-progress"));
};

window.updateCalc = function() {
    const target = parseFloat(document.getElementById("cvTarget").value || 0);
    const contrib = parseFloat(document.getElementById("cvContrib").value || 0);
    const minInv = parseFloat(document.getElementById("cvMinInv").value || 0);

    const remaining = Math.max(0, target - contrib);
    const progress = target > 0 ? Math.round((contrib / target) * 100) : 0;

    const feeRate = 0.005;

    const calcPartners = document.getElementById("calcPartners");
    const calcMembers = document.getElementById("calcMembers");
    const calcFee = document.getElementById("calcFee");
    if (calcPartners) calcPartners.textContent = `₹${remaining.toLocaleString('en-IN')}`;
    // Founder included, so it matches the label and the 25-member ceiling it is checked
    // against — members_count has always counted the founder too.
    if (calcMembers) calcMembers.textContent = membersNeeded();
    if (calcFee) calcFee.textContent = `₹${Math.round(minInv * feeRate).toLocaleString('en-IN')} (${(feeRate * 100)}%)`;

    const calcListingFee = document.getElementById("calcListingFee");
    if (calcListingFee) {
        calcListingFee.textContent = `₹${listingFeeAmount().toLocaleString('en-IN')}`;
    }

    syncPublishButton();

    updateSilentCapHint();
    paintMemberLimit();
    syncEquityAmounts();

    // Also sync the live preview card on the right
    syncPreviewCard();
};

/**
 * Roles whose equity is earned two ways — capital in, and work done. A silent
 * partner is absent by design: capital-only is what defines the role.
 */
const EQUITY_PAIRS = [
    ["cvFounderEquity", "cvFounderOpsEquity", "Founder equity"],
    ["cvActiveEquity", "cvActiveOpsEquity", "Active partner equity"]
];

function formatEquityPct(n) {
    return `${n % 1 === 0 ? n.toFixed(0) : n.toFixed(1)}%`;
}

/**
 * Total equity per role = investment + operations, recomputed as the founder
 * types. Derived and displayed only — never an input and never posted, so the
 * total the listing shows can't disagree with the two halves that make it.
 */
function syncEquityTotals() {
    const totalFor = (investId, opsId) => {
        const invest = parseFloat((document.getElementById(investId)?.value ?? "").trim());
        const ops = parseFloat((document.getElementById(opsId)?.value ?? "").trim());
        if (isNaN(invest) && isNaN(ops)) return null;
        return (isNaN(invest) ? 0 : invest) + (isNaN(ops) ? 0 : ops);
    };
    const show = (cellId, total) => {
        const el = document.getElementById(cellId);
        if (!el) return;
        el.textContent = total === null ? "—" : formatEquityPct(total);
        el.classList.toggle("is-empty", total === null);
    };

    show("cvEqFounderTotal", totalFor("cvFounderEquity", "cvFounderOpsEquity"));
    show("cvEqActiveTotal", totalFor("cvActiveEquity", "cvActiveOpsEquity"));

    // A silent partner has no operations half, so its total is just what was
    // typed — shown anyway so the column reads consistently down the table.
    const silent = parseFloat((document.getElementById("cvSilentEquity")?.value ?? "").trim());
    show("cvEqSilentTotal", isNaN(silent) ? null : silent);
}

/*
 * The founder's investment equity follows their own money, live in the form.
 *
 * A percentage only means anything next to the amount it was quoted against: 30% of the
 * company for 10L is a different deal from 30% for 5L. So when the founder changes Your
 * Contribution, the Investment Equity % moves with it and they watch it happen, instead
 * of saving and finding out later. `update` applies the same rule server-side; this is
 * the visible half, and the two agree because both scale from the same baseline.
 *
 * Anchored on a BASELINE rather than compounding off the field's current value. Typing
 * "1000000" fires seven input events, and scaling seven times in a row would produce
 * nonsense; recomputing from a fixed (contribution, percentage) pair every time is
 * idempotent, so the founder can type, backspace and retype freely.
 *
 * OPERATIONS equity is deliberately untouched — it is paid for the work the founder
 * does, not for the capital they put in, the same rule that stops an active partner's
 * operations equity scaling with their pledge.
 */
let founderEquityBaseline = null;   // { contrib, pct }

/*
 * The PARTNER percentages follow the minimum ticket, live in the form.
 *
 * Every partner percentage is quoted "per member at the minimum investment", so the ticket
 * is the unit they are measured in. Move the ticket without moving the percentage and the
 * founder silently changes the price of their company: 50,000 at 6% is 12% of equity per
 * 1L raised, 25,000 at 6% is 24%. The client's own example is the fix — "ab 25000 kardi
 * toh investment equity 3% per shift ho jaaye".
 *
 * Same baseline machinery as the founder's half, and separate from it: this one anchors on
 * (minimum, active %, silent %), that one on (contribution, founder %). Recomputing from a
 * fixed pair every time is what stops seven keystrokes compounding into nonsense.
 *
 * OPERATIONS equity is untouched — paid for work, not capital.
 */
let partnerEquityBaseline = null;   // { min, active, silent }

function setPartnerEquityBaseline() {
    const min = parseFloat(document.getElementById("cvMinInv")?.value);
    if (!isFinite(min) || min <= 0) { partnerEquityBaseline = null; return; }
    const num = (id) => {
        const v = parseFloat(document.getElementById(id)?.value);
        return isFinite(v) ? v : null;
    };
    partnerEquityBaseline = { min: min, active: num("cvActiveEquity"), silent: num("cvSilentEquity") };
}

function rescalePartnerEquity() {
    const b = partnerEquityBaseline;
    if (!b || !(b.min > 0)) return;

    const min = parseFloat(document.getElementById("cvMinInv")?.value);
    if (!isFinite(min) || min <= 0) return;

    [["cvActiveEquity", b.active], ["cvSilentEquity", b.silent]].forEach(([id, basePct]) => {
        if (basePct === null) return;
        const el = document.getElementById(id);
        if (!el || document.activeElement === el) return;   // never fight a half-typed number
        const scaled = Math.max(0, Math.min(100, Math.round(basePct * min / b.min * 100) / 100));
        const next = String(scaled);
        if (el.value !== next) {
            el.value = next;
            el.classList.add("cv-equity-autoscaled");
            setTimeout(() => el.classList.remove("cv-equity-autoscaled"), 900);
        }
    });
}

/** Re-anchor: the percentage now in the box is what the founder means at today's amount. */
function setFounderEquityBaseline() {
    const pct = parseFloat(document.getElementById("cvFounderEquity")?.value);
    const contrib = parseFloat(document.getElementById("cvContrib")?.value);
    founderEquityBaseline = (isFinite(pct) && isFinite(contrib) && contrib > 0)
        ? { contrib: contrib, pct: pct }
        : null;
}

function rescaleFounderEquity() {
    const b = founderEquityBaseline;
    const el = document.getElementById("cvFounderEquity");
    if (!b || !el) return;

    // Never fight the founder while their cursor is in the percentage box.
    if (document.activeElement === el) return;

    const contrib = parseFloat(document.getElementById("cvContrib")?.value);
    if (!isFinite(contrib) || contrib <= 0) return;

    const scaled = Math.max(0, Math.min(100, Math.round(b.pct * contrib / b.contrib * 100) / 100));
    const next = String(scaled);
    if (el.value !== next) {
        el.value = next;
        el.classList.add("cv-equity-autoscaled");
        clearTimeout(rescaleFounderEquity._t);
        rescaleFounderEquity._t = setTimeout(() => el.classList.remove("cv-equity-autoscaled"), 900);
    }
}

/**
 * The equity table shows the amount each row's percentage is measured against.
 * Those amounts are never re-entered — they mirror the Capital fields above, so
 * the table cannot state a figure the venture does not actually use.
 */
function syncEquityAmounts() {
    const contrib = parseFloat(document.getElementById("cvContrib")?.value || 0) || 0;
    const minInv = parseFloat(document.getElementById("cvMinInv")?.value || 0) || 0;
    const show = (id, amt) => {
        const el = document.getElementById(id);
        if (el) el.textContent = amt > 0 ? VH.card.money(amt) : "—";
    };
    show("cvEqFounderAmt", contrib);
    show("cvEqActiveAmt", minInv);
    show("cvEqSilentAmt", minInv);
    rescaleFounderEquity();
    rescalePartnerEquity();
    syncEquityTotals();

    // Only show the roles this venture actually accepts — an equity row for a
    // partner type nobody can be would invite a number that means nothing.
    const picked = document.querySelector("input[name='partnerType']:checked");
    const partnerType = picked ? picked.value : 'both';
    const rows = document.querySelectorAll("#cvEquityTable .cv-equity-row");
    if (rows.length === 3) {
        rows[1].classList.toggle("hidden", partnerType === 'silent');
        rows[2].classList.toggle("hidden", partnerType === 'active');
    }
}

/*
 * An Asset may not need more than MAX_MEMBERS partners.
 *
 * The founder never types a headcount — it falls out of the arithmetic: the partner pool
 * (target less their own contribution) divided by the minimum ticket is how many people
 * have to turn up. So the cap is enforced as a FLOOR under the minimum ticket.
 *
 * The 25 counts the FOUNDER, so there are 24 partner seats. His example: 1Cr target, 10L
 * from the founder, 90L from partners, and the ticket floors at 90,00,000 / 24 = 3,75,000.
 * Same rule for a silent-only listing and a both-types one — he was explicit on that too.
 *
 * Twin of vh_min_investment_floor() / vh_member_limit_message() in config/membership.php.
 * The server is the authority and refuses the save; this is so the founder sees it first.
 */
const MAX_MEMBERS = 25;                       // the whole room, founder included
const MAX_PARTNER_SLOTS = MAX_MEMBERS - 1;    // the founder has taken one

function partnerPool() {
    const target = parseFloat(document.getElementById("cvTarget")?.value || 0) || 0;
    const contrib = parseFloat(document.getElementById("cvContrib")?.value || 0) || 0;
    return Math.max(0, target - Math.min(Math.max(0, contrib), Math.max(0, target)));
}

function minInvestmentFloor() {
    const pool = partnerPool();
    return pool > 0 ? Math.ceil(pool / MAX_PARTNER_SLOTS) : 0;
}

/** Everyone in the room: the partners the ticket implies, plus the founder. */
function membersNeeded() {
    const pool = partnerPool();
    const minInv = parseFloat(document.getElementById("cvMinInv")?.value || 0) || 0;
    if (!(pool > 0) || !(minInv > 0)) return 0;
    return Math.ceil(pool / minInv) + 1;
}

function memberLimitMessage(floor) {
    return `Minimum investment per member must be at least ${VH.equity.inr(floor)}. `
         + `Maximum Member Limit: ${MAX_MEMBERS} (upper limit), to make venture exits `
         + `and operations easier for members.`;
}

/**
 * The live "Est. Members Needed" figure turns red the moment it goes over the cap, so the
 * founder sees the consequence while typing rather than on submit. Paired with the hint
 * under the field, which names the exact minimum they need.
 */
function paintMemberLimit() {
    const needed = membersNeeded();
    const floor = minInvestmentFloor();
    const over = needed > MAX_MEMBERS;

    const cell = document.getElementById("calcMembers");
    if (cell) cell.classList.toggle("cv-calc-over", over);

    const hint = document.getElementById("cvMinInvHint");
    if (hint) {
        if (floor <= 0) {
            hint.textContent = "";
            hint.classList.add("hidden");
        } else {
            hint.textContent = over
                ? memberLimitMessage(floor)
                : `At least ${VH.equity.inr(floor)} — an Asset can have at most ${MAX_MEMBERS} members, including you.`;
            hint.classList.toggle("cv-hint-error", over);
            hint.classList.remove("hidden");
        }
    }
}

function listingFeeAmount() {
    const contribEl = document.getElementById("cvContrib");
    const contrib = parseFloat((contribEl && contribEl.value) || 0);
    if (!contrib || contrib <= 0) return 0;
    return Math.round(contrib * 0.005);
}

function syncPublishButton() {
    const btn = document.getElementById("publishBtn");
    if (!btn || btn.disabled) return;   // mid-submit: leave "Publishing..." alone

    if (editVentureId) {
        btn.innerHTML = VH.icon("save", 15) + " Save Changes";
        return;
    }

    if (isShowcaseMode) {
        btn.innerHTML = VH.icon("rocket", 15) + " Publish Sample Listing";
        return;
    }
    const fee = listingFeeAmount();
    btn.innerHTML = (fee > 0 && gatewayLive)
        ? `${VH.icon("card", 15)} Pay ₹${fee.toLocaleString('en-IN')} &amp; Publish`
        : VH.icon("rocket", 15) + " Publish Listing";
}

// The asset class is picked for the founder from the industry, but stops being
// derived the moment they choose one themselves — otherwise changing the industry
// would silently overwrite a deliberate choice. One flag, set only by their own
// change event, is what separates the two cases.
let assetClassChosen = false;

// Mirrors VH_ASSET_CLASS_MAX in config/lookups.php. The server caps too — this is
// only so the founder sees the limit instead of losing a pick silently on save.
const ASSET_CLASS_MAX = 3;

window.markAssetClassChosen = function() {
    assetClassChosen = true;
};

// Mirrors vh_asset_class_for_industry() in config/lookups.php and the back-fill in
// migration step 49. Keep the three in step.
function assetClassForIndustry(industry) {
    const map = {
        "real estate": "real_estate",
        "infrastructure": "infrastructure",
        "renewable energy": "infrastructure"
    };
    return map[String(industry || "").trim().toLowerCase()] || "businesses";
}

function assetClassBoxes() {
    return Array.from(document.querySelectorAll('#cvAssetClassPicker input[name="assetClass"]'));
}

function selectedAssetClasses() {
    return assetClassBoxes().filter(b => b.checked).map(b => b.value);
}

// Mirrors vh_asset_class_slug(): a custom class has to slug identically on both
// sides or the same words become two different classes.
function assetClassSlug(label) {
    return String(label || "").trim().toLowerCase()
        .replace(/&/g, " and ")
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "")
        .slice(0, 40);
}

// Paint selection state and, once the cap is reached, grey out what is still
// unpicked — the limit should be visible before it is hit, not only after.
function paintAssetClasses() {
    const boxes = assetClassBoxes();
    const atCap = boxes.filter(b => b.checked).length >= ASSET_CLASS_MAX;
    boxes.forEach(b => {
        const chip = b.closest(".cv-class-chip");
        if (!chip) return;
        chip.classList.toggle("is-on", b.checked);
        const locked = atCap && !b.checked;
        chip.classList.toggle("is-locked", locked);
        b.disabled = locked;
    });
    const err = document.getElementById("cvAssetClassErr");
    if (err && boxes.some(b => b.checked)) err.textContent = "";
    syncPreviewCard();
}

window.addCustomAssetClass = function() {
    const input = document.getElementById("cvAssetClassCustom");
    const picker = document.getElementById("cvAssetClassPicker");
    if (!input || !picker) return;
    const raw  = input.value.trim();
    const slug = assetClassSlug(raw);
    if (!slug) return;

    const existing = assetClassBoxes().find(b => b.value === slug);
    if (existing) {                       // already offered - just select it
        if (!existing.checked && selectedAssetClasses().length < ASSET_CLASS_MAX) {
            existing.checked = true; assetClassChosen = true;
        }
        input.value = ""; paintAssetClasses(); return;
    }
    if (selectedAssetClasses().length >= ASSET_CLASS_MAX) {
        const err = document.getElementById("cvAssetClassErr");
        if (err) err.textContent = `You can pick up to ${ASSET_CLASS_MAX} classes. Remove one first.`;
        return;
    }
    const chip = document.createElement("label");
    chip.className = "cv-class-chip cv-class-chip--custom is-on";
    chip.innerHTML = `<input type="checkbox" name="assetClass" checked/><span></span>`;
    chip.querySelector("input").value = slug;
    chip.querySelector("span").textContent = raw;
    chip.querySelector("input").addEventListener("change", () => {
        assetClassChosen = true;
        // An unticked custom chip has nothing to go back to, so it is removed.
        if (!chip.querySelector("input").checked) chip.remove();
        paintAssetClasses();
    });
    picker.appendChild(chip);
    input.value = "";
    assetClassChosen = true;
    paintAssetClasses();
};

window.syncAssetClass = function() {
    if (assetClassChosen) return;
    const want = assetClassForIndustry(getIndustryValue());
    assetClassBoxes().forEach(b => { b.checked = (b.value === want); });
    paintAssetClasses();
};

function bindAssetClassPicker() {
    assetClassBoxes().forEach(b => b.addEventListener("change", () => {
        assetClassChosen = true;
        paintAssetClasses();
    }));
    const add = document.getElementById("cvAssetClassAdd");
    if (add) add.addEventListener("click", addCustomAssetClass);
    const input = document.getElementById("cvAssetClassCustom");
    if (input) input.addEventListener("keydown", e => {
        // Enter inside the create form would otherwise submit the step.
        if (e.key === "Enter") { e.preventDefault(); addCustomAssetClass(); }
    });
    paintAssetClasses();
}
document.addEventListener("DOMContentLoaded", bindAssetClassPicker);

window.toggleIndustryOther = function() {
    const sel = document.getElementById("cvIndustry");
    const other = document.getElementById("cvIndustryOther");
    if (!sel || !other) return;
    const isOther = sel.value === "__other__";
    other.classList.toggle("hidden", !isOther);
    if (isOther) other.focus(); else other.value = "";
    syncPreviewCard();
};

async function loadVentureForEdit(id) {
    if (!id) return;
    try {
        const res = await (await fetch(`../api/ventures.php?action=detail&id=${id}`)).json();
        if (!res.success || !res.venture) {
            VH.toast.error("Could not load that listing for editing.");
            return;
        }
        const v = res.venture;
        const user = VH.auth.getUser();
        // A sample listing belongs to the platform, so any admin may edit it.
        const isSample = String(v.is_showcase) === '1';
        const mayEdit = isSample
            ? (user && user.role === 'admin')
            : (user && String(v.founder_user_id) === String(user.id));
        if (!mayEdit) {
            VH.toast.error(isSample
                ? "Only an administrator can edit a sample listing."
                : "Only the founder can edit this listing.");
            setTimeout(() => {
                window.location.href = isSample ? "../admin/admin.php#showcase" : "../admin/dashboard.php";
            }, 1200);
            return;
        }
        editVentureId = id;

        // `change` so a chip input re-renders from the value it was just given —
        // without it, editing a listing shows an empty skills box holding data.
        const setVal = (elId, val) => {
            const el = document.getElementById(elId);
            if (!el) return;
            el.value = val ?? "";
            el.dispatchEvent(new Event("change", { bubbles: true }));
        };
        const setRadio = (name, val) => {
            const el = document.querySelector(`input[name='${name}'][value='${val}']`);
            if (el) el.checked = true;
        };

        if (isSample) {
            setVal("cvShowcaseFounder", v.founder_name);
            setVal("cvShowcaseSilent", v.showcase_raised_silent);
            setVal("cvShowcaseActive", v.showcase_raised_active);
        }

        setVal("cvTitle", v.title);
        setVal("cvDesc", v.description);
        setVal("cvFullDesc", v.full_description);
        setVal("cvUseOfFunds", v.use_of_funds);

        (function () {
            const loc = v.location || "";
            const stateSel = document.getElementById("cvState");
            const ci = loc.lastIndexOf(",");
            let cityPart = loc, statePart = "";
            if (ci > -1) {
                statePart = loc.slice(ci + 1).trim();
                cityPart = loc.slice(0, ci).trim();
            }
            const known = statePart && stateSel && Array.from(stateSel.options).some(o => o.value === statePart);
            if (known) {
                setVal("cvCity", cityPart);
                stateSel.value = statePart;
            } else {
                setVal("cvCity", loc);
                if (stateSel) stateSel.value = "";
            }
        })();
        setVal("cvTarget", v.target_capital);
        setVal("cvContrib", v.founder_contribution);
        setVal("cvMinInv", v.min_investment);
        setVal("cvSilentCap", v.silent_capital_limit);
        // Remember whether this listing predates the compulsory limit.
        editingSilentCapWasNull = !(parseInt(v.silent_capital_limit) > 0);
        setVal("cvDaysLeft", v.days_left);
        lockListingDuration(v);
        lockJoinedTerms(v);
        prefillExitTerms(v);
        loadedFounderType = v.founder_type || null;
        setVal("cvRequirements", v.requirements);
        setVal("cvRequiredSkills", v.required_skills);
        // NULL on a listing published before the split, so these simply start empty.
        setVal("cvSilentRequirements", v.silent_requirements);
        setVal("cvSilentRequiredSkills", v.silent_required_skills);
        setVal("cvApplicationDeadline", v.application_deadline);
        setRadio("partnerType", v.partner_types);

        const indSel = document.getElementById("cvIndustry");
        const known = indSel && Array.from(indSel.options).some(o => o.value === v.industry);
        if (known) {
            indSel.value = v.industry;
        } else if (indSel) {
            indSel.value = "__other__";
            const other = document.getElementById("cvIndustryOther");
            if (other) { other.classList.remove("hidden"); other.value = v.industry || ""; }
        }

        const storedClasses = String(v.asset_class || "").split(",")
            .map(x => assetClassSlug(x)).filter(Boolean);
        if (storedClasses.length) {
            const boxes = assetClassBoxes();
            boxes.forEach(b => { b.checked = storedClasses.indexOf(b.value) !== -1; });
            // Anything stored that the picker does not offer is a custom class.
            // Without this, editing a listing would silently drop it.
            const known = boxes.map(b => b.value);
            storedClasses.filter(c => known.indexOf(c) === -1).forEach(c => {
                const input = document.getElementById("cvAssetClassCustom");
                if (!input) return;
                input.value = c.replace(/_/g, " ").replace(/\b\w/g, m => m.toUpperCase());
                addCustomAssetClass();
            });
            assetClassChosen = true;
            paintAssetClasses();
        } else {
            syncAssetClass();
        }

        existingVentureLogoUrl = v.logo_url || null;
        if (v.logo_url) {
            const src = v.logo_url.startsWith("http") ? v.logo_url : "../" + v.logo_url;
            const preview = document.getElementById("cvLogoPreview");
            if (preview) preview.innerHTML = `<img src="${src}" alt=""/>`;
            const prevIcon = document.getElementById("prevIcon");
            if (prevIcon) prevIcon.innerHTML = `<img src="${src}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;"/>`;
        }

        const heading = document.querySelector(".cv-header h1");
        if (heading) heading.textContent = "Edit Listing";

        syncPublishButton();

        const descCount = document.getElementById("descCount");
        if (descCount) descCount.textContent = `${(v.description || "").length}/200`;

        updateCalc();

        toggleSilentCap();
        toggleSkillsField();
        VH.toast.info("Editing an existing listing — changes go live when you save.");
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error loading the listing.");
    }
}

window.clearVentureLogo = function() {
    selectedVentureLogoFile = null;
    const input = document.getElementById("cvLogoInput");
    if (input) input.value = "";
    const removeBtn = document.getElementById("cvLogoRemove");
    if (removeBtn) removeBtn.classList.add("hidden");
    const errEl = document.getElementById("cvLogoErr");
    if (errEl) errEl.textContent = "";

    const preview = document.getElementById("cvLogoPreview");
    const prevIcon = document.getElementById("prevIcon");
    if (existingVentureLogoUrl) {
        const src = existingVentureLogoUrl.startsWith("http") ? existingVentureLogoUrl : "../" + existingVentureLogoUrl;
        if (preview) preview.innerHTML = `<img src="${src}" alt=""/>`;
        if (prevIcon) prevIcon.innerHTML = `<img src="${src}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;"/>`;
    } else {
        if (preview) preview.textContent = "";
        if (prevIcon) prevIcon.textContent = "";
    }
    syncPreviewCard();
};

async function uploadVentureLogo(ventureId) {
    if (!ventureId || !selectedVentureLogoFile) return;
    try {
        const formData = new FormData();
        formData.append("action", "upload_logo");
        formData.append("venture_id", ventureId);
        formData.append("logo", selectedVentureLogoFile);

        const res = await (await fetch("../api/ventures.php", { method: "POST", body: formData })).json();
        if (!res.success) {
            VH.toast.error(res.message || "Listing saved, but the image could not be uploaded.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Listing saved, but the image upload failed.");
    }
}

function getIndustryValue() {
    const sel = document.getElementById("cvIndustry");
    if (!sel) return "";
    if (sel.value === "__other__") {
        return (document.getElementById("cvIndustryOther") || {}).value?.trim() || "";
    }
    return sel.value;
}

function getLocationValue() {
    const city = (document.getElementById("cvCity").value || "").trim();
    const stateEl = document.getElementById("cvState");
    const state = stateEl ? stateEl.value : "";
    if (city && state) return city + ", " + state;
    return city || state || "";
}

function syncPreviewCard() {
    const rawTitle = document.getElementById("cvTitle").value.trim();
    const title = rawTitle || "Your Venture Title";
    const ind = getIndustryValue() || "Industry";
    const city = getLocationValue() || "City";
    const target = parseFloat(document.getElementById("cvTarget").value || 0);
    const contrib = parseFloat(document.getElementById("cvContrib").value || 0);
    const minInv = parseFloat(document.getElementById("cvMinInv").value || 0);

    const partnerLabel = 'Open to any partner';

    const progress = target > 0 ? Math.round((contrib / target) * 100) : 0;

    const pIcon = document.getElementById("prevIcon");
    const pTitle = document.getElementById("prevTitle");
    const pIndustry = document.getElementById("prevIndustry");
    const pCity = document.getElementById("prevCity");
    const pTarget = document.getElementById("prevTarget");
    const pMin = document.getElementById("prevMin");
    const pContrib = document.getElementById("prevContrib");
    const pPartner = document.getElementById("prevPartner");
    const pProgFill = document.querySelector("#previewCard .vc-prog-fill");
    const pProgLabel = document.querySelector("#previewCard .vc-prog-label");
    const pStats = document.querySelector("#previewCard .vc-stats span:first-child");

    if (pIcon) {
        pIcon.style.background = '#f1f5f9';
        pIcon.style.color = '#0f172a';
        pIcon.style.fontWeight = '700';
        pIcon.style.overflow = 'hidden';
        if (!selectedVentureLogoFile && !existingVentureLogoUrl) {
            pIcon.textContent = VH.ventureInitial(rawTitle || 'Asset');
        }
    }
    const logoPreview = document.getElementById("cvLogoPreview");
    if (logoPreview && !selectedVentureLogoFile && !existingVentureLogoUrl) {
        logoPreview.textContent = VH.ventureInitial(rawTitle || 'Asset');
    }
    if (pTitle) pTitle.textContent = title;
    if (pIndustry) {
        pIndustry.textContent = ind;
        pIndustry.style.background = '#f1f5f9';
        pIndustry.style.color = '#0f172a';
    }
    if (pCity) pCity.textContent = city;

    if (pTarget) pTarget.textContent = VH.card.money(target);
    if (pMin) pMin.textContent = VH.card.money(minInv);
    if (pContrib) pContrib.textContent = VH.card.money(contrib);
    if (pPartner) pPartner.textContent = partnerLabel;
    if (pProgFill) { pProgFill.style.width = `${progress}%`; pProgFill.style.background = '#16a34a'; }
    if (pProgLabel) pProgLabel.textContent = `Just Listed · ${progress}% Funded`;
    if (pStats) pStats.textContent = `1 Member`;
}

function readListingDays() {
    const el = document.getElementById("cvDaysLeft");
    if (!el) return 15;
    const max = parseInt(el.getAttribute("data-max-days"), 10) || 30;
    const typed = parseInt(el.value, 10);
    if (!typed || typed < 1) return Math.min(15, max);
    return Math.min(typed, max);
}

function lockListingDuration(v) {
    const el = document.getElementById("cvDaysLeft");
    if (!el || v.status === "pending_payment") return;

    el.readOnly = true;
    el.tabIndex = -1;
    el.style.background = "#F1F5F9";
    el.style.color = "#64748B";
    el.style.cursor = "not-allowed";
    el.removeAttribute("list");

    const hint = document.getElementById("cvDaysHint");
    if (hint) {
        hint.innerHTML = VH.icon("lock", 13) + " The listing duration is fixed when a listing goes live and "
            + "cannot be changed by editing. If the listing runs out before it is fully "
            + "funded you can extend it once from your dashboard.";
    }
}

/**
 * Total Capital is frozen once anybody has joined — the visible half of the server guard
 * in api/ventures.php's `update`, exactly as lockListingDuration() is for the duration.
 *
 * The minimum ticket and the founder's own contribution stay editable on purpose: a
 * founder with days left needs to be able to lower either one to attract partners, and
 * since every member's equity is now frozen at the terms they joined on, neither edit can
 * reach back and change somebody's agreed share. Total Capital is the exception because
 * it is the denominator of the raise a partner already bought into.
 */
/**
 * What a founder may no longer change once a partner has money in — the visible half of
 * the server guard in api/ventures.php's `update`, as lockListingDuration() is for the
 * duration. Two fields:
 *
 *   Total Capital   the denominator of the raise a partner already bought into.
 *   Partner Type    the shape of the venture they chose to join. Flipping "both types"
 *                   to silent-only retires the role an active partner is already filling.
 *
 * The minimum ticket and the founder's own contribution deliberately stay open: that is
 * what a founder with days left actually needs, and neither can reach an existing
 * partner now that their equity is frozen at the terms they joined on.
 */
/* Once a partner has joined, the PARTNER TYPE and the whole CAPITAL step freeze.
   "Capital page ko complete unedit kar deta ha jese partner type kiya hua ha" — the
   client, 4 Sep 2026, and he confirmed the founder may not lower their own contribution
   either. The server ignores every one of these fields (vh_capital_step_columns), so
   this is the visible half, the twin of lockListingDuration().

   It disables the whole panel rather than naming fields one by one, because a field
   added to the Capital step later would otherwise look editable and then silently not
   save — and a list here would be a second place to remember. */
let capitalStepLocked = false;

function lockJoinedTerms(v) {
    if (!v || !v.terms_locked) return;
    capitalStepLocked = true;

    // Radios rather than a text field, so disable the inputs and mark the cards. A
    // disabled radio posts nothing, and the server ignores the field regardless.
    const cards = document.querySelectorAll(".cv-partner-card");
    cards.forEach(card => {
        const input = card.querySelector("input[name='partnerType']");
        if (!input) return;
        input.disabled = true;
        card.classList.add("cv-partner-card--locked");
        if (!input.checked) card.classList.add("cv-partner-card--dim");
    });
    const options = document.querySelector(".cv-partner-options");
    if (options && !document.getElementById("cvPartnerTypeLock")) {
        const note = document.createElement("p");
        note.id = "cvPartnerTypeLock";
        note.className = "cv-lock-note";
        note.innerHTML = VH.icon("lock", 13)
            + " Partner type is fixed once a partner joins — they chose this Asset on these"
            + " terms. Everything below it (requirements, skills, deadlines) can still be edited.";
        options.parentNode.insertBefore(note, options.nextSibling);
    }

    const capital = document.getElementById("cvStep3");
    if (!capital) return;

    capital.classList.add("cv-panel--locked");
    capital.querySelectorAll("input, select, textarea").forEach(el => {
        // readOnly keeps a text value selectable and copyable; a checkbox or radio has
        // no such thing, so those have to be disabled outright.
        if (el.type === "checkbox" || el.type === "radio" || el.tagName === "SELECT") {
            el.disabled = true;
        } else {
            el.readOnly = true;
        }
        el.tabIndex = -1;
    });

    // One note at the top of the step, not one per field — twenty locks reading the same
    // sentence is noise, and the founder needs the reason once.
    if (!document.getElementById("cvCapitalLock")) {
        const note = document.createElement("p");
        note.id = "cvCapitalLock";
        note.className = "cv-lock-note";
        note.innerHTML = VH.icon("lock", 13)
            + " <strong>These figures are fixed — a partner has joined this Asset.</strong>"
            + " The capital, the minimum per member, your own contribution, the equity table"
            + " and the exit terms are all part of the deal they agreed to, so none of them"
            + " can change now. Everything on the other steps — title, description,"
            + " requirements, skills, photos and documents — can still be edited.";
        const after = capital.querySelector(".cv-subtitle") || capital.querySelector("h2");
        if (after) after.parentNode.insertBefore(note, after.nextSibling);
        else capital.insertBefore(note, capital.firstChild);
    }
}

function listingFeeNoticeHTML() {
    if (editVentureId) return '';

    if (isShowcaseMode) return '';
    const fee = listingFeeAmount();
    if (fee <= 0) return '';

    const contrib = parseFloat(document.getElementById("cvContrib").value || 0);
    const feeStr = `₹${fee.toLocaleString('en-IN')}`;
    const line = gatewayLive
        ? `Payable now — you'll be taken to the payment page when you press
           <strong>Pay ${feeStr} &amp; Publish</strong>. Your Venture goes live the
           moment the payment succeeds.`
        : `This is recorded on your Payment Statement when the listing is published.`;

    return `
        <div style="border:1px solid #FBBF24;background:#FFFBEB;border-radius:10px;
                    padding:0.85rem 1rem;margin-bottom:1.1rem;font-size:0.9rem;line-height:1.5">
          <div style="font-weight:700;color:#92400E;margin-bottom:0.25rem">
            Listing fee: ${feeStr}
          </div>
          <div style="color:#78350F">
            0.5% of your own contribution of ₹${contrib.toLocaleString('en-IN')} —
            the same rate every partner pays on theirs. ${line}
            <strong>This fee is not refundable</strong>, including if you later delete this Venture.
          </div>
        </div>`;
}

function populateReviewCard() {
    const reviewEl = document.getElementById("reviewContent");
    if (!reviewEl) return;

    const title = document.getElementById("cvTitle").value.trim();
    const desc = document.getElementById("cvDesc").value.trim();
    const ind = getIndustryValue();
    const city = getLocationValue();
    const target = parseFloat(document.getElementById("cvTarget").value || 0);
    const contrib = parseFloat(document.getElementById("cvContrib").value || 0);
    const minInv = parseFloat(document.getElementById("cvMinInv").value || 0);
    const daysLeft = readListingDays();

    const partnerTypeRadio = document.querySelector("input[name='partnerType']:checked");
    const partnerType = partnerTypeRadio ? partnerTypeRadio.value : 'both';
    const requirements = document.getElementById("cvRequirements").value.trim();
    const requiredSkills = document.getElementById("cvRequiredSkills").value.trim();
    const silentRequirements = (document.getElementById("cvSilentRequirements")?.value || "").trim();
    const silentRequiredSkills = (document.getElementById("cvSilentRequiredSkills")?.value || "").trim();
    const applicationDeadline = document.getElementById("cvApplicationDeadline").value;
    const exit = readExitTerms();

    reviewEl.innerHTML = `
        ${listingFeeNoticeHTML()}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem 1.5rem;font-size:0.9rem;">
            <div><strong>Title</strong><p style="margin:0.2rem 0 0;color:#475569">${title || '—'}</p></div>
            <div><strong>Industry / City</strong><p style="margin:0.2rem 0 0;color:#475569">${ind || '—'} · ${city || '—'}</p></div>
            <div style="grid-column:1/-1"><strong>Description</strong><p style="margin:0.2rem 0 0;color:#475569">${desc || '—'}</p></div>
            <div><strong>Target Capital</strong><p style="margin:0.2rem 0 0;color:#475569">₹${target.toLocaleString('en-IN')}</p></div>
            <div><strong>Founder Contribution</strong><p style="margin:0.2rem 0 0;color:#475569">₹${contrib.toLocaleString('en-IN')}</p></div>
            <div><strong>Min. Investment</strong><p style="margin:0.2rem 0 0;color:#475569">₹${minInv.toLocaleString('en-IN')}</p></div>
            <div><strong>Listing Duration</strong><p style="margin:0.2rem 0 0;color:#475569">${daysLeft} days</p></div>
            <div><strong>Partner Type</strong><p style="margin:0.2rem 0 0;color:#475569;text-transform:capitalize">${partnerType}</p></div>
            ${partnerType !== 'both' ? '' : `
            <div><strong>Silent / Active Split</strong><p style="margin:0.2rem 0 0;color:#475569">${describeSilentCap(target, contrib)}</p></div>`}
            ${partnerType === 'silent' ? '' : `
            <div><strong>Active Partner Application Deadline</strong><p style="margin:0.2rem 0 0;color:#475569">${applicationDeadline || 'None (instant join)'}</p></div>`}
            ${partnerType === 'active' ? '' : `
            <div style="grid-column:1/-1"><strong>Silent Partner Requirements</strong><p style="margin:0.2rem 0 0;color:#475569">${silentRequirements || 'None specified'}</p></div>
            <div style="grid-column:1/-1"><strong>Required Skills (Silent Partners)</strong><p style="margin:0.2rem 0 0;color:#475569">${silentRequiredSkills || 'None specified'}</p></div>`}
            ${partnerType === 'silent' ? '' : `
            <div style="grid-column:1/-1"><strong>Active Partner Requirements</strong><p style="margin:0.2rem 0 0;color:#475569">${requirements || 'None specified'}</p></div>
            <div style="grid-column:1/-1"><strong>Required Skills (Active Partners)</strong><p style="margin:0.2rem 0 0;color:#475569">${requiredSkills || 'None specified'}</p></div>`}

            <div style="grid-column:1/-1;border-top:1px solid #E8ECF2;padding-top:0.75rem;margin-top:0.25rem"><strong>Investment &amp; Exit Terms</strong></div>
            <div><strong>Equity Distribution</strong><p style="margin:0.2rem 0 0;color:#475569">${EQUITY_LABELS[exit.equity_distribution] || 'Not specified'}</p></div>
            <div style="grid-column:1/-1"><strong>Equity &amp; Salary per Role</strong>${equityTableReviewHTML(exit, contrib, minInv, partnerType)}</div>
            <div><strong>Expected ROI</strong><p style="margin:0.2rem 0 0;color:#475569">${roiRangeLabel(exit)}${exit.expected_roi ? ` <span style="color:#7A8AA3">(${exit.expected_roi})</span>` : ""}</p></div>
            <div><strong>Minimum Holding Period</strong><p style="margin:0.2rem 0 0;color:#475569">${exit.lockin_period || 'Not specified'}</p></div>
            <div><strong>Expected Exit Timeline</strong><p style="margin:0.2rem 0 0;color:#475569">${exit.expected_exit_timeline || 'Not specified'}</p></div>
            <div style="grid-column:1/-1"><strong>Exit Options</strong><p style="margin:0.2rem 0 0;color:#475569">${exit.exit_options || 'Not specified'}</p></div>
            <div><strong>Early Exit</strong><p style="margin:0.2rem 0 0;color:#475569">${describeEarlyExit(exit)}</p></div>
            <div><strong>Ownership Transfer</strong><p style="margin:0.2rem 0 0;color:#475569">${describeTransfer(exit)}</p></div>
            <div><strong>Profit Distribution</strong><p style="margin:0.2rem 0 0;color:#475569">${PROFIT_FREQ_LABELS[exit.profit_distribution_frequency] || 'Not specified'}</p></div>
            <div><strong>Exit Valuation</strong><p style="margin:0.2rem 0 0;color:#475569">${VALUATION_LABELS[exit.exit_valuation_method] || 'Not specified'}</p></div>
            ${exit.exit_valuation_notes ? `<div style="grid-column:1/-1"><strong>Valuation Terms</strong><p style="margin:0.2rem 0 0;color:#475569">${exit.exit_valuation_notes}</p></div>` : ''}
        </div>
    `;
}

const EQUITY_LABELS = {
    capital_based: 'Capital-Based Share',
    equal_split: 'Equal Split',
    negotiated: 'Negotiated'
};
const PROFIT_FREQ_LABELS = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Annually',
    none: 'No regular distribution'
};
const VALUATION_LABELS = {
    fixed: 'Fixed valuation',
    business_valuation: 'Business valuation at exit',
    multiple: 'Revenue / Profit multiple',
    custom: 'Custom terms'
};
const APPROVAL_LABELS = {
    founder: 'Founder approval required',
    partners: 'Partner approval required',
    both: 'Founder and partner approval required'
};

function describeEarlyExit(exit) {
    if (exit.early_exit_allowed === null) return 'Not specified';
    if (exit.early_exit_allowed === 0) return 'Not allowed';
    const extras = [
        exit.early_exit_notice_period ? `${exit.early_exit_notice_period} notice` : '',
        exit.early_exit_conditions || ''
    ].filter(Boolean);
    return 'Allowed' + (extras.length ? ` — ${extras.join(' · ')}` : '');
}

/**
 * The review's read-only twin of the equity table. Renders nothing at all when
 * the founder filled none of it in, rather than three rows of "Not specified".
 */
function equityTableReviewHTML(exit, contrib, minInv, partnerType) {
    const money = (v) => (v === null || v === "" || v === undefined || parseFloat(v) <= 0)
        ? null : VH.card.money(parseFloat(v));

    // Total plus the split that produced it, e.g. "50%" over
    // "30% investment + 20% operations".
    const equity = (investRaw, opsRaw) => {
        const invest = parseFloat(investRaw);
        const ops = parseFloat(opsRaw);
        if (isNaN(invest) && isNaN(ops)) return null;
        const total = (isNaN(invest) ? 0 : invest) + (isNaN(ops) ? 0 : ops);
        const parts = [];
        if (!isNaN(invest)) parts.push(`${formatEquityPct(invest)} investment`);
        if (!isNaN(ops)) parts.push(`${formatEquityPct(ops)} operations`);
        return `<strong>${formatEquityPct(total)}</strong>`
             + (parts.length > 1 ? `<br><span style="font-size:0.78rem;color:#8A98AC">${parts.join(' + ')}</span>` : '');
    };

    const rows = [
        ['Founder', contrib, equity(exit.founder_equity_percent, exit.founder_ops_equity_percent), money(exit.founder_monthly_salary), true],
        ['Active Partner', minInv, equity(exit.active_equity_percent, exit.active_ops_equity_percent), money(exit.active_monthly_salary), partnerType !== 'silent'],
        ['Silent Partner', minInv, equity(exit.silent_equity_percent, null), null, partnerType !== 'active']
    ].filter(r => r[4]);

    if (!rows.some(r => r[2] || r[3])) {
        return '<p style="margin:0.2rem 0 0;color:#475569">Not specified</p>';
    }

    const cells = rows.map(([label, amt, equityHTML, salary]) => `
        <tr>
          <td style="padding:0.3rem 0.6rem 0.3rem 0;color:#0F1D2E;font-weight:600">${label}</td>
          <td style="padding:0.3rem 0.6rem;color:#475569">${amt > 0 ? VH.card.money(amt) : '—'}</td>
          <td style="padding:0.3rem 0.6rem;color:#475569">${equityHTML || '—'}</td>
          <td style="padding:0.3rem 0;color:#475569">${
            label === 'Silent Partner' ? '<em style="color:#8A98AC">No salary</em>' : (salary || '—')
          }</td>
        </tr>`).join('');

    return `<table style="width:100%;border-collapse:collapse;margin-top:0.35rem;font-size:0.85rem">
        <tr style="color:#8A98AC;font-size:0.7rem;text-transform:uppercase;letter-spacing:.05em">
          <th style="text-align:left;padding:0 0.6rem 0.2rem 0">Role</th>
          <th style="text-align:left;padding:0 0.6rem 0.2rem">Investment</th>
          <th style="text-align:left;padding:0 0.6rem 0.2rem">Equity</th>
          <th style="text-align:left;padding:0 0 0.2rem">Salary</th>
        </tr>${cells}</table>`;
}

function describeTransfer(exit) {
    if (exit.ownership_transfer_allowed === null) return 'Not specified';
    if (exit.ownership_transfer_allowed === 0) return 'Not allowed';
    const approval = APPROVAL_LABELS[exit.ownership_transfer_approval];
    return 'Allowed' + (approval ? ` — ${approval}` : '');
}

// Shared by readExitTerms() and publishVenture() — keep it at module scope, or a
// caller outside readExitTerms throws a ReferenceError the publish catch reports
// as a network failure.
function cvVal(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function readExitTerms() {
    const val = cvVal;
    const radio = (name) => {
        const el = document.querySelector(`input[name='${name}']:checked`);
        return el ? parseInt(el.value, 10) : null;
    };

    const lockinSel = val("cvLockin");
    const lockin = lockinSel === 'custom' ? val("cvLockinCustom") : lockinSel;

    const options = Array.from(document.querySelectorAll("input[name='exitOption']:checked"))
        .map(cb => {
            if (cb.value !== 'Other') return cb.value;
            const other = val("cvExitOtherText");
            return other ? `Other: ${other}` : 'Other';
        });

    const earlyExitAllowed = radio("earlyExit");
    const transferAllowed = radio("transferAllowed");

    return {
        equity_distribution: val("cvEquityDistribution") || null,
        // The two halves are posted; the total is derived server-side and on
        // display, so it is never sent and never stored.
        founder_equity_percent: val("cvFounderEquity") || null,
        founder_ops_equity_percent: val("cvFounderOpsEquity") || null,
        founder_monthly_salary: val("cvFounderSalary") || null,
        active_equity_percent: val("cvActiveEquity") || null,
        active_ops_equity_percent: val("cvActiveOpsEquity") || null,
        active_monthly_salary: val("cvActiveSalary") || null,
        silent_equity_percent: val("cvSilentEquity") || null,
        expected_roi: val("cvExpectedRoi") || null,
        expected_roi_min: val("cvExpectedRoiMin") || null,
        expected_roi_max: val("cvExpectedRoiMax") || null,
        lockin_period: lockin || null,
        expected_exit_timeline: val("cvExitTimeline") || null,
        exit_options: options.length ? options.join(', ') : null,
        early_exit_allowed: earlyExitAllowed,

        early_exit_notice_period: earlyExitAllowed === 1 ? (val("cvEarlyExitNotice") || null) : null,
        early_exit_conditions: earlyExitAllowed === 1 ? (val("cvEarlyExitConditions") || null) : null,
        ownership_transfer_allowed: transferAllowed,
        ownership_transfer_approval: transferAllowed === 1 ? (val("cvTransferApproval") || null) : null,
        profit_distribution_frequency: val("cvProfitFrequency") || null,
        exit_valuation_method: val("cvExitValuation") || null,
        exit_valuation_notes: val("cvExitValuationNotes") || null
    };
}

function prefillExitTerms(v) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ""; };

    set("cvEquityDistribution", v.equity_distribution);
    set("cvFounderEquity", v.founder_equity_percent);
    set("cvFounderOpsEquity", v.founder_ops_equity_percent);
    set("cvFounderSalary", v.founder_monthly_salary);
    set("cvActiveEquity", v.active_equity_percent);
    set("cvActiveOpsEquity", v.active_ops_equity_percent);
    set("cvActiveSalary", v.active_monthly_salary);
    set("cvSilentEquity", v.silent_equity_percent);
    // Anchor the rescale on the terms as published, so reopening the form and changing
    // nothing changes nothing -- the first pass computes pct * contrib/contrib.
    setFounderEquityBaseline();
    setPartnerEquityBaseline();
    syncEquityAmounts();
    set("cvExpectedRoi", v.expected_roi);
    set("cvExpectedRoiMin", v.expected_roi_min);
    set("cvExpectedRoiMax", v.expected_roi_max);

    const lockinSel = document.getElementById("cvLockin");
    const lockinCustom = document.getElementById("cvLockinCustom");
    if (lockinSel && lockinCustom) {
        const stored = v.lockin_period || "";
        const isPreset = Array.from(lockinSel.options).some(o => o.value === stored && o.value !== 'custom');
        if (!stored) {
            lockinSel.value = "";
        } else if (isPreset) {
            lockinSel.value = stored;
        } else {
            lockinSel.value = "custom";
            lockinCustom.value = stored;
            lockinCustom.classList.remove("hidden");
        }
    }

    set("cvExitTimeline", v.expected_exit_timeline);
    set("cvProfitFrequency", v.profit_distribution_frequency);
    set("cvExitValuation", v.exit_valuation_method);
    set("cvExitValuationNotes", v.exit_valuation_notes);

    const stored = (v.exit_options || "").split(",").map(s => s.trim()).filter(Boolean);
    document.querySelectorAll("input[name='exitOption']").forEach(cb => {
        cb.checked = stored.some(s => s === cb.value || s.startsWith(cb.value + ":"));
    });
    const otherEntry = stored.find(s => s.startsWith("Other:"));
    if (otherEntry) {
        const otherText = document.getElementById("cvExitOtherText");
        if (otherText) {
            otherText.value = otherEntry.slice("Other:".length).trim();
            otherText.classList.remove("hidden");
        }
    }

    const checkRadio = (name, value) => {
        if (value === null || value === undefined || value === "") return;
        const el = document.querySelector(`input[name='${name}'][value='${Number(value)}']`);
        if (el) el.checked = true;
    };
    checkRadio("earlyExit", v.early_exit_allowed);
    checkRadio("transferAllowed", v.ownership_transfer_allowed);
    set("cvEarlyExitNotice", v.early_exit_notice_period);
    set("cvEarlyExitConditions", v.early_exit_conditions);
    set("cvTransferApproval", v.ownership_transfer_approval);

    syncExitTermsVisibility();
}

function syncExitTermsVisibility() {
    const toggle = (id, show) => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle("hidden", !show);
    };

    const lockinSel = document.getElementById("cvLockin");
    toggle("cvLockinCustom", !!lockinSel && lockinSel.value === "custom");

    const otherBox = document.getElementById("cvExitOther");
    toggle("cvExitOtherText", !!otherBox && otherBox.checked);

    const early = document.querySelector("input[name='earlyExit']:checked");
    toggle("cvEarlyExitFields", !!early && early.value === "1");

    const transfer = document.querySelector("input[name='transferAllowed']:checked");
    toggle("cvTransferFields", !!transfer && transfer.value === "1");
}

document.addEventListener("DOMContentLoaded", () => {
    const formArea = document.querySelector(".cv-form-area") || document;
    formArea.addEventListener("change", (e) => {
        if (e.target.matches("#cvLockin, #cvExitOther, input[name='earlyExit'], input[name='transferAllowed']")) {
            syncExitTermsVisibility();
        }
    });
});

window.publishVenture = async function() {
    const user = VH.auth.getUser();
    if (!user) return;

    const stepChecks = [
        { step: 1, run: validateBasicInfo },
        { step: 2, run: validateCapital }
    ];
    for (const check of stepChecks) {
        const firstInvalid = check.run();
        if (firstInvalid) {
            VH.toast.error("Please fill in the required fields before publishing.");
            goStep(check.step);
            revealFieldError(firstInvalid);
            return;
        }
    }

    const termsCheckbox = document.getElementById("cvTerms");
    if (termsCheckbox && !termsCheckbox.checked) {
        VH.toast.error("You must agree to the Terms of Service to publish.");
        return;
    }

    const showcaseFounderEl = document.getElementById("cvShowcaseFounder");
    if (isShowcaseMode && showcaseFounderEl && !showcaseFounderEl.value.trim()) {
        VH.toast.error("Enter the founder name to show on this sample listing.");
        goStep(1);
        revealFieldError(showcaseFounderEl);
        return;
    }

    const title = document.getElementById("cvTitle").value.trim();
    const desc = document.getElementById("cvDesc").value.trim();
    const fullDesc = document.getElementById("cvFullDesc").value.trim();
    const useOfFunds = document.getElementById("cvUseOfFunds").value.trim();
    const industry = getIndustryValue();
    const assetClass = selectedAssetClasses();
    const location = getLocationValue();

    const founderType = (editVentureId && loadedFounderType)
        ? loadedFounderType
        : (document.querySelector('.cv-locked-field strong')?.textContent || '').includes('Company')
            ? 'brand'
            : 'individual';

    const target = parseFloat(document.getElementById("cvTarget").value || 0);
    const contrib = parseFloat(document.getElementById("cvContrib").value || 0);
    const minInv = parseFloat(document.getElementById("cvMinInv").value || 0);
    const daysLeft = readListingDays();
    const exitTerms = readExitTerms();

    const partnerType = 'both';
    const requirements = document.getElementById("cvRequirements").value.trim();
    const requiredSkills = document.getElementById("cvRequiredSkills").value.trim();
    const applicationDeadline = document.getElementById("cvApplicationDeadline").value;

    const isEdit = !!editVentureId;
    const publishBtn = document.getElementById("publishBtn");
    publishBtn.textContent = isEdit ? "Saving..." : "Publishing...";
    publishBtn.disabled = true;

    try {
        const response = await fetch("../api/ventures.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: isEdit ? "update" : "create",
                venture_id: editVentureId,
                user_id: user.id,
                title,
                description: desc,
                full_description: fullDesc,
                use_of_funds: useOfFunds,
                industry,
                asset_class: assetClass,
                location,
                target_capital: target,
                min_investment: minInv,

                silent_capital_limit: parseFloat(document.getElementById("cvSilentCap")?.value || 0) || 0,
                founder_contribution: contrib,
                days_left: daysLeft,
                partner_type: partnerType,
                requirements,
                required_skills: requiredSkills,
                silent_requirements: (document.getElementById("cvSilentRequirements")?.value || "").trim(),
                silent_required_skills: (document.getElementById("cvSilentRequiredSkills")?.value || "").trim(),
                application_deadline: applicationDeadline || null,
                // Ignored by the API unless the session is an admin's.
                is_showcase: isShowcaseMode ? 1 : 0,
                showcase_founder_name: showcaseFounderEl ? showcaseFounderEl.value.trim() : '',

                showcase_raised_silent: cvVal("cvShowcaseSilent") || null,
                showcase_raised_active: cvVal("cvShowcaseActive") || null,

                ...exitTerms
            })
        });
        const res = await response.json();
        if (res.success) {
            await uploadVentureLogo(res.id);
            await uploadSelectedVentureMedia(res.id);
            await uploadSelectedVentureDocs(res.id);

            if (!isEdit && res.needs_payment) {
                VH.toast.success("Listing saved. Redirecting to payment...");
                publishBtn.textContent = "Redirecting to payment...";
                const started = await startListingFeePayment(res.id);
                if (!started) {
                    publishBtn.disabled = false;
                    syncPublishButton();
                }
                return;
            }

            if (res.is_showcase || (isEdit && isShowcaseMode)) {
                VH.toast.success(res.message || (isEdit ? "Sample listing updated." : "Sample listing published."));
                setTimeout(() => { window.location.href = "../admin/admin.php#showcase"; }, 1100);
            } else if (isEdit) {
                VH.toast.success("Listing updated successfully!");
                setTimeout(() => { window.location.href = `venture-detail.php?id=${res.id}`; }, 900);
            } else {
                VH.toast.success("Listing successfully published!");
                VH.modal.open("successModal");
            }
        } else {
            VH.toast.error(res.message || (isEdit ? "Failed to save changes." : "Failed to publish listing."));
            publishBtn.disabled = false;
            syncPublishButton();
        }
    } catch (err) {
        console.error(err);
        // A bug in this function and a server fatal both land here. Reporting
        // both as "network error" sent two real faults to the client under a
        // message that named neither, so say which one it was.
        VH.toast.error(
            err instanceof TypeError
                ? "Network error publishing listing. Check your connection and try again."
                : `Could not publish: ${err.message || err}. Please report this.`
        );
        publishBtn.disabled = false;
        syncPublishButton();
    }
};

async function uploadSelectedVentureMedia(ventureId) {
    if (!ventureId || !selectedVentureMediaFiles.length) return;

    for (const file of selectedVentureMediaFiles) {
        try {
            const formData = new FormData();
            formData.append("action", "upload");
            formData.append("venture_id", ventureId);
            formData.append("media", file);

            const response = await fetch("../api/venture_media.php?action=upload", {
                method: "POST",
                body: formData
            });
            const res = await response.json();
            if (!res.success) {
                VH.toast.error(`Failed to upload ${file.name}: ${res.message || 'unknown error'}`);
            }
        } catch (err) {
            console.error(err);
            VH.toast.error(`Network error uploading ${file.name}.`);
        }
    }
}

async function uploadSelectedVentureDocs(ventureId) {
    if (!ventureId || !selectedVentureDocFiles.length) return;

    for (const file of selectedVentureDocFiles) {
        try {
            const formData = new FormData();
            formData.append("venture_id", ventureId);
            formData.append("title", file.name.replace(/\.pdf$/i, ""));
            formData.append("doc", file);

            const response = await fetch("../api/venture_documents.php?action=upload", {
                method: "POST",
                body: formData
            });
            const res = await response.json();
            if (!res.success) {
                VH.toast.error(`Failed to upload ${file.name}: ${res.message || 'unknown error'}`);
            }
        } catch (err) {
            console.error(err);
            VH.toast.error(`Network error uploading ${file.name}.`);
        }
    }
}

async function startListingFeePayment(ventureId) {
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
            return false;
        }

        postToPaymentGateway(res.endpoint, res.fields);
        return true;
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error starting the listing-fee payment.");
        return false;
    }
}

function postToPaymentGateway(endpoint, fields) {
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
