<?php
/**
 * Emergency Model
 * Handles all database operations for emergency bookings,
 * including doctor lookup, slot finding, and appointment creation.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// GROQ offers a completely FREE OpenAI-compatible API tier
define('GROQ_API_KEY', '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');

// ----- Find approved doctors by exact specialization -----
function findDoctorsBySpecialization($specialization)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id as doctor_id, d.specialization, u.name as doctor_name
        FROM users u
        INNER JOIN doctor_profiles d ON u.id = d.user_id
        WHERE u.role = 'doctor' 
        AND u.status = 'active'
        AND d.approval_status = 'approved'
        AND d.specialization = :specialization
    ");
    $stmt->execute([':specialization' => $specialization]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ----- Fuzzy-match doctors by partial specialization name -----
function findDoctorsByFuzzySpecialization($department, $originalDepartment)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id as doctor_id, d.specialization, u.name as doctor_name
        FROM users u
        INNER JOIN doctor_profiles d ON u.id = d.user_id
        WHERE u.role = 'doctor' 
        AND u.status = 'active'
        AND d.approval_status = 'approved'
        AND (d.specialization LIKE :fuzzy1 OR d.specialization LIKE :fuzzy2)
    ");
    $stmt->execute([
        ':fuzzy1' => '%' . $department . '%',
        ':fuzzy2' => '%' . $originalDepartment . '%'
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ----- Get availability records for a doctor -----
function getEmergencyDoctorAvailability($doctorId)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time FROM doctor_availability WHERE doctor_id = :doc_id AND is_available = 1");
    $stmt->execute([':doc_id' => $doctorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ----- Check if a specific time slot is already booked -----
function isSlotBooked($doctorId, $date, $time)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = :doc_id AND appointment_date = :date AND appointment_time = :time AND status NOT IN ('cancelled', 'rejected')");
    $stmt->execute([':doc_id' => $doctorId, ':date' => $date, ':time' => $time]);
    return (bool) $stmt->fetch();
}

// ----- Find the earliest available slot across a set of doctors (next 14 days) -----
function findFastestAvailableSlot($doctors)
{
    $fastestSlot = null;
    $fastestDoctor = null;

    $today = date('Y-m-d');
    $now = date('H:i:s');
    $debugInfo = ['doctors_found' => count($doctors), 'doctor_details' => []];

    foreach ($doctors as $doc) {
        // Get availability of this doctor
        $availabilities = getEmergencyDoctorAvailability($doc['doctor_id']);

        $debugInfo['doctor_details'][] = [
            'name' => $doc['doctor_name'],
            'specialization' => $doc['specialization'],
            'availability_count' => count($availabilities)
        ];

        if (empty($availabilities)) {
            // Doctor has no availability records — use default Mon-Fri 9AM-5PM for emergencies
            $defaultDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            $availabilities = [];
            foreach ($defaultDays as $day) {
                $availabilities[] = [
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00'
                ];
            }
        }

        // Build a map of day_of_week => availability for quick lookup
        $availByDay = [];
        foreach ($availabilities as $avail) {
            $dayName = ucfirst(strtolower(trim($avail['day_of_week']))); // Normalize: "monday" -> "Monday"
            $availByDay[$dayName][] = $avail;
        }

        // Check each of the next 14 days
        for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
            $checkDate = date('Y-m-d', strtotime("+$dayOffset days"));
            $checkDayName = date('l', strtotime($checkDate)); // e.g. "Wednesday"

            if (!isset($availByDay[$checkDayName])) {
                continue; // Doctor doesn't work this day
            }

            foreach ($availByDay[$checkDayName] as $avail) {
                $start = strtotime($avail['start_time']);
                $end = strtotime($avail['end_time']);

                for ($time = $start; $time < $end; $time += 1800) {
                    $timeStr = date('H:i:s', $time);

                    // Skip past times if checking today
                    if ($checkDate == $today && $timeStr < $now) {
                        continue;
                    }

                    // Check if already booked
                    if (!isSlotBooked($doc['doctor_id'], $checkDate, $timeStr)) {
                        // It's available!
                        $slotTimestamp = strtotime("$checkDate $timeStr");

                        if ($fastestSlot === null || $slotTimestamp < $fastestSlot['timestamp']) {
                            $fastestSlot = [
                                'timestamp' => $slotTimestamp,
                                'date' => $checkDate,
                                'time' => $timeStr
                            ];
                            $fastestDoctor = $doc;
                        }
                        break 2; // Found earliest slot for this doctor, move to next doctor
                    }
                }
            }
        }
    }

    return [
        'slot' => $fastestSlot,
        'doctor' => $fastestDoctor,
        'debug' => $debugInfo
    ];
}

// ----- Create an emergency appointment record -----
function createEmergencyAppointment($patientId, $doctorId, $date, $time, $reason, $urgencyLevel, $department)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason, is_emergency, urgency_level, ai_department) 
        VALUES (:patient_id, :doctor_id, :date, :time, 'approved', :reason, 1, :urgency_level, :ai_department)
    ");

    // Automatically approve emergency appointments
    $stmt->execute([
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':date' => $date,
        ':time' => $time,
        ':reason' => $reason,
        ':urgency_level' => $urgencyLevel,
        ':ai_department' => $department
    ]);

    return $pdo->lastInsertId();
}

// ----- Send a critical emergency notification to a doctor -----
function sendEmergencyNotification($doctorId, $date, $time, $urgencyLevel)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, 'critical')");
    return $stmt->execute([
        ':uid' => $doctorId,
        ':title' => 'EMERGENCY APPOINTMENT',
        ':msg' => "An emergency appointment has been booked for you on {$date} at {$time}. Urgency: " . strtoupper($urgencyLevel)
    ]);
}

/**
 * Normalize AI-returned department names to match our SPECIALIZATIONS list.
 * The AI may return abbreviations or alternative names that don't exactly match.
 */
