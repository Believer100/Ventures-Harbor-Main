/* VENTURES HARBOR — PROFILE LOGIC (profile.js) */

/**
 * Name, Bio and Website are ONE input each, relabelled per account type
 * ("Full Name" / "Company Name"). Saving as a company therefore used to
 * overwrite the individual's own name — the client's "when I change in one and
 * save changes it changes in both sections".
 *
 * Each side is kept separately here and on the server. Switching type stashes
 * what is on screen for the type being left, then fills in what was remembered
 * for the type being entered.
 */
let currentAccountType = "individual";
const identityDraft = {
    individual: { name: "", bio: "", website: "" },
    company: { name: "", bio: "", website: "" }
};

function rememberIdentityFor(type) {
    const side = identityDraft[type === "company" ? "company" : "individual"];
    const get = (id) => (document.getElementById(id)?.value ?? "");
    side.name = get("pfName");
    side.bio = get("pfBio");
    side.website = get("pfWebsite");
}

function fillIdentityFor(type) {
    const side = identityDraft[type === "company" ? "company" : "individual"];
    const put = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ""; };
    put("pfName", side.name);
    put("pfBio", side.bio);
    put("pfWebsite", side.website);

    const bioCount = document.getElementById("bioCount");
    if (bioCount) bioCount.textContent = `${(side.bio || "").length}/250`;
}

/* Account type is asked at signup, so on the profile it is normally a statement
 * of what this account already is. The picker is kept behind Change — someone
 * who incorporates later still has to be able to switch, and switching loses
 * nothing, since the two identities are stored side by side. */
const PF_ACCT_LABELS = {
    individual: ["Individual", "A person investing or partnering", "user"],
    company:    ["Company", "A registered business or brand", "building"]
};

function paintAccountTypeSettled(type) {
    const [name, desc, icon] = PF_ACCT_LABELS[type === "company" ? "company" : "individual"];
    const nameEl = document.getElementById("pfTypeSettledName");
    const descEl = document.getElementById("pfTypeSettledDesc");
    // innerHTML, not textContent: the label carries an inline SVG icon now. Both
    // halves are our own constants, never user input.
    if (nameEl) nameEl.innerHTML = VH.icon(icon, 18) + " " + name;
    if (descEl) descEl.textContent = desc;
}

function showAccountTypePicker(show) {
    const picker = document.getElementById("pfTypePicker");
    const settled = document.getElementById("pfTypeSettled");
    if (picker) picker.classList.toggle("hidden", !show);
    if (settled) settled.classList.toggle("hidden", show);
}

