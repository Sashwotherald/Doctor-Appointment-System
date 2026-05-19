/**
 * Admin Dashboard Scripts
 * Manages dashboard stats, doctors, patients, appointments, reports.
 */

document.addEventListener("DOMContentLoaded", () => {
  // Require admin role to access this page
  if (!requireAuth("admin")) return;

  const user = getSession();
  initSidebarNavigation();
  initTabs(handleAdminTabChange);
  setupAdminUI(user);
  loadDashboard();

  // Handle section changes from sidebar navigation
  window.onSectionChange = (section) => {
    switch (section) {
      case "dashboard":
        loadDashboard();
        break;
      case "appointments":
        loadAllAppointments();
        break;
      case "doctors":
        loadDoctors();
        break;
      case "patients":
        loadPatients();
        break;
      case "reports":
        loadReports();
        break;
    }
  };
});

// ----- Set admin name in the top bar -----
function setupAdminUI(user) {
  document.getElementById("topbar-name").textContent = user.name;
}

// ----- Build the admin API URL -----
function adminEndpoint(action, extraQuery = "") {
  return `admin.php?action=${action}${extraQuery}`;
}

// ----- POST helper for admin API -----
function adminPost(action, payload = {}) {
  return postJson(adminEndpoint(action), payload);
}

// =====================================================
// DASHBOARD
// =====================================================
async function loadDashboard() {
  const result = await apiCall(adminEndpoint("getDashboard"));

  if (result.success) {
    const d = result.dashboard;
    // Update stat cards
    document.getElementById("stat-patients").textContent =
      d.total_patients || 0;
    document.getElementById("stat-doctors").textContent = d.total_doctors || 0;
    document.getElementById("stat-pending-docs").textContent =
      d.pending_doctors || 0;
    document.getElementById("stat-appointments").textContent =
      d.total_appointments || 0;
    document.getElementById("stat-today").textContent =
      d.today_appointments || 0;
    document.getElementById("stat-completed").textContent =
      d.completed_appointments || 0;
    document.getElementById("stat-pending-appts").textContent =
      d.pending_appointments || 0;
    document.getElementById("stat-cancelled").textContent =
      d.cancelled_appointments || 0;

    // Show badge on sidebar if there are pending doctor approvals
    setBadgeValue("pending-doc-badge", d.pending_doctors, "inline");

    // Render the recent appointments table
    renderRecentAppointments(d.recent_appointments || []);
  }
}

// ----- Render the "Recent Appointments" table rows -----
function renderRecentAppointments(appointments) {
  const tbody = document.getElementById("recent-appts-tbody");
  if (!appointments.length) {
    tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">📋</div><h3>No appointments yet</h3></div></td></tr>`;
    return;
  }

  tbody.innerHTML = appointments
    .map(
      (apt) => `
        <tr>
            <td>${escapeHtml(apt.patient_name)}</td>
            <td>Dr. ${escapeHtml(apt.doctor_name)}</td>
            <td>${formatDate(apt.appointment_date)}</td>
            <td>${formatTime(apt.appointment_time)}</td>
            <td>${getStatusBadge(apt.status)}</td>
        </tr>
    `,
    )
    .join("");
}

// =====================================================
// ALL APPOINTMENTS
// =====================================================
let currentAdminApptFilter = null;

