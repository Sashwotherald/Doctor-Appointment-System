/**
 * Utility Functions - Shared across all pages
 * API calls, validation, session management, UI helpers
 */

// ===== API Configuration =====
const API_BASE = "/Doctor_Appointment_System/backend/api";

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

  // Don't set Content-Type for FormData (file uploads)
  if (options.body instanceof FormData) {
    delete mergedOptions.headers["Content-Type"];
  }

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

function getCurrentUserRole() {
  const user = getSession();
  return user ? user.role : null;
}

function getCurrentUserName() {
  const user = getSession();
  return user ? user.name : "User";
}

function requireAuth(role) {
  const user = getSession();
  if (!user) {
    window.location.href = "login.html";
    return false;
  }
  if (role && user.role !== role) {
    window.location.href = `${user.role}.html`;
    return false;
  }
  return true;
}

async function logout() {
  await apiCall("auth.php?action=logout", { method: "POST" });
  clearSession();
  window.location.href = "login.html";
}

function setBadgeValue(elementId, count, displayMode = "inline") {
  const badge = document.getElementById(elementId);
  if (!badge) return;

  if (count > 0) {
    badge.textContent = count;
    badge.classList.remove("d-none");
    badge.classList.add(
      displayMode === "inline-block" ? "d-inline-block" : "d-block",
    );
  } else {
    badge.classList.add("d-none");
    badge.classList.remove("d-inline-block", "d-block");
  }
}

async function postJson(endpoint, payload = {}) {
  return apiCall(endpoint, {
    method: "POST",
    body: JSON.stringify(payload),
  });
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
  if (!/[a-zA-Z]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one letter",
    };
  }
  if (!/[A-Z]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one capital letter",
    };
  }
  if (!/[0-9]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one number",
    };
  }
  if (!/[^a-zA-Z0-9]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one special character",
    };
  }
  return { valid: true };
}

function validateRequired(value, fieldName) {
  if (!value || value.trim() === "") {
    return { valid: false, message: `${fieldName} is required` };
  }
  return { valid: true };
}

function validatePhone(phone) {
  if (!phone) return { valid: true }; // Optional
  const re = /^[\+]?[\d\s\-\(\)]{7,15}$/;
  return re.test(phone)
    ? { valid: true }
    : { valid: false, message: "Invalid phone number" };
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

// ===== Date/Time Formatting =====
function formatDate(dateStr) {
  if (!dateStr) return "N/A";
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatTime(timeStr) {
  if (!timeStr) return "N/A";
  const [hours, minutes] = timeStr.split(":");
  const h = parseInt(hours);
  const ampm = h >= 12 ? "PM" : "AM";
  const displayHour = h % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

function formatDateTime(dateStr) {
  if (!dateStr) return "N/A";
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getGreeting() {
  const hour = new Date().getHours();
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
}

function getDateParts(dateStr) {
  const date = new Date(dateStr);
  return {
    month: date.toLocaleDateString("en-US", { month: "short" }).toUpperCase(),
    day: date.getDate(),
  };
}

function getMinDate() {
  return new Date().toISOString().split("T")[0];
}

// ===== Status Badge =====
function getStatusBadge(status) {
  const statusMap = {
    pending: "badge-pending",
    approved: "badge-approved",
    rejected: "badge-rejected",
    completed: "badge-completed",
    cancelled: "badge-cancelled",
    rescheduled: "badge-rescheduled",
    active: "badge-active",
    inactive: "badge-inactive",
  };
  const cls = statusMap[status] || "badge-pending";
  return `<span class="badge ${cls}">${status}</span>`;
}

// ===== Generate Time Slots =====
function generateTimeSlots(
  startTime = "09:00",
  endTime = "17:00",
  interval = 30,
) {
  const slots = [];
  let [startH, startM] = startTime.split(":").map(Number);
  const [endH, endM] = endTime.split(":").map(Number);

  while (startH < endH || (startH === endH && startM < endM)) {
    const timeStr = `${String(startH).padStart(2, "0")}:${String(startM).padStart(2, "0")}`;
    slots.push(timeStr);
    startM += interval;
    if (startM >= 60) {
      startH += Math.floor(startM / 60);
      startM = startM % 60;
    }
  }
  return slots;
}

// ===== Dashboard Navigation Helper =====
function initSidebarNavigation() {
  // Sidebar navigation
  document.querySelectorAll(".sidebar-nav .nav-item").forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();
      const section = item.dataset.section;
      if (section) navigateToSection(section);
    });
  });

  // Quick action navigation
  document.querySelectorAll("[data-nav]").forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();
      navigateToSection(item.dataset.nav);
    });
  });

  // Mobile toggle
  const mobileToggle = document.getElementById("mobile-toggle");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebar-overlay");

  if (mobileToggle) {
    mobileToggle.addEventListener("click", () => {
      if (sidebar) sidebar.classList.toggle("open");
      if (overlay) overlay.classList.toggle("active");
    });
  }

  if (overlay) {
    overlay.addEventListener("click", () => {
      sidebar.classList.remove("open");
      overlay.classList.remove("active");
    });
  }

  // Logout
  const logoutBtn = document.getElementById("logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault();
      logout();
    });
  }
}