function applyAccountTypeLayout(type) {
    const isCompany = type === "company";

    document.querySelectorAll("[data-acct]").forEach((el) => {
        const belongs = el.getAttribute("data-acct");
        el.classList.toggle("hidden", belongs !== (isCompany ? "company" : "individual"));
    });

    const setText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    };
    setText("pfCardTitle", isCompany ? "Company Details" : "Personal Details");
    setText("pfNameLabel", isCompany ? "Company Name" : "Full Name");

    const nameEl = document.getElementById("pfName");
    if (nameEl) nameEl.placeholder = isCompany ? "Your registered company name" : "Full Name";

    const bioLabel = document.getElementById("pfBioLabel");
    if (bioLabel) {
        bioLabel.childNodes[0].nodeValue = isCompany ? "Company Description " : "Bio ";
    }
    const bioEl = document.getElementById("pfBio");
    if (bioEl) {
        bioEl.placeholder = isCompany
            ? "What your company does, and what it is looking for..."
            : "Tell other members about yourself...";
    }

    setText("pfWebsiteLabel", isCompany ? "Company Website " : "Website ");
    const websiteLabel = document.getElementById("pfWebsiteLabel");
    if (websiteLabel) {
        const hint = document.createElement("span");
        hint.style.cssText = "color:#7A8AA3;font-size:0.75rem";
        hint.textContent = "(optional)";
        websiteLabel.appendChild(hint);
    }

    setText("pfSkillsLabel", isCompany ? "Services & Capabilities " : "Skills ");
    const skillsLabel = document.getElementById("pfSkillsLabel");
    if (skillsLabel) {
        const hint = document.createElement("span");
        hint.style.cssText = "color:#7A8AA3;font-size:0.75rem";
        hint.id = "pfSkillsHint";
        hint.textContent = isCompany
            ? "(comma-separated — what your company can bring to an Asset)"
            : "(comma-separated — shown to founders when you apply as a Partner)";
        skillsLabel.appendChild(hint);
    }
    const skillsEl = document.getElementById("pfSkills");
    if (skillsEl) {
        skillsEl.placeholder = isCompany
            ? "e.g. Logistics, Retail Ops, Supply Chain"
            : "e.g. Marketing, Sales, Excel";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const user = VH.auth.getUser();
    if (!user) {
        VH.auth.redirectToLogin();
        return;
    }

    // Set sidebars/avatars
    updateHeaderAndSidebar(user);

    // Initial page load profile data
    loadUserProfile(user.id);

    document.querySelectorAll("input[name='pfAccountType']").forEach((radio) => {
        radio.addEventListener("change", () => {
            if (!radio.checked) return;
            rememberIdentityFor(currentAccountType);
            currentAccountType = radio.value;
            applyAccountTypeLayout(radio.value);
            fillIdentityFor(radio.value);
        });
    });

    // Opening the picker is one-way for this visit: once they are choosing, the
    // settled line would only contradict whichever card is now selected.
    const typeChangeBtn = document.getElementById("pfTypeChange");
    if (typeChangeBtn) {
        typeChangeBtn.addEventListener("click", () => showAccountTypePicker(true));
    }

    // Bio character counter
    const pfBio = document.getElementById("pfBio");
    const bioCount = document.getElementById("bioCount");
    if (pfBio && bioCount) {
        pfBio.addEventListener("input", () => {
            bioCount.textContent = `${pfBio.value.length}/250`;
        });
    }

    // Past experience character counter
    const pfPastExperience = document.getElementById("pfPastExperience");
    const experienceCount = document.getElementById("experienceCount");
    if (pfPastExperience && experienceCount) {
        pfPastExperience.addEventListener("input", () => {
            experienceCount.textContent = `${pfPastExperience.value.length}/500`;
        });
    }

    // Profile photo upload
    const avatarInput = document.getElementById("avatarInput");
    if (avatarInput) {
        avatarInput.addEventListener("change", async () => {
            const picked = avatarInput.files[0];
            avatarInput.value = "";
            if (!picked) return;

            if (!picked.type.startsWith("image/")) {
                VH.toast.error("Please choose an image file (JPG, PNG, or WEBP).");
                return;
            }
            if (picked.size > 3 * 1024 * 1024) {
                VH.toast.error("Image must be smaller than 3MB.");
                return;
            }

            // Crop/zoom the photo (square) before uploading.
            const file = await VH.cropImage(picked, { aspectRatio: 1, title: "Adjust profile photo", maxWidth: 800, maxHeight: 800 });
            if (!file) return;

            const profileAvCircle = document.getElementById("profileAvCircle");
            const editBtn = document.getElementById("avatarEditBtn");
            if (editBtn) { editBtn.classList.add("is-busy"); editBtn.style.pointerEvents = "none"; }

            const fd = new FormData();
            fd.append("action", "upload_avatar");
            fd.append("avatar", file);

            try {
                const response = await fetch("../api/profile.php", {
                    method: "POST",
                    credentials: "same-origin",
                    body: fd
                });
                const res = await response.json();

                if (res.success) {
                    VH.toast.success("Profile photo updated!");
                    const updatedUser = { ...user, avatarUrl: res.avatarUrl };
                    localStorage.setItem("vh_user", JSON.stringify(updatedUser));
                    if (profileAvCircle) VH.renderAvatarInto(profileAvCircle, updatedUser);
                    updateHeaderAndSidebar(updatedUser);
                } else {
                    VH.toast.error(res.message || "Failed to upload photo.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error uploading photo.");
            } finally {
                if (editBtn) { editBtn.classList.remove("is-busy"); editBtn.style.pointerEvents = "auto"; }
                avatarInput.value = "";
            }
        });
    }

    // Cover photo upload
    const coverEditBtn = document.getElementById("coverEditBtn");
    const coverInput = document.getElementById("coverInput");
    if (coverEditBtn && coverInput) {
        coverEditBtn.addEventListener("click", () => coverInput.click());
        coverInput.addEventListener("change", async () => {
            const picked = coverInput.files[0];
            coverInput.value = "";
            if (!picked) return;

            if (!picked.type.startsWith("image/")) {
                VH.toast.error("Please choose an image file (JPG, PNG, or WEBP).");
                return;
            }
            if (picked.size > 5 * 1024 * 1024) {
                VH.toast.error("Image must be smaller than 5MB.");
                return;
            }

            // Crop/position the cover (wide banner) before uploading.
            const file = await VH.cropImage(picked, { aspectRatio: 3, title: "Adjust cover photo", maxWidth: 1800, maxHeight: 700 });
            if (!file) return;

            const coverEditBtnLabel = coverEditBtn.querySelector("span");
            const originalLabel = coverEditBtnLabel ? coverEditBtnLabel.textContent : "";
            if (coverEditBtnLabel) coverEditBtnLabel.textContent = "Uploading...";
            coverEditBtn.style.pointerEvents = "none";

            const fd = new FormData();
            fd.append("action", "upload_cover");
            fd.append("cover", file);

            try {
                const response = await fetch("../api/profile.php", {
                    method: "POST",
                    credentials: "same-origin",
                    body: fd
                });
                const res = await response.json();

                if (res.success) {
                    VH.toast.success("Cover photo updated!");
                    const updatedUser = { ...user, coverUrl: res.coverUrl };
                    localStorage.setItem("vh_user", JSON.stringify(updatedUser));
                    applyProfileCover(res.coverUrl);
                } else {
                    VH.toast.error(res.message || "Failed to upload cover photo.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error uploading cover photo.");
            } finally {
                if (coverEditBtnLabel) coverEditBtnLabel.textContent = originalLabel;
                coverEditBtn.style.pointerEvents = "auto";
                coverInput.value = "";
            }
        });
    }

    const deleteAccountBtn = document.getElementById("sidebarDeleteAccount");
    if (deleteAccountBtn) deleteAccountBtn.addEventListener("click", () => {
        VH.modal.open("deleteAccountModal");
        VH.deleteAccountRefund.gate();
    });

    VH.deleteAccountRefund.init();

    const confirmDeleteAccountBtn = document.getElementById("confirmDeleteAccountBtn");
    if (confirmDeleteAccountBtn) {
        confirmDeleteAccountBtn.addEventListener("click", async () => {
            const typed = document.getElementById("deleteAccountConfirm").value.trim();
            if (typed !== "DELETE") { VH.toast.error('Please type DELETE to confirm.'); return; }

            // Optional refund-of-fees claim filed alongside the closure.
            const refund = VH.deleteAccountRefund.collect();
            if (refund.error) { VH.toast.error(refund.error); return; }

            confirmDeleteAccountBtn.disabled = true;
            confirmDeleteAccountBtn.textContent = "Deleting...";
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
                    confirmDeleteAccountBtn.disabled = false;
                    confirmDeleteAccountBtn.textContent = "Yes, delete permanently";
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error deleting your account.");
                confirmDeleteAccountBtn.disabled = false;
                confirmDeleteAccountBtn.textContent = "Yes, delete permanently";
            }
        });
    }

    // Mobile nav sidebar hamburger
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
            const pageWrapper = document.querySelector(".page-wrapper");
            if (pageWrapper) pageWrapper.classList.toggle("sidebar-collapsed");
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

    // Profile page tab switching
    const tabBtns = document.querySelectorAll(".tab-btn[data-tab]");
    const panels = document.querySelectorAll(".tab-panel[data-panel]");
    tabBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            tabBtns.forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            const target = btn.dataset.tab;

            panels.forEach(p => {
                if (p.dataset.panel === target) {
                    p.classList.add("active");
                } else {
                    p.classList.remove("active");
                }
            });
        });
    });

    // Profile Form submit
    const profileForm = document.getElementById("profileForm");
    if (profileForm) {
        profileForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            const btn = profileForm.querySelector("button[type='submit']");
            btn.textContent = "Saving...";
            btn.disabled = true;

            const name = document.getElementById("pfName").value.trim();
            const phone = document.getElementById("pfPhone").value.trim();
            const age = document.getElementById("pfAge").value.trim();
            const occupation = document.getElementById("pfOccupation").value.trim();
            const skills = document.getElementById("pfSkills").value.trim();
            const city = document.getElementById("pfCity").value.trim();
            const bio = document.getElementById("pfBio").value.trim();
            const linkedinUrl = document.getElementById("pfLinkedin").value.trim();
            const twitterUrl = document.getElementById("pfTwitter").value.trim();
            const instagramUrl = document.getElementById("pfInstagram").value.trim();
            const websiteUrl = document.getElementById("pfWebsite").value.trim();
            const pastExperience = document.getElementById("pfPastExperience").value.trim();
            const accountTypeEl = document.querySelector("input[name='pfAccountType']:checked");
            const accountType = accountTypeEl ? accountTypeEl.value : "individual";
            const companyReg = (document.getElementById("pfCompanyReg")?.value || "").trim();
            const companyFounded = (document.getElementById("pfCompanyFounded")?.value || "").trim();
            const companySize = (document.getElementById("pfCompanySize")?.value || "").trim();
            const companyIndustry = (document.getElementById("pfCompanyIndustry")?.value || "").trim();

            // Calculate two letters avatar
            const avatar = name.split(" ").map(n => n[0]).join("").slice(0,2).toUpperCase();

            try {
                const response = await fetch("../api/profile.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        action: "update",
                        user_id: user.id,
                        name,
                        phone,
                        age: age ? parseInt(age) : null,
                        occupation,
                        skills,
                        city,
                        bio,
                        avatar,
                        linkedin_url: linkedinUrl,
                        twitter_url: twitterUrl,
                        instagram_url: instagramUrl,
                        website_url: websiteUrl,
                        past_experience: pastExperience,

                        account_type: accountType,
                        company_registration_no: companyReg,
                        company_founded_year: companyFounded ? parseInt(companyFounded) : null,
                        company_size: companySize,
                        company_industry: companyIndustry
                    })
                });
                const res = await response.json();
                if (res.success) {
                    // Update user session storage
                    const updatedUser = { ...user, name, phone, age, occupation, skills, city, bio, avatar, linkedinUrl, twitterUrl, instagramUrl, websiteUrl, pastExperience, accountType, accountTypeConfirmed: 1 };
                    VH.auth.setUser(updatedUser);
                    updateHeaderAndSidebar(updatedUser);

                    // Every save confirms the type, so the question is settled from
                    // here on and the picker folds back into the statement.
                    paintAccountTypeSettled(accountType);
                    showAccountTypePicker(false);
                    const savedNotice = document.getElementById("pfTypeNotice");
                    if (savedNotice) savedNotice.classList.add("hidden");

                    VH.toast.success("Profile saved successfully.");
                } else {
                    VH.toast.error(res.message || "Failed to update profile.");
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error updating profile.");
            } finally {
                btn.textContent = "Save Changes";
                btn.disabled = false;
            }
        });
    }

    // Fetch activities under Activity tab
    fetchUserActivity(user.id);
});

