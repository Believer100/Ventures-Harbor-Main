/* VENTURES HARBOR — AUTHENTICATION LOGIC (auth.js) */

document.addEventListener("DOMContentLoaded", () => {
    // DOM Elements
    const authTabs = document.getElementById("authTabs");
    const tabSignin = document.getElementById("tabSignin");
    const tabSignup = document.getElementById("tabSignup");

    const panelSignin = document.getElementById("panelSignin");
    const panelSignup = document.getElementById("panelSignup");
    const panelVerify = document.getElementById("panelVerify");
    const panelForgot = document.getElementById("panelForgot");

    const signinForm = document.getElementById("signinForm");
    const signupForm = document.getElementById("signupForm");
    const verifyForm = document.getElementById("verifyForm");
    const forgotForm = document.getElementById("forgotForm");

    // Form inputs - Sign In
    const siEmail = document.getElementById("siEmail");
    const siPassword = document.getElementById("siPassword");
    const rememberMe = document.getElementById("rememberMe");

    // Form inputs - Sign Up
    const suName = document.getElementById("suName");

    const accountType = "individual";
    const suEmail = document.getElementById("suEmail");
    const suPassword = document.getElementById("suPassword");
    const suConfirm = document.getElementById("suConfirm");
    const suTerms = document.getElementById("suTerms");

    // Form inputs - Forgot
    const feEmail = document.getElementById("feEmail");

    // Eye toggles
    const siEye = document.getElementById("siEye");
    const suEye = document.getElementById("suEye");

    // Verification elements
    const verifyEmailSpan = document.getElementById("verifyEmail");
    const otpCountdown = document.getElementById("otpCountdown");
    const otpInputs = Array.from({ length: 6 }, (_, i) => document.getElementById(`otp${i}`));

    // Links for switching panels
    const forgotPassLink = document.getElementById("forgotPassLink");
    const switchToSignup = document.getElementById("switchToSignup");
    const switchToSignin = document.getElementById("switchToSignin");
    const backToSignin = document.getElementById("backToSignin");
    const forgotBackToSignin = document.getElementById("forgotBackToSignin");

    // Current state variables
    let emailForVerification = "";
    let otpResendTimer = null;
    let otpSecondsLeft = 60;

    /* ── PANEL SWITCHING FUNCTIONS ── */
    function showPanel(panelToShow) {
        // Hide all panels
        [panelSignin, panelSignup, panelVerify, panelForgot].forEach(p => {
            if (p) p.classList.add("hidden");
        });
        // Show selected panel
        if (panelToShow) {
            panelToShow.classList.remove("hidden");
        }

        // Toggle tab header visibility
        if (panelToShow === panelSignin || panelToShow === panelSignup) {
            if (authTabs) authTabs.classList.remove("hidden");
            if (panelToShow === panelSignin) {
                tabSignin.classList.add("active");
                tabSignup.classList.remove("active");
            } else {
                tabSignup.classList.add("active");
                tabSignin.classList.remove("active");
            }
        } else {
            if (authTabs) authTabs.classList.add("hidden");
        }
    }

    // Tab buttons event listeners
    if (tabSignin) tabSignin.addEventListener("click", () => showPanel(panelSignin));
    if (tabSignup) tabSignup.addEventListener("click", () => showPanel(panelSignup));

    // Link redirection listeners
    if (forgotPassLink) {
        forgotPassLink.addEventListener("click", (e) => {
            e.preventDefault();
            showPanel(panelForgot);
        });
    }
    if (switchToSignup) {
        switchToSignup.addEventListener("click", (e) => {
            e.preventDefault();
            showPanel(panelSignup);
        });
    }
    if (switchToSignin) {
        switchToSignin.addEventListener("click", (e) => {
            e.preventDefault();
            showPanel(panelSignin);
        });
    }
    if (backToSignin) {
        backToSignin.addEventListener("click", (e) => {
            e.preventDefault();
            showPanel(panelSignin);
        });
    }
    if (forgotBackToSignin) {
        forgotBackToSignin.addEventListener("click", (e) => {
            e.preventDefault();
            showPanel(panelSignin);
        });
    }

    /* ── PASSWORD EYE TOGGLE ── */
    function setupEyeToggle(btn, input) {
        if (!btn || !input) return;
        btn.addEventListener("click", () => {
            const isPassword = input.getAttribute("type") === "password";
            input.setAttribute("type", isPassword ? "text" : "password");
            btn.innerHTML = isPassword 
                ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
                : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
        });
    }
    setupEyeToggle(siEye, siPassword);
    setupEyeToggle(suEye, suPassword);

    /* ── PASSWORD STRENGTH METER ── */
    if (suPassword) {
        suPassword.addEventListener("input", () => {
            const val = suPassword.value;
            let score = 0;
            if (!val) {
                updateStrengthBar(0);
                return;
            }
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            updateStrengthBar(score);
        });
    }

    function updateStrengthBar(score) {
        const segs = [
            document.getElementById("ps1"),
            document.getElementById("ps2"),
            document.getElementById("ps3"),
            document.getElementById("ps4")
        ];
        const label = document.getElementById("psLabel");

        // Reset segments
        segs.forEach(s => {
            if (s) {
                s.className = "ps-seg";
            }
        });

        if (score === 0) {
            if (label) label.textContent = "Enter password";
            return;
        }

        const classes = ["weak", "fair", "good", "strong"];
        const texts = ["Weak (must be min. 8 chars)", "Fair", "Good", "Strong password"];

        if (label) {
            label.textContent = texts[score - 1];
            label.style.color = score === 1 ? '#ef4444' : score === 2 ? '#f97316' : score === 3 ? '#3983F6' : '#16a34a';
        }

        for (let i = 0; i < score; i++) {
            if (segs[i]) segs[i].classList.add(classes[score - 1]);
        }
    }

    /* ── OTP INPUT FLOW ── */
    otpInputs.forEach((input, index) => {
        if (!input) return;
        input.addEventListener("input", (e) => {
            const val = e.target.value;
            // Clean up non-digits
            e.target.value = val.replace(/[^0-9]/g, '');
            if (e.target.value && index < 5) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && !e.target.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });

        // Paste event support
        input.addEventListener("paste", (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData("text").trim();
            if (/^\d{6}$/.test(text)) {
                for (let i = 0; i < 6; i++) {
                    otpInputs[i].value = text[i];
                }
                otpInputs[5].focus();
            }
        });
    });

    function startOTPResendTimer() {
        if (otpResendTimer) clearInterval(otpResendTimer);
        otpSecondsLeft = 60;
        if (otpCountdown) otpCountdown.textContent = `0:${otpSecondsLeft}`;

        otpResendTimer = setInterval(() => {
            otpSecondsLeft--;
            if (otpSecondsLeft <= 0) {
                clearInterval(otpResendTimer);
                if (otpCountdown) {
                    otpCountdown.innerHTML = `<a href="#" id="resendOtpBtn" style="color:#3983F6;font-weight:600;text-decoration:none;">Resend code</a>`;
                    document.getElementById("resendOtpBtn").addEventListener("click", (e) => {
                        e.preventDefault();
                        resendOTP();
                    });
                }
            } else {
                if (otpCountdown) otpCountdown.textContent = `0:${otpSecondsLeft < 10 ? '0' : ''}${otpSecondsLeft}`;
            }
        }, 1000);
    }

    async function resendOTP() {
        try {
            const response = await fetch("../api/auth.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    action: "resend_otp",
                    email: emailForVerification
                })
            });
            const data = await response.json();
            if (data.success) {
                VH.toast.success(data.message || "OTP verification code resent successfully!");
                if (data.devOtp) {
                    VH.toast.info("Dev OTP: " + data.devOtp, "Email fallback");
                }
                startOTPResendTimer();
            } else {
                VH.toast.error(data.message || "Failed to resend OTP.");
            }
        } catch (err) {
            console.error(err);
            VH.toast.error("Network error. Please try again.");
        }
    }

    /* ── FORM SUBMISSIONS ── */

    // 1. SIGN IN
    if (signinForm) {
        signinForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            // Clear errors
            document.getElementById("siEmailErr").textContent = "";
            document.getElementById("siPassErr").textContent = "";

            const email = siEmail.value.trim();
            const password = siPassword.value;

            let valid = true;
            if (!email) {
                document.getElementById("siEmailErr").textContent = "Email address is required";
                valid = false;
            }
            if (!password) {
                document.getElementById("siPassErr").textContent = "Password is required";
                valid = false;
            }
            if (!valid) return;

            const btn = document.getElementById("signinBtn");
            btn.textContent = "Signing In...";
            btn.disabled = true;

            try {
                const response = await fetch("../api/auth.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action: "login",
                        email,
                        password
                    })
                });
                const data = await response.json();
                if (data.success) {
                    VH.toast.success("Welcome back! Redirecting...");
                    VH.auth.setUser(data.user);
                    setTimeout(() => {
                        VH.auth.redirectToDashboard(data.user);
                    }, 1000);
                } else {
                    VH.toast.error(data.message || "Invalid credentials.");
                    btn.textContent = "Sign In";
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error signing in. Please check connection.");
                btn.textContent = "Sign In";
                btn.disabled = false;
            }
        });
    }

    // 2. SIGN UP
    if (signupForm) {
        signupForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            // Clear errors
            ["suNameErr", "suEmailErr", "suPassErr", "suConfirmErr", "suTermsErr"].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = "";
            });

            const name = suName.value.trim();
            const email = suEmail.value.trim();
            const password = suPassword.value;
            const confirm = suConfirm.value;
            const termsChecked = suTerms.checked;

            let valid = true;
            if (!name) {
                document.getElementById("suNameErr").textContent = "Full name is required";
                valid = false;
            }
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                document.getElementById("suEmailErr").textContent = "Enter a valid email address";
                valid = false;
            }
            if (!password || password.length < 8) {
                document.getElementById("suPassErr").textContent = "Password must be at least 8 characters";
                valid = false;
            }
            if (password !== confirm) {
                document.getElementById("suConfirmErr").textContent = "Passwords do not match";
                valid = false;
            }
            if (!termsChecked) {
                document.getElementById("suTermsErr").textContent = "You must accept the terms of service";
                valid = false;
            }
            if (!valid) return;

            const btn = document.getElementById("signupBtn");
            btn.textContent = "Creating Account...";
            btn.disabled = true;

            try {
                const response = await fetch("../api/auth.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action: "register",
                        name,
                        email,
                        password,
                        account_type: accountType
                    })
                });
                const data = await response.json();
                if (data.success) {
                    VH.toast.success(data.message);
                    if (data.devOtp) {
                        VH.toast.info("Dev OTP: " + data.devOtp, "Email fallback");
                    }
                    emailForVerification = email;
                    if (verifyEmailSpan) verifyEmailSpan.textContent = email;
                    showPanel(panelVerify);
                    startOTPResendTimer();
                } else {
                    VH.toast.error(data.message || "Failed to create account.");
                    btn.textContent = "Create Account";
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error registration failed.");
                btn.textContent = "Create Account";
                btn.disabled = false;
            }
        });
    }

    // 3. VERIFY OTP
    if (verifyForm) {
        verifyForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const errEl = document.getElementById("otpErr");
            if (errEl) errEl.textContent = "";

            const code = otpInputs.map(input => input.value).join("");
            if (code.length < 6) {
                if (errEl) errEl.textContent = "Please enter the full 6-digit verification code.";
                return;
            }

            const btn = document.getElementById("verifyBtn");
            btn.textContent = "Verifying...";
            btn.disabled = true;

            try {
                const response = await fetch("../api/auth.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action: "verify_otp",
                        email: emailForVerification || suEmail.value.trim(),
                        code
                    })
                });
                const data = await response.json();
                if (data.success) {
                    VH.toast.success("Account verified! Logged in successfully.");
                    VH.auth.setUser(data.user);
                    if (otpResendTimer) clearInterval(otpResendTimer);
                    setTimeout(() => {
                        VH.auth.redirectToDashboard(data.user);
                    }, 1000);
                } else {
                    VH.toast.error(data.message || "Invalid or expired OTP code.");
                    btn.textContent = "Verify & Continue";
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error during verification.");
                btn.textContent = "Verify & Continue";
                btn.disabled = false;
            }
        });
    }

    // 4. FORGOT PASSWORD
    if (forgotForm) {
        forgotForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const errEl = document.getElementById("feEmailErr");
            if (errEl) errEl.textContent = "";

            const email = feEmail.value.trim();
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                if (errEl) errEl.textContent = "Enter a valid email address";
                return;
            }

            const btn = document.getElementById("forgotBtn");
            btn.textContent = "Sending Link...";
            btn.disabled = true;

            try {
                const response = await fetch("../api/auth.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        action: "forgot",
                        email
                    })
                });
                const data = await response.json();
                if (data.success) {
                    VH.toast.success(data.message || "Reset link sent! Please check your email inbox.");
                    showPanel(panelSignin);
                } else {
                    VH.toast.error(data.message || "Email address could not be found.");
                    btn.textContent = "Send Reset Link";
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                VH.toast.error("Network error sending reset link.");
                btn.textContent = "Send Reset Link";
                btn.disabled = false;
            }
        });
    }
});
