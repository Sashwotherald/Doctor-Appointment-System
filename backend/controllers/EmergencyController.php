<?php
/**
 * Emergency Controller
 * Handles logic for AI-powered emergency booking.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../models/Emergency.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

function handleEmergencyBook($data, $patientId)
{
    if (empty($data['symptoms'])) {
        return ['success' => false, 'message' => 'Symptoms are required for emergency booking'];
    }

    $symptoms = trim($data['symptoms']);

    // 1. Send symptoms to AI API for triage
    $aiAnalysis = analyzeSymptomsWithAI($symptoms);

    if (!$aiAnalysis['success']) {
        return ['success' => false, 'message' => 'Failed to analyze symptoms. ' . $aiAnalysis['message']];
    }

    $urgencyLevel = strtolower($aiAnalysis['urgency']); // low, medium, critical
    $department = $aiAnalysis['department']; // e.g. Cardiologist

    // Validate urgency level
    if (!in_array($urgencyLevel, ['low', 'medium', 'critical'])) {
        $urgencyLevel = 'medium'; // default fallback
    }

    // Normalize department name to match our SPECIALIZATIONS list
    $department = normalizeDepartment($department);

    // 2. Find available doctors (exact match → fuzzy match → General Physician fallback)
    $doctors = findDoctorsBySpecialization($department);

    if (empty($doctors)) {
        // Fallback 1: Fuzzy match — search by partial name (e.g. "ENT" matches "ENT Specialist")
        $doctors = findDoctorsByFuzzySpecialization($department, $aiAnalysis['department']);
        if (!empty($doctors)) {
            $department = $doctors[0]['specialization']; // use the actual DB specialization
        }
    }

    if (empty($doctors)) {
        // Fallback 2: If still no specialist found, fallback to General Physician
        $doctors = findDoctorsBySpecialization('General Physician');
        $department = 'General Physician'; // update department for the record
    }

    if (empty($doctors)) {
        return [
            'success' => false,
            'message' => 'No doctors available currently for this emergency. Please visit the nearest hospital.'
        ];
    }

    // 3. Find nearest available appointment slot (next 14 days)
    $slotResult = findFastestAvailableSlot($doctors);
    $fastestSlot = $slotResult['slot'];
    $fastestDoctor = $slotResult['doctor'];
    $debugInfo = $slotResult['debug'];
    $debugInfo['department'] = $department;

    if (!$fastestSlot) {
        return [
            'success' => false,
            'message' => 'All relevant doctors are fully booked. Please visit an emergency room.',
            'debug' => $debugInfo
        ];
    }

    // 4. Create emergency booking and notify doctor
    $reason = "EMERGENCY: " . $symptoms;
    $appointmentId = createEmergencyAppointment(
        $patientId,
        $fastestDoctor['doctor_id'],
        $fastestSlot['date'],
        $fastestSlot['time'],
        $reason,
        $urgencyLevel,
        $department
    );

    // Send priority notification to doctor
    sendEmergencyNotification(
        $fastestDoctor['doctor_id'],
        $fastestSlot['date'],
        $fastestSlot['time'],
        $urgencyLevel
    );

    return [
        'success' => true,
        'message' => 'Emergency booking created successfully.',
        'data' => [
            'appointment_id' => $appointmentId,
            'urgency' => ucfirst($urgencyLevel),
            'department' => $department,
            'doctor_name' => $fastestDoctor['doctor_name'],
            'date' => $fastestSlot['date'],
            'time' => $fastestSlot['time']
        ]
    ];
}