function applyProfileCover(coverUrl) {
    const coverEl = document.getElementById("profileCover");
    const hintEl = document.getElementById("profileCoverHint");
    if (!coverEl) return;
    if (coverUrl) {
        coverEl.style.backgroundImage =
            `linear-gradient(135deg, rgba(15,23,42,0.35), rgba(57,131,246,0.35)), url('../${coverUrl}')`;
    } else {
        coverEl.style.backgroundImage = "";
    }
    if (hintEl) hintEl.classList.toggle("hidden", !!coverUrl);
}

function updateHeaderAndSidebar(user) {
    const sidebarAvatar = document.getElementById("sidebarAvatar");
    const sidebarUserName = document.getElementById("sidebarUserName");
    const navAvatar = document.getElementById("navAvatar");
    const navAvatarName = document.getElementById("navAvatarName");

    if (sidebarAvatar) VH.renderAvatarInto(sidebarAvatar, user);
    if (sidebarUserName) sidebarUserName.textContent = user.name;
    if (navAvatar) VH.renderAvatarInto(navAvatar, user);
    if (navAvatarName) navAvatarName.textContent = user.name.split(" ")[0];

    // Left card on profile
    const profileAvCircle = document.getElementById("profileAvCircle");
    const profileName = document.getElementById("profileName");

    if (profileAvCircle) VH.renderAvatarInto(profileAvCircle, user);
    if (profileName) profileName.textContent = user.name;

    const profileOccText = document.getElementById("profileOccText");
    const profileCityText = document.getElementById("profileCityText");
    if (profileOccText) profileOccText.textContent = user.occupation || 'Member';
    if (profileCityText) profileCityText.textContent = user.city || 'India';

    renderProfileSocialLinks(user);
    applyProfileCover(user.coverUrl);
}

