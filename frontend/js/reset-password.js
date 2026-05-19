/**
 * Reset Password Page Script
<<<<<<< HEAD
 * Reads the token from the URL, validates it, and shows the reset form.
 */
document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');

  const resetCard = document.getElementById('reset-card');
  const invalidCard = document.getElementById('invalid-token-card');

  // If no token in URL, show invalid card
  if (!token) {
    resetCard.classList.add('is-hidden');
    invalidCard.classList.remove('is-hidden');
    return;
  }

  // Validate token with backend
  const tokenResult = await apiCall(`auth.php?action=validateToken&token=${token}`);
  if (!tokenResult.success) {
    resetCard.classList.add('is-hidden');
    invalidCard.classList.remove('is-hidden');
    return;
  }

  // ----- Password toggle -----
  const toggleBtn = document.getElementById('toggle-reset-pw');
  const pwInput = document.getElementById('reset-password');
  if (toggleBtn && pwInput) {
    toggleBtn.addEventListener('click', () => {
      const isHidden = pwInput.type === 'password';
      pwInput.type = isHidden ? 'text' : 'password';
      toggleBtn.querySelector('i').className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
=======
 * Reads the OTP from user input, and submits it along with the new password.
 */
document.addEventListener("DOMContentLoaded", async () => {
  const resetCard = document.getElementById("reset-card");
  const invalidCard = document.getElementById("invalid-token-card");

  // ----- Password toggle -----
  const toggleBtn = document.getElementById("toggle-reset-pw");
  const pwInput = document.getElementById("reset-password");
  if (toggleBtn && pwInput) {
    toggleBtn.addEventListener("click", () => {
      const isHidden = pwInput.type === "password";
      pwInput.type = isHidden ? "text" : "password";
      toggleBtn.querySelector("i").className = isHidden
        ? "fas fa-eye-slash"
        : "fas fa-eye";
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    });
  }

  // ----- Confirm password toggle -----
<<<<<<< HEAD
  const toggleConfirmBtn = document.getElementById('toggle-reset-confirm-pw');
  const confirmPwInput = document.getElementById('reset-confirm-password');
  if (toggleConfirmBtn && confirmPwInput) {
    toggleConfirmBtn.addEventListener('click', () => {
      const isHidden = confirmPwInput.type === 'password';
      confirmPwInput.type = isHidden ? 'text' : 'password';
      toggleConfirmBtn.querySelector('i').className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
  }

  // ----- Reset form submit -----
  document.getElementById('reset-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    clearAllErrors();

    const password = document.getElementById('reset-password').value;
    const confirmPassword = document.getElementById('reset-confirm-password').value;

    // Validate password
    let isValid = true;

    if (!password) {
      showFieldError('reset-password', 'reset-password-error', 'Password is required');
      isValid = false;
    } else if (password.length < 6) {
      showFieldError('reset-password', 'reset-password-error', 'Password must be at least 6 characters');
      isValid = false;
    }

    if (password !== confirmPassword) {
      showFieldError('reset-confirm-password', 'reset-confirm-error', 'Passwords do not match');
      isValid = false;
    }

    if (!isValid) return;

    // Disable button
    const submitBtn = document.getElementById('reset-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Resetting...';

    // Call reset API
    const result = await apiCall('auth.php?action=resetPassword', {
      method: 'POST',
      body: JSON.stringify({ token: token, password: password })
    });

    if (result.success) {
      showToast('Password reset successful! Redirecting to login...', 'success', 5000);
      setTimeout(() => {
        window.location.href = 'login.html';
      }, 2000);
    } else {
      showToast(result.message, 'error');
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Reset Password';
    }
  });
=======
  const toggleConfirmBtn = document.getElementById("toggle-reset-confirm-pw");
  const confirmPwInput = document.getElementById("reset-confirm-password");
  if (toggleConfirmBtn && confirmPwInput) {
    toggleConfirmBtn.addEventListener("click", () => {
      const isHidden = confirmPwInput.type === "password";
      confirmPwInput.type = isHidden ? "text" : "password";
      toggleConfirmBtn.querySelector("i").className = isHidden
        ? "fas fa-eye-slash"
        : "fas fa-eye";
    });
  }

  // ----- Clear errors on input -----
  document.getElementById("reset-otp").addEventListener("input", () => {
    clearFieldError("reset-otp", "reset-otp-error");
  });

  // ----- Reset form submit -----
  document
    .getElementById("reset-form")
    .addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAllErrors();

      const otp = document.getElementById("reset-otp").value.trim();
      const password = document.getElementById("reset-password").value;
      const confirmPassword = document.getElementById(
        "reset-confirm-password",
      ).value;

      let isValid = true;

      if (!otp) {
        showFieldError("reset-otp", "reset-otp-error", "OTP is required");
        isValid = false;
      }

      // Validate password
      if (!password) {
        showFieldError(
          "reset-password",
          "reset-password-error",
          "Password is required",
        );
        isValid = false;
      } else if (password.length < 6) {
        showFieldError(
          "reset-password",
          "reset-password-error",
          "Password must be at least 6 characters",
        );
        isValid = false;
      }

      if (password !== confirmPassword) {
        showFieldError(
          "reset-confirm-password",
          "reset-confirm-error",
          "Passwords do not match",
        );
        isValid = false;
      }

      if (!isValid) return;

      // Disable button
      const submitBtn = document.getElementById("reset-submit-btn");
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner"></span> Resetting...';

      // Call reset API
      const result = await apiCall("auth.php?action=resetPassword", {
        method: "POST",
        body: JSON.stringify({ otp: otp, password: password }),
      });

      if (result.success) {
        showToast(
          "Password reset successful! Redirecting to login...",
          "success",
          5000,
        );
        setTimeout(() => {
          window.location.href = "login.html";
        }, 2000);
      } else {
        showToast(result.message, "error");
        submitBtn.disabled = false;
        submitBtn.innerHTML = "Reset Password";
      }
    });
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
});
