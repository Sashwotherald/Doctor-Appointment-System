/**
 * Patient Dashboard Scripts
 */

document.addEventListener("DOMContentLoaded", () => {
  if (!requireAuth("patient")) return;

  const user = getSession();
  initSidebarNavigation();
  initNotificationPanel();
  initTabs(handleTabChange);
  setupPatientUI(user);
  loadDashboard();

  // Section change handler
  window.onSectionChange = (section) => {
    switch (section) {
      case "dashboard":
        loadDashboard();
        break;
      case "appointments":
        loadAppointments();
        break;
      case "find-doctor":
        loadDoctors();
        break;
      case "profile":
        loadProfile();
        break;
    }
  };
});

function patientEndpoint(action, extraQuery = "") {
  return `patient.php?action=${action}&user_id=${getCurrentUserId()}${extraQuery}`;
}

function patientPost(action, payload = {}) {
  return postJson(patientEndpoint(action), payload);
}

function setupPatientUI(user) {
  const nameEl = document.getElementById("topbar-name");
  const avatarEl = document.getElementById("topbar-avatar");
  const greetingEl = document.getElementById("greeting-text");

  if (nameEl) nameEl.textContent = user.name;
  if (avatarEl) avatarEl.textContent = user.name.charAt(0).toUpperCase();
  if (greetingEl)
    greetingEl.textContent = `${getGreeting()}, ${user.name.split(" ")[0]}.`;

  // Setup booking modal events
  setupBookingModal();
  setupRescheduleModal();
  setupDoctorProfileModal();

  // Mark all read
  const markAllBtn = document.getElementById("mark-all-read-btn");
  if (markAllBtn) {
    markAllBtn.addEventListener("click", async () => {
      const result = await patientPost("markAllRead");

      if (result.success) {
        showToast("All notifications marked as read", "success");
        loadNotifications();
        loadDashboard();
      } else {
        showToast(result.message || "Failed to update notifications", "error");
      }
    });
  }
}

// ===== DASHBOARD =====
async function loadDashboard() {
  const result = await apiCall(patientEndpoint("getDashboard"));

  if (result.success) {
    const d = result.dashboard;
    document.getElementById("stat-total").textContent = d.total_appointments;
    document.getElementById("stat-upcoming").textContent =
      d.upcoming_appointments.length;
    document.getElementById("stat-pending").textContent =
      d.pending_appointments.length;
    document.getElementById("stat-notifs").textContent = d.unread_notifications;

    setBadgeValue("notif-count", d.unread_notifications, "flex");
    setBadgeValue("pending-badge", d.pending_appointments.length, "inline");

    // Render upcoming appointments
    renderUpcomingAppointments([
      ...d.upcoming_appointments,
      ...d.pending_appointments,
    ]);
  }
}

