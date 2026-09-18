/* VENTURES HARBOR — VENTURE DETAIL LOGIC (venture-detail.js) */

let currentVenture = null;

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();

    if (user) {
        const navAvatar = document.getElementById("navAvatar");
        const navAvatarName = document.getElementById("navAvatarName");
        if (navAvatar) VH.renderAvatarInto(navAvatar, user);
        if (navAvatarName) navAvatarName.textContent = user.name.split(" ")[0];
    } else {
        const avatarBtn = document.getElementById("avatarBtn");
        if (avatarBtn) {
            avatarBtn.outerHTML = `<a href="../users/auth.php" class="vh-nav-signin">Sign In</a>`;
        }
    }

    // Avatar dropdown toggle
    const avatarBtn = document.getElementById("avatarBtn");
    const navDropdown = document.getElementById("navDropdown");
    if (avatarBtn && navDropdown) {
        avatarBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            navDropdown.classList.toggle("open");
        });
        document.addEventListener("click", () => navDropdown.classList.remove("open"));
    }

    // Tabs
    const tabBtns = document.querySelectorAll(".tab-btn[data-tab]");
    const panels = document.querySelectorAll(".tab-panel[data-panel]");
    tabBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            tabBtns.forEach(b => b.classList.remove("active"));
            panels.forEach(p => p.classList.remove("active"));
            btn.classList.add("active");
            const panel = document.querySelector(`.tab-panel[data-panel="${btn.dataset.tab}"]`);
            if (panel) panel.classList.add("active");

            try {
                const url = new URL(window.location.href);
                url.searchParams.set("tab", btn.dataset.tab);
                history.replaceState(null, "", url);
            } catch (e) { /* older browsers just don't get the deep link */ }

            if (btn.dataset.tab === 'chat') {
                startChatIfMember();
            } else {
                stopChatPolling();
            }

            if (btn.dataset.tab === 'qna') loadQuestions();
        });
    });

    // Get venture ID from URL
    const params = new URLSearchParams(window.location.search);
    const ventureId = parseInt(params.get('id') || 0);

    if (!ventureId) {
        VH.toast.error("Asset ID is required.");
        setTimeout(() => { window.location.href = "browse.php"; }, 1200);
        return;
    }


    loadQuestions();
    initQnaComposer();

    /* ?tab= is applied only AFTER the detail fetch has answered, and that ordering is
       the whole point. Two tabs cannot decide anything about themselves until it has:

         - Group Chat gates on `isCurrentUserMember`, which is derived from the members
           list the fetch returns. Activated any earlier it read the initial `false` and
           showed "Group Chat is for Members Only" — to the founder and to every real
           member — and nothing re-ran the gate afterwards, so the lock stayed until the
           visitor clicked away and back. Following a notification or a dashboard chip
           straight to ?tab=chat hit this every single time.
         - Applications starts `hidden` in the markup and is only revealed for the
           founder once the fetch says so, so the guard below skipped it and the founder
           landed on Overview instead.

       Deferring costs one round trip on a deep link and nothing at all otherwise. */
    const wantTabRaw = params.get("tab");
    // "founder" was its own tab until the Founder Info card moved inside Members,
    // so a bookmarked or shared ?tab=founder link must still land somewhere.
    const wantTab = wantTabRaw === "founder" ? "members" : wantTabRaw;
    const applyDeepLinkTab = () => {
        if (!wantTab) return;
        const target = document.querySelector(`.tab-btn[data-tab="${CSS.escape(wantTab)}"]`);
        if (target && !target.classList.contains("hidden")) target.click();
    };

    fetchVentureDetail(ventureId).then(applyDeepLinkTab, applyDeepLinkTab);

    // Founder-only: upload a new venture document PDF
    const ventureDocFileInput = document.getElementById("ventureDocFileInput");
    if (ventureDocFileInput) {
        ventureDocFileInput.addEventListener("change", async () => {
            const file = ventureDocFileInput.files[0];
            if (!file || !currentVentureId) return;

            try {
                const formData = new FormData();
                formData.append("venture_id", currentVentureId);
                formData.append("title", file.name.replace(/\.pdf$/i, ""));
                formData.append("doc", file);

                const response = await fetch("../api/venture_documents.php?action=upload", {
                    method: "POST",
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    VH.toast.success("Document uploaded.");
                    loadVentureSpecificDocuments(currentVentureId, currentUserCanEditContent);
                } else {
                    VH.toast.error(res.message || "Failed to upload document.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error uploading document.");
            } finally {
                ventureDocFileInput.value = "";
            }
        });
    }

    const editLogoBtn = document.getElementById("editLogoBtn");
    const logoFileInput = document.getElementById("logoFileInput");
    if (editLogoBtn && logoFileInput) {
        editLogoBtn.addEventListener("click", () => logoFileInput.click());
        logoFileInput.addEventListener("change", async () => {
            const picked = logoFileInput.files[0];
            logoFileInput.value = "";
            if (!picked || !currentVentureId) return;

            if (!picked.type.startsWith("image/")) {
                VH.toast.error("Please choose an image file.");
                return;
            }
            if (picked.size > 5 * 1024 * 1024) {
                VH.toast.error("Image must be smaller than 5MB.");
                return;
            }

            // Crop/zoom the logo (square) before uploading.
            const file = await VH.cropImage(picked, { aspectRatio: 1, title: "Adjust Asset logo", maxWidth: 800, maxHeight: 800 });
            if (!file) return;

            editLogoBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append("action", "upload_logo");
                formData.append("venture_id", currentVentureId);
                formData.append("logo", file);

                const response = await fetch("../api/ventures.php", {
                    method: "POST",
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    if (currentVenture) currentVenture.logo_url = res.logoUrl;
                    VH.renderVentureIconInto(document.getElementById("vdIconContent"), { logo_url: res.logoUrl, title: currentVenture ? currentVenture.title : '' });
                    VH.toast.success("Asset logo updated.");
                } else {
                    VH.toast.error(res.message || "Failed to update Asset logo.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error uploading Asset logo.");
            } finally {
                editLogoBtn.disabled = false;
                logoFileInput.value = "";
            }
        });
    }

    // Group chat send handler
    const chatForm = document.getElementById("chatForm");
    if (chatForm) {
        chatForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            await sendChatMessage();
        });
    }

    const chatInputEl = document.getElementById("chatInput");
    if (chatInputEl) {
        chatInputEl.addEventListener("input", updateChatSendButtonState);
    }

    // Chat image attachment
    const chatAttachBtn = document.getElementById("chatAttachBtn");
    const chatImageInput = document.getElementById("chatImageInput");
    const chatImageRemoveBtn = document.getElementById("chatImageRemoveBtn");

    if (chatAttachBtn && chatImageInput) {
        chatAttachBtn.addEventListener("click", () => chatImageInput.click());
        chatImageInput.addEventListener("change", () => {
            const file = chatImageInput.files[0];
            if (!file) return;

            if (!file.type.startsWith("image/")) {
                VH.toast.error("Please choose an image file.");
                chatImageInput.value = "";
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                VH.toast.error("Image must be smaller than 5MB.");
                chatImageInput.value = "";
                return;
            }

            selectedChatImage = file;
            const preview = document.getElementById("chatImagePreview");
            const wrap = document.getElementById("chatImagePreviewWrap");
            const reader = new FileReader();
            reader.onload = (ev) => {
                if (preview) preview.src = ev.target.result;
                if (wrap) wrap.classList.remove("hidden");
            };
            reader.readAsDataURL(file);
            updateChatSendButtonState();
        });
    }

    if (chatImageRemoveBtn) {
        chatImageRemoveBtn.addEventListener("click", () => {
            selectedChatImage = null;
            if (chatImageInput) chatImageInput.value = "";
            const wrap = document.getElementById("chatImagePreviewWrap");
            if (wrap) wrap.classList.add("hidden");
            updateChatSendButtonState();
        });
    }
});

let currentVentureId = null;
let lastChatMessageId = 0;
let chatPollTimer = null;
let isCurrentUserMember = false;
let selectedChatImage = null;
let renderedChatMessageIds = new Set();
let chatPollInFlight = false;

function fmtMoney(n) {
    return Number(n || 0).toLocaleString('en-IN');
}

function applyVentureCover(coverImage) {
    const vdHeroEl = document.getElementById("vdHero");
    if (!vdHeroEl) return;
    if (coverImage) {
        vdHeroEl.style.backgroundImage =
            `linear-gradient(135deg, rgba(15,23,42,0.65), rgba(57,131,246,0.55)), url('../${coverImage}')`;
        vdHeroEl.classList.add("vd-hero--has-cover");
    } else {
        vdHeroEl.style.backgroundImage = "";
        vdHeroEl.classList.remove("vd-hero--has-cover");
    }
}

