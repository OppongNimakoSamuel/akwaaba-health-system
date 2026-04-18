<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getDB();

session_start();



$departments = [];

try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

$doctors = [];

try {
    $stmt = $pdo->query("
    SELECT id, full_name 
    FROM staff 
    WHERE role = 'doctor'
");
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $doctors = [];
}

// --- Auth gate ---
if (!isset($_SESSION['akwaaba_user'])) {
    $_SESSION['akwaaba_user'] = [
        'full_name' => 'Dr. Kofi Mensah',
        'role' => 'administrator',
    ];
}

$user = $_SESSION['akwaaba_user'];
$full_name = htmlspecialchars($user['full_name'] ?? 'Dr. Kofi Mensah');
$role = htmlspecialchars($user['role'] ?? 'administrator');

$initials = strtoupper(
    implode('', array_map(fn($p) => $p[0] ?? '', array_slice(array_filter(explode(' ', trim($full_name))), 0, 2)))
);



// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $patient_id = (int) $_POST['patient_id'];
        $department_id = (int) ($_POST['department_id'] ?? 0);
        $doctor_id = (int) ($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? date('Y-m-d');
        $appointment_time = $_POST['appointment_time'] ?? date('H:i');
        $appointment_type = $_POST['appointment_type'] ?? 'general';
        $use_nhis = isset($_POST['use_nhis']) ? 1 : 0;
        $payment_required = isset($_POST['payment_required']) ? 1 : 0;
        $reason = $_POST['reason_for_visit'] ?? '';
        $clinical_notes = $_POST['clinical_notes'] ?? '';

        if (empty($_POST['patient_id'])) {
            $_SESSION['flash_error'] = "No patient selected!";
            header("Location: appointments.php");
            exit;

            var_dump($_POST['doctor_id']);
            exit;
        }
        try {
            $appointment_id = uniqid('APT-');
            $status = 'pending';
            $stmt = $pdo->prepare("
            
    INSERT INTO appointments 
    (appointment_id, patient_id, department_id, doctor_id, appointment_date, appointment_time, appointment_type, reason_for_visit, clinical_notes, status, use_nhis, payment_required)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

            $stmt->execute([
                $appointment_id,
                $patient_id,
                $department_id,
                $doctor_id,
                $appointment_date,
                $appointment_time,
                $appointment_type,
                $reason,
                $clinical_notes,
                $status,
                $use_nhis,
                $payment_required
            ]);
            $_SESSION['flash_success'] = "Appointment completely scheduled!";
        } catch (Exception $e) {
            die("SQL ERROR: " . $e->getMessage());
        }

        header("Location: appointments.php");
        exit;
    }

    if ($action === 'update_status') {
        header('Content-Type: application/json');
        $id = (int) ($_POST['appointment_record_id'] ?? 0);
        $status = $_POST['new_status'] ?? 'pending';
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Fetch base DB data
$appointments = [];
try {
    $departments = $pdo->query("
        SELECT id, name 
        FROM departments 
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $doctors = $pdo->query("
        SELECT id, full_name, department_id, role,
            (SELECT name FROM departments WHERE id = staff.department_id LIMIT 1) AS department_name
        FROM staff 
        WHERE role LIKE '%doctor%' OR role = 'specialist'
        ORDER BY full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $departments = [];
    $doctors = [];
}

$query = "SELECT a.*, 
            COALESCE(p.full_name, a.patient_id) AS patient_name, 
            p.phone AS contact,
            COALESCE(d.full_name, 'Unassigned') AS doctor_name,
            COALESCE(dep.name, 'General') AS department_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.patient_id OR a.patient_id = p.id
    LEFT JOIN staff d ON a.doctor_id = d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$raw_appointments = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

foreach ($raw_appointments as $a) {
    $appointments[] = [
        'id' => $a['id'],
        'appointment_id' => $a['appointment_id'],
        'patient_uuid' => $a['patient_id'],
        'patient_name' => $a['patient_name'],
        'patient_id' => $a['patient_id'],
        'patient_phone' => $a['contact'],
        'doctor_uuid' => $a['doctor_id'],
        'doctor_name' => $a['doctor_name'],
        'department_id' => $a['department_id'],
        'department_name' => $a['department_name'],
        'date' => $a['appointment_date'],

        // ✅ FIXED LINE
        'time' => !empty($a['appointment_time'])
            ? substr($a['appointment_time'], 0, 5)
            : '',

        'status' => $a['status'],
        'appointment_type' => $a['appointment_type']
    ];
}

// Hydrate structures for JS
$departments_js = array_map(function ($d) {
    return ['id' => $d['id'], 'name' => $d['name']];
}, $departments);

$doctors_js = array_map(function ($d) {
    return ['id' => $d['id'], 'full_name' => $d['full_name'], 'department' => $d['department_name'], 'is_active' => true];
}, $doctors);

$hospital_name = 'Akwaaba Health';

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akwaaba Health | Appointment Scheduling</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-soft: rgba(255, 255, 255, 0.6);
            --border: #dde6ef;
            --line-strong: #d3dce7;
            --text: #17253a;
            --muted: #6f8093;
            --primary: #2775b6;
            --primary-dark: #1f6299;
            --primary-soft: #eef6fd;
            --success: #3eb66b;
            --danger: #d85959;
            --danger-soft: #fff1f1;
            --warning: #d39a2f;
            --warning-soft: #fff7e7;
            --shadow: 0 18px 42px rgba(25, 54, 85, 0.08);
            --shadow-sm: 0 4px 12px rgba(28, 56, 92, .04);
            --radius-lg: 22px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --sidebar-width: 270px;
            --topbar-height: 82px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #f8fafc, #eef3f8);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .hidden {
            display: none !important;
        }

        .page-shell {
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            border-right: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(249, 251, 254, 0.8), rgba(242, 245, 250, 0.8));
            backdrop-filter: blur(12px);
            padding: 24px 16px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 30;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 18px;
            border-bottom: 1px solid var(--border);
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2f86cc, #205f96);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-brand strong {
            font-size: 1.2rem;
            color: #1e5a8f;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 18px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            color: #4b5a70;
            border-radius: 16px;
            transition: .2s ease;
            font-weight: 600;
        }

        .nav-item:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(135deg, #2775b6, #215f95);
            color: #fff;
            box-shadow: 0 12px 24px rgba(39, 117, 182, .18);
        }

        .nav-arrow {
            margin-left: auto;
            font-size: 1.25rem;
        }

        .sidebar-footer {
            padding: 16px 6px 6px;
            border-top: 1px solid var(--border);
            display: grid;
            gap: 14px;
        }

        .logout-btn {
            color: #cc5b5b;
            border-radius: 14px;
            padding: 12px 14px;
            background: none;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            width: 100%;
            transition: .2s ease;
        }

        .logout-btn:hover {
            background: #fff1f1;
        }

        .system-panel {
            border-radius: 16px;
            border: 1px solid var(--border);
            background: rgba(248, 251, 255, 0.6);
            padding: 14px;
            backdrop-filter: blur(4px);
        }

        .system-panel-label {
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-size: .72rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: .92rem;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #9db3c5;
        }

        .status-dot.status-active {
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(62, 182, 107, 0.12);
        }

        .main-stage {
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            background: rgba(255, 255, 255, 0.7);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(16px);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            z-index: 20;
        }

        .search-wrap {
            position: relative;
            max-width: 520px;
            flex: 1;
        }

        .search-wrap input {
            width: 100%;
            border: 1px solid var(--border);
            background: rgba(248, 250, 253, 0.92);
            border-radius: 14px;
            padding: 13px 18px 13px 44px;
            outline: none;
            color: var(--text);
            transition: .2s;
        }

        .search-wrap input:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12);
            background: #fff;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #7e8aa0;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.8);
            color: #617188;
            transition: .2s;
        }

        .icon-btn:hover {
            color: var(--primary);
            background: #fff;
        }

        .icon-btn.subtle {
            border: none;
            background: transparent;
        }

        .profile-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.8);
        }

        .profile-copy {
            display: flex;
            flex-direction: column;
            text-align: right;
        }

        .profile-copy strong {
            font-size: .95rem;
        }

        .profile-copy span {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 600;
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #8ca9c7, #407cb6);
        }

        .presence-dot {
            position: absolute;
            right: 10px;
            bottom: 10px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--success);
            box-shadow: 0 0 0 2px #fff;
        }

        .content-shell {
            padding: 30px;
            padding-top: calc(var(--topbar-height) + 30px);
            display: grid;
            gap: 22px;
        }

        .hero-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .hero-row h1 {
            margin: 0 0 6px;
            font-size: 2.3rem;
            letter-spacing: -.03em;
        }

        .hero-row p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .primary-btn,
        .secondary-btn,
        .icon-pill,
        .clear-filters-btn,
        .segment-btn {
            border-radius: 14px;
            padding: 13px 18px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            transition: .2s ease;
        }

        .primary-btn {
            background: linear-gradient(135deg, #2775b6, #215f95);
            color: #fff;
            box-shadow: 0 14px 28px rgba(39, 117, 182, .24);
        }

        .primary-btn:hover {
            background: linear-gradient(135deg, #327ebd, #1f6299);
            transform: translateY(-1px);
        }

        .secondary-btn,
        .icon-pill,
        .clear-filters-btn {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--border);
            color: #536276;
            backdrop-filter: blur(4px);
        }

        .secondary-btn.small {
            padding: 10px 14px;
            font-size: .92rem;
        }

        .secondary-btn:hover,
        .icon-pill:hover,
        .clear-filters-btn:hover {
            border-color: rgba(39, 117, 182, .28);
            color: var(--primary);
            background: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            padding: 22px;
            min-height: 135px;
            display: grid;
            align-content: start;
            gap: 10px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: transparent;
        }

        .stat-card.accent-blue::before {
            background: #2f7fbe;
        }

        .stat-card.accent-slate::before {
            background: #92a6bb;
        }

        .stat-card.accent-red::before {
            background: #c95a5a;
        }

        .stat-card.accent-neutral::before {
            background: #acbccc;
        }

        .stat-label {
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #7f92a5;
            font-size: .8rem;
            font-weight: 800;
        }

        .stat-value {
            font-size: 2.1rem;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .stat-meta {
            color: var(--muted);
            font-size: .92rem;
        }

        .appointments-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 22px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
        }

        .filter-card {
            padding: 22px;
            position: sticky;
            top: calc(var(--topbar-height) + 22px);
        }

        .filter-card-header h2,
        .card-header h2 {
            margin: 0;
            font-size: 1.45rem;
        }

        .filter-card-header p,
        .card-header p {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .filter-section {
            margin-top: 22px;
        }

        .section-title {
            font-size: .84rem;
            color: #7c8ea2;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 12px;
        }

        .field-group {
            display: grid;
            gap: 8px;
        }

        .field-group label {
            font-weight: 700;
            font-size: .93rem;
            color: #3b4e62;
        }

        .field-group input,
        .field-group textarea,
        .field-group select {
            width: 100%;
            border: 1px solid var(--border);
            background: rgba(250, 252, 255, 0.8);
            border-radius: 14px;
            padding: 13px 14px;
            outline: none;
            color: var(--text);
            resize: vertical;
            transition: .2s;
        }

        .field-group input:focus,
        .field-group textarea:focus,
        .field-group select:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12);
            background: #fff;
        }

        .checkbox-list,
        .doctor-list {
            display: grid;
            gap: 10px;
        }

        .checkbox-list.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .checkbox-row,
        .doctor-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid transparent;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.6);
            transition: .2s;
        }

        .checkbox-row:hover,
        .doctor-row:hover {
            background: #fff;
            border-color: var(--border);
            box-shadow: var(--shadow-sm);
        }

        .checkbox-row input,
        .doctor-row input {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: var(--primary);
        }

        .doctor-row {
            justify-content: space-between;
            gap: 14px;
        }

        .doctor-row-copy {
            display: grid;
            gap: 3px;
        }

        .doctor-row-copy strong {
            font-size: .95rem;
        }

        .doctor-row-copy span {
            color: var(--muted);
            font-size: .84rem;
        }

        .doctor-row-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .doctor-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: .82rem;
            font-weight: 800;
            background: linear-gradient(145deg, #e2edf8, #d2e3f3);
            color: #386a98;
            flex-shrink: 0;
        }

        .clear-filters-btn {
            width: 100%;
            margin-top: 22px;
        }

        .schedule-column {
            display: grid;
            gap: 22px;
        }

        .card-header {
            padding: 22px 22px 0;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .calendar-toolbar {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .toolbar-group {
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .segmented {
            padding: 4px;
            background: rgba(243, 247, 251, 0.8);
            border: 1px solid var(--border);
            border-radius: 14px;
        }

        .segment-btn {
            border: 0;
            background: transparent;
            padding: 8px 12px;
            border-radius: 10px;
            font-weight: 700;
            color: #6a7c8d;
        }

        .segment-btn.active {
            background: #fff;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
        }

        .calendar-grid {
            padding: 22px;
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 12px;
        }

        .calendar-day {
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 18px;
            padding: 12px;
            min-height: 214px;
            background: rgba(255, 255, 255, 0.4);
            display: grid;
            align-content: start;
            gap: 10px;
            cursor: pointer;
            transition: .2s ease;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
        }

        .calendar-day:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .calendar-day.active {
            border-color: #98bce0;
            box-shadow: inset 0 0 0 2px rgba(46, 120, 183, 0.14);
            background: #fff;
        }

        .calendar-day.today .calendar-day-date {
            background: var(--primary);
            color: #fff;
        }

        .calendar-day-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .calendar-day-label {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #7b8d9f;
            font-weight: 800;
        }

        .calendar-day-date {
            min-width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: rgba(238, 244, 251, 0.8);
            color: #44627d;
            font-weight: 700;
            font-size: .84rem;
        }

        .calendar-events {
            display: grid;
            gap: 8px;
            align-content: start;
        }

        .calendar-event-chip {
            border-radius: 12px;
            padding: 9px 10px;
            display: grid;
            gap: 4px;
            border: 1px solid transparent;
            font-size: .82rem;
        }

        .calendar-event-chip.pending {
            background: rgba(255, 248, 234, 0.8);
            border-color: rgba(241, 223, 173, 0.6);
            color: #876317;
        }

        .calendar-event-chip.confirmed {
            background: rgba(238, 247, 255, 0.8);
            border-color: rgba(212, 231, 251, 0.6);
            color: #2f6c9c;
        }

        .calendar-event-chip.completed {
            background: rgba(239, 250, 243, 0.8);
            border-color: rgba(212, 238, 220, 0.6);
            color: #2f8751;
        }

        .calendar-event-chip.cancelled,
        .calendar-event-chip.rescheduled,
        .calendar-event-chip.no_show {
            background: rgba(255, 242, 242, 0.8);
            border-color: rgba(245, 213, 213, 0.6);
            color: #a95353;
        }

        .calendar-event-top {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
        }

        .table-wrap {
            overflow: auto;
            padding: 16px 22px 12px;
        }

        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 760px;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            text-align: left;
        }

        .schedule-table thead th {
            color: #7e8fa0;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: .78rem;
            font-weight: 800;
            border-bottom-width: 2px;
        }

        .schedule-table tbody tr {
            transition: .2s ease;
            background: rgba(255, 255, 255, 0.4);
        }

        .schedule-table tbody tr:hover {
            background: #fff;
        }

        .time-stack,
        .person-stack,
        .meta-stack {
            display: grid;
            gap: 4px;
        }

        .time-stack strong,
        .person-stack strong,
        .meta-stack strong {
            font-size: .96rem;
        }

        .person-stack span,
        .meta-stack span,
        .time-stack span {
            color: var(--muted);
            font-size: .84rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 96px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
            text-transform: capitalize;
            border: 1px solid transparent;
        }

        .status-pill.pending {
            background: #fff8ea;
            color: #946d18;
            border-color: #f0ddb0;
        }

        .status-pill.confirmed {
            background: #edf6ff;
            color: #2d6ea4;
            border-color: #d1e6fb;
        }

        .status-pill.completed {
            background: #eef9f2;
            color: #2f8a56;
            border-color: #d7eedf;
        }

        .status-pill.cancelled,
        .status-pill.rescheduled,
        .status-pill.no_show {
            background: #fff2f2;
            color: #b15353;
            border-color: #f4d3d3;
        }

        .action-dropdown {
            padding: 6px 10px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--border);
            color: var(--text);
            outline: none;
            cursor: pointer;
        }

        .action-dropdown:hover {
            border-color: var(--primary);
        }

        .empty-state {
            padding: 34px 22px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state strong {
            display: block;
            color: var(--text);
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(18, 28, 39, 0.45);
            backdrop-filter: blur(4px);
            display: grid;
            place-items: center;
            padding: 22px;
            z-index: 100;
        }

        .modal-card {
            width: min(980px, 100%);
            max-height: calc(100vh - 44px);
            overflow: auto;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 24px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.15);
        }

        .modal-header,
        .modal-footer {
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .modal-header h2 {
            margin: 0;
        }

        .modal-header p {
            margin: 6px 0 0;
            color: var(--muted);
        }

        #appointmentForm {
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .form-grid {
            display: grid;
            gap: 16px;
        }

        .form-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .full-span {
            grid-column: 1 / -1;
        }

        .toggle-card-grid {
            display: grid;
            gap: 12px;
        }

        .toggle-card {
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #fff;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            cursor: pointer;
            transition: .2s;
        }

        .toggle-card:hover {
            border-color: rgba(39, 117, 182, .28);
        }

        .toggle-card strong {
            display: block;
            font-size: 0.95rem;
        }

        .toggle-card span {
            color: var(--muted);
            font-size: .84rem;
        }

        .toggle-card input {
            display: none;
        }

        .toggle-switch {
            width: 52px;
            height: 30px;
            border-radius: 999px;
            background: #ced9e5;
            position: relative;
            transition: .3s ease;
            flex-shrink: 0;
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #fff;
            top: 3px;
            left: 3px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
            transition: .3s ease;
        }

        .toggle-card input:checked+.toggle-switch {
            background: var(--success);
        }

        .toggle-card input:checked+.toggle-switch::after {
            left: 25px;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            border-bottom: 0;
            margin: 0 -24px -24px;
            padding: 18px 24px;
            background: rgba(250, 252, 255, 0.8);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
        }

        .toast-stack {
            position: fixed;
            right: 22px;
            bottom: 22px;
            display: grid;
            gap: 10px;
            z-index: 150;
        }

        .toast {
            min-width: 280px;
            max-width: 380px;
            border-radius: 16px;
            padding: 14px 16px;
            box-shadow: var(--shadow);
            font-weight: 700;
            color: #fff;
            animation: slideInFade 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .toast.success {
            background: linear-gradient(135deg, #2aa966, #1d8c51);
        }

        .toast.error {
            background: linear-gradient(135deg, #cf6161, #b54d4d);
        }

        @keyframes slideInFade {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1080px) {
            .page-shell {}

            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .main-stage {
                margin-left: 0;
            }

            .topbar {
                position: static;
            }

            .content-shell {
                padding-top: 30px;
            }

            .appointments-layout {
                grid-template-columns: 1fr;
            }

            .filter-card {
                position: static;
            }
        }

        @media (max-width: 760px) {

            .stats-grid,
            .form-grid.two-col,
            .calendar-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Notifications from Backend -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="toast-stack" id="toastStackInit">
            <div class="toast success" onclick="this.remove()">
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="toast-stack" id="toastStackInit">
            <div class="toast error" onclick="this.remove()">
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); endif; ?>

    <div class="page-shell">

        <aside class="sidebar">
            <div>
                <div class="sidebar-brand">
                    <div class="brand-mark">
                        <svg viewBox="0 0 64 64">
                            <rect x="2" y="2" width="60" height="60" rx="16"></rect>
                            <path d="M13 34h10l4-10 8 22 6-16h10" fill="none" stroke="currentColor" stroke-width="4"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </div>
                    <div><strong><?= $hospital_name ?></strong></div>
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
                    <a href="appointments.php" class="nav-item active">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14Z" />
                            </svg></span>
                        <span>Appointments</span>
                        <span class="nav-arrow">›</span>
                    </a>
                    <a href="ehr_record.php" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5" />
                                <path d="M8 13h8M8 17h8M8 9h3" />
                            </svg></span>
                        <span>Clinical (EHR)</span>
                    </a>
                    <a href="<?= htmlspecialchars($billing_url) ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2Zm0 10H4V8h16ZM6 12h4" />
                            </svg></span>
                        <span>Billing &amp; NHIS</span>
                    </a>
                    <a href="<?= htmlspecialchars($laboratory_url) ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Zm2 2h2v4.12l4.98 8.63a1 1 0 0 1-.87 1.5H6.9a1 1 0 0 1-.87-1.5L11 8.12Z" />
                            </svg></span>
                        <span>Laboratory</span>
                    </a>
                    <?php if (strtolower(trim($role)) === 'administrator'): ?>
                        <a href="<?= htmlspecialchars($staff_url) ?>" class="nav-item">
                            <span class="nav-icon">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M12 8a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5Z" />
                                </svg>
                            </span>
                            <span>Staff Management</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="sidebar-footer">
                <button type="button" class="logout-btn" onclick="window.location.href='logout.php'">
                    <span aria-hidden="true">↪</span><span>Logout</span>
                </button>
                <div class="system-panel">
                    <div class="system-panel-label">System Status</div>
                    <div class="system-panel-row">
                        <span class="status-dot status-active"></span>
                        <span>Server Online</span>
                    </div>
                </div>
            </div>
        </aside>

        <div class="main-stage">
            <header class="topbar">
                <div class="search-wrap top-search-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                    </svg>
                    <input type="search" placeholder="Quick search..." autocomplete="off" />
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

            <main class="content-shell" id="contentShell">
                <section class="hero-row">
                    <div>
                        <h1>Appointment Scheduling</h1>
                        <p>Manage, filter, and track hospital-wide medical bookings seamlessly.</p>
                    </div>
                    <div class="hero-actions">
                        <button type="button" class="primary-btn"
                            onclick="document.getElementById('appointmentModalBackdrop').classList.remove('hidden')">
                            <svg viewBox="0 0 24 24">
                                <path d="M11 5h2v14h-2zM5 11h14v2H5z" />
                            </svg>
                            <span>New Appointment</span>
                        </button>
                    </div>
                </section>

                <section class="stats-grid">
                    <article class="stat-card accent-blue">
                        <div class="stat-label">Confirmed Today</div>
                        <div class="stat-value" id="confirmedTodayStat">0</div>
                        <div class="stat-meta">Appointments confirmed today</div>
                    </article>
                    <article class="stat-card accent-slate">
                        <div class="stat-label">Pending Action</div>
                        <div class="stat-value" id="pendingActionStat">0</div>
                        <div class="stat-meta">Requires staff follow-up</div>
                    </article>
                    <article class="stat-card accent-red">
                        <div class="stat-label">Cancelled / Rescheduled</div>
                        <div class="stat-value" id="cancelledRescheduledStat">0</div>
                        <div class="stat-meta">Recent changes within view</div>
                    </article>
                    <article class="stat-card accent-neutral">
                        <div class="stat-label">Total in View</div>
                        <div class="stat-value" id="totalWeeklyStat">0</div>
                        <div class="stat-meta">Appointments in current scope</div>
                    </article>
                </section>

                <section class="appointments-layout">
                    <aside class="glass-card filter-card" id="filterCard">
                        <div class="filter-card-header">
                            <div>
                                <h2>Filter View</h2>
                                <p>Refine by department, practitioner, or status.</p>
                            </div>
                        </div>
                        <div class="filter-section">
                            <div class="section-title">Hospital Departments</div>
                            <div class="checkbox-list" id="departmentFilters">
                                <!-- Rendered via JS -->
                            </div>
                        </div>
                        <div class="filter-section">
                            <div class="section-title">Appointment Status</div>
                            <div class="checkbox-list two-col" id="statusFilters">
                                <label class="checkbox-row"><input type="checkbox" value="pending"
                                        class="status-chk" /><span>Pending</span></label>
                                <label class="checkbox-row"><input type="checkbox" value="confirmed"
                                        class="status-chk" /><span>Confirmed</span></label>
                                <label class="checkbox-row"><input type="checkbox" value="completed"
                                        class="status-chk" /><span>Completed</span></label>
                                <label class="checkbox-row"><input type="checkbox" value="cancelled"
                                        class="status-chk" /><span>Cancelled</span></label>
                                <label class="checkbox-row"><input type="checkbox" value="rescheduled"
                                        class="status-chk" /><span>Rescheduled</span></label>
                                <label class="checkbox-row"><input type="checkbox" value="no_show"
                                        class="status-chk" /><span>No-show</span></label>
                            </div>
                        </div>
                        <div class="filter-section">
                            <div class="section-title">Appointment Type</div>
                            <select id="typeFilter">
                                <option value="">All appointment types</option>
                                <option value="general">General</option>
                                <option value="specialist">Specialist</option>
                                <option value="follow_up">Follow up</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="filter-section">
                            <div class="section-title">Medical Practitioners</div>
                            <div class="doctor-list" id="doctorFilters">
                                <!-- Rendered via JS -->
                            </div>
                        </div>
                        <button type="button" class="clear-filters-btn" onclick="clearFilters()">Clear All
                            Filters</button>
                    </aside>

                    <div class="schedule-column">
                        <section class="glass-card">
                            <div class="card-header">
                                <div>
                                    <h2 id="calendarRangeTitle">Week Schedule</h2>
                                    <p id="calendarRangeSubtitle">Weekly calendar view</p>
                                </div>
                                <div class="calendar-toolbar">
                                    <div class="toolbar-group">
                                        <button type="button" class="icon-pill" onclick="changeWeek(-7)">‹</button>
                                        <button type="button" class="icon-pill" onclick="resetToToday()">Today</button>
                                        <button type="button" class="icon-pill" onclick="changeWeek(7)">›</button>
                                    </div>
                                </div>
                            </div>
                            <div class="calendar-grid" id="calendarGrid">
                                <!-- Rendered via JS -->
                            </div>
                        </section>

                        <section class="glass-card list-card">
                            <div class="card-header">
                                <div>
                                    <h2 id="detailedListTitle">Detailed Schedule</h2>
                                    <p>Complete view of scheduled patient visits for selected date.</p>
                                </div>
                            </div>
                            <div class="table-wrap">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Patient &amp; ID</th>
                                            <th>Doctor &amp; Dept</th>
                                            <th>Contact</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appointmentsTableBody">
                                        <!-- Rendered via JS -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="empty-state hidden" id="appointmentsEmptyState">
                                <strong>No appointments found</strong>
                                <p>Try another date range or adjust the active filters.</p>
                            </div>
                        </section>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Appointment Modal -->
    <div class="modal-backdrop hidden" id="appointmentModalBackdrop" aria-hidden="true">
        <section class="modal-card appointment-modal" role="dialog" aria-modal="true">
            <div class="modal-header">
                <div>
                    <h2>New Appointment</h2>
                    <p>Schedule a new consultation for a patient.</p>
                </div>
                <button type="button" class="icon-btn subtle"
                    onclick="document.getElementById('appointmentModalBackdrop').classList.add('hidden')">✕</button>
            </div>

            <form method="POST" action="appointments.php" id="appointmentForm" novalidate>
                <input type="hidden" name="action" value="save">
                <div class="form-grid two-col" style="padding-top:20px;">
                    <div class="field-group full-span">
                        <label>Patient ID or Name <span>*</span></label>
                        <div class="field-group full-span">
                            <label>Patient <span>*</span></label>
                            <select name="patient_id">
                                <?php
                                $patients = $pdo->query("SELECT id, full_name FROM patients")->fetchAll();
                                foreach ($patients as $p):
                                    ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= $p['full_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                            $stmt = $pdo->query("SELECT id, full_name FROM patients ORDER BY full_name ASC");
                            while ($p = $stmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['full_name']) ?>
                                </option>
                            <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Department</label>
                        <select name="department_id" id="modalDepartmentSelect" required>
                            <option value="">Select department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Consulting Doctor <span>*</span></label>
                        <select name="doctor_id" id="modalDoctorSelect" required>
                            <option value="">Select doctor</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?> —
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Appointment Date <span>*</span></label>
                        <input type="date" name="appointment_date" required />
                    </div>
                    <div class="field-group">
                        <label>Preferred Time <span>*</span></label>
                        <input type="time" name="appointment_time" required />
                    </div>
                </div>
                <div class="form-grid two-col" style="align-items:start; margin-top:16px;">
                    <div class="field-group">
                        <label>Appointment Type <span>*</span></label>
                        <select name="appointment_type" required>
                            <option value="general">General</option>
                            <option value="specialist">Specialist</option>
                            <option value="follow_up">Follow up</option>
                            <option value="emergency">Emergency</option>
                            <option value="lab">Lab</option>
                            <option value="procedure">Procedure</option>
                        </select>
                    </div>
                    <div class="toggle-card-grid">
                        <label class="toggle-card">
                            <div>
                                <strong>Use NHIS Card</strong>
                                <span>National Health Insurance Scheme</span>
                            </div>
                            <input type="checkbox" name="use_nhis" />
                            <span class="toggle-switch"></span>
                        </label>
                        <label class="toggle-card">
                            <div>
                                <strong>Payment Required</strong>
                                <span>Upfront deposit for consultation</span>
                            </div>
                            <input type="checkbox" name="payment_required" checked />
                            <span class="toggle-switch"></span>
                        </label>
                    </div>
                </div>
                <div class="field-group full-span" style="margin-top:16px;">
                    <label>Reason for Visit</label>
                    <textarea name="reason_for_visit" rows="3"
                        placeholder="Enter brief reason for visit or clinical instructions..."></textarea>
                </div>
                <div class="field-group full-span">
                    <label>Patient <span>*</span></label>
                    <select name="patient_id" required>
                        <option value="">Select patient</option>

                        <?php
                        $stmt = $pdo->query("SELECT id, full_name FROM patients ORDER BY full_name ASC");
                        while ($p = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['full_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="secondary-btn"
                        onclick="document.getElementById('appointmentModalBackdrop').classList.add('hidden')">Cancel</button>
                    <button type="submit" class="primary-btn">Schedule Appointment</button>
                </div>
            </form>
        </section>
    </div>

    <!-- Toast Container for Ajax Responses -->
    <div class="toast-stack" id="ajaxToastStack"></div>

    <script>
        // Automatically dismiss initial toasts
        setTimeout(() => {
            const initToasts = document.querySelectorAll('#toastStackInit .toast');
            initToasts.forEach(t => {
                t.style.opacity = '0';
                t.style.transform = 'translateY(10px)';
                t.style.transition = '0.3s ease';
                setTimeout(() => t.remove(), 300);
            });
        }, 4000);

        // --- HYDRATION ---
        const SERVER_DATA = {
            departments: <?= json_encode($departments_js) ?>,
            doctors: <?= json_encode($doctors_js) ?>,
            appointments: <?= json_encode($appointments) ?>
        };

        const state = {
            weekStart: startOfWeek(new Date()),
            selectedDate: toIsoDate(new Date()),
            filterDepts: new Set(),
            filterDoctors: new Set(),
            filterStatuses: new Set(),
            filterType: ''
        };

        // --- UTILS ---
        function startOfWeek(d) {
            const date = new Date(d);
            const day = date.getDay();
            const diff = (day === 0 ? -6 : 1) - day; // Monday is first day
            date.setDate(date.getDate() + diff);
            date.setHours(0, 0, 0, 0);
            return date;
        }
        function toIsoDate(d) {
            const date = new Date(d);
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }
        function addDays(d, amount) {
            const date = new Date(d);
            date.setDate(date.getDate() + amount);
            return date;
        }
        function escapeHtml(text) {
            return (text || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }
        function showAjaxToast(msg, isError) {
            const stack = document.getElementById('ajaxToastStack');
            const t = document.createElement('div');
            t.className = 'toast ' + (isError ? 'error' : 'success');
            t.innerText = msg;
            stack.appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0'; t.style.transition = '.3s';
                setTimeout(() => t.remove(), 300);
            }, 3500);
        }

        // --- INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', () => {
            renderSidebarFilters();
            bindFilterEvents();
            renderUI();
        });

        function renderSidebarFilters() {
            const dList = document.getElementById('departmentFilters');
            SERVER_DATA.departments.forEach(d => {
                dList.innerHTML += `<label class="checkbox-row">
                  <input type="checkbox" value="${d.id}" class="dept-chk"/>
                  <span>${escapeHtml(d.name)}</span>
              </label>`;
            });

            const docList = document.getElementById('doctorFilters');
            SERVER_DATA.doctors.forEach(d => {
                const ini = d.full_name.substring(0, 2).toUpperCase();
                docList.innerHTML += `<label class="doctor-row">
                  <div class="doctor-row-left">
                      <div class="doctor-avatar">${escapeHtml(ini)}</div>
                      <div class="doctor-row-copy">
                          <strong>${escapeHtml(d.full_name)}</strong>
                          <span>${escapeHtml(d.department)}</span>
                      </div>
                  </div>
                  <input type="checkbox" value="${d.id}" class="doc-chk"/>
              </label>`;
            });
        }

        function bindFilterEvents() {
            document.querySelectorAll('.dept-chk').forEach(c => c.addEventListener('change', e => {
                if (e.target.checked) state.filterDepts.add(e.target.value);
                else state.filterDepts.delete(e.target.value);
                renderUI();
            }));
            document.querySelectorAll('.doc-chk').forEach(c => c.addEventListener('change', e => {
                if (e.target.checked) state.filterDoctors.add(e.target.value);
                else state.filterDoctors.delete(e.target.value);
                renderUI();
            }));
            document.querySelectorAll('.status-chk').forEach(c => c.addEventListener('change', e => {
                if (e.target.checked) state.filterStatuses.add(e.target.value);
                else state.filterStatuses.delete(e.target.value);
                renderUI();
            }));
            document.getElementById('typeFilter').addEventListener('change', e => {
                state.filterType = e.target.value;
                renderUI();
            });
        }

        window.clearFilters = function () {
            document.querySelectorAll('#filterCard input[type="checkbox"]').forEach(c => c.checked = false);
            document.getElementById('typeFilter').value = '';
            state.filterDepts.clear();
            state.filterDoctors.clear();
            state.filterStatuses.clear();
            state.filterType = '';
            renderUI();
        };

        // --- LOGIC ---
        function getFilteredAppointments() {
            return SERVER_DATA.appointments.filter(a => {
                if (state.filterDepts.size && !state.filterDepts.has(String(a.department_id))) return false;
                if (state.filterDoctors.size && !state.filterDoctors.has(String(a.doctor_uuid))) return false;
                if (state.filterStatuses.size && !state.filterStatuses.has(a.status.toLowerCase())) return false;
                if (state.filterType && a.appointment_type !== state.filterType) return false;
                return true;
            });
        }

        window.changeWeek = function (amount) {
            state.weekStart = addDays(state.weekStart, amount);
            renderUI();
        };

        window.resetToToday = function () {
            state.weekStart = startOfWeek(new Date());
            state.selectedDate = toIsoDate(new Date());
            renderUI();
        };

        window.selectDate = function (iso) {
            state.selectedDate = iso;
            renderUI();
        };

        window.updateStatus = function (dropdown, recordId) {
            const val = dropdown.value;
            if (!val) return;

            let formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('appointment_record_id', recordId);
            formData.append('new_status', val);

            fetch('appointments.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    showAjaxToast(`Appointment marked as ${val}`, false);
                    // Quick UI mutate for fast react
                    SERVER_DATA.appointments.forEach(a => { if (a.id == recordId) a.status = val; });
                    renderUI();
                } else {
                    showAjaxToast(`Error: ${res.error}`, true);
                }
            }).catch(e => showAjaxToast("Network Error", true));
        };

        function renderUI() {
            const filteredItems = getFilteredAppointments();

            // 1. Render Calendar Grid (Weekly Window based on state.weekStart)
            const weekStartTitle = state.weekStart.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
            const weekEnd = addDays(state.weekStart, 6);
            const weekEndTitle = weekEnd.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            document.getElementById('calendarRangeTitle').innerText = `${weekStartTitle} - ${weekEndTitle}`;

            const cGrid = document.getElementById('calendarGrid');
            cGrid.innerHTML = '';

            const todayIso = toIsoDate(new Date());

            let weeklyCount = 0;
            let confirmedToday = 0;
            let pendingAction = 0;
            let cancelledRescheduled = 0;

            // Process weekly stats internally
            const weekWindowItems = filteredItems.filter(a => {
                const d = new Date(a.date);
                return d >= state.weekStart && d <= weekEnd;
            });

            Object.values(filteredItems).forEach(a => {
                if (a.date === todayIso && a.status === 'pending') pendingAction++;
                if (a.date === todayIso && a.status === 'confirmed') confirmedToday++;
                if (a.status === 'cancelled' || a.status === 'rescheduled') cancelledRescheduled++;
            });

            for (let i = 0; i < 7; i++) {
                const currentD = addDays(state.weekStart, i);
                const curIso = toIsoDate(currentD);
                const isToday = curIso === todayIso;
                const isActive = curIso === state.selectedDate;

                const dayItems = filteredItems.filter(a => a.date === curIso);
                weeklyCount += dayItems.length;

                let eventsHtml = '';
                if (dayItems.length === 0) {
                    eventsHtml = '<div style="color:var(--muted); font-size:0.84rem;">No appointments</div>';
                } else {
                    dayItems.slice(0, 4).forEach(a => {
                        eventsHtml += `<div class="calendar-event-chip ${escapeHtml(a.status)}">
                          <div class="calendar-event-top">
                              <span>${escapeHtml(a.time)}</span>
                          </div>
                          <div style="font-size:0.8rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                              ${escapeHtml(a.patient_name)}
                          </div>
                      </div>`;
                    });
                    if (dayItems.length > 4) {
                        eventsHtml += `<div style="color:var(--muted); font-size:0.84rem; text-align:center;">+${dayItems.length - 4} more</div>`;
                    }
                }

                const labelDay = currentD.toLocaleDateString('en-US', { weekday: 'short' });
                const numDay = currentD.getDate();

                cGrid.innerHTML += `<article class="calendar-day ${isToday ? 'today' : ''} ${isActive ? 'active' : ''}" onclick="selectDate('${curIso}')">
                  <div class="calendar-day-head">
                      <div class="calendar-day-label">${escapeHtml(labelDay)}</div>
                      <div class="calendar-day-date">${escapeHtml(numDay)}</div>
                  </div>
                  <div class="calendar-events">
                      ${eventsHtml}
                  </div>
              </article>`;
            }

            // Update Stats
            document.getElementById('totalWeeklyStat').innerText = weeklyCount;
            document.getElementById('confirmedTodayStat').innerText = confirmedToday;
            document.getElementById('pendingActionStat').innerText = pendingAction;
            document.getElementById('cancelledRescheduledStat').innerText = cancelledRescheduled;

            // 2. Render Detailed List for selectedDate
            const listTitleDate = new Date(state.selectedDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            document.getElementById('detailedListTitle').innerText = `Detailed List: ${listTitleDate}`;

            const tbody = document.getElementById('appointmentsTableBody');
            const emptyState = document.getElementById('appointmentsEmptyState');
            tbody.innerHTML = '';

            const todaysList = filteredItems.filter(a => a.date === state.selectedDate);

            if (todaysList.length === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
                todaysList.forEach(a => {
                    let selOptions = ['pending', 'confirmed', 'completed', 'rescheduled', 'cancelled']
                        .map(o => `<option value="${o}" ${a.status.toLowerCase() === o ? 'selected' : ''}>${o.charAt(0).toUpperCase() + o.slice(1)}</option>`)
                        .join('');

                    tbody.innerHTML += `<tr>
                      <td>
                          <div class="time-stack">
                              <strong>${escapeHtml(a.time)}</strong>
                              <span style="text-transform:capitalize;">${escapeHtml(a.appointment_type)}</span>
                          </div>
                      </td>
                      <td>
                          <div class="person-stack">
                              <strong>${escapeHtml(a.patient_name)}</strong>
                              <span>${escapeHtml(a.patient_id)}</span>
                          </div>
                      </td>
                      <td>
                          <div class="meta-stack">
                              <strong>${escapeHtml(a.doctor_name)}</strong>
                              <span>${escapeHtml(a.department_name)}</span>
                          </div>
                      </td>
                      <td>
                          <div style="font-weight:600; padding-top:4px;">${escapeHtml(a.patient_phone || 'No contact')}</div>
                      </td>
                      <td>
                          <span class="status-pill ${escapeHtml(a.status.toLowerCase())}">${escapeHtml(a.status)}</span>
                      </td>
                      <td>
                          <select class="action-dropdown" onchange="updateStatus(this, ${a.id})">
                              <option disabled>Change Status</option>
                              ${selOptions}
                          </select>
                      </td>
                  </tr>`;
                });
            }
        }
    </script>
</body>

</html>