function renderUpcomingAppointments(appointments) {
  const container = document.getElementById("upcoming-list");
  if (!appointments || appointments.length === 0) {
    container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <h3>No upcoming appointments</h3>
                <p>Find a doctor and book your first appointment.</p>
                <a href="#" class="btn btn-primary btn-sm" data-nav="find-doctor">Find a Doctor</a>
            </div>`;
    container.querySelector("[data-nav]")?.addEventListener("click", (e) => {
      e.preventDefault();
      navigateToSection("find-doctor");
    });
    return;
  }

  container.innerHTML = appointments
    .slice(0, 5)
    .map((apt) => {
      const dateParts = getDateParts(apt.appointment_date);
      return `
            <div class="appointment-item">
                <div class="appointment-date-box">
                    <div class="month">${dateParts.month}</div>
                    <div class="day">${dateParts.day}</div>
                    <div class="time">${formatTime(apt.appointment_time)}</div>
                </div>
            <div class="appointment-main">
                    ${getDoctorPhotoHTML(apt.doctor_photo, apt.doctor_name, "42px")}
                    <div class="appointment-details">
                        <h4>Dr. ${escapeHtml(apt.doctor_name)}</h4>
                        <p>${escapeHtml(apt.specialization || "General")} • ${getStatusBadge(apt.status)}</p>
                    </div>
                </div>
            </div>`;
    })
    .join("");
}

// ===== APPOINTMENTS =====
let currentAppointmentFilter = null;
let doctorSearchInitialized = false;
const doctorLookup = new Map();

async function loadAppointments(status = null) {
  currentAppointmentFilter = status;
  const statusQuery = status ? `&status=${status}` : "";

  const result = await apiCall(patientEndpoint("getAppointments", statusQuery));
  const container = document.getElementById("appointments-list");

  if (result.success && result.appointments.length > 0) {
    container.innerHTML = result.appointments
      .map((apt) => {
        const dateParts = getDateParts(apt.appointment_date);
        const canCancel = ["pending", "approved", "rescheduled"].includes(
          apt.status,
        );
        const canReschedule = ["pending", "approved"].includes(apt.status);

        return `
                <div class="appointment-item">
                    <div class="appointment-date-box">
                        <div class="month">${dateParts.month}</div>
                        <div class="day">${dateParts.day}</div>
                        <div class="time">${formatTime(apt.appointment_time)}</div>
                    </div>
                  <div class="appointment-main appointment-main-tight">
                        ${getDoctorPhotoHTML(apt.doctor_photo, apt.doctor_name, "42px")}
                        <div class="appointment-details">
                            <h4>Dr. ${escapeHtml(apt.doctor_name)}</h4>
                            <p>${escapeHtml(apt.specialization || "General")} • ${getStatusBadge(apt.status)}</p>
                      ${apt.reason ? `<p class="appointment-reason">${escapeHtml(apt.reason)}</p>` : ""}
                        </div>
                    </div>
                    <div class="appointment-actions">
                        ${canReschedule ? `<button class="btn btn-sm btn-secondary" onclick="openRescheduleModal(${apt.id})"><i class="fas fa-calendar-alt"></i> Reschedule</button>` : ""}
                        ${canCancel ? `<button class="btn btn-sm btn-danger" onclick="cancelAppointment(${apt.id})"><i class="fas fa-times"></i> Cancel</button>` : ""}
                    </div>
                </div>`;
      })
      .join("");
  } else {
    container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>No appointments found</h3>
                <p>You don't have any ${status || ""} appointments yet.</p>
            </div>`;
  }
}

function handleTabChange(tab) {
  const statusMap = {
    "all-appts": null,
    "pending-appts": "pending",
    "approved-appts": "approved",
    "completed-appts": "completed",
    "cancelled-appts": "cancelled",
  };

  const selectedStatus = statusMap[tab];
  currentAppointmentFilter = selectedStatus;
  loadAppointments(selectedStatus);
}