async function fetchVentureDetail(id) {
    try {
        const response = await fetch(`../api/ventures.php?action=detail&id=${id}`);
        const res = await response.json();

        if (!res.success || !res.venture) {
            clearLoadingState();
            VH.toast.error(res.message || "Asset not found.");
            setTimeout(() => { window.location.href = "browse.php"; }, 1200);
            return;
        }

        currentVenture = res.venture;
        currentVentureId = id;
        const v = res.venture;

        // Breadcrumb + title
        const breadName = document.getElementById("breadName");
        if (breadName) breadName.textContent = v.title;
        const pageTitle = document.getElementById("pageTitle");
        if (pageTitle) pageTitle.textContent = `${v.title} – Ventures Harbor`;

        // Hero
        VH.renderVentureIconInto(document.getElementById("vdIconContent"), v);
        const vdIconEl = document.getElementById("vdIcon");
        if (vdIconEl) {
            vdIconEl.style.background = v.icon_bg || '#f1f5f9';
            vdIconEl.style.color = v.icon_color || '#1e293b';
        }
        setText("vdTitle", v.title);
        setText("vdIndustry", v.industry);
        renderAssetClasses(v);
        setText("vdLocation", v.location);

        const sampleNoDeadline = String(v.is_showcase ?? '0') === '1';
        setText("vdDays", sampleNoDeadline ? "No deadline —" : v.days_left);
        const vdDaysPill = document.getElementById("vdDays");
        if (vdDaysPill && vdDaysPill.parentElement) {
            vdDaysPill.parentElement.lastChild.textContent = sampleNoDeadline ? " example listing" : " days left";
        }
        setText("vdRaised", fmtMoney(v.raised_capital));
        setText("vdTarget", fmtMoney(v.target_capital));
        setText("vdPct", `${v.progress_percent}%`);
        setText("vdMembers", v.members_count);
        setText("vdMinInv", fmtMoney(v.min_investment));
        setText("vdFounderContrib", fmtMoney(v.founder_contribution));

        applyVentureCover(v.cover_image);

        const vdProgFill = document.getElementById("vdProgFill");
        if (vdProgFill) { vdProgFill.style.width = `${v.progress_percent}%`; }

        setText("vdSummary", v.description || "");
        const fullDesc = (v.full_description || "").trim();

        setText("vdDescription", fullDesc === (v.description || "").trim() ? "" : fullDesc);

        if (v.use_of_funds) {
            setText("vdUseOfFunds", v.use_of_funds);
            document.getElementById("vdUseOfFundsHeading").classList.remove("hidden");
            document.getElementById("vdUseOfFunds").classList.remove("hidden");
        }
        renderExitTerms(v);
        // Each side is drawn only when this listing has that role and the founder
        // answered for it — an empty heading is worse than no heading. The single
        // fallback line covers a listing that stated nothing at all.
        const pTypes = String(v.partner_types || 'both');
        const showReqBlock = (headingId, bodyId, value, applies) => {
            const on = !!applies && !!value && String(value).trim() !== '';
            const heading = document.getElementById(headingId);
            const body = document.getElementById(bodyId);
            if (heading) heading.classList.toggle("hidden", !on);
            if (body) {
                body.classList.toggle("hidden", !on);
                if (on) body.textContent = value;
            }
            return on;
        };

        const anyReq = [
            showReqBlock("vdSilentReqHeading",    "vdSilentRequirements",   v.silent_requirements,    pTypes !== 'active'),
            showReqBlock("vdSilentSkillsHeading", "vdSilentRequiredSkills", v.silent_required_skills, pTypes !== 'active'),
            showReqBlock("vdReqHeading",          "vdRequirements",         v.requirements,           pTypes !== 'silent'),
            showReqBlock("vdSkillsHeading",       "vdRequiredSkills",       v.required_skills,        pTypes !== 'silent')
        ].some(Boolean);

        ["vdNoReqHeading", "vdNoRequirements"].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle("hidden", anyReq);
        });

        // Founder — now the top block of the Members tab, not a tab of its own.
        setText("founderName", v.founder_name);
        setHTML("founderCity", `${VH.icon("pin", 14)} ${escapeHtml(v.location)}`);
        setText("founderBio", v.founder_bio);

        const founder = res.founder;

        // This used to be setText(v.founder_avatar), which can only ever print
        // text — an uploaded logo had no way to appear, and founder_avatar is a
        // snapshot taken when the venture was published, so it went stale the
        // moment the founder changed their picture. Read the live account and
        // render through the shared helper, the same source the profile popup
        // uses. Initials fall back to the founder name the page is *showing*
        // (a sample listing types its own), not the snapshot.
        const founderAvEl = document.getElementById("founderAvLg");
        if (founderAvEl) {
            VH.renderAvatarInto(founderAvEl, {
                avatarUrl: (founder && founder.avatar_url) || null,
                name: v.founder_name || (founder && founder.name) || ''
            });
        }

        const founderCard = document.getElementById("founderCard");
        if (founderCard) {
            if (founder && founder.id) {
                founderCard.style.cursor = "pointer";
                founderCard.setAttribute("title", "View full profile");
                founderCard.onclick = () => openUserProfile(founder.id);
            } else {
                founderCard.style.cursor = "default";
                founderCard.removeAttribute("title");
                founderCard.onclick = null;
            }
        }

        const founderSocialLinksEl = document.getElementById("founderSocialLinks");
        if (founderSocialLinksEl) {
            const links = founder ? [
                founder.linkedin_url ? `<a href="${founder.linkedin_url}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("link", 14)} LinkedIn</a>` : '',
                founder.twitter_url ? `<a href="${founder.twitter_url}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("globe", 14)} Twitter</a>` : '',
                founder.instagram_url ? `<a href="${founder.instagram_url}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("image", 14)} Instagram</a>` : '',
                founder.website_url ? `<a href="${founder.website_url}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("globe", 14)} Website</a>` : ''
            ].filter(Boolean) : [];
            founderSocialLinksEl.innerHTML = links.join(' &middot; ');
        }
        // Members tab
        renderMembers(res.members || [], (founder && founder.id) || v.founder_user_id);

        const currentUser = VH.auth.getUser();
        isCurrentUserMember = !!(currentUser && (res.members || []).some(m => String(m.user_id) === String(currentUser.id)));
        const chatJoinLink = document.getElementById("chatJoinLink");
        if (chatJoinLink) chatJoinLink.href = `join-venture.php?id=${v.id}`;

        /* Somebody may have clicked Group Chat while this request was still in flight —
           the deep link above is not the only way to reach the tab early. Now that
           membership is actually known, re-run the gate rather than leave them looking
           at a lock they have the key to. */
        const chatPanel = document.querySelector('.tab-panel[data-panel="chat"]');
        if (chatPanel && chatPanel.classList.contains("active")) startChatIfMember();

        // Meetups tab
        renderMeetups(res.meetups || []);

        // Sidebar join card
        setText("jMinInv", `₹${fmtMoney(v.min_investment)}`);
        setText("jTarget", `₹${fmtMoney(v.target_capital)}`);
        setText("jMembers", v.members_count);
        setText("jDays", String(v.is_showcase ?? '0') === '1' ? "—" : v.days_left);
        setText("jPct", `${v.progress_percent}% Funded`);

        // Hero, founder, members and sidebar all hold real data now — reveal the lot at once.
        clearLoadingState();

        const deadlineBox = document.getElementById("vdDeadlineBox");
        if (deadlineBox) {
            const showsActive = v.partner_types === 'active' || v.partner_types === 'both';
            const hasDeadline = !!v.application_deadline;
            deadlineBox.classList.toggle("hidden", !(showsActive && hasDeadline));
            if (showsActive && hasDeadline) setText("vdDeadlineDate", v.application_deadline);
        }
        const jProgFill = document.getElementById("jProgFill");
        if (jProgFill) { jProgFill.style.width = `${v.progress_percent}%`; }

        const isFounderOfThis = !!(currentUser && String(v.founder_user_id) === String(currentUser.id));
        const isSample = String(v.is_showcase ?? '0') === '1';

        const isFullyFunded = (parseFloat(v.target_capital) || 0) > 0
            && (parseFloat(v.raised_capital) || 0) >= (parseFloat(v.target_capital) || 0);
        const takesActivePartners = v.partner_types === 'active' || v.partner_types === 'both';

        const silentCapped = !!(v.openness && v.openness.silent_capped) && !isSample;
        renderSampleBanner(isSample);
        renderCapacity(v, isSample);

        const joinBtn = document.getElementById("joinVentureBtn");
        if (joinBtn) {
            if (isSample) {
                joinBtn.href = `join-venture.php?id=${v.id}`;
                joinBtn.textContent = "Preview the Join Flow →";
                joinBtn.title = "Walk through how joining works on this example — nothing is charged";
            } else if (isFounderOfThis) {
                joinBtn.href = `create-venture.php?edit=${v.id}`;
                joinBtn.textContent = "Edit Asset →";
            } else if (v.viewer_state === 'member') {
                joinBtn.href = `../admin/dashboard.php`;
                joinBtn.textContent = "✓ You've Joined — Go to Dashboard";
                joinBtn.classList.add("btn--joined");
            } else if (v.viewer_state === 'selected') {
                const app = v.viewer_application_id ? `&application_id=${encodeURIComponent(v.viewer_application_id)}` : '';
                joinBtn.href = `join-venture.php?id=${v.id}${app}`;
                joinBtn.textContent = "Pay & Confirm Your Place →";
            } else if (v.viewer_state === 'waitlisted') {
                joinBtn.href = `../admin/waitlist.php`;
                joinBtn.textContent = "On the Waitlist — Open Your Waitlist →";
                joinBtn.title = "You are waiting for a seat on this Asset";
            } else if (v.viewer_state === 'applied') {
                joinBtn.href = `../admin/dashboard.php`;
                joinBtn.textContent = "Application Submitted";
                joinBtn.classList.add("btn--disabled");
                joinBtn.style.pointerEvents = "none";
                joinBtn.style.opacity = "0.6";
            } else {
                joinBtn.href = `join-venture.php?id=${v.id}`;

                if (v.status !== 'active') {
                    joinBtn.textContent = v.status === 'cancelled'
                        ? "Asset Cancelled"
                        : (v.status === 'expired' ? "Listing Ended" : "Asset Closed");
                    joinBtn.classList.add("btn--disabled");
                    joinBtn.style.pointerEvents = "none";
                    joinBtn.style.opacity = "0.6";
                } else if (isFullyFunded) {
                    // Out of room but the listing is still running, so a partner may
                    // yet leave. waitlist_open is the server's answer
                    // (vh_waitlist_is_open) — the browser cannot derive it.
                    if (v.waitlist_open) {
                        joinBtn.href = `../admin/waitlist.php?join=${v.id}`;
                        joinBtn.textContent = "Join Waitlist →";
                        joinBtn.title = "Fully funded for now. Join the waitlist and you are notified the moment a partner exits.";
                        // Gold, matching btn-join--waitlist on the card. The Asset has
                        // changed state and the button should say so before the words are
                        // read; leaving it the ordinary Co-Own blue hid that entirely.
                        joinBtn.classList.remove("btn--primary");
                        joinBtn.classList.add("btn--waitlist");
                    } else {
                        // Funded and out of waitlist time: applications are closed too.
                        // vh_venture_openness() refuses them server-side, so offering the
                        // button here would only produce an application nobody can accept.
                        joinBtn.textContent = "Fully Funded — Closed to New Partners";
                        joinBtn.classList.add("btn--disabled");
                        joinBtn.style.pointerEvents = "none";
                        joinBtn.style.opacity = "0.6";
                    }
                } else if (silentCapped) {
                    if (takesActivePartners) {
                        joinBtn.href = `join-venture.php?id=${v.id}&role=active`;
                        joinBtn.textContent = "Apply as Active Partner →";
                    } else {
                        joinBtn.textContent = "Silent Partnership Full";
                        joinBtn.classList.add("btn--disabled");
                        joinBtn.style.pointerEvents = "none";
                        joinBtn.style.opacity = "0.6";
                    }
                }
            }
        }

        renderLifecycleBanner(v, isFounderOfThis);

        setText("jFeeNote", isSample
            ? "Sample listing — no fee, nothing to join"
            : (isFounderOfThis
                ? "You founded this Asset."
                : (isFullyFunded
                    ? (takesActivePartners
                        ? "Fully funded — no capital left to commit, and no fee. Still hiring active partners."
                        : "Fully funded — this Asset has closed to new partners.")
                    : "0.5% commitment fee to confirm your intent")));

        const interestedBtn = document.getElementById("vdInterestedBtn");
        if (interestedBtn) {
            if (isFounderOfThis) {
                interestedBtn.classList.add("hidden");
            } else {
                interestedBtn.classList.remove("hidden");
                interestedBtn.setAttribute("data-venture-id", v.id);

                VH.wishlist.paint(v.id, !!v.is_wishlisted, false);
            }
        }

        const currentUserIsFounder = !!(currentUser && String(v.founder_user_id) === String(currentUser.id));

        // Founder-only, and only where an application can actually arrive.
        //
        // A silent-only Asset never receives one: a silent partner joins and pays in a
        // single step, so `venture_applications` stays empty for it for the life of the
        // listing. The tab could only ever read "No pending applications yet", and the
        // client cut it for exactly that reason — "no applications comes in case of
        // silent partners". `partner_types` is the whole test: 'both' and the legacy
        // 'active' both take applications, 'silent' does not.
        const takesApplications = String(v.partner_types || 'both') !== 'silent';
        const applicationsTabBtn = document.getElementById("applicationsTabBtn");
        if (applicationsTabBtn) {
            applicationsTabBtn.classList.toggle("hidden", !currentUserIsFounder || !takesApplications);
        }
        // A sample listing is platform content, so any admin may edit its logo,
        // gallery and documents — not only the admin account that happened to
        // publish it. The server agrees via canEditVentureContent() in
        // config/session.php. Financial reports are deliberately NOT included:
        // they are a real venture's trading record, not presentation material.
        const isAdminViewer = !!(currentUser && currentUser.role === "admin");
        const isSampleListing = VH.card.isShowcase(v);
        const canEditContent = currentUserIsFounder || (isAdminViewer && isSampleListing);
        currentUserCanEditContent = canEditContent;

        const addReportBtn = document.getElementById("addFinancialReportBtn");
        if (addReportBtn) addReportBtn.classList.toggle("hidden", !currentUserIsFounder);
        const addDocBtn = document.getElementById("addVentureDocBtn");
        if (addDocBtn) addDocBtn.classList.toggle("hidden", !canEditContent);

        const editLogoBtn = document.getElementById("editLogoBtn");
        if (editLogoBtn) editLogoBtn.classList.toggle("hidden", !canEditContent);

        if (typeof window.initVentureGallery === "function") {
            window.initVentureGallery(v.id, currentUserIsFounder, isAdminViewer, isSampleListing);
        }
        // Skipped on a silent-only Asset along with the tab: the request could only ever
        // come back empty, and there is now no tab for it to fill.
        if (currentUserIsFounder && takesApplications) {
            loadApplications(v.id);
        }

        setupQuitSection(v);
        currentVentureIsShowcase = String(v.is_showcase ?? '0') === '1';
        loadFinancialReports(v.id, currentUserIsFounder);
        // Documents follow canEditContent, not founder-ness: an admin managing a
        // sample listing could already add a PDF but had no way to remove one,
        // because the per-row bin was rendered only for the founder.
        loadVentureSpecificDocuments(v.id, currentUserCanEditContent);

        // Related ventures
        renderRelatedVentures(res.related || []);
    } catch (err) {
        console.error(err);
        clearLoadingState();
        VH.toast.error("Network error loading Asset details.");
    }
}

