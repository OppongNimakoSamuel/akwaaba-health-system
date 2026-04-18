<?php
// ============================================================
//  Akwaaba Health - Dashboard (XAMPP / PHP / MySQL version)
//  FIX: removed duplicate session_start(), require, and $search_query declarations
//  FIX: moved all PHP logic before any HTML output
// ============================================================

session_start();         // FIX: only one session_start(), at the very top
require 'db.php';        // FIX: only one require

$pdo = getDB();

// --- Auth gate -------------------------------------------------
// FIX: session key now matches login.php which sets $_SESSION['user']
if (!isset($_SESSION['user'])) {
    // Demo fallback — remove for production and uncomment redirect
    $_SESSION['user'] = 'DR_KOFI';
    $_SESSION['akwaaba_user'] = [
        'full_name' => 'Dr. Kofi Mensah',
        'role' => 'administrator',
    ];
    // header('Location: login.php'); exit;
}

$user = $_SESSION['akwaaba_user'] ?? ['full_name' => 'Dr. Kofi Mensah', 'role' => 'administrator'];
$full_name = htmlspecialchars($user['full_name'] ?? 'Dr. Kofi Mensah');
$role = htmlspecialchars($user['role'] ?? 'administrator');
$names = array_filter(explode(' ', trim($full_name)));
$initials = '';
foreach (array_slice($names, 0, 2) as $n) {
    if (isset($n[0]))
        $initials .= $n[0];
}
$initials = strtoupper($initials ?: 'U');

// --- Config ----------------------------------------------------
$hospital_name = 'Akwaaba Health';
$logout_url = 'logout.php';
$patients_url = 'patients.php';
$appointments_url = 'appointments.php';
$clinical_url = 'ehr_record.php';
$billing_url = 'billing.php';
$laboratory_url = 'laboratory.php';
$staff_url = 'staff_management.php';
$audit_logs_url = 'staff_audit.php';
$report_url = 'daily_report.php';
$live_vitals_url = '#';
$system_status = 'online';
$ghs_server = 'active';

// --- Search (FIX: declare $search_query once, escape wildcards properly) ---
$search_results = [];
$search_query = trim($_GET['q'] ?? '');