async function cancelAppointment(appointmentId) {
  if (!confirm("Are you sure you want to cancel this appointment?")) return;

  const result = await patientPost("cancelAppointment", {
    appointment_id: appointmentId,
  });

  if (result.success) {
    showToast("Appointment cancelled successfully", "success");
    loadAppointments(currentAppointmentFilter);
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// ===== RESCHEDULE =====
function setupRescheduleModal() {
  document
    .getElementById("close-reschedule-modal")
    ?.addEventListener("click", () => closeModal("reschedule-modal"));
  document
    .getElementById("cancel-reschedule-btn")
    ?.addEventListener("click", () => closeModal("reschedule-modal"));

  document
    .getElementById("confirm-reschedule-btn")
    ?.addEventListener("click", async () => {
      const aptId = document.getElementById("reschedule-apt-id").value;
      const newDate = document.getElementById("reschedule-date").value;
      const newTime = document.getElementById("reschedule-time").value;

      if (!newDate || !newTime) {
        showToast("Please select date and time", "warning");
        return;
      }

      const result = await patientPost("rescheduleAppointment", {
        appointment_id: aptId,
        new_date: newDate,
        new_time: newTime,
      });

      if (result.success) {
        showToast("Appointment rescheduled successfully", "success");
        closeModal("reschedule-modal");
        loadAppointments(currentAppointmentFilter);
      } else {
        showToast(result.message, "error");
      }
    });

  // Set min date
  const dateInput = document.getElementById("reschedule-date");
  if (dateInput) dateInput.min = getMinDate();
}

function openRescheduleModal(appointmentId) {
  document.getElementById("reschedule-apt-id").value = appointmentId;
  document.getElementById("reschedule-date").value = "";
  document.getElementById("reschedule-time").value = "";
  openModal("reschedule-modal");
}

// ===== FIND DOCTORS =====
async function loadDoctors() {
  // Load specializations into filter
  const specResult = await apiCall("patient.php?action=getSpecializations");
  if (specResult.success) {
    const filterSpec = document.getElementById("filter-specialization");
    filterSpec.innerHTML = '<option value="all">All Specializations</option>';
    specResult.specializations.forEach((s) => {
      filterSpec.innerHTML += `<option value="${s}">${s}</option>`;
    });
  }

  // Load all doctors
  await searchDoctors();

  if (!doctorSearchInitialized) {
    document
      .getElementById("filter-search-btn")
      ?.addEventListener("click", searchDoctors);
    document
      .getElementById("filter-reset-btn")
      ?.addEventListener("click", () => {
        document.getElementById("filter-specialization").value = "all";
        searchDoctors();
      });
    doctorSearchInitialized = true;
  }
}

async function searchDoctors() {
  const specialization =
    document.getElementById("filter-specialization")?.value || "all";

  let url = `patient.php?action=getDoctors&specialization=${specialization}`;

  const container = document.getElementById("doctor-list");
  container.innerHTML =
    '<div class="loading-overlay"><div class="loader"></div></div>';

  const result = await apiCall(url);

  if (result.success && result.doctors.length > 0) {
    doctorLookup.clear();
    result.doctors.forEach((doc) => doctorLookup.set(Number(doc.id), doc));

    container.innerHTML = result.doctors
      .map(
        (doc) => `
            <div class="doctor-card" data-doctor-id="${doc.id}">
                <div class="doctor-card-header doctor-card-clickable" data-doctor-id="${doc.id}">
                    <div class="doctor-photo">
                        ${doc.photo ? `<img src="${doc.photo}" alt="Dr. ${escapeHtml(doc.name)}">` : `<span class="placeholder">👨‍⚕️</span>`}
                    </div>
                    <div class="doctor-info">
                        <h4>Dr. ${escapeHtml(doc.name)}</h4>
                        <span class="doctor-specialization">${escapeHtml(doc.specialization || "General")}</span>
                    </div>
                </div>
                <div class="doctor-card-body">
                    ${doc.experience ? `<div class="doctor-detail"><span class="icon"><i class="fas fa-briefcase"></i></span> ${doc.experience} years experience</div>` : ""}
                    ${doc.qualification ? `<div class="doctor-detail"><span class="icon"><i class="fas fa-graduation-cap"></i></span> ${escapeHtml(doc.qualification)}</div>` : ""}
                    ${doc.nmc ? `<div class="doctor-detail"><span class="icon"><i class="fas fa-id-card"></i></span> NMC: ${escapeHtml(doc.nmc)}</div>` : ""}
                    ${doc.consultation_fee ? `<div class="doctor-detail"><span class="icon"><i class="fas fa-indian-rupee-sign"></i></span> Rs. ${parseFloat(doc.consultation_fee).toFixed(2)} per visit</div>` : ""}
                    ${doc.availability ? `<div class="doctor-detail"><span class="icon"><i class="fas fa-clock"></i></span> Available today</div>` : ""}
                </div>
                <div class="doctor-card-footer">
                    <button class="btn btn-secondary btn-sm view-profile-btn" data-doctor-id="${doc.id}">
                        <i class="fas fa-user"></i> View Profile
                    </button>
                    <button class="btn btn-primary btn-sm book-now-btn" data-doctor-id="${doc.id}">
                        <i class="fas fa-calendar-plus"></i> Book Now
                    </button>
                </div>
            </div>
        `,
      )
      .join("");

    // View Profile button click
    container.querySelectorAll(".view-profile-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        const doctorId = Number(button.dataset.doctorId);
        openDoctorProfileModal(doctorId);
      });
    });

    // Clickable card header to open profile
    container.querySelectorAll(".doctor-card-clickable").forEach((header) => {
      header.addEventListener("click", () => {
        const doctorId = Number(header.dataset.doctorId);
        openDoctorProfileModal(doctorId);
      });
    });

    // Book Now button click
    container.querySelectorAll(".book-now-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        const doctorId = Number(button.dataset.doctorId);
        const doctor = doctorLookup.get(doctorId);
        if (doctor) {
          openBookingModal(doctor);
        }
      });
    });
  } else {
    container.innerHTML = `
            <div class="empty-state doctor-list-empty">
                <div class="empty-icon">🔍</div>
                <h3>No doctors found</h3>
                <p>Try adjusting your search filters or check back later.</p>
            </div>`;
  }
}

// ===== DOCTOR PROFILE MODAL =====
let currentProfileDoctorId = null;