/* Drops the page's skeleton state. Safe to call more than once, and called on every exit
   from fetchVentureDetail — a failed fetch must not leave the visitor shimmering forever. */
function clearLoadingState() {
    document.body.classList.remove("vd-loading");
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value ?? '';
}

/* Same, for a label that carries an inline SVG icon. Only ever called with our
   own markup plus escapeHtml()'d values — never with a raw server string. */
function setHTML(id, value) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = value ?? '';
}

// ── FEATURE 1: QUIT WINDOW ──

let quitCountdownTimer = null;

function setupQuitSection(v) {
    if (quitCountdownTimer) { clearInterval(quitCountdownTimer); quitCountdownTimer = null; }

    const section = document.getElementById("vdQuitSection");
    if (!section) return;

    const currentUser = VH.auth.getUser();
    if (!currentUser || !isCurrentUserMember) {
        section.classList.add("hidden");
        return;
    }

    /* The founder holds a venture_members row of their own, so every membership test
       above passes for them and this section offered them "Exit Without Refund" on
       their own listing. The server refuses it; this is so they are never shown it.
       Their way out is Delete Listing, which refunds the partners. */
    if (String(v.founder_user_id) === String(currentUser.id)) {
        section.classList.add("hidden");
        return;
    }

    section.classList.remove("hidden");
    const note = document.getElementById("vdQuitNote");
    const btn = document.getElementById("vdQuitBtn");

    if (!v.quit_window_opened_at) {
        note.style.color = "#7C8BA3";
        note.textContent = "The meetup hasn't been marked completed yet, so the commitment fee isn't refundable. You can still exit without a refund.";
        if (btn) btn.textContent = "Exit Without Refund";
        return;
    }

    /* The deadline comes from the server's own measurement of how long is left
       (vh_quit_window_seconds_left, computed in SQL) anchored to this browser's
       clock, NOT from parsing the stored timestamp — that string carries no
       timezone, so reading it here measured the offset between MySQL and the
       viewer rather than the time remaining. Only the ticking is local now. */
    const serverLeft = Number(v.quit_window_seconds_left);
    const closesAt = !isNaN(serverLeft) && v.quit_window_seconds_left !== null
        ? Date.now() + serverLeft * 1000
        : new Date(v.quit_window_opened_at.replace(/-/g, "/")).getTime() + 24 * 60 * 60 * 1000;

    const tick = () => {
        const remainingMs = closesAt - Date.now();
        if (remainingMs <= 0) {
            clearInterval(quitCountdownTimer);
            quitCountdownTimer = null;
            note.style.color = "#7C8BA3";
            note.textContent = "The 24-hour refund window after the meetup has closed. You can still exit without a refund.";
            if (btn) btn.textContent = "Exit Without Refund";
            return;
        }
        const h = Math.floor(remainingMs / 3600000);
        const m = Math.floor((remainingMs % 3600000) / 60000);
        const s = Math.floor((remainingMs % 60000) / 1000);
        note.style.color = "#8A6D00";
        note.innerHTML = `${VH.icon("hourglass", 14)} Full commitment fee refundable — window closes in <strong>${h}h ${m}m ${s}s</strong>`;
        if (btn) btn.textContent = "Exit Asset";
    };
    tick();
    quitCountdownTimer = setInterval(tick, 1000);
}

window.quitVenture = async function() {
    const user = VH.auth.getUser();
    if (!user || !currentVentureId) return;

    let eligibility;
    try {
        eligibility = await (await fetch(
            `../api/ventures.php?action=exit_eligibility&venture_id=${currentVentureId}`,
            { credentials: "same-origin" }
        )).json();
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error checking your refund eligibility.");
        return;
    }

    if (!eligibility.success) {
        VH.toast.error(eligibility.message || "Could not check your refund eligibility.");
        return;
    }

    /* Both routes can be shut at once: on a funded Asset whose meetup has not been
       completed the forfeit route is closed too, so leaving cannot hand a seat to
       the waitlist before anybody has met the team. Stop here rather than walking
       the visitor through the prompts and letting the server refuse at the end —
       the server does refuse (409 + noRefundBlocked), this is the courtesy half. */
    if (!eligibility.refundable && eligibility.no_refund_blocked) {
        VH.toast.error(eligibility.no_refund_blocked_reason
            || "This Asset is fully funded and its meetup has not been completed yet.");
        return;
    }

    const payload = { action: "exit", venture_id: currentVentureId };

    if (eligibility.refundable) {
        const feeText = "₹" + Number(eligibility.fee_paid).toLocaleString("en-IN");
        if (!confirm(`Exit this Asset?\n\nYou're within 24 hours of the meetup, so your full commitment fee of ${feeText} is refundable. We'll need your bank details to transfer it offline.\n\nThis cannot be undone.`)) return;

        const holder = (prompt("Account holder name (as on your bank account):", "") || "").trim();
        if (!holder) { VH.toast.error("Account holder name is required."); return; }

        const number = (prompt("Bank account number:", "") || "").trim();
        if (!/^\d{6,20}$/.test(number)) { VH.toast.error("Account number must be 6-20 digits."); return; }

        const ifsc = (prompt("IFSC code (e.g. HDFC0001234):", "") || "").trim().toUpperCase();
        if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc)) { VH.toast.error("Please enter a valid IFSC code."); return; }

        const bankName = (prompt("Bank name (optional):", "") || "").trim();

        payload.refund_choice = "refund";
        payload.bank_account_name = holder;
        payload.bank_account_number = number;
        payload.bank_ifsc = ifsc;
        payload.bank_name = bankName;
    } else {
        const reason = eligibility.window_state === "not_opened"
            ? "The meetup hasn't been marked completed yet, so the commitment fee is not refundable."
            : "The 24-hour refund window after the meetup has closed, so the commitment fee is not refundable.";
        if (!confirm(`Exit this Asset without a refund?\n\n${reason}\n\nYour commitment fee will not be returned. This cannot be undone.`)) return;
        payload.refund_choice = "no_refund";
    }

    try {
        const response = await fetch("../api/ventures.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success(res.message || "You have exited the Asset.");
            setTimeout(() => { window.location.href = "browse.php"; }, 1600);
        } else {
            VH.toast.error(res.message || "Failed to quit Asset.");
        }
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error quitting Asset.");
    }
};