function navigateToSection(sectionName) {
  // Hide all sections
  document.querySelectorAll(".content-section").forEach((s) => {
    s.classList.remove("active");
  });

  // Show target section
  const target = document.getElementById(`section-${sectionName}`);
  if (target) {
    target.classList.add("active");
  }

  // Update nav active state
  document
    .querySelectorAll(".sidebar-nav .nav-item")
    .forEach((n) => n.classList.remove("active"));
  const activeNav = document.querySelector(
    `.sidebar-nav .nav-item[data-section="${sectionName}"]`,
  );
  if (activeNav) activeNav.classList.add("active");

  // Close mobile sidebar
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebar-overlay");
  if (sidebar) sidebar.classList.remove("open");
  if (overlay) overlay.classList.remove("active");

  // Trigger section load callback if exists
  if (typeof window.onSectionChange === "function") {
    window.onSectionChange(sectionName);
  }
}

// ===== Tab Navigation =====
function initTabs(callback) {
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document
        .querySelectorAll(".tab-btn")
        .forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      if (callback) callback(btn.dataset.tab);
    });
  });
}

// ===== Modal Helpers =====
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.add("active");
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove("active");
}

// ===== Notification Panel =====
function initNotificationPanel() {
  const btn = document.getElementById("notification-btn");
  const panel = document.getElementById("notification-panel");
  const closeBtn = document.getElementById("close-notif-panel");

  if (btn && panel) {
    btn.addEventListener("click", () => {
      panel.classList.toggle("open");
      if (typeof loadNotifications === "function") loadNotifications();
    });
  }

  if (closeBtn && panel) {
    closeBtn.addEventListener("click", () => {
      panel.classList.remove("open");
    });
  }
}

// ===== Doctor Photo Helper =====
function getDoctorPhotoSizeClass(size) {
  const sizeInPx = parseInt(size, 10);
  return sizeInPx <= 42 ? "doctor-avatar-sm" : "doctor-avatar-md";
}

function getDoctorPhotoHTML(photo, name, size = "64px") {
  const sizeClass = getDoctorPhotoSizeClass(size);

  if (photo) {
    return `<img src="${photo}" alt="${name}" class="doctor-avatar ${sizeClass}">`;
  }

  const initials = name ? name.charAt(0).toUpperCase() : "?";
  return `<div class="doctor-avatar doctor-avatar-fallback ${sizeClass}">${initials}</div>`;
}

// ===== Escape HTML =====
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// ===== Check login on homepage =====
function checkExistingSession() {
  const user = getSession();
  if (user) {
    window.location.href = `${user.role}.html`;
  }
}

// ===== Global Table Search =====
function initGlobalSearch() {
  const searchInput = document.getElementById("global-search");
  if (!searchInput) return;

  searchInput.addEventListener("input", (e) => {
    const term = e.target.value.toLowerCase().trim();

    // Find the currently active section
    const activeSection = document.querySelector(".content-section.active");
    if (!activeSection) return;

    // Filter table rows inside the active section
    const rows = activeSection.querySelectorAll("tbody tr");
    rows.forEach((row) => {
      if (
        row.querySelector(".empty-state") ||
        row.querySelector(".loading-overlay") ||
        row.querySelector(".loader")
      ) {
        return; // Don't filter empty state / loading rows
      }

      const searchTarget =
        row.querySelector("strong") || row.firstElementChild || row;
      const text = searchTarget.textContent.toLowerCase();
      if (text.includes(term)) {
        row.style.display = ""; // Using inline CSS for row/table flow
      } else {
        row.style.display = "none";
      }
    });

    // Filter cards/list items if any (e.g. today's schedule)
    const cards = activeSection.querySelectorAll(
      ".appointment-item, .doctor-card",
    );
    cards.forEach((card) => {
      if (card.querySelector(".empty-state")) return;

      const searchTarget =
        card.querySelector(".doctor-info h4") ||
        card.querySelector(".appointment-details h4") ||
        card;
      const text = searchTarget.textContent.toLowerCase();
      if (text.includes(term)) {
        card.style.display = "";
      } else {
        card.style.display = "none";
      }
    });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initGlobalSearch();
});
