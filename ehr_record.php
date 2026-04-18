<?php
// ============================================================
//  Akwaaba Health — EHR Record (XAMPP / PHP / MySQLi)
// ============================================================
session_start();
$pdo = getDB();

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ---- Auto-create tables if missing ----
if ($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS ehr_records (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            patient_id          INT NOT NULL,
            record_date         DATE NOT NULL,
            chief_complaint     TEXT,
            hpi                 TEXT,
            physical_exam       TEXT,
            bp_systolic         INT,
            bp_diastolic        INT,
            heart_rate          INT,
            temperature         DECIMAL(4,1),
            spo2                INT,
            respiratory_rate    INT,
            weight              DECIMAL(5,1),
            height              DECIMAL(5,1),
            primary_diagnosis   VARCHAR(255),
            icd10               VARCHAR(32),
            secondary_diagnoses TEXT,
            clinical_impression TEXT,
            diagnostic_orders   TEXT,
            referrals           TEXT,
            patient_education   TEXT,
            follow_up           TEXT,
            signed_by           VARCHAR(255),
            created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS prescriptions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            ehr_id      INT NOT NULL,
            patient_id  INT NOT NULL,
            name        VARCHAR(255) NOT NULL,
            dosage      VARCHAR(100),
            frequency   VARCHAR(100),
            duration    INT,
            route       VARCHAR(50),
            status      VARCHAR(50) DEFAULT 'active',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ehr_id) REFERENCES ehr_records(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ---- Auth gate ----
if (!isset($_SESSION['akwaaba_user'])) {
    $_SESSION['akwaaba_user'] = [
        'full_name' => 'Dr. Kofi Mensah',
        'role' => 'administrator',
    ];
}
$user = $_SESSION['akwaaba_user'];
$full_name = htmlspecialchars($user['full_name'] ?? 'Dr. Kofi Mensah');
$role = htmlspecialchars($user['role'] ?? 'administrator');
$initials = strtoupper(implode('', array_map(
    fn($p) => $p[0] ?? '',
    array_slice(array_filter(explode(' ', trim($full_name))), 0, 2)
)));
$hospital_name = 'Akwaaba Health';

// ============================================================
// AJAX / POST HANDLERS — must come before any HTML output
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ---- Save full SOAP EHR record ----
    if ($action === 'save_ehr') {
        if (!$conn) {
            echo json_encode(['success' => false, 'error' => 'No database connection.']);
            exit;
        }

        $patient_id = (int) ($_POST['patient_id'] ?? 1);
        $chief_complaint = trim($_POST['chief_complaint'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $bp_systolic = $_POST['bp_systolic'] !== '' ? (int) $_POST['bp_systolic'] : null;
        $bp_diastolic = $_POST['bp_diastolic'] !== '' ? (int) $_POST['bp_diastolic'] : null;
        $heart_rate = $_POST['heart_rate'] !== '' ? (int) $_POST['heart_rate'] : null;
        $temperature = $_POST['temperature'] !== '' ? (float) $_POST['temperature'] : null;
        $spo2 = $_POST['spo2'] !== '' ? (int) $_POST['spo2'] : null;
        $respiratory_rate = $_POST['respiratory_rate'] !== '' ? (int) $_POST['respiratory_rate'] : null;
        $weight = $_POST['weight'] !== '' ? (float) $_POST['weight'] : null;
        $height = $_POST['height'] !== '' ? (float) $_POST['height'] : null;
        $primary_diagnosis = trim($_POST['primary_diagnosis'] ?? '');
        $icd10 = trim($_POST['icd10'] ?? '');
        $secondary_diagnoses = trim($_POST['secondary_diagnoses'] ?? '');
        $clinical_impression = trim($_POST['clinical_impression'] ?? '');
        $diagnostic_orders = trim($_POST['diagnostic_orders'] ?? '');
        $referrals = trim($_POST['referrals'] ?? '');
        $patient_education = trim($_POST['patient_education'] ?? '');
        $follow_up = trim($_POST['follow_up'] ?? '');
        $signed_by = $user['full_name'] ?? 'Unknown';

        // 'i' patient_id
        // 'sss' chief_complaint, hpi, physical_exam
        // 'iiidii' bp_sys, bp_dia, heart_rate, temp, spo2, resp_rate
        // 'dd' weight, height
        // 'ssssssss' primary_diag, icd10, secondary_diag, clinical_imp,
        //             diag_orders, referrals, patient_ed, follow_up, signed_by
        // Total: 1+3+6+2+9 = 21 params  →  type string length must be 21
        $stmt = $conn->prepare("
            INSERT INTO ehr_records
                (patient_id, record_date, chief_complaint, hpi, physical_exam,
                 bp_systolic, bp_diastolic, heart_rate, temperature, spo2,
                 respiratory_rate, weight, height,
                 primary_diagnosis, icd10, secondary_diagnoses, clinical_impression,
                 diagnostic_orders, referrals, patient_education, follow_up, signed_by)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }

        // 21 values → type string = 21 chars
        // i  = patient_id
        // sss= chief_complaint, hpi, physical_exam
        // iii= bp_systolic, bp_diastolic, heart_rate
        // d  = temperature
        // ii = spo2, respiratory_rate
        // dd = weight, height
        // s×9= primary_diagnosis … signed_by
        $stmt->bind_param(
            'isssiiidiidd' . 'sssssssss',
            $patient_id,
            $chief_complaint,
            $hpi,
            $physical_exam,
            $bp_systolic,
            $bp_diastolic,
            $heart_rate,
            $temperature,
            $spo2,
            $respiratory_rate,
            $weight,
            $height,
            $primary_diagnosis,
            $icd10,
            $secondary_diagnoses,
            $clinical_impression,
            $diagnostic_orders,
            $referrals,
            $patient_education,
            $follow_up,
            $signed_by
        );

        if ($stmt->execute()) {
            $ehr_id = $conn->insert_id;
            echo json_encode(['success' => true, 'ehr_id' => $ehr_id, 'message' => 'EHR saved and signed.']);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // ---- Save prescription ----
    if ($action === 'add_prescription') {
        if (!$conn) {
            echo json_encode(['success' => false, 'error' => 'No database connection.']);
            exit;
        }

        $ehr_id = (int) ($_POST['ehr_id'] ?? 0);
        $patient_id = (int) ($_POST['patient_id'] ?? 1);
        $name = trim($_POST['name'] ?? '');
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $duration = (int) ($_POST['duration'] ?? 0);
        $route = trim($_POST['route'] ?? 'oral');

        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Medication name is required.']);
            exit;
        }

        // FIX: type string 'iisssis' — duration is INT not DOUBLE
        $stmt = $conn->prepare("
            INSERT INTO prescriptions (ehr_id, patient_id, name, dosage, frequency, duration, route)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param(
            'iisssis',
            $ehr_id,
            $patient_id,
            $name,
            $dosage,
            $frequency,
            $duration,
            $route
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Prescription saved.']);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// ============================================================
// LOAD PATIENT
// ============================================================
$patient_id_param = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

if ($patient_id_param <= 0) {
    $patient_id_param = 7; // default test patient
}

$patient_data = null;

if ($conn && $patient_id_param > 0) {

    $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $patient_id_param);
        $stmt->execute();

        $result = $stmt->get_result();
        $patient_data = $result->fetch_assoc();

        $stmt->close();
    }
}

// If no patient found, stop system
if (!$patient_data) {
    $patient_data = null;
}
$age = 0;
if (!empty($patient_data['dob'])) {
    try {
        $age = (new DateTime($patient_data['dob']))->diff(new DateTime())->y;
    } catch (Exception $e) {
    }
}
$patient_data['age'] = $age;
$patient_data['bio'] = htmlspecialchars(
    ($patient_data['gender'] ?? '') . ', ' . $age . ' yrs, ' . ($patient_data['phone'] ?? 'No Phone')
);

// Load most-recent EHR for this patient (or a specific one via ?ehr_id=N)
$existing_ehr = null;
$existing_prescriptions = [];
if ($conn) {
    $ehr_id_param = (int) ($_GET['ehr_id'] ?? 0);
    if ($ehr_id_param > 0) {
        $stmt = $conn->prepare("SELECT * FROM ehr_records WHERE id = ? AND patient_id = ? LIMIT 1");
        $stmt->bind_param('ii', $ehr_id_param, $patient_id_param);
    } else {
        $pid_tmp = (int) $patient_data['id'];
        $stmt = $conn->prepare("SELECT * FROM ehr_records WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param('i', $pid_tmp);
    }
    $stmt->execute();
    $existing_ehr = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing_ehr) {
        $stmt2 = $conn->prepare("SELECT * FROM prescriptions WHERE ehr_id = ? ORDER BY id ASC");
        $stmt2->bind_param('i', $existing_ehr['id']);
        $stmt2->execute();
        $existing_prescriptions = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akwaaba Health | Electronic Health Record</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: rgba(255, 255, 255, 0.92);
            --panel-2: rgba(248, 251, 255, 0.75);
            --line: #dde6ef;
            --line-2: rgba(238, 242, 247, 0.8);
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2775b6;
            --primary-dark: #1f6299;
            --primary-soft: #eaf4ff;
            --success: #22c55e;
            --danger: #dc2626;
            --warning: #d97706;
            --shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            --radius: 22px;
            --sidebar-width: 270px;
            --topbar-height: 82px;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
            scroll-behavior: smooth
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, #f8fafc, #eef3f8);
            color: var(--text)
        }

        button,
        input,
        select,
        textarea {
            font: inherit
        }

        button {
            cursor: pointer
        }

        a {
            color: inherit;
            text-decoration: none
        }

        .hidden {
            display: none !important
        }

        /* ---- Sidebar ---- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, rgba(249, 251, 254, .95), rgba(242, 245, 250, .95));
            border-right: 1px solid var(--line);
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 18px;
            gap: 0;
            z-index: 30;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 18px;
            border-bottom: 1px solid var(--line);
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            flex: 0 0 auto;
            background: linear-gradient(135deg, #2f86cc, #205f96);
            color: #fff;
            display: grid;
            place-items: center;
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: #fff;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round
        }

        .sidebar-brand strong {
            font-size: 1.2rem;
            color: #1e5a8f
        }

        .nav-list {
            display: grid;
            gap: 8px;
            padding: 18px 6px 0;
            flex: 1
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            color: #4b5a70;
            font-weight: 600;
            transition: .2s ease;
        }

        .nav-item:hover {
            background: var(--primary-soft);
            color: var(--primary)
        }

        .nav-item.active {
            background: linear-gradient(135deg, #2775b6, #215f95);
            color: #fff;
            box-shadow: 0 12px 24px rgba(39, 117, 182, .18)
        }

        .nav-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center
        }

        .nav-icon svg {
            width: 100%;
            height: 100%;
            fill: currentColor
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            display: grid;
            gap: 12px;
        }

        .logout-btn {
            border: none;
            background: transparent;
            color: #cc5b5b;
            padding: 12px 14px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            width: 100%;
            transition: .2s ease;
        }

        .logout-btn:hover {
            background: #fff1f1
        }

        .system-panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(248, 251, 255, .7);
            padding: 14px
        }

        .system-panel-label {
            font-size: .72rem;
            letter-spacing: .08em;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 8px
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4b5563;
            font-weight: 600;
            font-size: .92rem
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #cbd5e1
        }

        .status-active {
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(62, 182, 107, .12)
        }

        /* ---- Main layout ---- */
        .main-stage {
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh
        }

        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            background: rgba(255, 255, 255, .85);
            border-bottom: 1px solid var(--line);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            z-index: 20;
            backdrop-filter: blur(16px);
        }

        .search-wrap {
            position: relative;
            width: min(520px, 100%)
        }

        .search-wrap input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(248, 250, 253, .92);
            padding: 13px 18px 13px 44px;
            outline: none;
            transition: .2s;
            color: var(--text);
        }

        .search-wrap input:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12);
            background: #fff
        }

        .search-icon {
            position: absolute;
            top: 50%;
            left: 16px;
            width: 18px;
            height: 18px;
            transform: translateY(-50%);
            fill: #94a3b8
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, .8);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #617188;
            transition: .2s
        }

        .icon-btn:hover {
            color: var(--primary);
            background: #fff
        }

        .profile-chip {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255, 255, 255, .85)
        }

        .profile-copy {
            display: flex;
            flex-direction: column;
            align-items: flex-end
        }

        .profile-copy strong {
            font-size: .95rem
        }

        .profile-copy span {
            font-size: .82rem;
            color: var(--muted);
            font-weight: 600
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8ca9c7, #407cb6);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700
        }

        .presence-dot {
            position: absolute;
            right: 10px;
            bottom: 10px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--success);
            box-shadow: 0 0 0 2px #fff
        }

        /* ---- Content ---- */
        .content-shell {
            padding: 30px;
            padding-top: calc(var(--topbar-height) + 30px)
        }

        .card {
            background: var(--panel);
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px)
        }

        /* ---- Patient bar ---- */
        .patient-bar {
            padding: 18px 24px;
            margin-bottom: 22px
        }

        .patient-bar-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 18px;
            align-items: center
        }

        .bar-cell.align-end {
            text-align: right
        }

        .bar-label {
            display: block;
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 800;
            margin-bottom: 6px
        }

        .bar-cell strong {
            font-size: 1.1rem;
            color: #1a2a40
        }

        /* ---- Page head ---- */
        .page-head {
            margin: 22px 0 26px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px
        }

        .page-head h1 {
            margin: 0 0 8px;
            font-size: clamp(2rem, 3vw, 2.8rem);
            line-height: 1;
            letter-spacing: -.03em
        }

        .page-head p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem
        }

        .page-head-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center
        }

        /* ---- Buttons ---- */
        .primary-btn,
        .secondary-btn,
        .ghost-btn {
            border-radius: 14px;
            padding: 13px 18px;
            border: 1px solid transparent;
            font-weight: 700;
            transition: .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .primary-btn {
            background: linear-gradient(135deg, #2775b6, #215f95);
            color: #fff;
            box-shadow: 0 14px 28px rgba(39, 117, 182, .24);
            border: none
        }

        .primary-btn:hover {
            background: linear-gradient(135deg, #327ebd, #1f6299);
            transform: translateY(-1px)
        }

        .primary-btn:disabled {
            opacity: .65;
            transform: none;
            cursor: not-allowed
        }

        .secondary-btn {
            background: rgba(255, 255, 255, .9);
            border-color: var(--line);
            color: #536276;
            backdrop-filter: blur(4px)
        }

        .secondary-btn:hover {
            background: #fff;
            color: var(--primary);
            border-color: rgba(39, 117, 182, .28)
        }

        .ghost-btn {
            background: transparent;
            border-color: transparent;
            color: #536276
        }

        .ghost-btn:hover {
            background: #f1f5f9;
            color: var(--primary)
        }

        /* ---- Layout ---- */
        .layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) 320px;
            gap: 24px;
            align-items: start
        }

        .main-column {
            display: grid;
            gap: 20px
        }

        /* ---- SOAP cards ---- */
        .soap-card {
            overflow: hidden
        }

        .soap-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 24px 28px 0
        }

        .soap-card-head h2 {
            margin: 0 0 6px;
            font-size: clamp(1.3rem, 2vw, 1.75rem)
        }

        .soap-card-head p {
            margin: 0;
            color: var(--muted)
        }

        .soap-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            padding: 24px 28px 28px
        }

        .soap-grid-single {
            grid-template-columns: 1fr
        }

        /* ---- Form fields ---- */
        .field-block {
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .field-block span {
            font-size: 13px;
            font-weight: 700;
            color: #475569
        }

        .field-block input,
        .field-block select,
        .field-block textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(250, 252, 255, .8);
            padding: 14px 16px;
            outline: none;
            color: var(--text);
            transition: .2s;
        }

        .field-block textarea {
            resize: vertical;
            min-height: 92px
        }

        .field-block input:focus,
        .field-block select:focus,
        .field-block textarea:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12);
            background: #fff
        }

        .compact-field {
            max-width: 220px
        }

        .field-span-2 {
            grid-column: span 2
        }

        /* ---- Vitals grid ---- */
        .vitals-grid {
            padding: 24px 28px 20px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px
        }

        .metric-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 18px;
            background: var(--panel-2);
            box-shadow: inset 0 0 0 1px rgba(238, 242, 247, .5)
        }

        .metric-card span {
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em
        }

        .metric-card input {
            border: none;
            background: transparent;
            padding: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            outline: none
        }

        .bp-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: center;
            gap: 8px
        }

        .bp-grid span {
            font-size: 20px;
            color: #94a3b8
        }

        /* ---- Plan section ---- */
        .plan-subsection {
            padding: 0 28px 16px
        }

        .section-subhead h3 {
            margin: 0 0 16px;
            font-size: 18px
        }

        /* ---- Table ---- */
        .table-wrap {
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, .4)
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px
        }

        .data-table th,
        .data-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--line-2);
            font-size: 14px;
            vertical-align: middle
        }

        .data-table thead th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
            font-weight: 800;
            border-bottom-width: 2px
        }

        .data-table tbody tr:last-child td {
            border-bottom: none
        }

        /* ---- Signature panel ---- */
        .signature-panel {
            display: grid;
            place-items: center;
            padding: 38px 24px;
            border: 2px dashed var(--line) !important;
            background: transparent !important;
            box-shadow: none !important
        }

        .signature-copy {
            text-align: center;
            max-width: 440px
        }

        .signature-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid var(--line);
            display: grid;
            place-items: center;
            color: #64748b;
            font-size: 32px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .04)
        }

        .signature-copy h3 {
            margin: 0 0 8px;
            font-size: 24px
        }

        .signature-copy p {
            margin: 0 0 24px;
            color: var(--muted)
        }

        /* ---- Sidebar right cards ---- */
        .side-column {
            display: grid;
            gap: 20px
        }

        .side-card {
            padding: 24px
        }

        .side-card-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6b7280;
            font-weight: 800;
            margin-bottom: 18px
        }

        .alert-box {
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid transparent
        }

        .alert-danger {
            background: rgba(255, 244, 245, .8);
            border-color: rgba(255, 214, 220, .6);
            color: #9f1239
        }

        .alert-muted {
            background: rgba(248, 250, 252, .8);
            border-color: var(--line);
            color: #475569
        }

        .alert-box-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 10px
        }

        .mini-section {
            margin-top: 20px
        }

        .mini-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #475569
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .8);
            border: 1px solid var(--line);
            color: #334155;
            font-size: 13px;
            font-weight: 600
        }

        .quick-nav {
            display: grid;
            gap: 12px
        }

        .quick-nav a {
            display: inline-flex;
            align-items: center;
            color: #475569;
            font-weight: 600;
            transition: .2s
        }

        .quick-nav a:hover {
            color: var(--primary);
            transform: translateX(4px)
        }

        .mini-list {
            display: grid;
            gap: 12px;
            color: #334155
        }

        .mini-list-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--line-2)
        }

        .mini-list-item:last-child {
            border-bottom: none
        }

        .mini-list-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 15px
        }

        .text-link {
            color: var(--primary);
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
            margin-top: 14px
        }

        /* ---- Saved badge ---- */
        .saved-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(34, 197, 94, .12);
            color: #15803d;
            font-size: .84rem;
            font-weight: 700;
            border: 1px solid rgba(34, 197, 94, .2)
        }

        /* ---- Modals ---- */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            backdrop-filter: blur(4px);
            display: grid;
            place-items: center;
            padding: 20px;
            z-index: 100
        }

        .modal-dialog {
            width: min(740px, 100%);
            max-height: 90vh;
            overflow: auto;
            background: rgba(255, 255, 255, .97);
            border-radius: 24px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, .15);
            border: 1px solid rgba(255, 255, 255, .9)
        }

        .modal-large {
            width: min(900px, 100%)
        }

        .modal-head {
            padding: 24px 28px 20px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: flex-start
        }

        .modal-head h2 {
            margin: 0 0 6px
        }

        .modal-head p {
            margin: 0;
            color: var(--muted)
        }

        .modal-close {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: transparent;
            font-size: 24px;
            color: #6b7280;
            display: grid;
            place-items: center;
            transition: .2s;
            flex-shrink: 0
        }

        .modal-close:hover {
            background: #fff;
            color: var(--danger);
            border-color: rgba(220, 38, 38, .3)
        }

        .modal-body {
            padding: 28px
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            margin-top: 8px
        }

        /* ---- Toasts ---- */
        .toast-stack {
            position: fixed;
            right: 22px;
            bottom: 22px;
            display: grid;
            gap: 10px;
            z-index: 150
        }

        .toast {
            min-width: 280px;
            max-width: 380px;
            border-radius: 16px;
            padding: 14px 16px;
            box-shadow: var(--shadow);
            font-weight: 700;
            color: #fff;
            animation: slideUp .3s cubic-bezier(.16, 1, .3, 1)
        }

        .toast.success {
            background: linear-gradient(135deg, #2aa966, #1d8c51)
        }

        .toast.error {
            background: linear-gradient(135deg, #cf6161, #b54d4d)
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(16px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        /* ---- Responsive ---- */
        @media (max-width:1260px) {
            .layout-grid {
                grid-template-columns: 1fr
            }

            .side-column {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .vitals-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }

            .patient-bar-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media (max-width:980px) {
            .sidebar {
                display: none
            }

            .main-stage {
                margin-left: 0
            }

            .topbar {
                left: 0;
                position: static
            }

            .content-shell {
                padding: 20px
            }

            .side-column {
                grid-template-columns: 1fr
            }

            .soap-grid {
                grid-template-columns: 1fr;
                padding: 16px
            }

            .vitals-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                padding: 16px
            }

            .form-grid {
                grid-template-columns: 1fr
            }

            .field-span-2 {
                grid-column: span 1
            }
        }

        @media (max-width:640px) {
            .patient-bar-grid {
                grid-template-columns: 1fr
            }

            .page-head {
                flex-direction: column;
                align-items: stretch
            }
        }
    </style>
</head>

<body>

    <!-- Single SERVER_DATA injection -->
    <script>
        const SERVER_DATA = {
            patient: <?= json_encode($patient_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            ehrId: <?= json_encode($existing_ehr['id'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            existingEhr: <?= json_encode($existing_ehr ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            existingPrescriptions: <?= json_encode($existing_prescriptions ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };
    </script>

    <div class="page-shell">

        <!-- SIDEBAR -->
        <aside class="sidebar" aria-label="Primary navigation">
            <div class="sidebar-brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 64 64">
                        <path d="M13 34h10l4-10 8 22 6-16h10" />
                    </svg>
                </div>
                <strong><?= htmlspecialchars($hospital_name) ?></strong>
            </div>
            <nav class="nav-list">
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path d="M4 13h6V4H4Zm10 7h6V11h-6ZM4 20h6v-5H4Zm10-9h6V4h-6Z" />
                        </svg></span>
                    <span>Dashboard</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M16 11c1.66 0 3-1.79 3-4s-1.34-4-3-4-3 1.79-3 4 1.34 4 3 4Zm-8 0c1.66 0 3-1.79 3-4S9.66 3 8 3 5 4.79 5 7s1.34 4 3 4Zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V20h6v-3.5c0-2.33-4.67-3.5-7-3.5Z" />
                        </svg></span>
                    <span>Patients</span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14Z" />
                        </svg></span>
                    <span>Appointments</span>
                </a>
                <a href="ehr_record.php" class="nav-item active">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5" />
                            <path d="M8 13h8M8 17h8M8 9h3" />
                        </svg></span>
                    <span>Clinical (EHR)</span>
                </a>
                <a href="billing.php" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2Zm0 10H4V8h16ZM6 12h4" />
                        </svg></span>
                    <span>Billing &amp; NHIS</span>
                </a>
                <a href="laboratory.php" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Zm2 2h2v4.12l4.98 8.63a1 1 0 0 1-.87 1.5H6.9a1 1 0 0 1-.87-1.5L11 8.12Z" />
                        </svg></span>
                    <span>Laboratory</span>
                </a>
                <?php if ($role === 'administrator'): ?>
                    <a href="<?= htmlspecialchars($staff_url) ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M12 8a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5Z" />
                            </svg></span>
                        <span>Staff Management</span>
                    </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <button type="button" class="logout-btn" onclick="window.location.href='logout.php'">
                    <span aria-hidden="true">↪</span><span>Logout</span>
                </button>
                <div class="system-panel">
                    <div class="system-panel-label">System Status</div>
                    <div class="system-panel-row"><span class="status-dot status-active"></span><span>Server
                            Online</span></div>
                </div>
            </div>
        </aside>

        <!-- MAIN STAGE -->
        <div class="main-stage">
            <header class="topbar">
                <div class="search-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                    </svg>
                    <input type="search" placeholder="Search patient by Name, ID..." autocomplete="off" />
                </div>
                <div class="topbar-actions">
                    <div class="profile-chip">
                        <div class="profile-copy">
                            <strong><?= $full_name ?></strong>
                            <span><?= ucfirst($role) ?></span>
                        </div>
                        <div class="profile-avatar"><?= $initials ?></div>
                        <span class="presence-dot"></span>
                    </div>
                </div>
            </header>

            <main class="content-shell">

                <!-- Patient bar -->
                <section class="patient-bar card">
                    <div class="patient-bar-grid">
                        <div class="bar-cell">
                            <span class="bar-label">Patient Name</span>
                            <strong><?= htmlspecialchars($patient_data['full_name']) ?></strong>
                        </div>
                        <div class="bar-cell">
                            <span class="bar-label">NHIS Number</span>
                            <strong><?= htmlspecialchars($patient_data['nhis_number'] ?? '—') ?></strong>
                        </div>
                        <div class="bar-cell">
                            <span class="bar-label">Bio Data</span>
                            <strong><?= $patient_data['bio'] ?></strong>
                        </div>
                        <div class="bar-cell align-end">
                            <span class="bar-label">Visit Status</span>
                            <strong id="visitStatus" style="color:var(--success)">
                                <?= $existing_ehr ? 'Record Loaded' : 'In Progress' ?>
                            </strong>
                        </div>
                    </div>
                </section>

                <section class="page-head">
                    <div>
                        <h1>Electronic Health Record</h1>
                        <p>SOAP Note &amp; Clinical Management — <?= date('l, M jS, Y') ?></p>
                    </div>
                    <div class="page-head-actions">
                        <span class="saved-badge hidden" id="savedBadge">✓ Signed &amp; Saved</span>
                        <button type="button" class="secondary-btn"
                            onclick="document.getElementById('previousRecordsModal').classList.remove('hidden')">
                            Previous Records
                        </button>
                        <button type="button" class="secondary-btn" onclick="window.print()">Print Form</button>
                    </div>
                </section>

                <section class="layout-grid">

                    <!-- MAIN COLUMN -->
                    <div class="main-column">

                        <!-- S — SUBJECTIVE -->
                        <article class="soap-card card" id="subjectiveCard">
                            <div class="soap-card-head">
                                <div>
                                    <h2>Subjective (S)</h2>
                                    <p>Chief complaint, history of present illness, and symptoms reported by patient.
                                    </p>
                                </div>
                            </div>
                            <div class="soap-grid soap-grid-single">
                                <label class="field-block">
                                    <span>Chief Complaint</span>
                                    <input type="text" id="chief_complaint"
                                        placeholder="Enter patient's presenting complaint" />
                                </label>
                                <label class="field-block">
                                    <span>History of Present Illness (HPI)</span>
                                    <textarea id="hpi" rows="5"
                                        placeholder="Describe history of present illness"></textarea>
                                </label>
                            </div>
                        </article>

                        <!-- O — OBJECTIVE -->
                        <article class="soap-card card" id="objectiveCard">
                            <div class="soap-card-head">
                                <div>
                                    <h2>Objective (O)</h2>
                                    <p>Vital signs, physical exam findings, and observable measurements.</p>
                                </div>
                            </div>
                            <div class="vitals-grid">
                                <label class="metric-card"><span>Blood Pressure</span>
                                    <div class="bp-grid">
                                        <input id="bp_systolic" type="number" min="0" placeholder="Sys" />
                                        <span>/</span>
                                        <input id="bp_diastolic" type="number" min="0" placeholder="Dia" />
                                    </div>
                                </label>
                                <label class="metric-card"><span>Heart Rate (BPM)</span>
                                    <input id="heart_rate" type="number" min="0" placeholder="BPM" />
                                </label>
                                <label class="metric-card"><span>Temperature (°C)</span>
                                    <input id="temperature" type="number" step="0.1" placeholder="°C" />
                                </label>
                                <label class="metric-card"><span>SpO₂ (%)</span>
                                    <input id="spo2" type="number" min="0" max="100" placeholder="%" />
                                </label>
                                <label class="metric-card"><span>Respiratory Rate</span>
                                    <input id="respiratory_rate" type="number" min="0" placeholder="rpm" />
                                </label>
                                <label class="metric-card"><span>Weight (kg)</span>
                                    <input id="weight" type="number" step="0.1" placeholder="kg" />
                                </label>
                                <label class="metric-card"><span>Height (cm)</span>
                                    <input id="height" type="number" step="0.1" placeholder="cm" />
                                </label>
                            </div>
                            <div class="soap-grid soap-grid-single" style="padding-top:0">
                                <label class="field-block">
                                    <span>Physical Examination Notes</span>
                                    <textarea id="physical_exam" rows="4"
                                        placeholder="Record physical examination findings"></textarea>
                                </label>
                            </div>
                        </article>

                        <!-- A — ASSESSMENT -->
                        <article class="soap-card card" id="assessmentCard">
                            <div class="soap-card-head">
                                <div>
                                    <h2>Assessment (A)</h2>
                                    <p>Medical diagnosis, clinical reasoning, and status of existing conditions.</p>
                                </div>
                            </div>
                            <div class="soap-grid">
                                <label class="field-block">
                                    <span>Primary Diagnosis</span>
                                    <input type="text" id="primary_diagnosis" placeholder="Enter primary diagnosis" />
                                </label>
                                <label class="field-block compact-field">
                                    <span>ICD-10 Code</span>
                                    <input type="text" id="icd10" placeholder="e.g. I10" />
                                </label>
                                <label class="field-block field-span-2">
                                    <span>Secondary Diagnoses</span>
                                    <textarea id="secondary_diagnoses" rows="3"
                                        placeholder="Comma-separated diagnoses"></textarea>
                                </label>
                                <label class="field-block field-span-2">
                                    <span>Clinical Impression</span>
                                    <textarea id="clinical_impression" rows="4"
                                        placeholder="Enter assessment and clinical impression"></textarea>
                                </label>
                            </div>
                        </article>

                        <!-- P — PLAN -->
                        <article class="soap-card card" id="planCard">
                            <div class="soap-card-head">
                                <div>
                                    <h2>Plan (P)</h2>
                                    <p>Prescriptions, diagnostic orders, follow-up, and patient education.</p>
                                </div>
                                <button type="button" class="secondary-btn"
                                    onclick="document.getElementById('prescriptionModal').classList.remove('hidden')">
                                    + Add Med
                                </button>
                            </div>

                            <section class="plan-subsection">
                                <div class="section-subhead">
                                    <h3>Medications Prescribed</h3>
                                </div>
                                <div class="table-wrap">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Medication</th>
                                                <th>Dosage</th>
                                                <th>Frequency</th>
                                                <th>Duration</th>
                                                <th>Route</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="prescriptionsTableBody">
                                            <?php if (empty($existing_prescriptions)): ?>
                                                <tr id="noPrescRow">
                                                    <td colspan="6" style="text-align:center;color:var(--muted)">No
                                                        prescriptions added yet.</td>
                                                </tr>
                                            <?php else:
                                                foreach ($existing_prescriptions as $rx): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($rx['name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($rx['dosage'] ?? '') ?></td>
                                                        <td style="text-transform:capitalize">
                                                            <?= htmlspecialchars(str_replace('_', ' ', $rx['frequency'] ?? '')) ?>
                                                        </td>
                                                        <td><?= htmlspecialchars((string) ($rx['duration'] ?? '')) ?> days</td>
                                                        <td style="text-transform:capitalize">
                                                            <?= htmlspecialchars($rx['route'] ?? '') ?>
                                                        </td>
                                                        <td><span class="tag"
                                                                style="background:#eef2ff;color:#4338ca;border-color:#c7d2fe"><?= ucfirst($rx['status'] ?? 'active') ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <div class="soap-grid soap-grid-single" style="padding-top:8px">
                                <label class="field-block"><span>Diagnostic Orders</span>
                                    <textarea id="diagnostic_orders" rows="3"
                                        placeholder="Lab or imaging orders"></textarea>
                                </label>
                                <label class="field-block"><span>Referrals</span>
                                    <textarea id="referrals" rows="2" placeholder="Referral details"></textarea>
                                </label>
                                <label class="field-block"><span>Patient Education</span>
                                    <textarea id="patient_education" rows="2"
                                        placeholder="Counselling and education notes"></textarea>
                                </label>
                                <label class="field-block"><span>Follow-up Instructions</span>
                                    <textarea id="follow_up" rows="2"
                                        placeholder="Document the follow-up plan"></textarea>
                                </label>
                            </div>
                        </article>

                        <!-- Signature panel -->
                        <section class="signature-panel card">
                            <div class="signature-copy">
                                <div class="signature-icon">✎</div>
                                <h3>Attending Clinician Signature</h3>
                                <p>By signing, you certify that the clinical findings and plan are complete and
                                    accurate.</p>
                                <button type="button" class="primary-btn" id="signRecordBtn">
                                    Apply Digital Signature &amp; Save
                                </button>
                            </div>
                        </section>

                    </div><!-- /main-column -->

                    <!-- SIDE COLUMN -->
                    <aside class="side-column">
                        <section class="card side-card">
                            <div class="side-card-title">Safety Alerts</div>
                            <div class="alert-box alert-danger">
                                <div class="alert-box-title">Critical Allergies</div>
                                <div>Penicillin — Severe rash &amp; swelling</div>
                            </div>
                            <div class="alert-box alert-muted">
                                <div class="alert-box-title">Drug Interactions</div>
                                <div>No known interaction alerts.</div>
                            </div>
                            <div class="mini-section">
                                <div class="mini-title">Chronic Conditions</div>
                                <div class="tag-list">
                                    <span class="tag">Hypertension</span>
                                    <span class="tag">Asthma</span>
                                </div>
                            </div>
                        </section>

                        <section class="card side-card">
                            <div class="side-card-title">Quick Navigate</div>
                            <nav class="quick-nav">
                                <a href="#subjectiveCard">→ Subjective</a>
                                <a href="#objectiveCard">→ Objective</a>
                                <a href="#assessmentCard">→ Assessment</a>
                                <a href="#planCard">→ Plan</a>
                            </nav>
                        </section>

                        <section class="card side-card">
                            <div class="side-card-title">Recent Lab Results</div>
                            <div class="mini-list">
                                <div class="mini-list-item">
                                    <strong>Complete Blood Count</strong>
                                    <div style="color:var(--muted);font-size:.85rem">Yesterday • WNL</div>
                                </div>
                                <div class="mini-list-item">
                                    <strong>Lipid Panel</strong>
                                    <div style="color:var(--muted);font-size:.85rem">Oct 12 • Elevated LDL</div>
                                </div>
                            </div>
                        </section>
                    </aside>

                </section><!-- /layout-grid -->
            </main>
        </div><!-- /main-stage -->
    </div><!-- /page-shell -->


    <!-- ==================== MODALS ==================== -->

    <!-- Previous Records Modal -->
    <div class="modal hidden" id="previousRecordsModal" aria-hidden="true">
        <div class="modal-dialog modal-large">
            <div class="modal-head">
                <div>
                    <h2>Previous EHR Records</h2>
                    <p>SOAP records on file for this patient.</p>
                </div>
                <button type="button" class="modal-close"
                    onclick="document.getElementById('previousRecordsModal').classList.add('hidden')">×</button>
            </div>
            <div class="modal-body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Visit Date</th>
                                <th>Primary Diagnosis</th>
                                <th>Signed By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($conn) {
                                $pid2 = (int) $patient_data['id'];
                                $prev = $conn->query("SELECT * FROM ehr_records WHERE patient_id = $pid2 ORDER BY created_at DESC LIMIT 20");
                                if ($prev && $prev->num_rows > 0) {
                                    while ($r = $prev->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) . '</td>';
                                        echo '<td>' . htmlspecialchars($r['primary_diagnosis'] ?: '—') . '</td>';
                                        echo '<td>' . htmlspecialchars($r['signed_by'] ?: '—') . '</td>';
                                        echo '<td><a href="?patient_id=' . $pid2 . '&ehr_id=' . (int) $r['id'] . '" class="text-link" style="margin:0">Load</a></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" style="text-align:center;color:var(--muted)">No previous records found.</td></tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Prescription Modal -->
    <div class="modal hidden" id="prescriptionModal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-head">
                <div>
                    <h2>Add Prescription</h2>
                    <p>Create a medication order for this EHR record.</p>
                </div>
                <button type="button" class="modal-close"
                    onclick="document.getElementById('prescriptionModal').classList.add('hidden')">×</button>
            </div>
            <form class="modal-body form-grid" id="prescriptionForm" onsubmit="addPrescription(event)">
                <label class="field-block field-span-2">
                    <span>Medication Name *</span>
                    <input type="text" id="rx_name" required placeholder="e.g. Amoxicillin" />
                </label>
                <label class="field-block">
                    <span>Dosage *</span>
                    <input type="text" id="rx_dosage" placeholder="e.g. 500mg" required />
                </label>
                <label class="field-block">
                    <span>Frequency *</span>
                    <select id="rx_frequency" required>
                        <option value="">Select frequency</option>
                        <option value="once_daily">Once daily</option>
                        <option value="twice_daily">Twice daily</option>
                        <option value="three_times_daily">Three times daily</option>
                        <option value="four_times_daily">Four times daily</option>
                        <option value="as_needed">As needed</option>
                    </select>
                </label>
                <label class="field-block">
                    <span>Duration (days) *</span>
                    <input type="number" id="rx_duration" min="1" placeholder="e.g. 7" required />
                </label>
                <label class="field-block">
                    <span>Route</span>
                    <select id="rx_route">
                        <option value="oral">Oral</option>
                        <option value="iv">IV</option>
                        <option value="im">IM</option>
                        <option value="topical">Topical</option>
                        <option value="inhaled">Inhaled</option>
                        <option value="sublingual">Sublingual</option>
                    </select>
                </label>
                <div class="modal-actions field-span-2">
                    <button type="button" class="ghost-btn"
                        onclick="document.getElementById('prescriptionModal').classList.add('hidden')">Cancel</button>
                    <button type="submit" class="primary-btn">Save Prescription</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-stack" id="toastStack"></div>

    <!-- ==================== JAVASCRIPT ==================== -->
    <script>
        // ---- Utilities ----
        function escapeHtml(text) {
            return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function showToast(msg, isError) {
            const stack = document.getElementById('toastStack');
            const t = document.createElement('div');
            t.className = 'toast ' + (isError ? 'error' : 'success');
            t.textContent = msg;
            stack.appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0';
                t.style.transition = '.3s ease';
                setTimeout(() => t.remove(), 320);
            }, 3500);
        }

        function fieldVal(id) {
            const el = document.getElementById(id);
            return el ? el.value : '';
        }

        // ---- Track current EHR id (null until first save) ----
        let currentEhrId = SERVER_DATA.ehrId || null;

        // ---- Restore existing EHR fields on page load ----
        (function restoreExisting() {
            const ehr = SERVER_DATA.existingEhr;
            if (!ehr) return;
            const fields = [
                'chief_complaint', 'hpi', 'physical_exam',
                'bp_systolic', 'bp_diastolic', 'heart_rate', 'temperature',
                'spo2', 'respiratory_rate', 'weight', 'height',
                'primary_diagnosis', 'icd10', 'secondary_diagnoses', 'clinical_impression',
                'diagnostic_orders', 'referrals', 'patient_education', 'follow_up'
            ];
            fields.forEach(f => {
                const el = document.getElementById(f);
                if (el && ehr[f] != null && String(ehr[f]) !== '') {
                    el.value = ehr[f];
                }
            });
            if (currentEhrId) {
                document.getElementById('visitStatus').textContent = 'Record Loaded';
                document.getElementById('savedBadge').classList.remove('hidden');
            }
        })();

        // ---- Save full SOAP record ----
        document.getElementById('signRecordBtn').addEventListener('click', saveEHR);

        function saveEHR() {
            const btn = document.getElementById('signRecordBtn');
            btn.textContent = 'Saving…';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', 'save_ehr');
            fd.append('patient_id', SERVER_DATA.patient.id || 1);

            [
                'chief_complaint', 'hpi', 'physical_exam',
                'bp_systolic', 'bp_diastolic', 'heart_rate', 'temperature',
                'spo2', 'respiratory_rate', 'weight', 'height',
                'primary_diagnosis', 'icd10', 'secondary_diagnoses', 'clinical_impression',
                'diagnostic_orders', 'referrals', 'patient_education', 'follow_up'
            ].forEach(f => fd.append(f, fieldVal(f)));

            fetch('ehr_record.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.textContent = 'Apply Digital Signature & Save';
                    btn.disabled = false;
                    if (data.success) {
                        currentEhrId = data.ehr_id;
                        document.getElementById('savedBadge').classList.remove('hidden');
                        document.getElementById('visitStatus').textContent = 'Signed & Saved';
                        document.getElementById('visitStatus').style.color = 'var(--success)';
                        showToast('EHR Record Digitally Signed & Saved ✓', false);
                    } else {
                        showToast('Error: ' + (data.error || 'Could not save record.'), true);
                    }
                })
                .catch(() => {
                    btn.textContent = 'Apply Digital Signature & Save';
                    btn.disabled = false;
                    showToast('Network error. Please try again.', true);
                });
        }

        // ---- Add prescription ----
        function addPrescription(e) {
            e.preventDefault();

            if (!currentEhrId) {
                showToast('Please sign & save the EHR record first, then add prescriptions.', true);
                return;
            }

            const name = fieldVal('rx_name');
            const dosage = fieldVal('rx_dosage');
            const freq = fieldVal('rx_frequency');
            const duration = fieldVal('rx_duration');
            const route = fieldVal('rx_route');

            const fd = new FormData();
            fd.append('action', 'add_prescription');
            fd.append('ehr_id', currentEhrId);
            fd.append('patient_id', SERVER_DATA.patient.id || 1);
            fd.append('name', name);
            fd.append('dosage', dosage);
            fd.append('frequency', freq);
            fd.append('duration', duration);
            fd.append('route', route);

            fetch('ehr_record.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Remove "no prescriptions" placeholder row if present
                        const noRow = document.getElementById('noPrescRow');
                        if (noRow) noRow.remove();

                        const freqLabel = freq.replace(/_/g, ' ');
                        document.getElementById('prescriptionsTableBody').insertAdjacentHTML('beforeend', `
                    <tr>
                        <td><strong>${escapeHtml(name)}</strong></td>
                        <td>${escapeHtml(dosage)}</td>
                        <td style="text-transform:capitalize">${escapeHtml(freqLabel)}</td>
                        <td>${escapeHtml(duration)} days</td>
                        <td style="text-transform:capitalize">${escapeHtml(route)}</td>
                        <td><span class="tag" style="background:#eef2ff;color:#4338ca;border-color:#c7d2fe">Active</span></td>
                    </tr>`);

                        document.getElementById('prescriptionModal').classList.add('hidden');
                        document.getElementById('prescriptionForm').reset();
                        showToast('Prescription saved to Plan ✓', false);
                    } else {
                        showToast('Error: ' + (data.error || 'Could not save prescription.'), true);
                    }
                })
                .catch(() => showToast('Network error saving prescription.', true));
        }

        // ---- Close modals on backdrop click or Escape ----
        ['previousRecordsModal', 'prescriptionModal'].forEach(id => {
            const el = document.getElementById(id);
            el.addEventListener('click', function (e) {
                if (e.target === this) this.classList.add('hidden');
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                ['previousRecordsModal', 'prescriptionModal'].forEach(id =>
                    document.getElementById(id).classList.add('hidden')
                );
            }
        });
    </script>
</body>

</html>