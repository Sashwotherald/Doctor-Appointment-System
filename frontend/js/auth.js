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
        showFieldError("forgot-email", "forgot-email-error", "Email is required");
        return;
      }
      if (!validateEmail(email)) {
        showFieldError("forgot-email", "forgot-email-error", "Please enter a valid email");
        return;
      }

      // Show loading state
      const submitBtn = document.getElementById("forgot-submit-btn");
      setButtonLoading(submitBtn, true, "Sending...", "Send Reset Link");

      // Call forgot-password API
      const result = await apiCall("auth.php?action=forgotPassword", {
        method: "POST",
        body: JSON.stringify({ email }),
      });

      if (result.success) {
        showToast("If the email exists, a reset link has been generated.", "success", 5000);

        // Display the reset link so the user can click it (simulates email)
        const linkDisplay = document.getElementById("reset-link-display");
        if (linkDisplay && result.reset_link) {
          linkDisplay.classList.remove("is-hidden");
          linkDisplay.innerHTML = `
            <p><strong>Reset link (click below):</strong></p>
            <a href="${result.reset_link}" class="reset-link-anchor">${result.reset_link}</a>
            <p class="reset-link-note">In a real system this link would be sent to your email.</p>
          `;
        }
      } else {
        showToast(result.message, "error");
      }

      setButtonLoading(submitBtn, false, "", "Send Reset Link");
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
        body: JSON.stringify({ name, email, password, role, specialization }),
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
