/**
 * Doctor Dashboard Scripts
 */

document.addEventListener("DOMContentLoaded", () => {
  if (!requireAuth("doctor")) return;

  const user = getSession();
  initSidebarNavigation();
  initNotificationPanel();
  initTabs(handleDocTabChange);
  setupDoctorUI(user);
  loadDashboard();

  window.onSectionChange = (section) => {
    switch (section) {
      case "dashboard":
        loadDashboard();
        break;
      case "appointments":
        loadAppointments();
        break;
      case "patients":
        loadPatients();
        break;
      case "availability":
        loadAvailability();
        break;
      case "profile":
        loadProfile();
        break;
    }
  };
});

function doctorEndpoint(action, extraQuery = "") {
  return `doctor.php?action=${action}&user_id=${getCurrentUserId()}${extraQuery}`;
}

function doctorPost(action, payload = {}) {
  return postJson(doctorEndpoint(action), payload);
}

function setupDoctorUI(user) {
  const topbarName = document.getElementById("topbar-name");
  const topbarAvatar = document.getElementById("topbar-avatar");
  const greeting = document.getElementById("greeting-text");

  if (topbarName) topbarName.textContent = user.name;
  if (topbarAvatar)
    topbarAvatar.textContent = user.name.charAt(0).toUpperCase();
  if (greeting)
    greeting.textContent = `${getGreeting()}, Dr. ${user.name.split(" ")[0]}.`;

  // Photo upload
  setupPhotoUpload();

  // Mark all notifications read
  document
    .getElementById("mark-all-read-btn")
    ?.addEventListener("click", async () => {
      const result = await doctorPost("markAllRead");

      if (result.success) {
        showToast("All notifications marked as read", "success");
        loadNotifications();
        loadDashboard();
      } else {
        showToast(result.message || "Failed to update notifications", "error");
      }
    });
}

// ===== DASHBOARD =====
async function loadDashboard() {
  const result = await apiCall(doctorEndpoint("getDashboard"));

  if (result.success) {
    const d = result.dashboard;
    document.getElementById("stat-today").textContent =
      d.today_appointments?.length || 0;
    document.getElementById("stat-pending").textContent = d.pending_count || 0;
    document.getElementById("stat-patients").textContent =
      d.total_patients || 0;
    document.getElementById("stat-completed").textContent = d.completed || 0;

    setBadgeValue("notif-count", d.unread_notifications, "flex");
    setBadgeValue("pending-badge", d.pending_count, "inline");

    renderTodaySchedule(d.today_appointments || []);
    renderPendingRequests();
  }
}

function renderTodaySchedule(appointments) {
  const container = document.getElementById("today-schedule");
  if (!appointments.length) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">📅</div><h3>No appointments today</h3><p>Enjoy your free day!</p></div>`;
    return;
  }

  container.innerHTML = appointments
    .map(
      (apt) => `
        <div class="appointment-item">
            <div class="appointment-date-box">
                <div class="time">${formatTime(apt.appointment_time)}</div>
            </div>
            <div class="appointment-details">
                <h4>${escapeHtml(apt.patient_name)}</h4>
                <p>${getStatusBadge(apt.status)}</p>
            </div>
        </div>
    `,
    )
    .join("");
}

async function renderPendingRequests() {
  const container = document.getElementById("pending-requests");
  const result = await apiCall(
    doctorEndpoint("getAppointments", "&status=pending"),
  );

  if (result.success && result.appointments.length > 0) {
    container.innerHTML = result.appointments
      .slice(0, 5)
      .map(
        (apt) => `
            <div class="appointment-item">
                <div class="appointment-date-box">
                    <div class="month">${getDateParts(apt.appointment_date).month}</div>
                    <div class="day">${getDateParts(apt.appointment_date).day}</div>
                    <div class="time">${formatTime(apt.appointment_time)}</div>
                </div>
                <div class="appointment-details">
                    <h4>${escapeHtml(apt.patient_name)}</h4>
                    <p>${escapeHtml(apt.reason || "No reason provided")}</p>
                </div>
                <div class="appointment-actions">
                    <button class="btn btn-sm btn-success" onclick="updateAppointmentStatus(${apt.id}, 'approved')">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="updateAppointmentStatus(${apt.id}, 'rejected')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `,
      )
      .join("");
  } else {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">✅</div><h3>No pending requests</h3><p>All caught up!</p></div>`;
  }
}

// ===== APPOINTMENTS =====
let currentDocFilter = null;