if (!empty($search_query)) {
    // FIX: escape LIKE wildcards so a bare % doesn't return every row
    $safe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search_query);
    $q = "%$safe%";

    $stmt = $conn->prepare("
        SELECT id, full_name, phone, address
        FROM patients
        WHERE full_name LIKE ?
           OR CAST(id AS CHAR) LIKE ?
           OR phone LIKE ?
        LIMIT 10
    ");
    $stmt->bind_param("sss", $q, $q, $q);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- Database metrics ------------------------------------------
$total_patients = (int) ($conn->query("SELECT COUNT(*) AS total FROM patients")->fetch_assoc()['total'] ?? 0);

$appointments_today = (int) $conn->query("
    SELECT COUNT(*) AS total FROM appointments
    WHERE DATE(appointment_date) = CURDATE()
")->fetch_assoc()['total'];

$confirmed_today = (int) $conn->query("
    SELECT COUNT(*) AS total FROM appointments
    WHERE DATE(appointment_date) = CURDATE() AND status = 'confirmed'
")->fetch_assoc()['total'];

$lab_tests_pending = (int) $conn->query("
    SELECT COUNT(*) AS total FROM lab_tests WHERE status = 'pending'
")->fetch_assoc()['total'];

$lab_urgent_cases = (int) $conn->query("
    SELECT COUNT(*) AS total FROM lab_tests WHERE priority = 'urgent'
")->fetch_assoc()['total'];

$lab_abnormal = (int) $conn->query("
    SELECT COUNT(*) AS total FROM lab_tests WHERE result_status = 'abnormal'
")->fetch_assoc()['total'];

$revenue_this_month = (int) ($conn->query("
    SELECT COALESCE(SUM(amount),0) AS total FROM payments
    WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['total'] ?? 0);

$metrics = [
    'total_patients' => $total_patients,
    'patient_growth_percent' => 0,
    'appointments_today' => $appointments_today,
    'confirmed_today' => $confirmed_today,
    'slots_remaining' => 56,
    'revenue_this_month_ghs' => $revenue_this_month,
    'lab_tests_pending' => $lab_tests_pending,
    'lab_urgent_cases' => $lab_urgent_cases,
    'lab_abnormal' => $lab_abnormal,
];

$revenue_chart = [
    ['month' => 'Jan', 'revenue' => 0],
    ['month' => 'Feb', 'revenue' => 0],
    ['month' => 'Mar', 'revenue' => 0],
    ['month' => 'Apr', 'revenue' => 0],
    ['month' => 'May', 'revenue' => 0],
    ['month' => 'Jun', 'revenue' => 0],
];

// --- Staffing --------------------------------------------------
$doctors = (int) $conn->query("SELECT COUNT(*) AS total FROM staff WHERE role='doctor'")->fetch_assoc()['total'];
$doctors_active = (int) $conn->query("SELECT COUNT(*) AS total FROM staff WHERE role='doctor' AND is_active=1")->fetch_assoc()['total'];
$nurses = (int) $conn->query("SELECT COUNT(*) AS total FROM staff WHERE role='nurse'")->fetch_assoc()['total'];
$nurses_active = (int) $conn->query("SELECT COUNT(*) AS total FROM staff WHERE role='nurse' AND is_active=1")->fetch_assoc()['total'];
$labs = (int) $conn->query("SELECT COUNT(*) AS total FROM staff WHERE role='lab_technician'")->fetch_assoc()['total'];
$labs_active = (int) $conn->query("SELECT COUNT(*) AS total FROM staff WHERE role='lab_technician' AND is_active=1")->fetch_assoc()['total'];

$staffing = [
    'doctors' => ['total' => $doctors, 'on_duty' => $doctors_active],
    'nurses' => ['total' => $nurses, 'on_duty' => $nurses_active],
    'lab_technicians' => ['total' => $labs, 'on_duty' => $labs_active],
];

// --- Recent audit activity -------------------------------------
// FIX: aliased created_at as 'timestamp' so $item['timestamp'] works in the table below
$recent_activity = $conn->query("
    SELECT
        a.id,
        p.full_name  AS patient_name,
        a.region,
        a.department,
        a.action,
        a.resource_type,
        a.created_at AS timestamp
    FROM audit_logs a
    LEFT JOIN patients p ON a.patient_id = p.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($hospital_name) ?> | Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --panel-soft: #f8fafc;
            --border: #e6ebf2;
            --shadow: 0 16px 40px rgba(15, 23, 42, .07);
            --text: #1b2430;
            --muted: #6b7789;
            --primary: #2775b6;
            --primary-700: #1d629c;
            --success: #2db36b;
            --warning: #d9a441;
            --danger: #d54e58;
            --blue-soft: #e8f2fb;
            --indigo-soft: #eef2ff;
            --sky-soft: #edf6ff;
            --amber-soft: #fff5df;
            --green-soft: #e9f8ef;
            --sidebar-width: 250px;
            --radius: 20px;
            --radius-sm: 14px;
            --transition: 180ms ease;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%;
            overflow-y: auto;
            margin: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text)
        }

        a {
            color: inherit;
            text-decoration: none
        }

        button,
        input {
            font: inherit
        }

        button {
            border: 0;
            background: none;
            cursor: pointer
        }

        svg {
            width: 100%;
            height: 100%;
            fill: currentColor
        }

        .dashboard-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr
        }

        .sidebar {
            background: linear-gradient(180deg, #f9fbfe, #f2f5fa);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px 18px;
            position: sticky;
            top: 0;
            height: 100vh;
            width: var(--sidebar-width);
            overflow: hidden
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 8px 20px;
            margin-bottom: 8px;
            border-bottom: 1px solid var(--border)
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            color: var(--primary);
            flex-shrink: 0
        }

        .sidebar-brand strong {
            font-size: 1.35rem;
            color: var(--primary-700)
        }

        .nav-list {
            display: grid;
            gap: 8px
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            color: #516070;
            border-radius: 14px;
            transition: background var(--transition), color var(--transition), transform var(--transition)
        }

        .nav-item:hover,
        .nav-item:focus-visible {
            background: #eff5fb;
            color: var(--primary-700);
            transform: translateX(2px);
            outline: none
        }

        .nav-item.active {
            background: #e6eef7;
            color: var(--primary-700);
            font-weight: 700
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            opacity: .85
        }

        .nav-arrow {
            margin-left: auto;
            font-size: 1.25rem;
            line-height: 1
        }

        .sidebar-footer {
            display: grid;
            gap: 16px;
            padding-top: 24px
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--danger);
            font-weight: 600;
            padding: 12px 14px;
            border-radius: 14px;
            transition: background var(--transition)
        }

        .logout-btn:hover,
        .logout-btn:focus-visible {
            background: rgba(213, 78, 88, .08);
            outline: none
        }

        .system-panel {
            background: #eef4fb;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            display: grid;
            gap: 10px
        }

        .system-panel-label {
            text-transform: uppercase;
            letter-spacing: .09em;
            font-size: .72rem;
            color: var(--muted);
            font-weight: 700
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .95rem;
            font-weight: 600
        }

        .subtle-row {
            font-size: .84rem;
            color: var(--muted);
            font-weight: 500
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #c6cfdb;
            flex-shrink: 0
        }

        .status-active {
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(45, 179, 107, .16)
        }

        .status-warning {
            background: var(--warning);
            box-shadow: 0 0 0 4px rgba(217, 164, 65, .16)
        }

        .status-offline {
            background: var(--danger);
            box-shadow: 0 0 0 4px rgba(213, 78, 88, .16)
        }

        .main-stage {
            display: flex;
            flex-direction: column;
            min-width: 0
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 26px;
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 20
        }

        .search-wrap {
            position: relative;
            flex: 1 1 540px;
            max-width: 520px
        }

        .search-wrap input {
            width: 100%;
            height: 50px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #fbfcfe;
            padding: 0 18px 0 48px;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition)
        }

        .search-wrap input:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12)
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 15px;
            width: 20px;
            height: 20px;
            color: #94a1b2
        }

        .search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 8px;
            display: grid;
            gap: 6px;
            max-height: 360px;
            overflow-y: auto;
            z-index: 30
        }

        .search-result-item {
            padding: 12px 14px;
            border-radius: 14px;
            display: grid;
            gap: 4px
        }

        .search-result-item:hover {
            background: #f4f8fc
        }

        .search-result-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-weight: 700
        }

        .search-result-meta {
            color: var(--muted);
            font-size: .85rem;
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .hidden {
            display: none !important
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px
        }

        .icon-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #525e6c;
            border: 1px solid var(--border);
            background: var(--panel)
        }

        .icon-btn svg {
            width: 20px;
            height: 20px
        }

        .profile-chip {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 18px;
            border-left: 1px solid var(--border)
        }

        .profile-copy {
            display: grid;
            gap: 2px;
            text-align: right
        }

        .profile-copy strong {
            font-size: .96rem
        }

        .profile-copy span {
            color: var(--muted);
            font-size: .84rem;
            text-transform: capitalize
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #deedf8, #bfdaf1);
            color: var(--primary-700);
            font-weight: 800
        }

        .presence-dot {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 3px #fff
        }

        .dashboard-main {
            padding: 30px 26px 28px;
            display: grid;
            gap: 24px;
            min-height: calc(100vh - 80px)
        }

        .hero-row,
        .panel-head.between,
        .panel-head.align-end {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px
        }

        .hero-row h1,
        .panel-head h2 {
            margin: 0;
            line-height: 1.1
        }

        .hero-row h1 {
            font-size: clamp(2rem, 2vw, 2.4rem);
            margin-bottom: 8px
        }

        .hero-row p,
        .panel-head p {
            margin: 0;
            color: var(--muted)
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap
        }

        .primary-btn,
        .secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 46px;
            padding: 0 16px;
            border-radius: 14px;
            font-weight: 700;
            transition: transform var(--transition), box-shadow var(--transition)
        }

        .primary-btn {
            background: linear-gradient(180deg, #2e82c6, #276fac);
            color: #fff;
            box-shadow: 0 12px 24px rgba(39, 117, 182, .22)
        }

        .primary-btn:hover {
            transform: translateY(-1px)
        }

        .secondary-btn {
            background: var(--panel);
            border: 1px solid var(--border);
            color: #495869
        }

        .secondary-btn:hover {
            background: #f8fbff
        }

        .primary-btn svg,
        .secondary-btn svg {
            width: 18px;
            height: 18px
        }

        .subtle-btn {
            min-height: 40px;
            padding: 0 14px
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px
        }

        .metric-card,
        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow)
        }

        .metric-card {
            padding: 20px 20px 18px;
            display: grid;
            gap: 14px
        }

        .highlight-card {
            background: linear-gradient(180deg, #f5f8ff, #eef4fd)
        }

        .metric-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            color: #5f6d7c;
            font-weight: 600
        }

        .metric-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: var(--primary)
        }

        .metric-icon svg {
            width: 20px;
            height: 20px
        }

        .metric-icon.blue {
            background: var(--blue-soft)
        }

        .metric-icon.indigo {
            background: var(--indigo-soft)
        }

        .metric-icon.sky {
            background: var(--sky-soft)
        }

        .metric-icon.amber {
            background: var(--amber-soft);
            color: #b88825
        }

        .metric-value {
            font-size: clamp(1.8rem, 2vw, 2.25rem);
            line-height: 1
        }

        .metric-meta {
            color: var(--muted);
            font-size: .93rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap
        }

        .split-meta {
            justify-content: space-between
        }

        .metric-trend {
            font-weight: 700
        }

        .metric-trend.positive {
            color: var(--success)
        }

        .metric-trend.negative {
            color: var(--danger)
        }

        .metric-trend.neutral {
            color: var(--muted)
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, .95fr);
            gap: 18px
        }

        .panel {
            padding: 22px
        }

        .panel-head {
            display: grid;
            gap: 6px;
            margin-bottom: 20px
        }

        .panel-badge {
            align-self: flex-start;
            background: #f8fbff;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px 12px;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700
        }

        .chart-wrap {
            display: grid;
            grid-template-columns: 66px minmax(0, 1fr);
            gap: 12px;
            align-items: stretch;
            min-height: 360px
        }

        .chart-axis-left {
            display: grid;
            justify-items: end;
            align-content: stretch;
            color: #9aa6b4;
            font-size: .86rem;
            padding-top: 16px
        }

        .chart-axis-left span {
            display: flex;
            align-items: center;
            height: 20%
        }

        .chart-canvas {
            position: relative;
            background: linear-gradient(to top, transparent 19.6%, rgba(154, 166, 180, .16) 20%, transparent 20.4%, transparent 39.6%, rgba(154, 166, 180, .16) 40%, transparent 40.4%, transparent 59.6%, rgba(154, 166, 180, .16) 60%, transparent 60.4%, transparent 79.6%, rgba(154, 166, 180, .16) 80%, transparent 80.4%);
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(230, 235, 242, .9);
            min-height: 320px
        }

        #revenueChartSvg {
            position: absolute;
            inset: 0
        }

        .chart-months {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 10px;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            color: #7a8796;
            font-size: .88rem;
            text-align: center;
            padding: 0 18px
        }

        .staff-list {
            display: grid;
            gap: 16px
        }

        .staff-item {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px 0
        }

        .staff-item+.staff-item {
            border-top: 1px solid var(--border)
        }

        .staff-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: grid;
            place-items: center
        }

        .staff-icon.blue {
            background: var(--blue-soft);
            color: #4a7cc6
        }

        .staff-icon.green {
            background: var(--green-soft);
            color: #40a76d
        }

        .staff-icon.amber {
            background: var(--amber-soft);
            color: #bf8f2f
        }

        .staff-icon svg {
            width: 22px;
            height: 22px
        }

        .staff-copy {
            display: grid;
            gap: 3px
        }

        .staff-copy strong {
            font-size: 1rem
        }

        .staff-copy span {
            color: var(--muted);
            font-size: .88rem
        }

        .staff-metrics {
            text-align: right;
            display: grid;
            gap: 4px
        }

        .staff-total {
            font-size: 1.45rem;
            font-weight: 800
        }

        .staff-duty {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            background: #f5f7fb;
            color: #697889;
            font-size: .82rem;
            font-weight: 700
        }

        .target-box {
            margin-top: 20px;
            padding: 18px;
            background: #fafcff;
            border: 1px dashed #d7e0eb;
            border-radius: 18px;
            display: grid;
            gap: 10px
        }

        .target-head {
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: .76rem;
            color: var(--muted);
            font-weight: 800;
            text-align: center
        }

        .progress-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e9eff5;
            overflow: hidden
        }

        .progress-bar {
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #2e82c6, #4ca2df);
            transition: width 300ms ease
        }

        .target-copy {
            text-align: center;
            color: #667485;
            font-size: .86rem;
            font-weight: 600
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 18px
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 880px
        }

        .activity-table th,
        .activity-table td {
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
            font-size: .92rem
        }

        .activity-table thead th {
            background: #f9fbfd;
            color: #687687;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .04em
        }

        .activity-table tbody tr:hover {
            background: #fbfdff
        }

        .region-pill,
        .resource-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            white-space: nowrap
        }

        .region-pill {
            background: #f6f8fb;
            color: #667587
        }

        .resource-pill {
            background: #eef5fd;
            color: #4771a3
        }

        .empty-cell {
            padding: 26px 14px !important;
            text-align: center !important;
            color: var(--muted)
        }

        .page-footer {
            padding: 18px 26px 24px;
            border-top: 1px solid var(--border);
            color: #6a7787;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
            font-size: .88rem;
            background: rgba(255, 255, 255, .65)
        }

        @media(max-width:1200px) {
            .metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .content-grid {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:960px) {
            .dashboard-shell {
                display: block
            }

            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000
            }

            .main-stage {
                margin-left: var(--sidebar-width)
            }
        }

        /* FIX: closed the unclosed @media(max-width:768px) block properly */
        @media(max-width:768px) {

            .topbar,
            .dashboard-main,
            .page-footer {
                padding-left: 16px;
                padding-right: 16px
            }

            .topbar {
                flex-direction: column;
                align-items: stretch
            }

            .search-wrap {
                max-width: none
            }

            .topbar-actions {
                justify-content: space-between
            }

            .hero-row,
            .panel-head.between,
            .panel-head.align-end {
                flex-direction: column;
                align-items: stretch
            }

            .metric-grid {
                grid-template-columns: 1fr
            }

            .chart-wrap {
                grid-template-columns: 1fr
            }

            .profile-chip {
                width: 100%;
                justify-content: flex-end
            }
        }
    </style>
