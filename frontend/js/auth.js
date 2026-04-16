/**
 * Auth.js - Login & Registration scripts
 */

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

function setButtonLoading(button, isLoading, loadingText, defaultText) {
  if (!button) return;

  button.disabled = isLoading;
  button.innerHTML = isLoading
    ? `<span class="spinner"></span> ${loadingText}`
    : defaultText;
}

function getPatientProfilePromptKey(userId) {
  return `patient-profile-prompt-shown-${userId}`;
}

document.addEventListener("DOMContentLoaded", () => {
  // Check existing session
  checkExistingSession();

  // ===== LOGIN FORM =====
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAllErrors();

      const email = document.getElementById("login-email").value.trim();
      const password = document.getElementById("login-password").value;

      // Validate
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

      // Disable button
      const submitBtn = document.getElementById("login-submit-btn");
      setButtonLoading(submitBtn, true, "Logging in...", "Login");

      const result = await apiCall("auth.php?action=login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });

      if (result.success) {
        saveSession(result.user);

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

  // ===== REGISTER FORM =====
  const registerForm = document.getElementById("register-form");
  if (registerForm) {
    // Show/hide doctor fields based on role
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

      // Validate
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

      // Disable button
      const submitBtn = document.getElementById("register-submit-btn");
      setButtonLoading(
        submitBtn,
        true,
        "Creating account...",
        "Create Account",
      );

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

    // Clear errors on input
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