const SOCIAL_ICON_SVGS = {
    linkedin: '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.03-1.85-3.03-1.85 0-2.14 1.45-2.14 2.94v5.66H9.36V9h3.41v1.56h.05c.47-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45z"/></svg>',
    twitter: '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 2H22l-7.6 8.7L23 22h-6.9l-5.4-6.9L4.4 22H1.3l8.1-9.3L1 2h7.1l4.9 6.4L18.9 2zm-1.2 18h1.9L7.4 4H5.4l12.3 16z"/></svg>',
    instagram: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17.4" cy="6.6" r="0.9" fill="currentColor" stroke="none"/></svg>',
    website: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>'
};

function renderProfileSocialLinks(user) {
    const wrap = document.getElementById("profileSocialLinks");
    if (!wrap) return;

    const links = [
        { key: 'linkedin', url: user.linkedinUrl },
        { key: 'twitter', url: user.twitterUrl },
        { key: 'instagram', url: user.instagramUrl },
        { key: 'website', url: user.websiteUrl }
    ].filter(l => l.url);

    if (links.length === 0) {
        wrap.innerHTML = "";
        wrap.classList.add("hidden");
        return;
    }

    wrap.innerHTML = links.map(l =>
        `<a href="${l.url}" target="_blank" rel="noopener" class="profile-social-icon" title="${l.key.charAt(0).toUpperCase() + l.key.slice(1)}">${SOCIAL_ICON_SVGS[l.key]}</a>`
    ).join("");
    wrap.classList.remove("hidden");
}