async function loadApplications(ventureId) {
    const list = document.getElementById("vdApplicationsList");
    if (!list) return;
    list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">Loading applications…</p>`;

    try {
        const response = await fetch(`../api/ventures.php?action=applications&venture_id=${ventureId}`, { credentials: 'same-origin' });
        const res = await response.json();
        if (!res.success) {
            list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">${escapeHtml(res.message || 'Failed to load applications.')}</p>`;
            return;
        }

        const applications = res.applications || [];
        const seats = res.seats || [];

        // Vacated ACTIVE seats lead the tab: an open seat is a decision with a
        // seven-day clock on it, where an ordinary application has no deadline.
        const seatsHtml = seats.map(vdSeatPanelHTML).join("");

        if (!applications.length && !seats.length) {
            list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">No pending applications yet.</p>`;
            return;
        }
        if (!applications.length) {
            list.innerHTML = seatsHtml
                + `<p style="color:#94a3b8;font-size:0.85rem;margin-top:1rem;">No other pending applications.</p>`;
            return;
        }

        let html = seatsHtml + '<div style="display:flex;flex-direction:column;gap:0.75rem;">';
        applications.forEach(a => {
            html += `
                <div class="card" style="padding:1rem;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;color:#0f172a;font-size:0.92rem;">${escapeHtml(a.name)} <span style="font-weight:400;color:#64748b;font-size:0.78rem;">· ${escapeHtml(a.city || '')} · ${escapeHtml(a.occupation || '')}</span></div>
                            ${/* WHAT THEY ARE PLEDGING — "here we forgot putting amount that add by
                                  investor" (client, 8 Sep 2026). The founder is deciding Select or
                                  Reject, and how much capital the applicant brings is the single
                                  biggest fact in that decision; it was collected at the apply step
                                  and stored on the row all along, just never shown back.

                                  EXACT, not VH.card.money(): the applicant was shown "₹3,75,000" when
                                  they typed it (paintApplySummary), so the founder must read the same
                                  figure. A compact "₹3.8L" here would round the very number the two
                                  sides have to agree on. The shared-formatter rule is about the
                                  K/L/Cr thresholds, which is not what this is. */''}
                            ${Number(a.invested_amount) > 0
                                ? `<div style="margin-top:0.35rem;font-weight:700;color:#0f172a;font-size:1rem;display:flex;align-items:center;gap:0.35rem;">${VH.icon("money", 15)}₹${Number(a.invested_amount).toLocaleString('en-IN')}<span style="font-weight:500;color:#64748b;font-size:0.78rem;">pledged</span></div>`
                                : `<div style="font-size:0.76rem;color:#94a3b8;margin-top:0.35rem;">No amount recorded — this application predates the field.</div>`}
                            ${a.resume_path ? `<div style="margin-top:0.4rem;"><a class="btn btn--outline btn--sm" href="../${a.resume_path}" target="_blank" rel="noopener" download="${escapeHtml(a.resume_name || 'resume')}">${VH.icon("file", 14)} View Resume${a.resume_name ? ' · ' + escapeHtml(a.resume_name) : ''}</a></div>` : ''}
                            ${a.skills ? `<div style="font-size:0.78rem;color:#64748b;margin-top:0.4rem;">${VH.icon("tools", 13)} Skills: ${escapeHtml(a.skills)}</div>` : ''}
                            ${a.skill_match_note ? `<div style="font-size:0.78rem;color:#16a34a;margin-top:0.2rem;">✓ ${escapeHtml(a.skill_match_note)}</div>` : ''}
                            ${a.message ? `<div style="font-size:0.8rem;color:#475569;margin-top:0.4rem;background:#f8fafc;padding:0.5rem;border-radius:6px;">${escapeHtml(a.message)}</div>` : ''}
                            ${a.whatsapp_link
                                ? `<div style="margin-top:0.5rem;"><a class="btn btn--outline btn--sm vd-app-wa" href="${escapeHtml(a.whatsapp_link)}" target="_blank" rel="noopener">${VH.icon("chat", 14)} WhatsApp ${escapeHtml(a.whatsapp_display || a.whatsapp_number || '')}</a></div>`
                                : `<div style="font-size:0.76rem;color:#94a3b8;margin-top:0.5rem;">No WhatsApp number — this application predates the field.</div>`}
                        </div>
                        <div style="display:flex;gap:0.4rem;flex-shrink:0;">
                            <button class="btn btn--sm btn--primary" onclick="decideApplication(${a.id}, 'selected')">Select</button>
                            <button class="btn btn--sm btn--outline" onclick="decideApplication(${a.id}, 'rejected')">Reject</button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        list.innerHTML = html;
    } catch (err) {
        console.error(err);
        list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">Network error loading applications.</p>`;
    }
}

/* ─────────────────────────────────────────────────────────────────────────
   A vacated ACTIVE seat, and the people who applied for it.

   Rendered above the ordinary applications because it is a different decision:
   choosing here HOLDS the seat for that person and starts their payment clock,
   where selecting an ordinary applicant does neither. The server refuses
   decide_application on a seat candidate for the same reason (409 +
   seatApplication) — picking one through the ordinary route would grant a
   membership while leaving the seat open for somebody else, counting the same
   capital twice.
   ───────────────────────────────────────────────────────────────────────── */
function vdSeatPanelHTML(seat) {
    const cands = seat.candidates || [];
    const chosen = cands.find(c => c.status === "selected");
    const pending = cands.filter(c => c.status === "pending");

    const head = `
        <div class="vd-seat-head">
            <div>
                <span class="wl-badge wl-badge--active">Vacated active seat</span>
                <h4>${VH.card.money(seat.invested_amount)} seat · ${VH.card.money(seat.fee_amount)} commitment fee</h4>
                <p>${seat.vacated_by_name ? escapeHtml(seat.vacated_by_name) + " left this seat. " : ""}${
                    seat.exit_reason ? "Reason given: “" + escapeHtml(seat.exit_reason) + "”" : "No reason was given for leaving."}</p>
            </div>
        </div>`;

    if (chosen) {
        const hrs = Math.max(0, Math.floor((seat.seconds_left || 0) / 3600));
        return `<div class="vd-seat vd-seat--chosen">${head}
            <div class="vd-seat-chosen-note">${VH.icon("check", 15)}
                <span><strong>${escapeHtml(chosen.user_name)}</strong> has been selected and has
                <strong>${hrs}h</strong> left to pay. If they don't, the seat comes back to you
                and you can choose again from the others.</span>
            </div>
        </div>`;
    }

    if (!pending.length) {
        return `<div class="vd-seat">${head}
            <p class="vd-seat-empty">Nobody has applied for this seat yet. Everyone on the waitlist has been told
            it is open. You have up to ${seat.offer_days} days from when it opened to choose someone.</p>
        </div>`;
    }

    const rows = pending.map(c => `
        <div class="vd-seat-cand">
            <div>
                <div class="vd-seat-cand-name">${escapeHtml(c.user_name)}${
                    c.city || c.occupation ? `<span> · ${escapeHtml([c.city, c.occupation].filter(Boolean).join(" · "))}</span>` : ""}</div>
                ${c.resume_path ? `<div style="margin-top:0.4rem"><a class="btn btn--outline btn--sm" href="../${c.resume_path}" target="_blank" rel="noopener" download="${escapeHtml(c.resume_name || 'resume')}">${VH.icon("file", 14)} View Resume</a></div>` : ""}
                ${c.skills ? `<div class="vd-seat-cand-meta">${VH.icon("tools", 13)} Skills: ${escapeHtml(c.skills)}</div>` : ""}
                ${c.skill_match_note ? `<div class="vd-seat-cand-match">✓ ${escapeHtml(c.skill_match_note)}</div>` : ""}
                ${c.message ? `<div class="vd-seat-cand-msg">${escapeHtml(c.message)}</div>` : ""}
                ${c.whatsapp_link
                    ? `<div style="margin-top:0.5rem"><a class="btn btn--outline btn--sm vd-app-wa" href="${escapeHtml(c.whatsapp_link)}" target="_blank" rel="noopener">${VH.icon("chat", 14)} WhatsApp ${escapeHtml(c.whatsapp_display || "")}</a></div>`
                    : ""}
            </div>
            <div style="flex-shrink:0">
                <button class="btn btn--sm btn--primary" onclick="selectSeatCandidate(${seat.id}, ${c.id})">Give them the seat</button>
            </div>
        </div>`).join("");

    return `<div class="vd-seat">${head}
        <p class="vd-seat-count">${pending.length} applicant${pending.length === 1 ? "" : "s"} —
            whoever you choose has ${seat.pay_days} days to pay the commitment fee.</p>
        ${rows}
        <div class="vd-seat-foot">
            <button class="btn btn--secondary btn--sm" onclick="reopenSeat(${seat.id})">None of these — reopen for new applicants</button>
        </div>
    </div>`;
}

window.selectSeatCandidate = async function (slotId, applicationId) {
    if (!confirm("Give this person the seat?\n\nThey will be notified and the seat is held for them while they pay. "
               + "The other applicants stay on your list in case they don't.")) return;
    try {
        const res = await (await fetch("../api/waitlist.php", {
            method: "POST", headers: { "Content-Type": "application/json" }, credentials: "same-origin",
            body: JSON.stringify({ action: "select_candidate", slot_id: slotId, application_id: applicationId })
        })).json();
        res.success ? VH.toast.success(res.message) : VH.toast.error(res.message || "Could not select that applicant.");
        loadApplications(currentVentureId);
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error selecting that applicant.");
    }
};

window.reopenSeat = async function (slotId) {
    if (!confirm("Reopen this seat?\n\nEveryone who applied is turned down and the whole waitlist is told the seat "
               + "is open again. This does not extend the seat's deadline.")) return;
    try {
        const res = await (await fetch("../api/waitlist.php", {
            method: "POST", headers: { "Content-Type": "application/json" }, credentials: "same-origin",
            body: JSON.stringify({ action: "reopen_slot", slot_id: slotId })
        })).json();
        res.success ? VH.toast.success(res.message) : VH.toast.error(res.message || "Could not reopen the seat.");
        loadApplications(currentVentureId);
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error reopening the seat.");
    }
};

window.decideApplication = async function(applicationId, decision) {
    try {
        const response = await fetch("../api/ventures.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "decide_application", application_id: applicationId, decision })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success(decision === 'selected' ? "Applicant selected — they've been notified to complete payment." : "Applicant rejected.");
            loadApplications(currentVentureId);
        } else {
            VH.toast.error(res.message || "Failed to update application.");
        }
    } catch (e) {
        console.error(e);
        VH.toast.error("Network error updating application.");
    }
};

// ── FEATURE 6: MONTHLY FINANCIAL REPORTS ──

let financialReportsCache = [];
let currentVentureIsFounder = false;
let currentVentureIsShowcase = false;
// Founder, or an admin looking at a sample listing. Mirrors canEditVentureContent()
// on the server; see the gating block in renderVenture().
let currentUserCanEditContent = false;

// On a sample listing the reports are open to everyone, so say why — a visitor
// who is not a member would otherwise wonder whether they are seeing something
// they should not. On every real venture this renders nothing.
function financialsSampleNote() {
    if (!currentVentureIsShowcase) return '';
    return `<p style="margin:0 0 0.9rem;padding:0.6rem 0.8rem;background:#FFFBEB;border:1px solid #F7E08A;border-radius:8px;color:#7A5E00;font-size:0.82rem;line-height:1.5;">
        <strong>Example reports.</strong> This is a sample listing, so its monthly reports are open to
        everyone — this is the statement a real Asset's partners receive each month.
    </p>`;
}

async function loadFinancialReports(ventureId, isFounder) {
    currentVentureIsFounder = !!isFounder;
    const list = document.getElementById("vdFinancialsList");
    if (!list) return;
    list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">Loading financial reports…</p>`;

    try {
        const response = await fetch(`../api/venture_financials.php?action=list&venture_id=${ventureId}`, { credentials: 'same-origin' });
        const res = await response.json();
        if (!res.success) {
            list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">${escapeHtml(res.message || 'Failed to load financial reports.')}</p>`;
            return;
        }

        const reports = res.reports || [];
        financialReportsCache = reports;
        if (!reports.length) {
            list.innerHTML = financialsSampleNote()
                + `<p style="color:#94a3b8;font-size:0.85rem;">No monthly reports published yet.</p>`;
            return;
        }

        let html = financialsSampleNote() + '<div style="display:flex;flex-direction:column;gap:1rem;">';
        reports.forEach(r => {
            const plColor = r.profit_loss >= 0 ? '#16a34a' : '#ef4444';
            html += `
                <div class="card" style="padding:1.25rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;flex-wrap:wrap;gap:0.5rem;">
                        <h4 style="margin:0;font-size:1rem;color:#0f172a;">${escapeHtml(r.report_month)}</h4>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <span style="font-weight:700;color:${plColor}">${r.profit_loss >= 0 ? 'Profit' : 'Loss'}: ₹${fmtMoney(Math.abs(r.profit_loss))}</span>
                            <a class="btn btn--outline btn--sm" href="financial-report.php?id=${r.id}" target="_blank">${VH.icon("file", 14)} View / PDF</a>
                            ${currentVentureIsFounder ? `<button class="btn btn--ghost btn--sm" onclick="editFinancialReport(${r.id})">${VH.icon("edit", 13)} Edit</button>` : ''}
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.6rem;font-size:0.82rem;color:#475569;margin-bottom:0.6rem;">
                        <div><strong>Revenue</strong><br>₹${fmtMoney(r.total_revenue)}</div>
                        <div><strong>Expenses</strong><br>₹${fmtMoney(r.total_expenses)}</div>
                    </div>
                    ${r.expenses && r.expenses.length ? `
                        <div style="font-size:0.78rem;color:#64748b;margin-bottom:0.6rem;">
                            <strong>Expense breakdown:</strong> ${r.expenses.map(e => `${escapeHtml(e.category)} ₹${fmtMoney(e.amount)}`).join(' · ')}
                        </div>` : ''}
                    ${r.pdf_path ? `
                        <div style="font-size:0.8rem;margin-bottom:0.6rem;">
                            <a href="${VH.getBasePath()}${escapeHtml(r.pdf_path)}" target="_blank" rel="noopener" style="color:#2563EB;text-decoration:none;">
                                ${VH.icon("paperclip", 14)} ${escapeHtml(r.pdf_name || 'Report PDF')}
                            </a>
                        </div>` : ''}
                    ${r.cash_flow_summary ? `<p style="margin:0.3rem 0;font-size:0.8rem;color:#475569;"><strong>Cash Flow:</strong> ${escapeHtml(r.cash_flow_summary)}</p>` : ''}
                    ${r.balance_sheet_summary ? `<p style="margin:0.3rem 0;font-size:0.8rem;color:#475569;"><strong>Balance Sheet:</strong> ${escapeHtml(r.balance_sheet_summary)}</p>` : ''}
                    ${r.partner_shares && r.partner_shares.length ? `
                        <div style="margin-top:0.6rem;border-top:1px solid #f1f5f9;padding-top:0.6rem;">
                            <strong style="font-size:0.78rem;color:#0f172a;">Partner Shares</strong>
                            <div style="font-size:0.78rem;color:#64748b;margin-top:0.3rem;">
                                ${r.partner_shares.map(p => `${escapeHtml(p.name)}: ₹${fmtMoney(p.share)}`).join(' · ')}
                            </div>
                        </div>` : ''}
                </div>
            `;
        });
        html += '</div>';
        list.innerHTML = html;
    } catch (err) {
        console.error(err);
        list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">Network error loading financial reports.</p>`;
    }
}

window.openFinancialReportModal = function() {
    if (!document.getElementById("financialReportModal")) {
        buildFinancialReportModal();
    }
    resetFinancialReportForm();
    VH.modal.open("financialReportModal");
};

window.editFinancialReport = function(reportId) {
    const report = financialReportsCache.find(r => String(r.id) === String(reportId));
    if (!report) return;

    if (!document.getElementById("financialReportModal")) {
        buildFinancialReportModal();
    }
    resetFinancialReportForm();

    document.getElementById("frModalTitle").textContent = `Edit Report — ${report.report_month}`;
    const monthInput = document.getElementById("frMonth");
    monthInput.value = report.report_month;
    monthInput.readOnly = true;
    document.getElementById("frRevenue").value = report.total_revenue;
    document.getElementById("frExpensesTotal").value = report.total_expenses;
    document.getElementById("frCashFlow").value = report.cash_flow_summary || '';
    document.getElementById("frBalanceSheet").value = report.balance_sheet_summary || '';

    existingReportPdfName = report.pdf_name || '';
    renderStagedPdfName();

    VH.modal.open("financialReportModal");
};

function resetFinancialReportForm() {
    const form = document.getElementById("frForm");
    if (form) form.reset();
    const monthInput = document.getElementById("frMonth");
    if (monthInput) monthInput.readOnly = false;
    const titleEl = document.getElementById("frModalTitle");
    if (titleEl) titleEl.textContent = "Add Monthly Financial Report";

    // form.reset() clears the file input's value but not our staged handle, so a
    // PDF picked for one month would otherwise follow the founder to the next.
    stagedReportPdf = null;
    existingReportPdfName = "";
    renderStagedPdfName();
}

function buildFinancialReportModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'financialReportModal';
    modal.innerHTML = `
        <div class="modal" style="max-width:550px">
            <button class="modal-close" onclick="VH.modal.close('financialReportModal')">×</button>
            <div class="modal-header"><h3 class="modal-title" id="frModalTitle">Add Monthly Financial Report</h3></div>
            <form id="frForm" style="padding:0 1.5rem 1.5rem">
                <div class="form-group">
                    <label class="form-label">Month *</label>
                    <input class="form-control" id="frMonth" type="month" required/>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label">Total Revenue (₹) *</label>
                        <input class="form-control" id="frRevenue" type="number" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Expenses (₹)</label>
                        <input class="form-control" id="frExpensesTotal" type="number" min="0" placeholder="e.g. 75000"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Report PDF</label>
                    <input type="file" id="frPdfInput" accept="application/pdf,.pdf" hidden/>
                    <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap">
                        <button type="button" class="btn btn--secondary btn--sm" id="frPdfBtn">${VH.icon("paperclip", 14)} Add PDF</button>
                        <span id="frPdfName" style="font-size:0.82rem;color:#64748b">No file chosen</span>
                        <button type="button" class="btn btn--ghost btn--sm" id="frPdfClear" style="display:none">Remove</button>
                    </div>
                    <small style="display:block;margin-top:0.35rem;font-size:0.75rem;color:#94a3b8">PDF only, up to 10MB. Uploaded once the report is published.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Cash Flow Summary</label>
                    <textarea class="form-control form-textarea" id="frCashFlow" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Balance Sheet (simple)</label>
                    <textarea class="form-control form-textarea" id="frBalanceSheet" rows="2"></textarea>
                </div>
                <div class="modal-footer" style="padding:1.5rem 0 0; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:0.5rem">
                    <button type="button" class="btn btn--secondary" onclick="VH.modal.close('financialReportModal')">Cancel</button>
                    <button type="submit" class="btn btn--primary">Publish Report</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);

    document.getElementById("frForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        await submitFinancialReport();
    });

    // Total Expenses is typed in directly now, so there is no line-item box to
    // sum. The PDF is only staged here — it needs a report id to attach to, and
    // that does not exist until the report itself is saved.
    const pdfInput = document.getElementById("frPdfInput");
    document.getElementById("frPdfBtn").addEventListener("click", () => pdfInput.click());
    document.getElementById("frPdfClear").addEventListener("click", () => {
        pdfInput.value = "";
        stagedReportPdf = null;
        renderStagedPdfName();
    });
    pdfInput.addEventListener("change", () => {
        const file = pdfInput.files && pdfInput.files[0];
        if (!file) { stagedReportPdf = null; renderStagedPdfName(); return; }
        if (file.type !== "application/pdf" && !/\.pdf$/i.test(file.name)) {
            VH.toast.error("Only PDF files are allowed.");
            pdfInput.value = "";
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            VH.toast.error("That PDF is larger than 10MB.");
            pdfInput.value = "";
            return;
        }
        stagedReportPdf = file;
        renderStagedPdfName();
    });
};

// The PDF chosen in the modal, held until the report has an id to attach it to.
let stagedReportPdf = null;
let existingReportPdfName = "";

function renderStagedPdfName() {
    const label = document.getElementById("frPdfName");
    const clear = document.getElementById("frPdfClear");
    if (!label) return;
    const name = stagedReportPdf ? stagedReportPdf.name : existingReportPdfName;
    label.textContent = name || "No file chosen";
    label.style.color = name ? "#0f172a" : "#64748b";
    if (clear) clear.style.display = stagedReportPdf ? "" : "none";
}

async function uploadReportPdf(reportId, file) {
    const body = new FormData();
    body.append("action", "upload_pdf");
    body.append("report_id", reportId);
    body.append("pdf", file);
    try {
        const res = await (await fetch("../api/venture_financials.php", {
            method: "POST", credentials: "same-origin", body
        })).json();
        if (!res.success) VH.toast.error(res.message || "Report saved, but the PDF could not be attached.");
        return !!res.success;
    } catch (err) {
        console.error(err);
        VH.toast.error("Report saved, but the PDF upload failed.");
        return false;
    }
}

async function submitFinancialReport() {
    const month = document.getElementById("frMonth").value;
    const revenue = parseFloat(document.getElementById("frRevenue").value || 0);
    const expensesTotal = parseFloat(document.getElementById("frExpensesTotal").value || 0);
    const cashFlow = document.getElementById("frCashFlow").value.trim();
    const balanceSheet = document.getElementById("frBalanceSheet").value.trim();

    try {
        const response = await fetch("../api/venture_financials.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
                action: "create",
                venture_id: currentVentureId,
                report_month: month,
                total_revenue: revenue,
                // Sent instead of line items — an empty `expenses` array clears
                // any breakdown a pre-existing report was carrying.
                total_expenses: isNaN(expensesTotal) ? 0 : expensesTotal,
                expenses: [],
                cash_flow_summary: cashFlow,
                balance_sheet_summary: balanceSheet
            })
        });
        const res = await response.json();
        if (!res.success) {
            VH.toast.error(res.message || "Failed to publish report.");
            return;
        }

        if (stagedReportPdf && res.id) {
            await uploadReportPdf(res.id, stagedReportPdf);
        }

        VH.toast.success("Financial report published.");
        VH.modal.close("financialReportModal");
        resetFinancialReportForm();
        loadFinancialReports(currentVentureId, currentVentureIsFounder);
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error publishing report.");
    }
}

async function loadVentureSpecificDocuments(ventureId, canEdit) {
    const container = document.getElementById("vdVentureDocsList");
    if (!container) return;

    try {
        const response = await fetch(`../api/venture_documents.php?action=list&venture_id=${ventureId}`, { credentials: 'same-origin' });
        const res = await response.json();

        if (!res.success || !res.documents || res.documents.length === 0) {
            container.innerHTML = `<div class="vd-doc-item">No documents uploaded by the founder yet.</div>`;
            return;
        }

        let html = "";
        res.documents.forEach(d => {
            html += `
                <div class="vd-doc-item">
                    ${VH.icon("file", 14)} <strong>${escapeHtml(d.title)}</strong>
                    <a class="btn btn--outline btn--sm" style="margin-left:auto;" href="../${d.file_path}" download="${escapeHtml(d.file_name)}">${VH.icon("download", 14)} Download</a>
                    ${canEdit ? `<button class="btn btn--ghost btn--sm" style="color:#ef4444;" title="Remove this document" onclick="deleteVentureDoc(${d.id}, ${ventureId})">${VH.icon("trash", 14)}</button>` : ''}
                </div>
            `;
        });
        container.innerHTML = html;
    } catch (err) {
        console.error(err);
        container.innerHTML = `<div class="vd-doc-item">Failed to load documents.</div>`;
    }
}

window.deleteVentureDoc = async function(docId, ventureId) {
    if (!confirm("Remove this document from the Asset?")) return;
    try {
        const response = await fetch("../api/venture_documents.php?action=delete", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: docId })
        });
        const res = await response.json();
        if (res.success) {
            VH.toast.success("Document removed.");
            loadVentureSpecificDocuments(ventureId, currentUserCanEditContent);
        } else {
            VH.toast.error(res.message || "Failed to remove document.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error removing document.");
    }
};

function renderMembers(members, founderUserId) {
    const grid = document.getElementById("membersGrid");
    if (!grid) return;

    // The founder holds a venture_members row of their own (role 'active',
    // written by `create`), and the Founder card now sits directly above this
    // grid — so without this filter they would be listed twice on the same tab.
    // It is dropped here rather than in the API because that row is what carries
    // the founder's contribution into vh_capital_split().
    const partners = (members || []).filter(m => String(m.user_id) !== String(founderUserId));

    const countEl = document.getElementById("membersCountTxt");
    if (countEl) {
        countEl.innerHTML = partners.length === 1
            ? `<strong>1</strong> partner has joined this Asset alongside the founder`
            : `<strong>${partners.length}</strong> partners have joined this Asset alongside the founder`;
    }

    if (!partners.length) {
        grid.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">No partners have joined yet.</p>`;
        return;
    }

    let html = "";
    partners.forEach(m => {
        const base = VH.getBasePath();
        const avatarHtml = m.avatar_url
            ? `<img src="${m.avatar_url.startsWith('http') ? m.avatar_url : base + m.avatar_url}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
            : (m.avatar || m.name.slice(0, 2).toUpperCase());

        const socialLinks = [
            m.linkedin_url ? `<a href="${m.linkedin_url}" target="_blank" rel="noopener" title="LinkedIn" style="text-decoration:none;font-size:0.75rem;">${VH.icon("link", 14)} LinkedIn</a>` : '',
            m.twitter_url ? `<a href="${m.twitter_url}" target="_blank" rel="noopener" title="Twitter/X" style="text-decoration:none;font-size:0.75rem;">${VH.icon("globe", 14)} Twitter</a>` : '',
            m.instagram_url ? `<a href="${m.instagram_url}" target="_blank" rel="noopener" title="Instagram" style="text-decoration:none;font-size:0.75rem;">${VH.icon("image", 14)} Instagram</a>` : '',
            m.website_url ? `<a href="${m.website_url}" target="_blank" rel="noopener" title="Website" style="text-decoration:none;font-size:0.75rem;">${VH.icon("globe", 14)} Website</a>` : ''
        ].filter(Boolean).join(' · ');

        html += `
            <div class="member-card">
                <div style="cursor:pointer" onclick="openUserProfile(${m.user_id})" title="View full profile">
                    <div class="member-avatar">${avatarHtml}</div>
                    <div class="member-name">${m.name}</div>
                </div>
                <div class="member-investment">₹${fmtMoney(m.invested_amount)}</div>
                ${memberEquityHTML(m)}
                <div style="display:flex;gap:0.35rem;justify-content:center;flex-wrap:wrap;">
                    <span class="badge badge--info" style="font-size:0.65rem;text-transform:capitalize;">${m.role} Partner</span>
                    ${m.past_experience ? `<span class="badge badge--success" style="font-size:0.65rem;cursor:help;" title="${String(m.past_experience).replace(/"/g, '&quot;')}">${VH.icon("clipboard", 12)} Experience</span>` : ''}
                </div>
                ${socialLinks ? `<div style="margin-top:0.4rem;color:#3983F6;">${socialLinks}</div>` : ''}
            </div>
        `;
    });
    grid.innerHTML = html;
}

/**
 * The equity a member holds, frozen at the terms they joined on.
 *
 * The founder is skipped: their percentage is `founder_equity_percent`, which rescales
 * with their own contribution rather than being frozen, so it is not this member row's
 * to state. `equity_drifted` means the founder has since edited the listing away from
 * what this partner agreed to — worth captioning, never worth changing the figure.
 */
function memberEquityHTML(m) {
    if (!m || m.is_founder || !m.equity_terms) return '';
    const pct = VH.equity.heldPct(m, null);
    if (!pct) return '';
    const title = m.equity_drifted
        ? 'Agreed when this partner joined. The listing has been edited since; their share is unchanged.'
        : 'Their agreed share of this Asset.';
    return `<div class="member-equity${m.equity_drifted ? ' member-equity--locked' : ''}" title="${title}">`
         + `${m.equity_drifted ? VH.icon("lock", 11) + ' ' : ''}${pct} equity</div>`;
}

window.openUserProfile = async function (userId) {
    if (!userId) return;
    buildUserProfileModal();
    const body = document.getElementById("userProfileBody");
    if (body) body.innerHTML = `<p style="text-align:center;color:#94a3b8;padding:2rem 0;">Loading profile…</p>`;
    VH.modal.open("userProfileModal");

    try {
        const res = await fetch(`../api/profile.php?action=public&id=${userId}`, { credentials: 'same-origin' }).then(r => r.json());
        if (!res.success || !res.user) {
            if (body) body.innerHTML = `<p style="text-align:center;color:#ef4444;padding:2rem 0;">${res.message || 'Could not load this profile.'}</p>`;
            return;
        }
        renderUserProfileModal(res.user, res.ventures || []);
    } catch (err) {
        console.error(err);
        if (body) body.innerHTML = `<p style="text-align:center;color:#ef4444;padding:2rem 0;">Network error loading profile.</p>`;
    }
};

function buildUserProfileModal() {
    if (document.getElementById("userProfileModal")) return;
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'userProfileModal';
    modal.innerHTML = `
        <div class="modal" style="max-width:460px">
            <button class="modal-close" onclick="VH.modal.close('userProfileModal')">×</button>
            <div class="modal-header"><h3 class="modal-title">Profile</h3></div>
            <div id="userProfileBody" style="padding:0 1.5rem 1.5rem"></div>
        </div>`;
    document.body.appendChild(modal);
}

/**
 * Ventures this person founded or joined, for the profile popup.
 *
 * The server only ever sends publicly visible listings, so this renders whatever
 * it is given. Each row links to the venture, which is the point — someone
 * reading a question wants to see what else the asker is part of.
 */
function profileVenturesHTML(ventures) {
    if (!ventures || !ventures.length) return '';
    const base = VH.getBasePath();
    const LABELS = { founder: 'Founder', active: 'Active Partner', silent: 'Silent Partner' };

    const rows = ventures.map(v => {
        const label = LABELS[v.relation] || 'Partner';
        const closed = v.status === 'cancelled' || v.status === 'suspended';
        return `
        <a href="${base}pages/venture-detail.php?id=${encodeURIComponent(v.id)}"
           style="display:flex;align-items:center;justify-content:space-between;gap:0.6rem;padding:0.5rem 0.6rem;border:1px solid #E8ECF2;border-radius:8px;text-decoration:none;background:#fff;">
          <span style="min-width:0;">
            <span style="display:block;font-size:0.85rem;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(v.title)}</span>
            <span style="font-size:0.72rem;color:#8A98AC;">${label}${closed ? ' · ' + escapeHtml(v.status) : ''}</span>
          </span>
          <span style="color:#2563EB;font-size:0.78rem;flex:none;">View →</span>
        </a>`;
    }).join('');

    return `<div style="margin-bottom:1rem;">
        <div style="font-weight:600;font-size:0.8rem;color:#0f172a;margin-bottom:0.4rem;">Assets (${ventures.length})</div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;max-height:220px;overflow-y:auto;">${rows}</div>
    </div>`;
}

function renderUserProfileModal(u, ventures) {
    const body = document.getElementById("userProfileBody");
    if (!body) return;
    const base = VH.getBasePath();
    const avatarHtml = u.avatarUrl
        ? `<img src="${u.avatarUrl.startsWith('http') ? u.avatarUrl : base + u.avatarUrl}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
        : (u.avatar || (u.name || '?').slice(0, 2).toUpperCase());

    const skills = (u.skills || '').split(',').map(s => s.trim()).filter(Boolean);
    const socials = [
        u.linkedinUrl ? `<a href="${u.linkedinUrl}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("link", 14)} LinkedIn</a>` : '',
        u.twitterUrl ? `<a href="${u.twitterUrl}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("globe", 14)} Twitter</a>` : '',
        u.instagramUrl ? `<a href="${u.instagramUrl}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("image", 14)} Instagram</a>` : '',
        u.websiteUrl ? `<a href="${u.websiteUrl}" target="_blank" rel="noopener" style="text-decoration:none;">${VH.icon("globe", 14)} Website</a>` : ''
    ].filter(Boolean).join(' &middot; ');

    const roleLabel = u.role ? (u.role.charAt(0).toUpperCase() + u.role.slice(1)) : '';

    body.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:0.3rem;margin-bottom:1rem;">
            <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#173463,#3983F6);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.4rem;">${avatarHtml}</div>
            <h3 style="margin:0.4rem 0 0;">${escapeHtml(u.name || '')}</h3>
            <div style="color:#64748b;font-size:0.85rem;">${u.occupation ? escapeHtml(u.occupation) : (roleLabel ? roleLabel + ' Member' : 'Member')}${u.city ? ' · ' + VH.icon('pin', 12) + ' ' + escapeHtml(u.city) : ''}</div>
        </div>
        ${u.bio ? `<p style="color:#334155;font-size:0.88rem;line-height:1.5;margin:0 0 1rem;">${escapeHtml(u.bio)}</p>` : ''}
        ${skills.length ? `<div style="margin-bottom:1rem;"><div style="font-weight:600;font-size:0.8rem;color:#0f172a;margin-bottom:0.4rem;">Skills</div><div style="display:flex;flex-wrap:wrap;gap:0.35rem;">${skills.map(s => `<span class="chip active" style="font-size:0.72rem;">${escapeHtml(s)}</span>`).join('')}</div></div>` : ''}
        ${u.pastExperience ? `<div style="margin-bottom:1rem;"><div style="font-weight:600;font-size:0.8rem;color:#0f172a;margin-bottom:0.4rem;">Past Experience</div><p style="color:#64748b;font-size:0.85rem;line-height:1.5;margin:0;">${escapeHtml(u.pastExperience)}</p></div>` : ''}
        ${profileVenturesHTML(ventures)}
        ${socials ? `<div style="color:#3983F6;font-size:0.82rem;text-align:center;">${socials}</div>` : ''}
    `;
}

function renderMeetups(meetups) {
    const list = document.getElementById("vdMeetupsList");
    if (!list) return;

    if (!meetups.length) {
        list.innerHTML = `<p style="color:#94a3b8;font-size:0.85rem;">No meetups scheduled for this Asset yet.</p>`;
        return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:0.75rem;">';
    meetups.forEach(m => {
        const isOnline = m.location_type === 'online';
        html += `
            <div class="card" style="padding:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                    <div>
                        <h4 style="margin:0 0 0.3rem;font-size:0.95rem;color:#0f172a;">${m.title}</h4>
                        <p style="margin:0;font-size:0.8rem;color:#64748b;">${VH.icon("clock", 13)} ${m.date} at ${m.time} · ${isOnline ? 'Online' : m.location}</p>
                    </div>
                    <span class="badge ${m.status === 'upcoming' ? 'badge--info' : 'badge--gold'}" style="font-size:0.68rem;">${m.status.toUpperCase()}</span>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

function renderRelatedVentures(related) {
    const container = document.getElementById("relatedVentures");
    if (!container) return;

    if (!related.length) {
        container.innerHTML = "";
        return;
    }

    let html = '<div class="card"><div class="card-body"><p style="font-weight:600;margin:0 0 0.75rem;font-size:0.88rem">Related Assets</p>';
    related.forEach(r => {
        html += `
            <a href="venture-detail.php?id=${r.id}" style="display:block;padding:0.6rem 0;border-top:1px solid #f1f5f9;text-decoration:none;">
                <div style="font-weight:600;font-size:0.85rem;color:#0f172a;">${r.title}</div>
                <div style="font-size:0.72rem;color:#94a3b8;">${r.industry} · ${r.progress_percent}% funded · ${r.days_left} days left</div>
            </a>
        `;
    });
    html += '</div></div>';
    container.innerHTML = html;
}

// ── GROUP CHAT ──

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function startChatIfMember() {
    const lockedState = document.getElementById("chatLockedState");
    const activeState = document.getElementById("chatActiveState");
    const user = VH.auth.getUser();

    if (!user) {
        if (lockedState) lockedState.classList.remove("hidden");
        if (activeState) activeState.classList.add("hidden");
        return;
    }

    if (!isCurrentUserMember) {
        if (lockedState) lockedState.classList.remove("hidden");
        if (activeState) activeState.classList.add("hidden");
        return;
    }

    if (lockedState) lockedState.classList.add("hidden");
    if (activeState) activeState.classList.remove("hidden");

    loadChatMessages(user.id);
    stopChatPolling();
    chatPollTimer = setInterval(() => pollChatMessages(user.id), 4000);
}

function stopChatPolling() {
    if (chatPollTimer) {
        clearInterval(chatPollTimer);
        chatPollTimer = null;
    }
}

async function loadChatMessages(userId) {
    const box = document.getElementById("chatMessages");
    if (box) box.innerHTML = `<p class="vd-chat-empty">Loading messages…</p>`;
    renderedChatMessageIds = new Set();

    try {
        const response = await fetch(`../api/chat.php?action=list&venture_id=${currentVentureId}`, { credentials: 'same-origin' });
        const res = await response.json();

        if (!res.success) {
            if (box) box.innerHTML = `<p class="vd-chat-empty">${escapeHtml(res.message || 'Failed to load chat.')}</p>`;
            return;
        }

        const memberCountEl = document.getElementById("chatMemberCount");
        if (memberCountEl && res.members) {
            memberCountEl.textContent = `${res.members.length} member${res.members.length === 1 ? '' : 's'}`;
        }

        if (!res.messages || res.messages.length === 0) {
            if (box) box.innerHTML = `<p class="vd-chat-empty">No messages yet — say hello to your co-partners!</p>`;
            return;
        }

        if (box) box.innerHTML = "";
        res.messages.forEach(m => appendChatMessage(m, userId));
        lastChatMessageId = res.messages[res.messages.length - 1].id;
        scrollChatToBottom();
    } catch (err) {
        console.error(err);
        if (box) box.innerHTML = `<p class="vd-chat-empty">Network error loading chat.</p>`;
    }
}

async function pollChatMessages(userId) {
    if (chatPollInFlight) return;
    chatPollInFlight = true;

    try {
        const response = await fetch(`../api/chat.php?action=list&venture_id=${currentVentureId}&after_id=${lastChatMessageId}`, { credentials: 'same-origin' });
        const res = await response.json();
        if (!res.success || !res.messages || !res.messages.length) return;

        clearChatEmptyState();
        res.messages.forEach(m => appendChatMessage(m, userId));
        lastChatMessageId = res.messages[res.messages.length - 1].id;
        scrollChatToBottom();
    } catch (err) {
        console.error(err);
    } finally {
        chatPollInFlight = false;
    }
}

function clearChatEmptyState() {
    const box = document.getElementById("chatMessages");
    const empty = box ? box.querySelector(".vd-chat-empty") : null;
    if (empty) box.innerHTML = "";
}

function appendChatMessage(m, currentUserId) {
    const box = document.getElementById("chatMessages");
    if (!box) return;

    if (renderedChatMessageIds.has(m.id)) return;
    renderedChatMessageIds.add(m.id);

    const isMine = String(m.user_id) === String(currentUserId);
    /* created_ts is UNIX_TIMESTAMP() from MySQL — a real epoch, so the browser
       renders it in the READER's timezone correctly. Parsing m.created_at instead
       treats a bare "Y-m-d H:i:s" as browser-local and prints the server's wall
       clock as if it were the reader's, which is only right by coincidence. */
    const stamp = Number(m.created_ts);
    const time = (!isNaN(stamp) && stamp > 0
        ? new Date(stamp * 1000)
        : new Date(String(m.created_at || "").replace(/-/g, "/"))
    ).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    const base = VH.getBasePath();

    const avatarHtml = m.avatar_url
        ? `<img src="${m.avatar_url.startsWith('http') ? m.avatar_url : base + m.avatar_url}" alt="">`
        : escapeHtml(m.avatar || (m.name || '?').slice(0, 2).toUpperCase());

    const imageHtml = m.image_path
        ? `<img class="vd-chat-image" src="${m.image_path.startsWith('http') ? m.image_path : base + m.image_path}" alt="attachment" onclick="window.open(this.src, '_blank')" onload="scrollChatToBottom()"/>`
        : '';

    const row = document.createElement('div');
    row.className = `vd-chat-row vd-chat-row--new ${isMine ? 'vd-chat-row--mine' : ''}`;
    row.innerHTML = `
        <div class="vd-chat-avatar">${avatarHtml}</div>
        <div class="vd-chat-bubble-col">
            <div class="vd-chat-bubble">
                ${!isMine ? `<div class="vd-chat-sender">${escapeHtml(m.name)}</div>` : ''}
                ${m.message ? `<div>${escapeHtml(m.message)}</div>` : ''}
                ${imageHtml}
            </div>
            <span class="vd-chat-time">${time}</span>
        </div>
    `;
    box.appendChild(row);
}

function updateChatSendButtonState() {
    const input = document.getElementById("chatInput");
    const sendBtn = document.getElementById("chatSendBtn");
    if (!input || !sendBtn) return;
    sendBtn.disabled = !(input.value.trim() || selectedChatImage);
}

function scrollChatToBottom() {
    const box = document.getElementById("chatMessages");
    if (box) box.scrollTop = box.scrollHeight;
}

async function sendChatMessage() {
    const input = document.getElementById("chatInput");
    const sendBtn = document.getElementById("chatSendBtn");
    const user = VH.auth.getUser();
    if (!input || !user || !currentVentureId) return;

    const message = input.value.trim();
    if (!message && !selectedChatImage) return;

    if (sendBtn) sendBtn.disabled = true;

    chatPollInFlight = true;

    const fd = new FormData();
    fd.append("action", "send");
    fd.append("venture_id", currentVentureId);
    fd.append("message", message);
    if (selectedChatImage) fd.append("image", selectedChatImage);

    try {
        const response = await fetch("../api/chat.php", {
            method: "POST",
            credentials: "same-origin",
            body: fd
        });
        const res = await response.json();
        if (res.success) {
            input.value = "";
            selectedChatImage = null;
            const imgInput = document.getElementById("chatImageInput");
            if (imgInput) imgInput.value = "";
            const wrap = document.getElementById("chatImagePreviewWrap");
            if (wrap) wrap.classList.add("hidden");

            clearChatEmptyState();
            appendChatMessage(res.message_data, user.id);
            lastChatMessageId = res.message_data.id;
            scrollChatToBottom();
            updateChatSendButtonState();
        } else {
            VH.toast.error(res.message || "Failed to send message.");
        }
    } catch (err) {
        console.error(err);
        VH.toast.error("Network error sending message.");
    } finally {
        chatPollInFlight = false;
        updateChatSendButtonState();
        input.focus();
    }
}

/* Investment & Exit Terms  (Overview tab) */

const VD_EQUITY_LABELS = {
    capital_based: "Capital-Based Share",
    equal_split: "Equal Split",
    negotiated: "Negotiated"
};
const VD_PROFIT_FREQ_LABELS = {
    monthly: "Monthly",
    quarterly: "Quarterly",
    yearly: "Annually",
    none: "No regular distribution"
};
const VD_VALUATION_LABELS = {
    fixed: "Fixed valuation",
    business_valuation: "Business valuation at exit",
    multiple: "Revenue / Profit multiple",
    custom: "Custom terms"
};
const VD_APPROVAL_LABELS = {
    founder: "Founder approval required",
    partners: "Partner approval required",
    both: "Founder and partner approval required"
};

/**
 * Equity and salary per role, as the founder set them. The amount column is the
 * venture's own figures — founder_contribution for the founder, min_investment
 * for either partner — never a second stored copy that could disagree.
 *
 * Returns '' when the founder filled none of it in, so the row disappears
 * instead of showing an empty table. A silent partner never has a salary.
 */
function vdEquityTableHTML(v) {
    const pct = (x) => {
        const n = parseFloat(x);
        return isNaN(n) ? null : `${n % 1 === 0 ? n.toFixed(0) : n.toFixed(1)}%`;
    };
    const money = (x) => {
        const n = parseFloat(x);
        return (isNaN(n) || n <= 0) ? null : VH.card.money(n);
    };

    // Equity is earned by capital in and by work done. The row leads with the
    // total — the number a partner is actually comparing — and states the split
    // beneath it. Listings from before the split have no operations figure, so
    // they print the single total exactly as they always did.
    const equity = (investRaw, opsRaw) => {
        const invest = parseFloat(investRaw);
        const ops = parseFloat(opsRaw);
        if (isNaN(invest) && isNaN(ops)) return null;
        const total = (isNaN(invest) ? 0 : invest) + (isNaN(ops) ? 0 : ops);
        const parts = [];
        if (!isNaN(invest)) parts.push(`${pct(invest)} investment`);
        if (!isNaN(ops)) parts.push(`${pct(ops)} operations`);
        return `<strong>${escapeHtml(pct(total))}</strong>`
             + (parts.length > 1 ? `<span class="vd-equity-split">${escapeHtml(parts.join(' + '))}</span>` : '');
    };

    const contrib = parseFloat(v.founder_contribution) || 0;
    const ticket = parseFloat(v.min_investment) || 0;
    const type = v.partner_types || 'both';

    const rows = [
        { role: 'Founder', amt: contrib, equity: equity(v.founder_equity_percent, v.founder_ops_equity_percent), salary: money(v.founder_monthly_salary), show: true },
        { role: 'Active Partner', amt: ticket, equity: equity(v.active_equity_percent, v.active_ops_equity_percent), salary: money(v.active_monthly_salary), show: type !== 'silent' },
        { role: 'Silent Partner', amt: ticket, equity: equity(v.silent_equity_percent, null), salary: null, noSalary: true, show: type !== 'active' }
    ].filter(r => r.show);

    // Nothing to tabulate. The rule that decides the split still applies, and on
    // a listing this bare it is the only thing a reader has to go on, so it is
    // printed on its own rather than the whole block disappearing.
    if (!rows.some(r => r.equity || r.salary)) {
        const rule = vdEquityRuleText(v);
        return rule ? `<div class="vd-equity-wrap">${rule}</div>` : '';
    }

    const body = rows.map(r => `
        <tr>
          <th scope="row">${escapeHtml(r.role)}</th>
          <td>${r.amt > 0 ? escapeHtml(VH.card.money(r.amt)) : '—'}</td>
          <td>${r.equity || '—'}</td>
          <td>${r.noSalary ? '<em>No salary</em>' : (r.salary ? escapeHtml(r.salary) : '—')}</td>
        </tr>`).join('');

    return `<div class="vd-equity-wrap"><table class="vd-equity">
        <thead><tr><th>Role</th><th>Minimum Investment</th><th>Equity</th><th>Monthly Salary</th></tr></thead>
        <tbody>${body}</tbody>
      </table>
      ${vdEquityRuleHTML(v)}
      ${vdEquityCalcHTML(v)}</div>`;
}

/**
 * The rule the table alone doesn't state: an active partner's percentage buys
 * one ticket of the active role, and anything above it is recognised as silent
 * capital. Written out here, under the table, with this venture's own numbers —
 * the same arithmetic VH.equity does, so nobody has to work it out from the
 * percentages themselves.
 */
function vdEquityRuleHTML(v) {
    return `<p class="vd-equity-note">Partner figures are per member at the minimum investment.</p>`
         + vdEquityRuleText(v);
}

/**
 * The rule on its own, without the table's footnote — so it can also be printed
 * where there is no table, on a listing whose founder published no percentages
 * at all. Returns '' where the rule does not apply.
 */
function vdEquityRuleText(v) {
    const r = VH.equity.rates(v);
    const type = v.partner_types || 'both';

    // Only true where there is a silent role for the excess to become.
    if (type !== 'both') return '';

    // The rule holds whether or not the founder filled the table in, so on a
    // listing with percentages missing it is stated in words rather than
    // dropped. Worked through with real figures only where there are figures —
    // an invented example would be worse than none.
    if (r.ticket <= 0 || r.silentInvest === null
        || (r.activeInvest === null && r.activeOps === null)) {
        return `<p class="vd-equity-rule">
            <strong>Investing more than the minimum as an active partner?</strong>
            The active partner percentage is what one minimum investment buys, so that minimum is the active limit
            for a single partner. Anything committed above it is extra capital, so it is recognised as
            <strong>silent (capital-only) partnership</strong> and earns the silent partner's rate on top.
            This founder hasn't published the percentages yet — agree them directly before you commit.
          </p>`;
    }

    const inr = VH.equity.inr;
    const pct = VH.equity.pct;
    const activeTotal = (r.activeInvest || 0) + (r.activeOps || 0);

    // Worked with one extra ticket, the case a reader is most likely to have in
    // mind, and priced off this listing's own figures.
    const example = VH.equity.forPledge(v, 'active', r.ticket * 2);

    return `<p class="vd-equity-rule">
        <strong>Investing more than the minimum as an active partner?</strong>
        The active partner's ${escapeHtml(pct(activeTotal))} is what ${escapeHtml(inr(r.ticket))} buys — that
        minimum is the active limit for one partner. Anything you commit above it is extra capital, so it is
        recognised as <strong>silent (capital-only) partnership</strong> and earns the silent partner's rate of
        ${escapeHtml(pct(r.silentInvest))} per ${escapeHtml(inr(r.ticket))} on top.
        Example: ${escapeHtml(inr(r.ticket * 2))} as an active partner =
        ${escapeHtml(pct(activeTotal))} + ${escapeHtml(pct(example.silentEquity))} =
        <strong>${escapeHtml(pct(example.total))}</strong>.
      </p>`;
}

/**
 * "What would my investment buy?" — the table states the rate per minimum
 * ticket, this turns it into the number the reader actually wants. The
 * arithmetic is VH.equity's, the same one the join flow uses, so the figure
 * here is the figure they will see when they commit.
 */
function vdEquityCalcHTML(v) {
    if (!VH.equity.isStated(v)) return '';

    const type = v.partner_types || 'both';
    const ticket = Math.max(0, parseFloat(v.min_investment) || 0);
    const roles = [];
    if (type !== 'active') roles.push(['silent', 'as a Silent Partner']);
    if (type !== 'silent') roles.push(['active', 'as an Active Partner']);
    if (!roles.length) return '';

    const options = roles.map(([value, label]) =>
        `<option value="${value}">${label}</option>`).join('');

    return `<div class="eq-tool" id="vdEqTool">
        <label class="eq-tool-label" for="vdEqAmount">What would my investment buy?</label>
        <div class="eq-tool-controls">
          <span class="eq-tool-amt"><span>₹</span>
            <input id="vdEqAmount" type="number" min="${ticket}" step="1000" value="${ticket}" inputmode="numeric"/>
          </span>
          ${roles.length > 1 ? `<select id="vdEqRole">${options}</select>` : `<span class="vd-equity-note">${escapeHtml(roles[0][1])}</span>`}
        </div>
        <div id="vdEqOut" class="hidden"></div>
      </div>`;
}

function bindEquityCalc(v) {
    const amount = document.getElementById("vdEqAmount");
    if (!amount) return;
    const roleEl = document.getElementById("vdEqRole");
    const type = v.partner_types || 'both';
    const paint = () => VH.equity.paintInto(
        "vdEqOut", v,
        roleEl ? roleEl.value : (type === 'active' ? 'active' : 'silent'),
        amount.value
    );

    amount.addEventListener("input", paint);
    if (roleEl) roleEl.addEventListener("change", paint);
    paint();

    // min= only constrains the spinner and native validation — a typed-in 40000
    // still reaches paint(), which is where the floor is actually enforced.
    const ticket = Math.max(0, parseFloat(v.min_investment) || 0);
    if (ticket > 0) amount.setAttribute("min", String(ticket));
}

// Asset classes on the listing header. The canonical five are also the Browse
// filter pills; a founder-named class is not, so showing it here is the only
// place it is visible. Labels mirror $VH_ASSET_CLASSES / vh_asset_class_label().
const VD_ASSET_CLASS_LABELS = {
    real_estate:    'Real Estate',
    businesses:     'Businesses',
    ip_royalties:   'IP & Royalties',
    franchise:      'Franchises',
    infrastructure: 'Infrastructure'
};

function renderAssetClasses(v) {
    const el = document.getElementById("vdAssetClasses");
    if (!el) return;
    const slugs = String(v.asset_class || '').split(',')
        .map(x => x.trim()).filter(Boolean);
    el.innerHTML = slugs.map(slug => {
        const known = Object.prototype.hasOwnProperty.call(VD_ASSET_CLASS_LABELS, slug);
        const label = known
            ? VD_ASSET_CLASS_LABELS[slug]
            : slug.replace(/_/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
        // A custom class is marked so nobody reads it as a marketplace category.
        const title = known
            ? 'Marketplace category — browse other ' + label + ' assets'
            : 'A class named by this founder. It is searchable, but is not one of the marketplace filters.';
        return `<span class="badge badge--info vd-class-badge${known ? '' : ' vd-class-badge--custom'}" title="${escapeHtml(title)}">${escapeHtml(label)}</span>`;
    }).join('');
}

function renderExitTerms(v) {
    const grid = document.getElementById("vdExitGrid");
    const empty = document.getElementById("vdExitEmpty");
    if (!grid) return;

    const rows = [];
    const add = (label, value, opts) => {
        if (value === null || value === undefined || value === "") return;
        rows.push({ label, value, tone: (opts && opts.tone) || null, wide: !!(opts && opts.wide) });
    };

    add("Equity Distribution", VD_EQUITY_LABELS[v.equity_distribution]);

    // Equity + salary per role. Each row states the amount its percentage is
    // measured against, so a partner can see what their own ticket buys.
    const equityHTML = vdEquityTableHTML(v);
    if (equityHTML) {
        rows.push({ label: "Equity & Salary per Role", html: equityHTML, wide: true });
    }

    const roiMin = parseFloat(v.expected_roi_min);
    const roiMax = parseFloat(v.expected_roi_max);
    if (!isNaN(roiMin) && !isNaN(roiMax)) {
        const fmtRoi = (x) => (x % 1 === 0 ? x.toFixed(0) : x.toFixed(1));
        const range = roiMin === roiMax ? `${fmtRoi(roiMin)}%` : `${fmtRoi(roiMin)}–${fmtRoi(roiMax)}%`;
        add("Expected ROI", v.expected_roi ? `${range} per year — ${v.expected_roi}` : `${range} per year`);
    } else {
        add("Expected ROI", v.expected_roi);
    }
    add("Minimum Holding Period", v.lockin_period);
    add("Expected Exit Timeline", v.expected_exit_timeline);

    if (v.exit_options) {
        const chips = v.exit_options.split(",").map(s => s.trim()).filter(Boolean);
        if (chips.length) {
            rows.push({
                label: "Exit Options Available",
                html: `<div class="vd-exit-chips">${chips.map(c => `<span class="vd-exit-chip">${escapeHtml(c)}</span>`).join("")}</div>`,
                wide: true
            });
        }
    }

    if (v.early_exit_allowed !== null && v.early_exit_allowed !== undefined) {
        const allowed = Number(v.early_exit_allowed) === 1;
        const extras = allowed
            ? [
                v.early_exit_notice_period ? `${v.early_exit_notice_period} notice` : "",
                v.early_exit_conditions || ""
              ].filter(Boolean)
            : [];
        add(
            "Early Exit",
            allowed ? (extras.length ? `Allowed — ${extras.join(" · ")}` : "Allowed") : "Not allowed before the minimum holding period ends",
            { tone: allowed ? "yes" : "no", wide: extras.length > 0 }
        );
    }

    if (v.ownership_transfer_allowed !== null && v.ownership_transfer_allowed !== undefined) {
        const allowed = Number(v.ownership_transfer_allowed) === 1;
        const approval = VD_APPROVAL_LABELS[v.ownership_transfer_approval];
        add(
            "Ownership Transfer",
            allowed ? (approval ? `Allowed — ${approval}` : "Allowed") : "Not allowed",
            { tone: allowed ? "yes" : "no" }
        );
    }

    add("Profit Distribution", VD_PROFIT_FREQ_LABELS[v.profit_distribution_frequency]);
    add("Exit Valuation Method", VD_VALUATION_LABELS[v.exit_valuation_method]);
    add("Valuation Terms", v.exit_valuation_notes, { wide: true });

    if (rows.length === 0) {
        grid.innerHTML = "";
        grid.classList.add("hidden");
        if (empty) empty.classList.remove("hidden");
        return;
    }

    grid.classList.remove("hidden");
    if (empty) empty.classList.add("hidden");

    grid.innerHTML = rows.map(r => `
        <div class="vd-exit-row${r.wide ? " vd-exit-row--wide" : ""}">
            <span class="vd-exit-label">${escapeHtml(r.label)}</span>
            ${r.html || `<span class="vd-exit-value${r.tone ? " vd-exit-value--" + r.tone : ""}">${escapeHtml(r.value)}</span>`}
        </div>
    `).join("");

    // The calculator is inside the markup just written, so it is wired here
    // rather than in the builder that returned the string.
    bindEquityCalc(v);
}

// ── SILENT / ACTIVE CAPACITY ──

function renderCapacity(v, isSample) {
    const host = document.getElementById("vdCapacityBox");
    if (!host) return;

    const b = v.capital_buckets;
    const hasCap = !!b && (b.has_cap === true || b.has_cap === 1 || b.has_cap === '1');
    if (isSample || !hasCap) {
        host.classList.add("hidden");
        host.innerHTML = "";
        return;
    }

    const n = (x) => parseFloat(x) || 0;
    /* VH.card.money, the same compact K/L/Cr formatter the card rows use — not a local
       one. This function had its own full-digit version, which is the local currency
       formatter the house rule forbids, and it was also why the block overflowed: in a
       320px sidebar "₹1,00,000 / ₹60,00,000" is 22 characters and wrapped onto two
       lines, while "₹1L / ₹60L" fits with room to spare. */
    const money = VH.card.money;
    const pctOf = (filled, total) => total > 0 ? Math.min(100, Math.round((filled / total) * 100)) : 0;

    const silentFull = n(b.silent_remaining) <= 0;
    const activeFull = n(b.remaining) <= 0;

    const row = (label, cls, filled, total, full) => `
        <div class="vc-cap-row${full ? ' vc-cap-row--full' : ''}">
            <span class="vc-cap-label"><span class="vc-cap-dot vc-cap-dot--${cls}"></span>${label}</span>
            <span class="vc-cap-figs">${money(n(filled))} / ${money(n(total))}</span>
            <span class="vc-cap-state">${full ? 'FULL' : 'OPEN'}</span>
            <span class="vc-cap-track"><span class="vc-cap-fill vc-cap-fill--${cls}" style="width:${pctOf(n(filled), n(total))}%"></span></span>
        </div>`;

    const note = (v.openness && v.openness.silent_capped)
        ? `<div class="vc-cap-note">
               <strong>Silent Partnership Full</strong>
               Remaining investment: ${money(n(b.remaining))}.
               ${(v.partner_types === 'active' || v.partner_types === 'both') ? 'Only active partners can join this Asset.' : ''}
           </div>`
        : '';

    host.innerHTML = `<div class="vc-capacity" style="border-top:none;margin-top:0;padding-top:0">
            ${row('Silent', 'silent', b.raised_silent, b.silent_cap, silentFull)}
            ${row('Active', 'active', b.raised_active, b.active_reserve, activeFull)}
        </div>${note}`;
    host.classList.remove("hidden");
}

function renderSampleBanner(isSample) {
    const host = document.getElementById("vdSampleBanner");
    if (!host) return;

    if (!isSample) {
        host.classList.add("hidden");
        host.innerHTML = "";
        return;
    }

    host.classList.remove("hidden");
    host.innerHTML = `
        <div class="vd-sample-banner">
            <div class="vd-sample-banner__tag">SAMPLE LISTING</div>
            <div>
                <strong>This is an example, not a Real Asset.</strong>
                Ventures Harbor published it to show what a completed listing looks like —
                the figures, the partner terms and the exit details are illustrative.
                This listing is not available to join. Browse the Real Assets, or list your own Venture.
            </div>
        </div>`;
}

function renderLifecycleBanner(v, isFounderOfThis) {
    const banner = document.getElementById("vdLifecycleBanner");
    if (!banner) return;

    const status = v.status;
    if (status !== 'expired' && status !== 'cancelled') {
        banner.classList.add("hidden");
        banner.innerHTML = "";
        return;
    }

    const cancelledOn = v.cancelled_at ? String(v.cancelled_at).split(" ")[0] : null;
    let title, body, tone;

    if (status === 'cancelled') {
        tone = "vd-lifecycle-banner--dead";
        title = "This Asset has been cancelled";
        body = (v.cancel_reason ? escapeHtml(v.cancel_reason) + " " : "")
            + (cancelledOn ? "Cancelled on " + escapeHtml(cancelledOn) + ". " : "")
            + "It is no longer accepting partners. Anyone who had paid a commitment fee is being refunded automatically — "
            + "check your Payment Statement for the status of yours.";
    } else {
        tone = "vd-lifecycle-banner--warn";
        title = "This listing period has ended";
        body = isFounderOfThis
            ? "Your Venture wasn't fully funded in time. From your dashboard you can extend the listing"
                + (Number(v.extension_count) > 0 ? " — though this one has already used its extension" : " once")
                + ", or cancel it. If you do neither it will be cancelled automatically and your partners refunded."
            : "It isn't accepting new partners right now. The founder may extend the listing, or the Asset may be "
                + "cancelled — in which case every partner who paid a commitment fee is refunded automatically.";
    }

    banner.className = "vd-lifecycle-banner " + tone;
    banner.innerHTML = `
        <div class="container">
            <strong>${escapeHtml(title)}</strong>
            <span>${body}</span>
            ${isFounderOfThis && status === 'expired'
                ? '<a href="../admin/dashboard.php" class="btn btn--primary btn--sm">Go to my dashboard</a>'
                : ''}
        </div>
    `;
}