async function loadAllAppointments(status = null) {
  currentAdminApptFilter = status;
  const statusQuery = status ? `&status=${status}` : "";

  const result = await apiCall(adminEndpoint("getAppointments", statusQuery));
  const tbody = document.getElementById("all-appts-tbody");

  if (result.success && result.appointments.length > 0) {
    tbody.innerHTML = result.appointments
      .map(
        (apt) => `
            <tr>
                <td>#${apt.id}</td>
                <td>${escapeHtml(apt.patient_name)}</td>
                <td>Dr. ${escapeHtml(apt.doctor_name)}</td>
                <td>${formatDate(apt.appointment_date)}</td>
                <td>${formatTime(apt.appointment_time)}</td>
                <td>${getStatusBadge(apt.status)}</td>
                <td>
                    <select class="btn btn-sm btn-secondary admin-status-select" onchange="adminUpdateAppointment(${apt.id}, this.value)">
                        <option value="">Change Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </td>
            </tr>
        `,
      )
      .join("");
  } else {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📋</div><h3>No ${status || ""} appointments found</h3></div></td></tr>`;
  }
}

// ----- Admin changes an appointment status -----
async function adminUpdateAppointment(appointmentId, status) {
  if (!status) return;
  if (!confirm(`Change appointment #${appointmentId} status to "${status}"?`))
    return;

  const result = await adminPost("updateAppointment", {
    appointment_id: appointmentId,
    status,
  });

  if (result.success) {
    showToast("Appointment updated", "success");
    loadAllAppointments(currentAdminApptFilter);
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// =====================================================
// DOCTORS (with Active/Inactive column)
// =====================================================
let currentDocFilter = "all-docs";

async function loadDoctors(filter = null) {
  if (filter) currentDocFilter = filter;
  const result = await apiCall(adminEndpoint("getDoctors"));
  const tbody = document.getElementById("doctors-tbody");

  if (result.success && result.doctors.length > 0) {
    let doctors = result.doctors;

    // Apply tab filter (all / pending / approved)
    if (currentDocFilter === "pending-docs") {
      doctors = doctors.filter((d) => d.approval_status === "pending");
    } else if (currentDocFilter === "approved-docs") {
      doctors = doctors.filter((d) => d.approval_status === "approved");
    }

    if (doctors.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="empty-icon">👨‍⚕️</div><h3>No doctors in this category</h3></div></td></tr>`;
      return;
    }

    tbody.innerHTML = doctors
      .map((doc) => {
        const isPending = doc.approval_status === "pending";

        // Doctor photo or fallback initial
        const photoHtml = doc.photo
          ? `<img src="${doc.photo}" alt="${escapeHtml(doc.name)}" class="admin-doctor-avatar admin-doctor-avatar-img">`
          : `<div class="admin-doctor-avatar admin-doctor-avatar-fallback">${doc.name ? doc.name.charAt(0) : "?"}</div>`;

        return `<tr>
                <td>${photoHtml}</td>
                <td><strong>${escapeHtml(doc.name)}</strong></td>
                <td>${escapeHtml(doc.email)}</td>
                <td>${escapeHtml(doc.specialization || "-")}</td>
                <td>${escapeHtml(doc.nmc || "-")}</td>
                <td>${doc.experience || 0} yrs</td>
                <td>${getStatusBadge(doc.approval_status || "pending")}</td>
                <td>${getStatusBadge(doc.status || "active")}</td>
                <td>
                  <div class="appointment-actions flex-wrap-start">
                    ${
                      isPending
                        ? `
                        <button class="btn btn-sm btn-success" onclick="approveDoctor(${doc.id})" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="rejectDoctor(${doc.id})" title="Reject">
                            <i class="fas fa-ban"></i>
                        </button>
                    `
                        : ""
                    }
                    <button class="btn btn-sm btn-secondary" onclick="toggleUserStatus(${doc.id})" title="Toggle Active/Inactive">
                        <i class="fas fa-power-off"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteDoctor(${doc.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
            </tr>`;
      })
      .join("");
  } else {
    tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="empty-icon">👨‍⚕️</div><h3>No doctors registered</h3></div></td></tr>`;
  }
}

// ----- Approve a doctor account -----
async function approveDoctor(doctorId) {
  if (!confirm("Approve this doctor?")) return;
  const result = await adminPost("approveDoctor", { doctor_id: doctorId });
  if (result.success) {
    showToast("Doctor approved successfully", "success");
    loadDoctors();
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// ----- Reject a doctor account -----
async function rejectDoctor(doctorId) {
  if (!confirm("Reject this doctor registration?")) return;
  const result = await adminPost("rejectDoctor", { doctor_id: doctorId });
  if (result.success) {
    showToast("Doctor rejected", "warning");
    loadDoctors();
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// ----- Permanently delete a doctor -----
async function deleteDoctor(doctorId) {
  if (
    !confirm(
      "⚠️ Are you sure you want to permanently delete this doctor? This action cannot be undone.",
    )
  )
    return;
  const result = await adminPost("deleteDoctor", { doctor_id: doctorId });
  if (result.success) {
    showToast("Doctor deleted", "success");
    loadDoctors();
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// =====================================================
// PATIENTS
// =====================================================
async function loadPatients() {
  const result = await apiCall(adminEndpoint("getPatients"));
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
                <td>${getStatusBadge(p.status || "active")}</td>
                <td>
                  <div class="appointment-actions flex-wrap-start">
                    <button class="btn btn-sm btn-secondary" onclick="toggleUserStatus(${p.id})" title="Toggle Status">
                        <i class="fas fa-power-off"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deletePatient(${p.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
            </tr>
        `,
      )
      .join("");
  } else {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">👥</div><h3>No patients registered yet</h3></div></td></tr>`;
  }
}

// ----- Permanently delete a patient -----
async function deletePatient(patientId) {
  if (
    !confirm(
      "⚠️ Permanently delete this patient account? This cannot be undone.",
    )
  )
    return;
  const result = await adminPost("deletePatient", { patient_id: patientId });
  if (result.success) {
    showToast("Patient deleted", "success");
    loadPatients();
    loadDashboard();
  } else {
    showToast(result.message, "error");
  }
}

// ----- Toggle a user between active / inactive -----
async function toggleUserStatus(userId) {
  const result = await adminPost("toggleUserStatus", { user_id: userId });
  if (result.success) {
    showToast(result.message, "success");
    loadDoctors();
    loadPatients();
  } else {
    showToast(result.message, "error");
  }
}

// =====================================================
// REPORTS
// =====================================================
async function loadReports() {
  const result = await apiCall(adminEndpoint("getReports"));

  if (result.success) {
    const r = result.reports;

    // Monthly stats table
    const monthlyTbody = document.getElementById("monthly-stats-tbody");
    if (r.monthly_stats && r.monthly_stats.length > 0) {
      monthlyTbody.innerHTML = r.monthly_stats
        .map(
          (m) => `
                <tr>
                    <td><strong>${m.month}</strong></td>
                    <td>${m.total}</td>
                    <td><span class="metric-success">${m.completed}</span></td>
                    <td><span class="metric-danger">${m.cancelled}</span></td>
                </tr>
            `,
        )
        .join("");
    } else {
      monthlyTbody.innerHTML = `<tr><td colspan="4" class="report-empty">No appointment data yet</td></tr>`;
    }

    // Top doctors table
    const topDocTbody = document.getElementById("top-doctors-tbody");
    if (r.top_doctors && r.top_doctors.length > 0) {
      topDocTbody.innerHTML = r.top_doctors
        .map(
          (d) => `
                <tr>
                    <td><strong>${escapeHtml(d.doctor_name)}</strong></td>
                    <td>${escapeHtml(d.specialization || "-")}</td>
                    <td>${d.total_appointments}</td>
                    <td>${d.completed_appointments}</td>
                </tr>
            `,
        )
        .join("");
    } else {
      topDocTbody.innerHTML = `<tr><td colspan="4" class="report-empty">No doctor data yet</td></tr>`;
    }

    // Specialization distribution bar chart
    const specChart = document.getElementById("specialization-chart");
    if (r.specializations && r.specializations.length > 0) {
      const maxCount = Math.max(...r.specializations.map((s) => s.count));
      specChart.innerHTML = r.specializations
        .map((s) => {
          const percent = (s.count / maxCount) * 100;
          return `
                    <div class="report-chart-item">
                        <div class="report-chart-header">
                            <span class="report-chart-label">${escapeHtml(s.specialization)}</span>
                            <span class="report-chart-value">${s.count} doctors</span>
                        </div>
                        <div class="report-chart-track">
                            <div class="report-chart-fill" data-width="${percent}"></div>
                        </div>
                    </div>
                `;
        })
        .join("");

      // Animate the bars
      specChart.querySelectorAll(".report-chart-fill").forEach((bar) => {
        bar.style.width = `${bar.dataset.width}%`;
      });
    } else {
      specChart.innerHTML = `<div class="report-empty report-empty-full">No specialization data available</div>`;
    }
  }
}

// =====================================================
// TAB HANDLER (routes tab clicks to the right loader)
// =====================================================
function handleAdminTabChange(tab) {
  // Appointment tabs
  const apptStatusMap = {
    all: null,
    pending: "pending",
    approved: "approved",
    completed: "completed",
    cancelled: "cancelled",
  };
  if (apptStatusMap.hasOwnProperty(tab)) {
    loadAllAppointments(apptStatusMap[tab]);
    return;
  }

  // Doctor tabs
  const docFilterMap = {
    "all-docs": "all-docs",
    "pending-docs": "pending-docs",
    "approved-docs": "approved-docs",
  };
  if (docFilterMap.hasOwnProperty(tab)) {
    currentDocFilter = tab;
    loadDoctors(tab);
    return;
  }
}