async function loadUserProfile(userId) {
    try {
        const response = await fetch(`../api/profile.php?user_id=${userId}`);
        const res = await response.json();

        if (res.success && res.user) {
            // Pre-fill profile form fields
            const nameEl = document.getElementById("pfName");
            const emailEl = document.getElementById("pfEmail");
            const phoneEl = document.getElementById("pfPhone");
            const ageEl = document.getElementById("pfAge");
            const occEl = document.getElementById("pfOccupation");
            const skillsEl = document.getElementById("pfSkills");
            const cityEl = document.getElementById("pfCity");
            const bioEl = document.getElementById("pfBio");
            const bioCount = document.getElementById("bioCount");
            const linkedinEl = document.getElementById("pfLinkedin");
            const twitterEl = document.getElementById("pfTwitter");
            const instagramEl = document.getElementById("pfInstagram");
            const websiteEl = document.getElementById("pfWebsite");
            const experienceEl = document.getElementById("pfPastExperience");
            const experienceCount = document.getElementById("experienceCount");

            const acctType = res.user.accountType === "company" ? "company" : "individual";
            const acctRadio = document.querySelector(`input[name='pfAccountType'][value='${acctType}']`);
            if (acctRadio) acctRadio.checked = true;
            const regEl = document.getElementById("pfCompanyReg");
            const foundedEl = document.getElementById("pfCompanyFounded");
            const sizeEl = document.getElementById("pfCompanySize");
            if (regEl) regEl.value = res.user.companyRegistrationNo || "";
            if (foundedEl) foundedEl.value = res.user.companyFoundedYear || "";
            if (sizeEl) sizeEl.value = res.user.companySize || "";
            const industryEl = document.getElementById("pfCompanyIndustry");
            if (industryEl) industryEl.value = res.user.companyIndustry || "";

            const confirmed = Number(res.user.accountTypeConfirmed) === 1;
            const notice = document.getElementById("pfTypeNotice");
            if (notice) notice.classList.toggle("hidden", confirmed);

            // Chosen at signup → state it. Never chosen (accounts that predate the
            // signup question) → ask, alongside the notice above.
            paintAccountTypeSettled(acctType);
            showAccountTypePicker(!confirmed);

            applyAccountTypeLayout(acctType);

            // Seed both sides. The active side falls back to the live name/bio/
            // website so an account that predates these columns still shows its
            // own details rather than three empty boxes.
            currentAccountType = acctType;
            identityDraft.individual = {
                name: res.user.individualName ?? (acctType === "individual" ? (res.user.name || "") : ""),
                bio: res.user.individualBio ?? (acctType === "individual" ? (res.user.bio || "") : ""),
                website: res.user.individualWebsiteUrl ?? (acctType === "individual" ? (res.user.websiteUrl || "") : "")
            };
            identityDraft.company = {
                name: res.user.companyName ?? (acctType === "company" ? (res.user.name || "") : ""),
                bio: res.user.companyBio ?? (acctType === "company" ? (res.user.bio || "") : ""),
                website: res.user.companyWebsiteUrl ?? (acctType === "company" ? (res.user.websiteUrl || "") : "")
            };

            if (nameEl) nameEl.value = res.user.name || "";
            if (emailEl) emailEl.value = res.user.email || "";
            if (phoneEl) phoneEl.value = res.user.phone || "";
            if (ageEl) ageEl.value = res.user.age || "";
            if (occEl) occEl.value = res.user.occupation || "";
            if (skillsEl) skillsEl.value = res.user.skills || "";
            if (cityEl) cityEl.value = res.user.city || "";
            if (bioEl) bioEl.value = res.user.bio || "";
            if (bioCount && bioEl) bioCount.textContent = `${bioEl.value.length}/250`;
            if (linkedinEl) linkedinEl.value = res.user.linkedinUrl || "";
            if (twitterEl) twitterEl.value = res.user.twitterUrl || "";
            if (instagramEl) instagramEl.value = res.user.instagramUrl || "";
            if (websiteEl) websiteEl.value = res.user.websiteUrl || "";
            if (experienceEl) experienceEl.value = res.user.pastExperience || "";
            if (experienceCount && experienceEl) experienceCount.textContent = `${experienceEl.value.length}/500`;

            // Sync session object
            VH.auth.setUser(res.user);
            updateHeaderAndSidebar(res.user);
        }
    } catch (err) {
        console.error(err);
    }
}