async function loadAppointments(status = null) {
  currentDocFilter = status;
  const statusQuery = status ? `&status=${status}` : "";

  const result = await apiCall(doctorEndpoint("getAppointments", statusQuery));
  const tbody = document.getElementById("appointments-tbody");

  if (result.success && result.appointments.length > 0) {
    tbody.innerHTML = result.appointments
      .map((apt) => {
        const isPending = apt.status === "pending";
        const isApproved = apt.status === "approved";

        return `<tr>
          <td><strong>${escapeHtml(apt.patient_name)}</strong><br><span class="table-subtext">${escapeHtml(apt.patient_email)}</span></td>
                <td>${formatDate(apt.appointment_date)}</td>
                <td>${formatTime(apt.appointment_time)}</td>
                <td>${getStatusBadge(apt.status)}</td>
                <td>${escapeHtml(apt.reason || "-")}</td>
                <td>
                    ${
                      isPending
                        ? `
                        <button class="btn btn-sm btn-success" onclick="updateAppointmentStatus(${apt.id}, 'approved')"><i class="fas fa-check"></i> Approve</button>
                        <button class="btn btn-sm btn-danger" onclick="updateAppointmentStatus(${apt.id}, 'rejected')"><i class="fas fa-times"></i> Reject</button>
                    `
                        : ""
                    }
                    ${
                      isApproved
                        ? `
                        <button class="btn btn-sm btn-primary" onclick="updateAppointmentStatus(${apt.id}, 'completed')"><i class="fas fa-check-double"></i> Complete</button>
                    `
                        : ""
                    }
                </td>
            </tr>`;
      })
      .join("");
  } else {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📋</div><h3>No ${status || ""} appointments</h3></div></td></tr>`;
  }
}

function handleDocTabChange(tab) {
  const statusMap = {
    all: null,
    pending: "pending",
    approved: "approved",
    completed: "completed",
    cancelled: "cancelled",
  };
  loadAppointments(statusMap[tab]);
}

async function updateAppointmentStatus(appointmentId, status) {
  const confirmMsg = {
    approved: "Approve this appointment?",
    rejected: "Reject this appointment?",
    completed: "Mark this appointment as completed?",
  };

  if (!confirm(confirmMsg[status] || `Change status to ${status}?`)) return;

  const result = await doctorPost("updateAppointmentStatus", {
    appointment_id: appointmentId,
    status,
  });

  if (result.success) {
    showToast(result.message, "success");
    loadAppointments(currentDocFilter);
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// ===== PATIENTS =====
async function loadPatients() {
  const result = await apiCall(doctorEndpoint("getPatients"));
  const tbody = document.getElementById("patients-tbody");

  if (result.success && result.patients.length > 0) {
    tbody.innerHTML = result.patients
      .map(
        (p) => `
            <tr>
                <td><strong>${escapeHtml(p.name)}</strong></td>
                <td>${escapeHtml(p.email)}</td>
                <td>${escapeHtml(p.phone || "-")}</td>
                <td>${p.age || "-"}</td>
                <td>${escapeHtml(p.gender || "-")}</td>
                <td>${escapeHtml(p.blood_group || "-")}</td>
            </tr>
        `,
      )
      .join("");
  } else {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><div class="empty-icon">👥</div><h3>No patients yet</h3><p>Patients will appear here after their first appointment.</p></div></td></tr>`;
  }
}

// ===== AVAILABILITY =====
const DAYS = [
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
  "Sunday",
];

async function loadAvailability() {
  const result = await apiCall(doctorEndpoint("getAvailability"));

  const grid = document.getElementById("schedule-grid");
  const existingSchedule = {};
  if (result.success && result.availability) {
    result.availability.forEach((a) => {
      existingSchedule[a.day_of_week] = a;
    });
  }

  grid.innerHTML = DAYS.map((day) => {
    const existing = existingSchedule[day];
    const startTime = existing ? existing.start_time.substring(0, 5) : "09:00";
    const endTime = existing ? existing.end_time.substring(0, 5) : "17:00";
    const isAvailable = existing
      ? existing.is_available == 1
      : day !== "Sunday";

    return `
            <div class="schedule-row">
                <span class="day-label">${day}</span>
                <input type="time" id="start-${day}" value="${startTime}">
              <span class="schedule-separator">to</span>
                <input type="time" id="end-${day}" value="${endTime}">
                <div class="toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" id="avail-${day}" ${isAvailable ? "checked" : ""}>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        `;
  }).join("");

  // Form submit
  document.getElementById("availability-form").onsubmit = async (e) => {
    e.preventDefault();

    const schedules = DAYS.map((day) => ({
      day: day,
      start_time: document.getElementById(`start-${day}`).value,
      end_time: document.getElementById(`end-${day}`).value,
      is_available: document.getElementById(`avail-${day}`).checked ? 1 : 0,
    }));

    const result = await doctorPost("setAvailability", { schedules });

    if (result.success) {
      showToast("Availability updated successfully", "success");
    } else {
      showToast(result.message, "error");
    }
  };
}

