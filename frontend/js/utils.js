/**
 * Utility Functions
 * API calls, validation, session management, UI helpers
 */

// ===== API Configuration =====
const API_BASE = "/version1/backend/api";

// ===== API Helper =====
async function apiCall(endpoint, options = {}) {
  const url = `${API_BASE}/${endpoint}`;
  const defaultOptions = {
    headers: {
      "Content-Type": "application/json",
    },
  };

  const mergedOptions = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...(options.headers || {}),
    },
  };

  try {
    const response = await fetch(url, mergedOptions);
    const contentType = response.headers.get("content-type") || "";
    let data;

    if (contentType.includes("application/json")) {
      try {
        data = await response.json();
      } catch {
        data = {
          success: false,
          message: "Invalid JSON response from server.",
        };
      }
    } else {
      data = {
        success: false,
        message: "Unexpected response format from server.",
      };
    }

    if (!response.ok && data.success !== false) {
      return {
        success: false,
        message:
          data.message || `Request failed with status ${response.status}`,
      };
    }

    return data;
  } catch (error) {
    console.error("API Error:", error);
    return { success: false, message: "Network error. Please try again." };
  }
}

// ===== Session Management =====
function saveSession(user) {
  sessionStorage.setItem("user", JSON.stringify(user));
}

function getSession() {
  const user = sessionStorage.getItem("user");
  return user ? JSON.parse(user) : null;
}

function clearSession() {
  sessionStorage.removeItem("user");
}

function isLoggedIn() {
  return getSession() !== null;
}

function getCurrentUserId() {
  const user = getSession();
  return user ? user.id : null;
}

function getCurrentUserName() {
  const user = getSession();
  return user ? user.name : "User";
}

function logout() {
  clearSession();
  window.location.href = "login.html";
}

// ===== Validation =====
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePassword(password) {
  if (password.length < 6) {
    return { valid: false, message: "Password must be at least 6 characters" };
  }
  if (!/[A-Za-z]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one letter",
    };
  }
  if (!/[0-9]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one number",
    };
  }
  return { valid: true };
}

// ===== Form Error Helpers =====
function showFieldError(inputId, errorId, message) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);
  if (input) {
    const wrapper = input.closest(".input-wrapper") || input.parentElement;
    wrapper.classList.add("error");
  }
  if (error) {
    error.textContent = message;
    error.classList.add("show");
  }
}

function clearFieldError(inputId, errorId) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);
  if (input) {
    const wrapper = input.closest(".input-wrapper") || input.parentElement;
    wrapper.classList.remove("error");
  }
  if (error) {
    error.textContent = "";
    error.classList.remove("show");
  }
}

function clearAllErrors() {
  document.querySelectorAll(".form-error").forEach((el) => {
    el.textContent = "";
    el.classList.remove("show");
  });
  document.querySelectorAll(".input-wrapper.error").forEach((el) => {
    el.classList.remove("error");
  });
}

// ===== Toast Notifications =====
function showToast(message, type = "info", duration = 4000) {
  const container = document.getElementById("toast-container");
  if (!container) return;

  const icons = {
    success: '<i class="fas fa-check-circle"></i>',
    error: '<i class="fas fa-exclamation-circle"></i>',
    warning: '<i class="fas fa-exclamation-triangle"></i>',
    info: '<i class="fas fa-info-circle"></i>',
  };

  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.innerHTML = `${icons[type] || icons.info} <span>${message}</span>`;

  container.appendChild(toast);

  toast.addEventListener("click", () => {
    toast.classList.add("hide");
    setTimeout(() => toast.remove(), 300);
  });

  setTimeout(() => {
    if (toast.parentElement) {
      toast.classList.add("hide");
      setTimeout(() => toast.remove(), 300);
    }
  }, duration);
}

// ===== Check login on homepage =====
function checkExistingSession() {
  const user = getSession();
  if (user) {
    window.location.href = "index.html";
  }
}
