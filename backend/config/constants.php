<?php
/**
 * Application Constants
 */

// User Roles
define('ROLE_PATIENT', 'patient');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_ADMIN', 'admin');

// Appointment Statuses
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_RESCHEDULED', 'rescheduled');

// Doctor Approval Statuses
define('DOCTOR_PENDING', 'pending');
define('DOCTOR_APPROVED', 'approved');
define('DOCTOR_REJECTED', 'rejected');

// Upload paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/doctors/');
define('UPLOAD_URL', '/Doctor_Appointment_System/backend/uploads/doctors/');

// Specializations
define('SPECIALIZATIONS', [
    'General Physician',
    'Cardiologist',
    'Dermatologist',
    'Neurologist',
    'Orthopedic',
    'Pediatrician',
    'Psychiatrist',
    'Gynecologist',
    'Ophthalmologist',
    'ENT Specialist',
    'Dentist',
    'Urologist',
    'Oncologist',
    'Endocrinologist',
    'Gastroenterologist'
]);
