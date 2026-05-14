/**
 * Auth.js - Login & Registration & Forgot Password scripts
 * Handles form validation, API calls, and page switching.
 */

// ----- Toggle password field visibility -----
function setupPasswordToggle(toggleId, inputId) {
  const toggleBtn = document.getElementById(toggleId);
  const input = document.getElementById(inputId);

  if (!toggleBtn || !input) return;

  toggleBtn.addEventListener("click", () => {
    const icon = toggleBtn.querySelector("i");
    const isHidden = input.type === "password";

    input.type = isHidden ? "text" : "password";
    if (icon) {
      icon.className = isHidden ? "fas fa-eye-slash" : "fas fa-eye";
    }
  });
}

// ----- Show / hide a loading spinner on a button -----
function setButtonLoading(button, isLoading, loadingText, defaultText) {
  if (!button) return;

  button.disabled = isLoading;
  button.innerHTML = isLoading
    ? `<span class="spinner"></span> ${loadingText}`
    : defaultText;
}

// ----- Key used to track whether the profile prompt was already shown -----
function getPatientProfilePromptKey(userId) {
  return `patient-profile-prompt-shown-${userId}`;
}

document.addEventListener("DOMContentLoaded", () => {
  // Redirect if already logged in
  checkExistingSession();

  // ===== LOGIN FORM =====
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAllErrors();

      const email = document.getElementById("login-email").value.trim();
      const password = document.getElementById("login-password").value;

      // Validate inputs
      let isValid = true;

      if (!email) {
        showFieldError("login-email", "login-email-error", "Email is required");
        isValid = false;
      } else if (!validateEmail(email)) {
        showFieldError(
          "login-email",
          "login-email-error",
          "Please enter a valid email",
        );
        isValid = false;
      }

      if (!password) {
        showFieldError(
          "login-password",
          "login-password-error",
          "Password is required",
        );
        isValid = false;
      }

      if (!isValid) return;

      // Show loading state
      const submitBtn = document.getElementById("login-submit-btn");
      setButtonLoading(submitBtn, true, "Logging in...", "Login");

      // Call login API
      const result = await apiCall("auth.php?action=login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });

      if (result.success) {
        saveSession(result.user);

        // Prompt patient to fill profile if incomplete
        if (
          result.user.role === "patient" &&
          result.user.needs_profile_completion
        ) {
          const promptKey = getPatientProfilePromptKey(result.user.id);
          if (localStorage.getItem(promptKey) !== "1") {
            alert("Please fill up your profile.");
            localStorage.setItem(promptKey, "1");
          }
        }

        showToast("Login successful! Redirecting...", "success");
        setTimeout(() => {
          window.location.href = `${result.user.role}.html`;
        }, 800);
      } else {
        showToast(result.message, "error");
        setButtonLoading(submitBtn, false, "", "Login");
      }
    });

    // Toggle password visibility
    setupPasswordToggle("toggle-login-pw", "login-password");

    // Clear errors on input
    document
      .getElementById("login-email")
      .addEventListener("input", () =>
        clearFieldError("login-email", "login-email-error"),
      );
    document
      .getElementById("login-password")
      .addEventListener("input", () =>
        clearFieldError("login-password", "login-password-error"),
      );
  }

  // ===== FORGOT PASSWORD TOGGLE =====
  const forgotLink = document.getElementById("forgot-password-link");
  const backToLoginLink = document.getElementById("back-to-login-link");
  const loginCard = document.getElementById("login-card");
  const forgotCard = document.getElementById("forgot-card");

  // Show forgot-password card when link is clicked
  if (forgotLink && loginCard && forgotCard) {
    forgotLink.addEventListener("click", (e) => {
      e.preventDefault();
      loginCard.classList.add("is-hidden");
      forgotCard.classList.remove("is-hidden");
    });
  }

  // Go back to login card
  if (backToLoginLink && loginCard && forgotCard) {
    backToLoginLink.addEventListener("click", (e) => {
      e.preventDefault();
      forgotCard.classList.add("is-hidden");
      loginCard.classList.remove("is-hidden");
    });
  }

  // ===== FORGOT PASSWORD FORM =====
  const forgotForm = document.getElementById("forgot-form");
  if (forgotForm) {
    forgotForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAllErrors();

      const email = document.getElementById("forgot-email").value.trim();

      // Validate email
      if (!email) {
        showFieldError(
          "forgot-email",
          "forgot-email-error",
          "Email is required",
        );
        return;
      }
      if (!validateEmail(email)) {
        showFieldError(
          "forgot-email",
          "forgot-email-error",
          "Please enter a valid email",
        );
        return;
      }

      // Show loading state
      const submitBtn = document.getElementById("forgot-submit-btn");
      setButtonLoading(submitBtn, true, "Sending...", "Send OTP");

      // Call forgot-password API
      const result = await apiCall("auth.php?action=forgotPassword", {
        method: "POST",
        body: JSON.stringify({ email }),
      });

      if (result.success) {
        showToast(result.message, "success", 5000);

        // Show email-sent confirmation with icon
        const linkDisplay = document.getElementById("reset-link-display");
        if (linkDisplay) {
          linkDisplay.classList.remove("is-hidden");

          if (result.otp) {
            // SMTP not configured – show the OTP directly (dev/demo mode)
            linkDisplay.innerHTML = `
              <div style="text-align:center;padding:10px 0;">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:40px;background:var(--primary-lighter, #e0f4f4);border-radius:6px;margin-bottom:16px;font-size:22px;box-shadow:0 2px 5px rgba(0,0,0,0.05);">🔑</div>
                <p style="margin:0 0 12px;font-weight:600;color:var(--text-primary);font-size:16px;">Your OTP (Dev Mode)</p>
                <p style="margin:0 0 8px;color:var(--text-secondary);font-size:15px;line-height:1.5;">
                  Email delivery not configured.
                </p>
                <p style="margin:0 0 16px;color:var(--text-muted);font-size:14px;">
                  Your OTP is: <strong style="color:var(--text-primary);">${result.otp}</strong>
                </p>
                <a href="reset-password.html" 
                   style="display:inline-block;padding:12px 32px;background:#7c3aed;color:#fff;font-size:16px;font-weight:600;text-decoration:none;border-radius:8px;margin-top:6px;transition:0.2s;box-shadow:0 4px 10px rgba(124,58,237,0.3);">
                  Enter OTP
                </a>
              </div>
            `;
          } else {
            // Email was sent successfully
            linkDisplay.innerHTML = `
              <div style="text-align:center;padding:10px 0;">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:40px;background:#ede9fe;border-radius:6px;margin-bottom:16px;font-size:22px;box-shadow:0 2px 5px rgba(0,0,0,0.05);">📧</div>
                <p style="margin:0 0 16px;font-weight:600;color:var(--text-primary);font-size:15px;">Check Your Email</p>
                <p style="margin:0 0 16px;color:var(--text-secondary);font-size:14px;line-height:1.5;">
                  We've sent an OTP to <strong style="color:var(--text-primary);">${email}</strong>.
                </p>
                <p style="margin:0 0 20px;color:var(--text-muted);font-size:13px;">
                  The OTP will expire in 60 minutes.
                </p>
                <a href="reset-password.html" 
                   style="display:inline-block;padding:12px 36px;background:#7c3aed;color:#fff;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;margin-top:4px;transition:0.2s;box-shadow:0 4px 10px rgba(124,58,237,0.3);">
                  Enter OTP
                </a>
              </div>
            `;
          }
        }
      } else {
        showToast(result.message, "error");
      }

      setButtonLoading(submitBtn, false, "", "Send OTP");
    });

    // Clear error on input
    const forgotEmailEl = document.getElementById("forgot-email");
    if (forgotEmailEl) {
      forgotEmailEl.addEventListener("input", () =>
        clearFieldError("forgot-email", "forgot-email-error"),
      );
    }
  }

  // ===== REGISTER FORM =====
  const registerForm = document.getElementById("register-form");
  if (registerForm) {
    // Show/hide doctor-specific fields based on role selection
    const roleSelect = document.getElementById("reg-role");
    const doctorFields = document.getElementById("doctor-fields");

    if (roleSelect && doctorFields) {
      const syncDoctorFieldVisibility = () => {
        const isDoctor = roleSelect.value === "doctor";
        doctorFields.classList.toggle("is-hidden", !isDoctor);
        clearFieldError("reg-role", "reg-role-error");
      };

      roleSelect.addEventListener("change", syncDoctorFieldVisibility);
      syncDoctorFieldVisibility();

      // Clear NMC error on input
      const nmcInput = document.getElementById("reg-nmc");
      if (nmcInput) {
        nmcInput.addEventListener("input", () =>
          clearFieldError("reg-nmc", "reg-nmc-error"),
        );
      }
    }

    registerForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAllErrors();

      const name = document.getElementById("reg-name").value.trim();
      const email = document.getElementById("reg-email").value.trim();
      const password = document.getElementById("reg-password").value;
      const confirmPassword = document.getElementById(
        "reg-confirm-password",
      ).value;
      const role = document.getElementById("reg-role").value;
      const specialization =
        document.getElementById("reg-specialization")?.value || "";
      const nmc = document.getElementById("reg-nmc")?.value.trim() || "";

      // Validate all fields
      let isValid = true;

      if (!name) {
        showFieldError("reg-name", "reg-name-error", "Full name is required");
        isValid = false;
      } else if (name.length < 2) {
        showFieldError(
          "reg-name",
          "reg-name-error",
          "Name must be at least 2 characters",
        );
        isValid = false;
      }

      if (!email) {
        showFieldError("reg-email", "reg-email-error", "Email is required");
        isValid = false;
      } else if (!validateEmail(email)) {
        showFieldError(
          "reg-email",
          "reg-email-error",
          "Please enter a valid email address",
        );
        isValid = false;
      }

      const pwValidation = validatePassword(password);
      if (!password) {
        showFieldError(
          "reg-password",
          "reg-password-error",
          "Password is required",
        );
        isValid = false;
      } else if (!pwValidation.valid) {
        showFieldError(
          "reg-password",
          "reg-password-error",
          pwValidation.message,
        );
        isValid = false;
      }

      if (password !== confirmPassword) {
        showFieldError(
          "reg-confirm-password",
          "reg-confirm-error",
          "Passwords do not match",
        );
        isValid = false;
      }

      if (!role) {
        showFieldError("reg-role", "reg-role-error", "Please select a role");
        isValid = false;
      } else if (role === "doctor") {
        if (!nmc) {
          showFieldError(
            "reg-nmc",
            "reg-nmc-error",
            "NMC number is required for doctors",
          );
          isValid = false;
        }
      }

      if (!isValid) return;

      // Show loading state
      const submitBtn = document.getElementById("register-submit-btn");
      setButtonLoading(
        submitBtn,
        true,
        "Creating account...",
        "Create Account",
      );

      // Call register API
      const result = await apiCall("auth.php?action=register", {
        method: "POST",
        body: JSON.stringify({
          name,
          email,
          password,
          role,
          specialization,
          nmc,
        }),
      });

      if (result.success) {
        showToast(result.message, "success", 5000);
        setTimeout(() => {
          window.location.href = "login.html";
        }, 2000);
      } else {
        showToast(result.message, "error");
        setButtonLoading(submitBtn, false, "", "Create Account");
      }
    });

    // Toggle password visibility
    setupPasswordToggle("toggle-reg-pw", "reg-password");
    setupPasswordToggle("toggle-reg-confirm-pw", "reg-confirm-password");

    // Clear errors on input for each field
    const fieldErrorMap = {
      "reg-name": "reg-name-error",
      "reg-email": "reg-email-error",
      "reg-password": "reg-password-error",
      "reg-confirm-password": "reg-confirm-error",
    };

    Object.entries(fieldErrorMap).forEach(([fieldId, errorId]) => {
      const el = document.getElementById(fieldId);
      if (el) {
        el.addEventListener("input", () => clearFieldError(fieldId, errorId));
      }
    });
  }
});