function setupDoctorProfileModal() {
  document
    .getElementById("close-doctor-profile-modal")
    ?.addEventListener("click", () => closeModal("doctor-profile-modal"));
  document
    .getElementById("close-profile-btn")
    ?.addEventListener("click", () => closeModal("doctor-profile-modal"));

  // Book from profile
  document
    .getElementById("book-from-profile-btn")
    ?.addEventListener("click", () => {
      closeModal("doctor-profile-modal");
      const doctor = doctorLookup.get(currentProfileDoctorId);
      if (doctor) {
        openBookingModal(doctor);
      }
    });

  // Close on overlay click
  document
    .getElementById("doctor-profile-modal")
    ?.addEventListener("click", (e) => {
      if (e.target.id === "doctor-profile-modal") {
        closeModal("doctor-profile-modal");
      }
    });
}

async function openDoctorProfileModal(doctorId) {
  currentProfileDoctorId = doctorId;
  const body = document.getElementById("doctor-profile-body");
  body.innerHTML =
    '<div class="loading-overlay"><div class="loader"></div></div>';
  openModal("doctor-profile-modal");

  const result = await apiCall(
    `patient.php?action=getDoctorProfile&doctor_id=${doctorId}`,
  );

  if (result.success && result.doctor) {
    const doc = result.doctor;
    const availability = doc.availability || [];

    // Build availability schedule HTML
    const daysOrder = [
      "Monday",
      "Tuesday",
      "Wednesday",
      "Thursday",
      "Friday",
      "Saturday",
      "Sunday",
    ];
    let scheduleHTML = "";

    if (availability.length > 0) {
      scheduleHTML = daysOrder
        .map((day) => {
          const dayData = availability.find((a) => a.day_of_week === day);
          if (dayData && dayData.is_available == 1) {
            return `
            <div class="profile-schedule-row available">
              <span class="profile-schedule-day">${day}</span>
              <span class="profile-schedule-time">
                <i class="fas fa-clock"></i>
                ${formatTime(dayData.start_time)} – ${formatTime(dayData.end_time)}
              </span>
            </div>`;
          } else {
            return `
            <div class="profile-schedule-row unavailable">
              <span class="profile-schedule-day">${day}</span>
              <span class="profile-schedule-time unavailable-text">
                <i class="fas fa-times-circle"></i> Unavailable
              </span>
            </div>`;
          }
        })
        .join("");
    } else {
      scheduleHTML = `
        <div class="profile-schedule-default">
          <i class="fas fa-info-circle"></i>
          Default hours: Mon – Fri, 9:00 AM – 5:00 PM
        </div>`;
    }

    body.innerHTML = `
      <!-- Profile Banner -->
      <div class="dp-banner">
        <div class="dp-photo-wrapper">
          ${
            doc.photo
              ? `<img src="${doc.photo}" alt="Dr. ${escapeHtml(doc.name)}" class="dp-photo">`
              : `<div class="dp-photo dp-photo-fallback">${doc.name ? doc.name.charAt(0).toUpperCase() : "?"}</div>`
          }
        </div>
        <div class="dp-banner-info">
          <h2 class="dp-name">Dr. ${escapeHtml(doc.name)}</h2>
          <span class="dp-specialization">${escapeHtml(doc.specialization || "General Medicine")}</span>
          ${
            doc.approval_status === "approved"
              ? '<span class="dp-verified"><i class="fas fa-check-circle"></i> Verified</span>'
              : ""
          }
        </div>
      </div>

      <!-- Bio -->
      ${
        doc.bio
          ? `
        <div class="dp-section">
          <h4 class="dp-section-title"><i class="fas fa-quote-left"></i> About</h4>
          <p class="dp-bio">${escapeHtml(doc.bio)}</p>
        </div>
      `
          : ""
      }

      <!-- Details Grid -->
      <div class="dp-section">
        <h4 class="dp-section-title"><i class="fas fa-info-circle"></i> Details</h4>
        <div class="dp-details-grid">
          ${
            doc.qualification
              ? `
            <div class="dp-detail-item">
              <div class="dp-detail-icon"><i class="fas fa-graduation-cap"></i></div>
              <div class="dp-detail-content">
                <span class="dp-detail-label">Qualification</span>
                <span class="dp-detail-value">${escapeHtml(doc.qualification)}</span>
              </div>
            </div>`
              : ""
          }
          ${
            doc.experience
              ? `
            <div class="dp-detail-item">
              <div class="dp-detail-icon"><i class="fas fa-briefcase"></i></div>
              <div class="dp-detail-content">
                <span class="dp-detail-label">Experience</span>
                <span class="dp-detail-value">${doc.experience} years</span>
              </div>
            </div>`
              : ""
          }
          ${
            doc.nmc
              ? `
            <div class="dp-detail-item">
              <div class="dp-detail-icon"><i class="fas fa-id-card"></i></div>
              <div class="dp-detail-content">
                <span class="dp-detail-label">NMC Registration</span>
                <span class="dp-detail-value">${escapeHtml(doc.nmc)}</span>
              </div>
            </div>`
              : ""
          }
          ${
            doc.consultation_fee
              ? `
            <div class="dp-detail-item">
              <div class="dp-detail-icon"><i class="fas fa-indian-rupee-sign"></i></div>
              <div class="dp-detail-content">
                <span class="dp-detail-label">Consultation Fee</span>
                <span class="dp-detail-value dp-fee">Rs. ${parseFloat(doc.consultation_fee).toFixed(2)}</span>
              </div>
            </div>`
              : ""
          }
          ${
            doc.phone
              ? `
            <div class="dp-detail-item">
              <div class="dp-detail-icon"><i class="fas fa-phone"></i></div>
              <div class="dp-detail-content">
                <span class="dp-detail-label">Phone</span>
                <span class="dp-detail-value">${escapeHtml(doc.phone)}</span>
              </div>
            </div>`
              : ""
          }
          ${
            doc.email
              ? `
            <div class="dp-detail-item">
              <div class="dp-detail-icon"><i class="fas fa-envelope"></i></div>
              <div class="dp-detail-content">
                <span class="dp-detail-label">Email</span>
                <span class="dp-detail-value">${escapeHtml(doc.email)}</span>
              </div>
            </div>`
              : ""
          }
        </div>
      </div>

      <!-- Availability Schedule -->
      <div class="dp-section">
        <h4 class="dp-section-title"><i class="fas fa-calendar-alt"></i> Weekly Availability</h4>
        <div class="dp-schedule">
          ${scheduleHTML}
        </div>
      </div>

      <!-- Member Since -->
      ${
        doc.created_at
          ? `
        <div class="dp-member-since">
          <i class="fas fa-user-clock"></i>
          Member since ${formatDate(doc.created_at)}
        </div>
      `
          : ""
      }
    `;
  } else {
    body.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">😔</div>
        <h3>Could not load profile</h3>
        <p>${result.message || "An error occurred while loading the doctor profile."}</p>
      </div>`;
  }
}

// ===== BOOKING MODAL =====
function setupBookingModal() {
  document
    .getElementById("close-booking-modal")
    ?.addEventListener("click", () => closeModal("booking-modal"));
  document
    .getElementById("cancel-booking-btn")
    ?.addEventListener("click", () => closeModal("booking-modal"));

  // Date change -> load time slots
  document
    .getElementById("booking-date")
    ?.addEventListener("change", async function () {
      const doctorId = document.getElementById("booking-doctor-id").value;
      const date = this.value;
      await loadTimeSlots(doctorId, date);
    });

  // Confirm booking
  document
    .getElementById("confirm-booking-btn")
    ?.addEventListener("click", confirmBooking);

  // Set min date
  const dateInput = document.getElementById("booking-date");
  if (dateInput) dateInput.min = getMinDate();
}

function openBookingModal(doctor) {
  if (!doctor) {
    showToast(
      "Doctor information is not available. Please try again.",
      "error",
    );
    return;
  }

  document.getElementById("booking-doctor-id").value = doctor.id;
  document.getElementById("booking-doc-name").textContent =
    `Dr. ${doctor.name}`;
  document.getElementById("booking-doc-spec").textContent =
    doctor.specialization || "General";

  const avatarContainer = document.getElementById("booking-doc-avatar");
  if (doctor.photo) {
    avatarContainer.innerHTML = `<img src="${doctor.photo}" alt="Dr. ${doctor.name}" class="booking-avatar-image">`;
  } else {
    avatarContainer.textContent = "👨‍⚕️";
  }

  // Reset form
  document.getElementById("booking-date").value = "";
  document.getElementById("booking-time").innerHTML =
    '<option value="">Select date first</option>';
  document.getElementById("booking-reason").value = "";

  openModal("booking-modal");
}

async function loadTimeSlots(doctorId, date) {
  const timeSelect = document.getElementById("booking-time");
  timeSelect.innerHTML = '<option value="">Loading...</option>';

  // Get doctor availability for the selected day
  const result = await apiCall(
    patientEndpoint("getDoctorAvailability", `&doctor_id=${doctorId}`),
  );

  const [year, month, day] = date.split('-');
  const dateObj = new Date(year, month - 1, day);
  const dayOfWeek = dateObj.toLocaleDateString("en-US", {
    weekday: "long",
  });
  let slots = [];

  if (result.success && result.availability && result.availability.length > 0) {
    // If the doctor has set their availability at least once (records exist)
    const dayAvail = result.availability.find(
      (a) => a.day_of_week === dayOfWeek
    );
    if (dayAvail && dayAvail.is_available == 1) {
      slots = generateTimeSlots(dayAvail.start_time, dayAvail.end_time);
    }
    // Else: slots remains empty. We DO NOT fallback if they have customized their schedule.
  } else {
    // Only fallback if the doctor has zero records in the database
    slots = generateTimeSlots("09:00", "17:00");
  }

  timeSelect.innerHTML = '';
  if (slots.length === 0) {
    timeSelect.innerHTML = '<option value="">Doctor is not available on this day</option>';
  } else {
    timeSelect.innerHTML = '<option value="">Select a time slot</option>';
    slots.forEach((slot) => {
      timeSelect.innerHTML += `<option value="${slot}">${formatTime(slot)}</option>`;
    });
  }
}

async function confirmBooking() {
  const doctorId = document.getElementById("booking-doctor-id").value;
  const date = document.getElementById("booking-date").value;
  const time = document.getElementById("booking-time").value;
  const reason = document.getElementById("booking-reason").value;

  if (!date || !time) {
    showToast("Please select date and time", "warning");
    return;
  }

  const btn = document.getElementById("confirm-booking-btn");
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Booking...';

  const result = await patientPost("bookAppointment", {
    doctor_id: doctorId,
    date,
    time,
    reason,
  });

  if (result.success) {
    showToast(
      "Appointment booked successfully! Waiting for doctor approval.",
      "success",
      5000,
    );
    closeModal("booking-modal");
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
}

// ===== PROFILE =====
async function loadProfile() {
  const result = await apiCall(patientEndpoint("getProfile"));

  if (result.success) {
    const p = result.profile;
    document.getElementById("profile-name-display").textContent = p.name;
    document.getElementById("profile-email-display").textContent = p.email;
    document.getElementById("profile-initials").textContent = p.name
      .charAt(0)
      .toUpperCase();

    document.getElementById("profile-name").value = p.name || "";
    document.getElementById("profile-phone").value = p.phone || "";
    document.getElementById("profile-age").value = p.age || "";
    document.getElementById("profile-gender").value = p.gender || "";
    document.getElementById("profile-blood").value = p.blood_group || "";
    document.getElementById("profile-address").value = p.address || "";
    document.getElementById("profile-medical-history").value =
      p.medical_history || "";
  }

  // Profile form submission
  const form = document.getElementById("profile-form");
  form.onsubmit = async (e) => {
    e.preventDefault();

    const data = {
      name: document.getElementById("profile-name").value,
      phone: document.getElementById("profile-phone").value,
      age: document.getElementById("profile-age").value,
      gender: document.getElementById("profile-gender").value,
      blood_group: document.getElementById("profile-blood").value,
      address: document.getElementById("profile-address").value,
      medical_history: document.getElementById("profile-medical-history").value,
    };

    const result = await patientPost("updateProfile", data);

    if (result.success) {
      showToast("Profile updated successfully", "success");
      // Update session name
      const user = getSession();
      user.name = data.name;
      saveSession(user);
      document.getElementById("topbar-name").textContent = data.name;
      document.getElementById("profile-name-display").textContent = data.name;
      document.getElementById("profile-initials").textContent = data.name
        .charAt(0)
        .toUpperCase();
    } else {
      showToast(result.message, "error");
    }
  };
}

// ===== NOTIFICATIONS =====
async function loadNotifications() {
  const result = await apiCall(patientEndpoint("getNotifications"));
  const container = document.getElementById("notification-list");

  if (result.success && result.notifications.length > 0) {
    container.innerHTML = result.notifications
      .map(
        (n) => `
            <div class="notification-item ${n.is_read == 0 ? "unread" : ""}" onclick="markNotifRead(${n.id})">
                <h4>${escapeHtml(n.title)}</h4>
                <p>${escapeHtml(n.message)}</p>
                <div class="notif-time">${formatDateTime(n.created_at)}</div>
            </div>
        `,
      )
      .join("");
  } else {
    container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🔔</div>
                <h3>No notifications</h3>
                <p>You're all caught up!</p>
            </div>`;
  }
}

async function markNotifRead(notificationId) {
  await patientPost("markNotificationRead", {
    notification_id: notificationId,
  });
  loadNotifications();
  loadDashboard();
}