// ===== PROFILE =====
async function loadProfile() {
  const result = await apiCall(doctorEndpoint("getProfile"));

  if (result.success) {
    const p = result.profile;
    document.getElementById("profile-name-display").textContent =
      `Dr. ${p.name}`;
    document.getElementById("profile-email-display").textContent = p.email;
    document.getElementById("profile-status-badge").textContent =
      p.approval_status || "pending";
    document.getElementById("profile-status-badge").className =
      `badge badge-${p.approval_status || "pending"}`;

    // Update avatar
    const avatarEl = document.getElementById("profile-avatar-display");
    if (p.photo) {
      avatarEl.innerHTML = `<img src="${p.photo}" alt="Profile Photo"><div class="photo-upload-overlay" id="photo-upload-trigger"><i class="fas fa-camera"></i> Change</div>`;
      setupPhotoUpload();
    } else {
      document.getElementById("profile-initials").textContent = p.name
        .charAt(0)
        .toUpperCase();
    }

    // Fill form
    document.getElementById("doc-name").value = p.name || "";
    document.getElementById("doc-phone").value = p.phone || "";
    document.getElementById("doc-specialization").value =
      p.specialization || "";
    document.getElementById("doc-qualification").value = p.qualification || "";
    document.getElementById("doc-nmc").value = p.nmc || "";
    document.getElementById("doc-experience").value = p.experience || "";
    document.getElementById("doc-fee").value = p.consultation_fee || "";
    document.getElementById("doc-bio").value = p.bio || "";
  }

  // Form submit
  document.getElementById("doctor-profile-form").onsubmit = async (e) => {
    e.preventDefault();

    const data = {
      name: document.getElementById("doc-name").value,
      phone: document.getElementById("doc-phone").value,
      specialization: document.getElementById("doc-specialization").value,
      qualification: document.getElementById("doc-qualification").value,
      nmc: document.getElementById("doc-nmc").value,
      experience: document.getElementById("doc-experience").value,
      consultation_fee: document.getElementById("doc-fee").value,
      bio: document.getElementById("doc-bio").value,
    };

    const result = await doctorPost("updateProfile", data);

    if (result.success) {
      showToast("Profile updated successfully", "success");
      const user = getSession();
      user.name = data.name;
      saveSession(user);
      document.getElementById("topbar-name").textContent = data.name;
    } else {
      showToast(result.message, "error");
    }
  };
}

// ===== PHOTO UPLOAD =====
function setupPhotoUpload() {
  const trigger = document.getElementById("photo-upload-trigger");
  const input = document.getElementById("photo-upload-input");

  if (trigger && input) {
    trigger.onclick = () => input.click();

    input.onchange = async () => {
      const file = input.files[0];
      if (!file) return;

      // Validate file
      const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp",
      ];
      if (!allowedTypes.includes(file.type)) {
        showToast("Invalid file type. Use JPG, PNG, GIF, or WebP.", "error");
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        showToast("File too large. Max 5MB.", "error");
        return;
      }

      const formData = new FormData();
      formData.append("photo", file);

      showToast("Uploading photo...", "info");

      try {
        const response = await fetch(
          `${API_BASE}/${doctorEndpoint("uploadPhoto")}`,
          {
            method: "POST",
            body: formData,
          },
        );
        const result = await response.json();

        if (result.success) {
          showToast("Photo uploaded successfully!", "success");
          loadProfile();
        } else {
          showToast(result.message, "error");
        }
      } catch {
        showToast("Failed to upload photo", "error");
      }
    };
  }
}

// ===== NOTIFICATIONS =====
async function loadNotifications() {
  const result = await apiCall(doctorEndpoint("getNotifications"));
  const container = document.getElementById("notification-list");

  if (result.success && result.notifications.length > 0) {
    container.innerHTML = result.notifications
      .map(
        (n) => `
            <div class="notification-item ${n.is_read == 0 ? "unread" : ""}">
                <h4>${escapeHtml(n.title)}</h4>
                <p>${escapeHtml(n.message)}</p>
                <div class="notif-time">${formatDateTime(n.created_at)}</div>
            </div>
        `,
      )
      .join("");
  } else {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">🔔</div><h3>No notifications</h3></div>`;
  }
}