function formatInvested(amount) {
    return VH.card.money(amount);
}

async function fetchUserActivity(userId) {
    const list = document.getElementById("activityList");
    if (!list) return;

    try {
        const response = await fetch(`../api/dashboard_data.php?user_id=${userId}`);
        const res = await response.json();

        if (res.success && res.stats) {
            const createdEl = document.getElementById("statCreated");
            const joinedEl = document.getElementById("statJoined");
            const investedEl = document.getElementById("statInvested");
            if (createdEl) createdEl.textContent = res.stats.myVenturesCount;
            if (joinedEl) joinedEl.textContent = res.stats.joinedCount;
            if (investedEl) investedEl.textContent = formatInvested(res.stats.investedTotal);
        }

        if (res.success && res.activities) {
            if (res.activities.length === 0) {
                list.innerHTML = `<p style="text-align:center;font-size:0.8rem;color:#94a3b8;margin:1rem 0;">No activities found.</p>`;
                return;
            }

            let html = '<div class="activity-feed-list" style="display:flex;flex-direction:column;gap:0.75rem;padding:0.5rem 0;">';
            res.activities.forEach(a => {
                /* Same source and same fix as the dashboard feed: the age is
                   measured by the database (seconds_ago), because parsing the bare
                   timestamp here measures the gap between MySQL's clock and this
                   browser's instead. The fallback keeps an old payload rendering. */
                let diffDays;
                if (a.seconds_ago !== undefined && a.seconds_ago !== null) {
                    diffDays = Math.floor(Math.max(0, Number(a.seconds_ago)) / 86400);
                } else {
                    const prev = new Date(String(a.time || "").replace(/-/g, "/"));
                    diffDays = Math.floor((Date.now() - prev.getTime()) / 86400000);
                }
                const timeStr = diffDays <= 0
                    ? "Today"
                    : `${diffDays} day${diffDays === 1 ? "" : "s"} ago`;

                html += `
                    <div style="display:flex;gap:0.75rem;font-size:0.8rem;">
                        <div style="color:#3983F6;font-weight:700;">•</div>
                        <div style="flex:1">
                            <span style="color:#334155;">${a.text}</span>
                            <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">${timeStr} · ${a.time}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            list.innerHTML = html;
        }
    } catch (err) {
        console.error(err);
    }
}