</head>

<body data-hospital-name="<?= htmlspecialchars($hospital_name) ?>" data-user-name="<?= $full_name ?>"
    data-user-role="<?= $role ?>" data-system-status="<?= htmlspecialchars($system_status) ?>"
    data-ghs-server="<?= htmlspecialchars($ghs_server) ?>">

    <div class="dashboard-shell">

        <!-- ===================== SIDEBAR ===================== -->
        <aside class="sidebar" aria-label="Primary navigation">
            <div class="sidebar-brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 64 64">
                        <rect x="2" y="2" width="60" height="60" rx="16"></rect>
                        <path d="M13 34h10l4-10 8 22 6-16h10" fill="none" stroke="currentColor" stroke-width="4"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
                <div><strong><?= htmlspecialchars($hospital_name) ?></strong></div>
            </div>

            <nav class="nav-list">
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path d="M4 13h6V4H4Zm10 7h6V11h-6ZM4 20h6v-5H4Zm10-9h6V4h-6Z" />
                        </svg></span>
                    <span>Dashboard</span><span class="nav-arrow">›</span>
                </a>
                <a href="<?= htmlspecialchars($patients_url) ?>" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M16 11c1.66 0 3-1.79 3-4s-1.34-4-3-4-3 1.79-3 4 1.34 4 3 4Zm-8 0c1.66 0 3-1.79 3-4S9.66 3 8 3 5 4.79 5 7s1.34 4 3 4Zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V20h6v-3.5c0-2.33-4.67-3.5-7-3.5Z" />
                        </svg></span>
                    <span>Patients</span>
                </a>
                <a href="<?= htmlspecialchars($appointments_url) ?>" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14Z" />
                        </svg></span>
                    <span>Appointments</span>
                </a>
                <a href="<?= htmlspecialchars($clinical_url) ?>" class="nav-item">
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
                <form method="POST" action="<?= htmlspecialchars($logout_url) ?>">
                    <button type="submit" class="logout-btn">
                        <span aria-hidden="true">↪</span><span>Logout</span>
                    </button>
                </form>
                <div class="system-panel">
                    <div class="system-panel-label">System Status</div>
                    <div class="system-panel-row">
                        <span class="status-dot status-active"></span>
                        <span>Server <?= ucfirst(htmlspecialchars($system_status)) ?></span>
                    </div>
                    <div class="system-panel-row subtle-row">
                        <span class="status-dot status-active"></span>
                        <span>GHS Server: <?= ucfirst(htmlspecialchars($ghs_server)) ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ===================== MAIN STAGE ===================== -->
        <div class="main-stage">

            <!-- TOPBAR -->
            <header class="topbar">
                <form class="search-wrap" method="get">
                    <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                    </svg>
                    <input type="search" name="q" placeholder="Search patient by Name, ID or NHIS..."
                        value="<?= htmlspecialchars($search_query) ?>" minlength="1" autocomplete="off">

                    <!-- FIX: search results are now inside the form/search-wrap so they position correctly, and rendered after all PHP logic -->
                    <?php if (!empty($search_query)): ?>
                        <div class="search-results">
                            <?php if (count($search_results) > 0): ?>
                                <?php foreach ($search_results as $p): ?>
                                    <div class="search-result-item">
                                        <div class="search-result-top">
                                            <span><?= htmlspecialchars($p['full_name']) ?></span>
                                            <small>ID: <?= htmlspecialchars((string) $p['id']) ?></small>
                                        </div>
                                        <div class="search-result-meta">
                                            <span><?= htmlspecialchars($p['phone'] ?? 'No phone') ?></span>
                                            <span><?= htmlspecialchars($p['address'] ?? 'No address') ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="search-result-item">No patient found</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>

                <div class="topbar-actions">
                    <button type="button" class="icon-btn" title="Notifications">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5L3 18v1h18v-1Zm-2 1H7v-6a5 5 0 1 1 10 0Z" />
                        </svg>
                    </button>
                    <div class="profile-chip">
                        <div class="profile-copy">
                            <strong><?= $full_name ?></strong>
                            <span><?= $role ?></span>
                        </div>
                        <div class="profile-avatar"><?= $initials ?></div>
                        <span class="presence-dot"></span>
                    </div>
                </div>
            </header>

            <!-- DASHBOARD MAIN -->
            <main class="dashboard-main">

                <section class="hero-row">
                    <div>
                        <h1>Hospital Dashboard</h1>
                        <p>Welcome back, <strong><?= $full_name ?></strong>. Here is the daily summary for
                            <strong><?= date('l, F j, Y') ?></strong>.
                        </p>
                    </div>
                    <div class="hero-actions">
                        <a href="<?= htmlspecialchars($report_url) ?>" class="secondary-btn">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M17 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm0 16H7V5h10Zm-8-9h6v2H9Zm0 4h6v2H9Zm0-8h6v2H9Z" />
                            </svg>
                            <span>Generate Daily Report</span>
                        </a>
                        <a href="<?= htmlspecialchars($live_vitals_url) ?>" class="primary-btn">
                            <svg viewBox="0 0 24 24">
                                <path d="M13 2 6 14h5l-1 8 8-14h-5l1-6Z" />
                            </svg>
                            <span>Live Vitals View</span>
                        </a>
                    </div>
                </section>

                <section class="metric-grid" aria-label="Key performance indicators">
                    <article class="metric-card">
                        <div class="metric-top">
                            <span>Total Patients</span>
                            <span class="metric-icon blue"><svg viewBox="0 0 24 24">
                                    <path
                                        d="M16 11c1.66 0 3-1.79 3-4s-1.34-4-3-4-3 1.79-3 4 1.34 4 3 4Zm-8 0c1.66 0 3-1.79 3-4S9.66 3 8 3 5 4.79 5 7s1.34 4 3 4Zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13Z" />
                                </svg></span>
                        </div>
                        <strong class="metric-value"><?= number_format($metrics['total_patients']) ?></strong>
                        <div class="metric-meta">
                            <span
                                class="metric-trend <?= $metrics['patient_growth_percent'] >= 0 ? 'positive' : 'negative' ?>">
                                <?= ($metrics['patient_growth_percent'] >= 0 ? '+' : '') . number_format($metrics['patient_growth_percent'], 1) ?>%
                            </span>
                            <span>Month-over-month growth</span>
                        </div>
                    </article>

                    <article class="metric-card highlight-card">
                        <div class="metric-top">
                            <span>Appointments Today</span>
                            <span class="metric-icon indigo"><svg viewBox="0 0 24 24">
                                    <path
                                        d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14Z" />
                                </svg></span>
                        </div>
                        <strong class="metric-value"><?= number_format($metrics['appointments_today']) ?></strong>
                        <div class="metric-meta split-meta">
                            <span><?= number_format($metrics['confirmed_today']) ?> confirmed</span>
                            <span><?= number_format($metrics['slots_remaining']) ?> slots remaining</span>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span>Revenue (GHS)</span>
                            <span class="metric-icon sky"><svg viewBox="0 0 24 24">
                                    <path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5Z" />
                                </svg></span>
                        </div>
                        <strong class="metric-value">GH₵
                            <?= number_format($metrics['revenue_this_month_ghs']) ?></strong>
                        <div class="metric-meta"><span class="metric-trend positive">Total this month</span></div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span>Lab Tests Pending</span>
                            <span class="metric-icon amber"><svg viewBox="0 0 24 24">
                                    <path d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Z" />
                                </svg></span>
                        </div>
                        <strong class="metric-value"><?= number_format($metrics['lab_tests_pending']) ?></strong>
                        <div class="metric-meta split-meta">
                            <span class="metric-trend negative"><?= number_format($metrics['lab_urgent_cases']) ?>
                                urgent</span>
                            <span><?= number_format($metrics['lab_abnormal']) ?> abnormal</span>
                        </div>
                    </article>
                </section>

                <section class="content-grid">
                    <article class="panel chart-panel">
                        <div class="panel-head between">
                            <div>
                                <h2>Monthly Revenue Performance</h2>
                                <p>Tracking revenue across the last 6 months.</p>
                            </div>
                            <span class="panel-badge">Currency: GHS</span>
                        </div>
                        <div class="chart-wrap">
                            <div class="chart-axis chart-axis-left">
                                <span id="yTick4">GH₵80k</span>
                                <span id="yTick3">GH₵60k</span>
                                <span id="yTick2">GH₵40k</span>
                                <span id="yTick1">GH₵20k</span>
                                <span id="yTick0">GH₵0</span>
                            </div>
                            <div class="chart-canvas">
                                <svg id="revenueChartSvg" viewBox="0 0 800 320" preserveAspectRatio="none"
                                    aria-label="Monthly revenue chart"></svg>
                                <div class="chart-months" id="chartMonths"></div>
                            </div>
                        </div>
                    </article>

                    <article class="panel staffing-panel">
                        <div class="panel-head">
                            <h2>Staffing Overview</h2>
                            <p>Active personnel by department.</p>
                        </div>
                        <div class="staff-list">
                            <?php
                            $staff_rows = [
                                ['label' => 'Doctors', 'subtitle' => 'Specialists & Residents', 'cls' => 'blue', 'data' => $staffing['doctors']],
                                ['label' => 'Nursing Staff', 'subtitle' => 'Registered & Midwives', 'cls' => 'green', 'data' => $staffing['nurses']],
                                ['label' => 'Lab Technicians', 'subtitle' => 'Pathology & Diagnostics', 'cls' => 'amber', 'data' => $staffing['lab_technicians']],
                            ];
                            foreach ($staff_rows as $r): ?>
                                <div class="staff-item">
                                    <div class="staff-icon <?= $r['cls'] ?>">
                                        <svg viewBox="0 0 24 24">
                                            <path
                                                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm7 8v-1a7 7 0 0 0-14 0v1h2v-1a5 5 0 0 1 10 0v1Z" />
                                        </svg>
                                    </div>
                                    <div class="staff-copy">
                                        <strong><?= htmlspecialchars($r['label']) ?></strong>
                                        <span><?= htmlspecialchars($r['subtitle']) ?></span>
                                    </div>
                                    <div class="staff-metrics">
                                        <div class="staff-total"><?= number_format($r['data']['total']) ?></div>
                                        <span class="staff-duty">On Duty: <?= number_format($r['data']['on_duty']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $totalSlots = 56;
                        $used = max($totalSlots - (int) $metrics['slots_remaining'], 0);
                        $pct = (int) round(min(100, ($used / $totalSlots) * 100));
                        ?>
                        <div class="target-box">
                            <div class="target-head">Weekly Target</div>
                            <div class="progress-track">
                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="target-copy"><?= $pct ?>% capacity reached today</div>
                        </div>
                    </article>
                </section>

                <section class="panel activity-panel">
                    <div class="panel-head between align-end">
                        <div>
                            <h2>Recent Hospital Activity</h2>
                            <p>Recent audit activity from across the hospital.</p>
                        </div>
                        <a href="<?= htmlspecialchars($audit_logs_url) ?>" class="secondary-btn subtle-btn">View All
                            Logs</a>
                    </div>
                    <div class="table-wrap">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>Patient Name</th>
                                    <th>Region</th>
                                    <th>Department</th>
                                    <th>Action</th>
                                    <th>Timestamp</th>
                                    <th>Resource</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_activity)): ?>
                                    <tr>
                                        <td colspan="7" class="empty-cell">No recent activity found.</td>
                                    </tr>
                                <?php else:
                                    foreach ($recent_activity as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($item['id'] ?? '—')) ?></td>
                                            <td><?= htmlspecialchars($item['patient_name'] ?? 'Unknown Patient') ?></td>
                                            <td><?= htmlspecialchars($item['region'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($item['department'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($item['action'] ?? '—') ?></td>
                                            <!-- FIX: was reading $item['timestamp'] but SQL selected 'created_at'; now aliased as 'timestamp' in the query -->
                                            <td><?= !empty($item['timestamp']) ? htmlspecialchars(date('d M Y, H:i', strtotime($item['timestamp']))) : '—' ?>
                                            </td>
                                            <!-- FIX: added the missing </td> closing tag -->
                                            <td><?= htmlspecialchars($item['resource_type'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </main>

            <footer class="page-footer">
                <span>© <?= date('Y') ?> <?= htmlspecialchars($hospital_name) ?> HMS</span>
                <span>Optimized for Ghanaian Healthcare</span>
                <span>GHS Server <?= ucfirst(htmlspecialchars($ghs_server)) ?></span>
            </footer>

        </div><!-- /.main-stage -->
    </div><!-- /.dashboard-shell -->

    <script>
        (function () {
            const chartData = <?= json_encode($revenue_chart) ?>;
            const svg = document.getElementById('revenueChartSvg');
            const monthsEl = document.getElementById('chartMonths');
            if (!svg || !chartData.length) return;
            const vals = chartData.map(i => Number(i.revenue || 0));
            const maxV = Math.max(...vals, 0);
            const roundedMax = maxV <= 0 ? 80000 : Math.ceil(maxV / 20000) * 20000;
            const W = 760, H = 280, pX = 24, pY = 18, uW = W - pX * 2, uH = H - pY * 2;
            const stepX = chartData.length > 1 ? uW / (chartData.length - 1) : uW;
            const pts = chartData.map((item, i) => {
                const v = Number(item.revenue || 0);
                const x = pX + stepX * i;
                const y = pY + uH - ((v / roundedMax) * uH || 0);
                return [x, y];
            });
            const line = pts.map(([x, y], i) => `${i ? 'L' : 'M'} ${x.toFixed(2)} ${y.toFixed(2)}`).join(' ');
            const area = `${line} L ${pts.at(-1)[0].toFixed(2)} ${(H - 8).toFixed(2)} L ${pts[0][0].toFixed(2)} ${(H - 8).toFixed(2)} Z`;
            svg.innerHTML = `
        <defs><linearGradient id="aG" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="rgba(39,117,182,.36)"/>
            <stop offset="100%" stop-color="rgba(39,117,182,.03)"/>
        </linearGradient></defs>
        <path d="${area}" fill="url(#aG)"></path>
        <path d="${line}" fill="none" stroke="#2f77b2" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
        ${pts.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="5" fill="#2f77b2"></circle>`).join('')}`;
            monthsEl.innerHTML = chartData.map(i => `<span>${i.month || '—'}</span>`).join('');
            const ticks = [0, roundedMax * .25, roundedMax * .5, roundedMax * .75, roundedMax];
            ticks.forEach((v, i) => {
                const el = document.getElementById('yTick' + i);
                if (el) el.textContent = v >= 1000 ? `GH₵${Math.round(v / 1000)}k` : `GH₵${Math.round(v)}`;
            });
        })();
    </script>
</body>

</html>