function normalizeDepartment($department)
{
    // Common aliases/abbreviations the AI might return mapped to our exact SPECIALIZATIONS
    $aliases = [
        'ENT' => 'ENT Specialist',
        'Otolaryngologist' => 'ENT Specialist',
        'Otolaryngology' => 'ENT Specialist',
        'Ear Nose Throat' => 'ENT Specialist',
        'Ear, Nose & Throat' => 'ENT Specialist',
        'Skin Specialist' => 'Dermatologist',
        'Skin Doctor' => 'Dermatologist',
        'Heart Specialist' => 'Cardiologist',
        'Cardiac' => 'Cardiologist',
        'Bone Specialist' => 'Orthopedic',
        'Orthopedics' => 'Orthopedic',
        'Orthopedist' => 'Orthopedic',
        'Orthopaedic' => 'Orthopedic',
        'Eye Specialist' => 'Ophthalmologist',
        'Eye Doctor' => 'Ophthalmologist',
        'Child Specialist' => 'Pediatrician',
        'Paediatrician' => 'Pediatrician',
        'Brain Specialist' => 'Neurologist',
        'Stomach Specialist' => 'Gastroenterologist',
        'GI Specialist' => 'Gastroenterologist',
        'Cancer Specialist' => 'Oncologist',
        'Hormone Specialist' => 'Endocrinologist',
        'Mental Health' => 'Psychiatrist',
        'Psychologist' => 'Psychiatrist',
        'Women Health' => 'Gynecologist',
        'Gynaecologist' => 'Gynecologist',
        'OB-GYN' => 'Gynecologist',
        'OBGYN' => 'Gynecologist',
        'General Practitioner' => 'General Physician',
        'GP' => 'General Physician',
        'Family Medicine' => 'General Physician',
        'Internal Medicine' => 'General Physician',
    ];

    // Check case-insensitive alias match
    foreach ($aliases as $alias => $specialization) {
        if (strcasecmp(trim($department), $alias) === 0) {
            return $specialization;
        }
    }

    // Check if the department is already a valid specialization (exact match)
    if (in_array($department, SPECIALIZATIONS)) {
        return $department;
    }

    // Check case-insensitive match against valid specializations
    foreach (SPECIALIZATIONS as $spec) {
        if (strcasecmp(trim($department), $spec) === 0) {
            return $spec;
        }
    }

    // Return as-is and let the fuzzy DB search handle it
    return $department;
}

// ----- External API Call: Analyze Symptoms using AI -----
function analyzeSymptomsWithAI($symptoms)
{
    // Using Groq's OpenAI-compatible endpoint
    $url = 'https://api.groq.com/openai/v1/chat/completions';

    $specialitiesList = implode(", ", SPECIALIZATIONS);

    $data = [
        'model' => 'llama-3.1-8b-instant', // Updated to Groq's current supported fast model
        'messages' => [
            [
                'role' => 'system',
                'content' => "You are an AI triage assistant for a clinic. Given a patient's symptoms, return a JSON response containing 'urgency' (strictly one of: Low, Medium, Critical) and 'department' (strictly one of these specializations: $specialitiesList). Return ONLY raw JSON without any markdown padding."
            ],
            [
                'role' => 'user',
                'content' => "Symptoms: $symptoms"
            ]
        ],
        'temperature' => 0.0
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);

    // Disable SSL verification for local XAMPP environment
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        $errorMsg = $curlError ? "cURL Error: $curlError" : "HTTP Code $httpCode. Response: $response";
        return ['success' => false, 'message' => 'AI API Error or Rate Limit. ' . $errorMsg];
    }

    $decoded = json_decode($response, true);

    if (isset($decoded['choices'][0]['message']['content'])) {
        $aiContent = $decoded['choices'][0]['message']['content'];
        $parsed = json_decode(trim($aiContent), true);

        if (isset($parsed['urgency']) && isset($parsed['department'])) {
            return [
                'success' => true,
                'urgency' => $parsed['urgency'],
                'department' => $parsed['department']
            ];
        }
    }

    return ['success' => false, 'message' => 'Invalid AI response format.'];
}
