<?php
session_start();
require 'db.php';
$pdo = getDB();

session_start();
// --- Auth gate (mock fallback for dev) ---
if (!isset($_SESSION['akwaaba_user'])) {
    $_SESSION['akwaaba_user'] = [
        'full_name' => 'Dr. Kofi Mensah',
        'role' => 'administrator',
    ];
}

$pdo = getDB();

// ============================================================
// AJAX HANDLERS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    // DELETE Action
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // SAVE Action (Create & Update)
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $gender = $_POST['gender'] ?? null;
        $status = $_POST['status'] ?? 'active';
        $email = trim($_POST['email'] ?? '');
        $region = $_POST['region'] ?? null;
        $address = trim($_POST['address'] ?? '');
        $nhis_number = trim($_POST['nhis_number'] ?? '');
        $nhis_verified = isset($_POST['nhis_verified']) && $_POST['nhis_verified'] === 'on' ? 1 : 0;

        $blood_group = trim($_POST['blood_group'] ?? '');
        $marital_status = trim($_POST['marital_status'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $nationality = trim($_POST['nationality'] ?? '');

        if ($full_name === '' || $phone === '') {
            echo json_encode(['success' => false, 'error' => 'Missing required fields (Name or Phone)']);
            exit;
        }

        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE patients
                    SET full_name = ?, phone = ?, dob = ?, gender = ?, status = ?,
                        email = ?, region = ?, address = ?, nhis_number = ?, nhis_verified = ?,
                        blood_group = ?, marital_status = ?, occupation = ?, nationality = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $full_name,
                    $phone,
                    $dob,
                    $gender,
                    $status,
                    $email,
                    $region,
                    $address,
                    $nhis_number,
                    $nhis_verified,
                    $blood_group,
                    $marital_status,
                    $occupation,
                    $nationality,
                    $id
                ]);
            } else {
                // INSERT
                // Ensure patient_id generates a custom format, or allow DB trigger. Here we supply a simple generator:
                $patient_id = uniqid('AH-');
                $created_at = date('Y-m-d H:i:s');

                $stmt = $pdo->prepare("
                    INSERT INTO patients 
                    (patient_id, full_name, phone, dob, gender, status, email, region, address, nhis_number, nhis_verified, blood_group, marital_status, occupation, nationality, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $patient_id,
                    $full_name,
                    $phone,
                    $dob,
                    $gender,
                    $status,
                    $email,
                    $region,
                    $address,
                    $nhis_number,
                    $nhis_verified,
                    $blood_group,
                    $marital_status,
                    $occupation,
                    $nationality,
                    $created_at
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ============================================================
// PAGE LOAD: FETCH ALL DATA
// ============================================================
$patients = [];
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY id DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['dob'])) {
            try {
                $dob = new DateTime($row['dob']);
                $row['age'] = (int) (new DateTime())->diff($dob)->y;
            } catch (Exception $e) {
                $row['age'] = null;
            }
        } else {
            $row['age'] = null;
        }
        $patients[] = $row;
    }
} catch (Exception $e) {
    // If the database has problems, ignore so page can still render.
}

$total = 0;
$nhis_cnt = 0;
$nhis_pct = 0;
$new_today = 0;
$top_region = '—';
$top_region_count = 0;

try {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $nhis_cnt = (int) $pdo->query("SELECT COUNT(*) FROM patients WHERE nhis_verified = 1")->fetchColumn();
    if ($total > 0)
        $nhis_pct = round(($nhis_cnt / $total) * 100);
    $new_today = (int) $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    $topRow = $pdo->query("SELECT region, COUNT(*) AS total FROM patients WHERE region IS NOT NULL AND region != '' GROUP BY region ORDER BY total DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($topRow) {
        $top_region = $topRow['region'];
        $top_region_count = $topRow['total'];
    }
} catch (Exception $e) {
}


$user = $_SESSION['akwaaba_user'];
$full_name = htmlspecialchars($user['full_name'] ?? 'Dr. Kofi Mensah');
$role = htmlspecialchars($user['role'] ?? 'administrator');

$initials = strtoupper(
    implode('', array_map(fn($p) => $p[0] ?? '', array_slice(array_filter(explode(' ', trim($full_name))), 0, 2)))
);

$hospital_name = 'Akwaaba Health';

$ghana_regions = [
    'Ahafo',
    'Ashanti',
    'Bono',
    'Bono East',
    'Central',
    'Eastern',
    'Greater Accra',
    'North East',
    'Northern',
    'Oti',
    'Savannah',
    'Upper East',
    'Upper West',
    'Volta',
    'Western',
    'Western North',
];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akwaaba Health | Patient Directory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --surface-alt: #f8fbff;
            --border: #e5eaf1;
            --text: #1d2433;
            --muted: #76849a;
            --primary: #2775b6;
            --primary-dark: #1f6299;
            --primary-soft: #eef6fd;
            --success: #3eb66b;
            --warning: #f59e0b;
            --danger: #da4a4a;
            --shadow: 0 20px 45px rgba(28, 56, 92, .08);
            --radius-lg: 22px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --sidebar-width: 270px;
            --topbar-height: 82px;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(180deg, #f8fafc, #eef3f8);
            color: var(--text);
        }

        body.modal-open {
            overflow: hidden
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

        svg {
            width: 1em;
            height: 1em;
            fill: currentColor
        }

        .hidden {
            display: none !important
        }

        .page-shell {
            min-height: 100vh
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #f9fbfe, #f2f5fa);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px 18px;
            overflow-y: auto;
            z-index: 30
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 18px;
            border-bottom: 1px solid var(--border)
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #2f86cc, #205f96)
        }

        .sidebar-brand strong {
            font-size: 1.2rem
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 18px 6px 0
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            text-decoration: none;
            color: #4b5a70;
            font-weight: 600;
            transition: .2s ease
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--primary-soft);
            color: var(--primary)
        }

        .nav-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem
        }

        .nav-arrow {
            margin-left: auto;
            font-size: 1.25rem
        }

        .sidebar-footer {
            padding: 16px 6px 6px;
            border-top: 1px solid var(--border)
        }

        .logout-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 14px;
            color: #cc5b5b;
            font-weight: 700;
            background: none;
            border: none
        }

        .logout-btn:hover {
            background: #fff1f1
        }

        .system-panel {
            margin-top: 14px;
            padding: 14px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: 16px
        }

        .system-panel-label {
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 10px;
            font-weight: 700
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .92rem;
            font-weight: 600
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #d0d7e4
        }

        .status-active {
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(62, 182, 107, .12)
        }

        .main-stage {
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-width: 0
        }

        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 30px;
            background: rgba(255, 255, 255, .88);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            z-index: 20
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: #617188;
            background: #fff;
            border: 1px solid var(--border)
        }

        .icon-btn:hover {
            color: var(--primary)
        }

        .search-wrap {
            position: relative
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7e8aa0;
            font-size: 1rem
        }

        .top-search-wrap {
            width: min(520px, 100%)
        }

        .table-search-wrap {
            min-width: 260px;
            flex: 1 1 280px
        }

        .search-wrap input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(248, 250, 253, .92);
            color: var(--text);
            outline: none;
            transition: .2s ease
        }

        .search-wrap input:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12);
            background: #fff
        }

        .search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 8px;
            z-index: 30;
            max-height: 340px;
            overflow-y: auto
        }

        .search-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            color: inherit;
            text-decoration: none
        }

        .search-result-item:hover {
            background: var(--primary-soft)
        }

        .search-result-item small {
            display: block;
            color: var(--muted);
            margin-top: 4px
        }

        .profile-chip {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px
        }

        .profile-copy {
            display: flex;
            flex-direction: column;
            text-align: right
        }

        .profile-copy strong {
            font-size: .95rem
        }

        .profile-copy span {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 600
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #93b8dd, #407cb6)
        }

        .presence-dot {
            position: absolute;
            right: 10px;
            bottom: 10px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            border: 2px solid #fff;
            background: var(--success)
        }

        .content-shell {
            padding: 30px;
            padding-top: calc(var(--topbar-height) + 30px)
        }

        .hero-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px
        }

        .hero-row h1 {
            margin: 0 0 8px;
            font-size: 2.3rem;
            letter-spacing: -.03em
        }

        .hero-row p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem
        }

        .primary-btn,
        .secondary-btn,
        .ghost-btn,
        .tab-btn,
        .page-btn,
        .menu-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 14px;
            font-weight: 700;
            transition: .2s ease;
            border: none
        }

        .primary-btn {
            padding: 13px 18px;
            color: #fff;
            background: linear-gradient(135deg, #2f86cc, #246ea9);
            box-shadow: 0 14px 28px rgba(39, 117, 182, .24)
        }

        .primary-btn:hover {
            background: linear-gradient(135deg, #327ebd, #1f6299)
        }

        .secondary-btn,
        .ghost-btn,
        .page-btn,
        .tab-btn {
            padding: 12px 16px;
            background: #fff;
            border: 1px solid var(--border);
            color: #536276
        }

        .secondary-btn:hover,
        .ghost-btn:hover,
        .page-btn:hover,
        .tab-btn:hover {
            border-color: rgba(39, 117, 182, .28);
            color: var(--primary)
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 18px
        }

        .stat-card {
            padding: 20px 22px;
            border-radius: 20px;
            background: rgba(255, 255, 255, .95);
            border: 1px solid rgba(229, 234, 241, .95);
            box-shadow: var(--shadow)
        }

        .stat-label {
            color: #7c899b;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .78rem;
            font-weight: 800
        }

        .stat-value {
            margin-top: 12px;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -.04em
        }

        .stat-meta {
            margin-top: 8px;
            font-size: .9rem;
            color: var(--muted)
        }

        .filter-panel {
            border-radius: 22px;
            padding: 16px;
            margin-bottom: 16px;
            background: rgba(255, 255, 255, .95);
            border: 1px solid rgba(229, 234, 241, .95);
            box-shadow: var(--shadow)
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px
        }

        .filter-row select {
            width: 180px;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            color: var(--text);
            background: rgba(250, 252, 255, .94);
            outline: none;
            transition: .2s ease
        }

        .filter-row select:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12)
        }

        .advanced-filters {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr)) auto;
            gap: 12px;
            align-items: end
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .field-group label {
            font-size: .9rem;
            font-weight: 700;
            color: #536276
        }

        .field-group input,
        .field-group select,
        .field-group textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            color: var(--text);
            background: rgba(250, 252, 255, .94);
            outline: none;
            transition: .2s ease
        }

        .field-group input:focus,
        .field-group select:focus {
            border-color: rgba(39, 117, 182, .45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, .12)
        }

        .field-actions {
            align-self: end
        }

        .patients-card {
            border-radius: 22px;
            background: rgba(255, 255, 255, .95);
            border: 1px solid rgba(229, 234, 241, .95);
            box-shadow: var(--shadow)
        }

        .patients-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 18px 0
        }

        .tabs {
            display: inline-flex;
            gap: 8px
        }

        .tab-btn {
            padding: 10px 16px;
            border-radius: 999px
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #2775b6, #215f95);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 12px 24px rgba(39, 117, 182, .18)
        }

        .table-summary,
        .footer-caption {
            font-size: .92rem;
            color: var(--muted);
            font-weight: 600
        }

        .table-wrap {
            overflow-x: auto;
            padding: 12px 18px 0
        }

        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px
        }

        .table-wrap th,
        .table-wrap td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: middle
        }

        .table-wrap th {
            color: #6f7d90;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .08em
        }

        .table-wrap td {
            font-size: .95rem
        }

        .table-wrap tbody tr:hover {
            background: #fbfdff
        }

        .patient-id-link {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none
        }

        .patient-name-cell strong {
            display: block;
            margin-bottom: 4px
        }

        .patient-name-cell small {
            color: var(--muted);
            font-size: .78rem
        }

        .gender-pill,
        .region-badge,
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700
        }

        .gender-pill {
            background: #f6f8fb;
            color: #69788f
        }

        .region-badge {
            background: #fff8ef;
            color: #9a6a17
        }

        .region-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: currentColor;
            opacity: .55
        }

        .status-badge.active {
            background: #eefaf2;
            color: #1c8c4f
        }

        .status-badge.inactive {
            background: #f4f6f9;
            color: #7a8698
        }

        .status-badge.deceased {
            background: #fff1f1;
            color: #c94b4b
        }

        .table-placeholder,
        .empty-state {
            text-align: center;
            color: var(--muted);
            padding: 30px 16px !important
        }

        .table-actions-cell {
            text-align: right;
            width: 56px
        }

        .row-action-wrap {
            position: relative
        }

        .menu-btn {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            font-size: 1.2rem;
            padding: 0;
            background: #fff;
            border: 1px solid var(--border);
            color: #536276
        }

        .row-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 180px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 8px;
            z-index: 12
        }

        .menu-item {
            width: 100%;
            justify-content: flex-start;
            padding: 10px 12px;
            border-radius: 12px;
            color: #516174;
            background: transparent
        }

        .menu-item.danger {
            color: var(--danger)
        }

        .menu-item:hover {
            background: var(--primary-soft)
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap
        }

        .page-btn.active {
            background: var(--primary-soft);
            border-color: rgba(39, 117, 182, .2);
            color: var(--primary)
        }

        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, .55);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 50
        }

        .modal-panel {
            width: min(980px, 100%);
            max-height: min(92vh, 980px);
            overflow-y: auto;
            border-radius: 28px;
            background: rgba(255, 255, 255, .95);
            border: 1px solid rgba(229, 234, 241, .95);
            box-shadow: var(--shadow)
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, .97);
            backdrop-filter: blur(10px);
            z-index: 2
        }

        .modal-header h2 {
            margin: 0 0 6px;
            font-size: 1.85rem
        }

        .modal-header p {
            margin: 0;
            color: var(--muted)
        }

        .close-btn {
            font-size: 1.5rem;
            font-weight: 500
        }

        .form-alert {
            margin: 18px 28px 0;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: .95rem;
            font-weight: 600
        }

        .form-alert.error {
            background: #fff2f2;
            color: #b53737;
            border: 1px solid #f1c4c4
        }

        .form-alert.success {
            background: #effaf3;
            color: #1f8a4e;
            border: 1px solid #c4ead2
        }

        #patientForm {
            padding: 22px 28px 28px
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px
        }

        .field-group textarea {
            resize: vertical;
            min-height: 108px
        }

        .full-span {
            grid-column: 1/-1
        }

        .required {
            color: var(--danger)
        }

        .helper-text,
        .field-error {
            font-size: .78rem
        }

        .helper-text {
            color: var(--muted)
        }

        .field-error {
            color: var(--danger);
            min-height: 1em
        }

        .inline-checkbox-group {
            align-self: end
        }

        .checkbox-row {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(250, 252, 255, .94)
        }

        .checkbox-row input {
            width: auto;
            margin: 0
        }

        .extended-fields {
            padding: 16px;
            background: #f9fbff;
            border: 1px solid var(--border);
            border-radius: 18px
        }

        .extended-grid,
        .emergency-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px
        }

        .emergency-card {
            padding: 18px;
            background: linear-gradient(180deg, #faf7ff, #f5f8ff);
            border: 1px solid #e7e3f8;
            border-radius: 20px
        }

        .emergency-title {
            margin-bottom: 14px;
            color: #7366a5;
            font-weight: 800
        }

        .modal-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--border)
        }

        .toast-stack {
            position: fixed;
            right: 20px;
            bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 60
        }

        .toast {
            min-width: 260px;
            max-width: 360px;
            padding: 14px 16px;
            border-radius: 16px;
            color: #fff;
            box-shadow: var(--shadow);
            font-weight: 700
        }

        .toast.success {
            background: linear-gradient(135deg, #46b66f, #2e8a53)
        }

        .toast.error {
            background: linear-gradient(135deg, #dd5b5b, #bb3535)
        }

        @media (max-width:1320px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .advanced-filters {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }
        }

        @media (max-width:1100px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: auto;
                width: 100%;
                height: auto;
                flex-direction: row;
                align-items: center;
                gap: 16px;
                padding: 14px 16px;
                overflow-x: auto;
                overflow-y: hidden;
                border-right: none;
                border-bottom: 1px solid var(--border)
            }

            .sidebar-brand,
            .sidebar-footer {
                border: none;
                padding: 0;
                flex-shrink: 0
            }

            .nav-list {
                flex-direction: row;
                padding: 0;
                overflow-x: auto
            }

            .nav-item {
                white-space: nowrap
            }

            .system-panel {
                display: none
            }

            .main-stage {
                margin-left: 0
            }

            .topbar {
                left: 0;
                top: 70px
            }

            .content-shell {
                padding-top: calc(70px + var(--topbar-height) + 10px)
            }
        }

        @media (max-width:860px) {

            .topbar,
            .hero-row,
            .patients-card-header,
            .table-footer,
            .modal-header,
            .modal-actions {
                flex-direction: column;
                align-items: stretch
            }

            .content-shell {
                padding: 18px;
                padding-top: calc(70px + 160px)
            }

            .stats-grid,
            .form-grid,
            .extended-grid,
            .emergency-grid,
            .advanced-filters {
                grid-template-columns: 1fr
            }

            .filter-row>select,
            .table-search-wrap {
                width: 100%
            }

            .modal-panel {
                width: 100%;
                max-height: 95vh
            }
        }
    </style>
</head>

<body>
    <div class="page-shell">

        <!-- SIDEBAR -->
        <aside class="sidebar" aria-label="Primary navigation">
            <div class="sidebar-brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 64 64" style="width:24px;height:24px;fill:none">
                        <rect x="2" y="2" width="60" height="60" rx="16" fill="white" fill-opacity=".25"></rect>
                        <path d="M13 34h10l4-10 8 22 6-16h10" stroke="white" stroke-width="4" stroke-linecap="round"
                            stroke-linejoin="round"></path>
                    </svg>
                </div>
                <strong><?= htmlspecialchars($hospital_name) ?></strong>
            </div>
            <nav class="nav-list">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path d="M4 13h6V4H4Zm10 7h6V11h-6ZM4 20h6v-5H4Zm10-9h6V4h-6Z" />
                        </svg></span><span>Dashboard</span></a>
                <a href="patients.php" class="nav-item active"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M16 11c1.66 0 3-1.79 3-4s-1.34-4-3-4-3 1.79-3 4 1.34 4 3 4Zm-8 0c1.66 0 3-1.79 3-4S9.66 3 8 3 5 4.79 5 7s1.34 4 3 4Zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V20h6v-3.5c0-2.33-4.67-3.5-7-3.5Z" />
                        </svg></span><span>Patients</span><span class="nav-arrow">›</span></a>
                <a href="appointments.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14Z" />
                        </svg></span><span>Appointments</span></a>
                <a href="ehr_record.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5" />
                            <path d="M8 13h8M8 17h8M8 9h3" />
                        </svg></span><span>Clinical (EHR)</span></a>
                <a href="billing.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2Zm0 10H4V8h16ZM6 12h4" />
                        </svg></span><span>Billing &amp; NHIS</span></a>
                <a href="laboratory.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Zm2 2h2v4.12l4.98 8.63a1 1 0 0 1-.87 1.5H6.9a1 1 0 0 1-.87-1.5L11 8.12Z" />
                        </svg></span><span>Laboratory</span></a>
                <a href="staff_management.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24">
                            <path
                                d="M12 8a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5Z" />
                        </svg></span><span>Staff Management</span></a>
            </nav>
            <div class="sidebar-footer">
                <form method="POST" action="logout.php">
                    <button type="submit" class="logout-btn"><span
                            aria-hidden="true">↪</span><span>Logout</span></button>
                </form>
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
                <div class="search-wrap top-search-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true" style="width:18px;height:18px">
                        <path
                            d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                    </svg>
                    <input type="search" id="globalSearchInput" placeholder="Search patient by Name, ID or NHIS..."
                        autocomplete="off" />
                    <div class="search-results hidden" id="globalSearchResults" role="listbox"></div>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="icon-btn" title="Notifications">
                        <svg viewBox="0 0 24 24" style="width:20px;height:20px">
                            <path
                                d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5L3 18v1h18v-1Zm-2 1H7v-6a5 5 0 1 1 10 0Z" />
                        </svg>
                    </button>
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
                <section class="hero-row">
                    <div>
                        <h1>Patient Directory</h1>
                        <p>Manage hospital records and NHIS registration for all patients.</p>
                    </div>
                    <button type="button" class="primary-btn" id="openCreateModalBtn">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px">
                            <path d="M11 5h2v14h-2zM5 11h14v2H5z" />
                        </svg>
                        <span>Add New Patient</span>
                    </button>
                </section>

                <!-- Stats -->
                <section class="stats-grid" aria-label="Patient statistics">
                    <article class="stat-card">
                        <div class="stat-label">Total Patients</div>
                        <div class="stat-value" id="totalPatientsStat"><?= $total ?></div>
                        <div class="stat-meta"><?= $total ?> registered records</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Active NHIS</div>
                        <div class="stat-value"><?= $nhis_pct ?>%</div>
                        <div class="stat-meta"><?= $nhis_cnt ?> verified users</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">New Registrations</div>
                        <div class="stat-value"><?= $new_today ?></div>
                        <div class="stat-meta">Enrolled today</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Top Region</div>
                        <div class="stat-value"><?= htmlspecialchars($top_region) ?></div>
                        <div class="stat-meta"><?= $top_region_count ?> patients</div>
                    </article>
                </section>

                <!-- Filters -->
                <section class="filter-panel">
                    <div class="filter-row">
                        <div class="search-wrap table-search-wrap">
                            <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"
                                style="width:18px;height:18px">
                                <path
                                    d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                            </svg>
                            <input type="search" id="tableFilterInput" placeholder="Filter by Name, ID or NHIS..."
                                autocomplete="off" />
                        </div>
                        <select id="regionFilter">
                            <option value="">All Regions</option>
                            <?php foreach ($ghana_regions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="deceased">Deceased</option>
                        </select>
                        <button type="button" class="secondary-btn" id="exportCsvBtn">
                            <svg viewBox="0 0 24 24"
                                style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                                <path d="M12 3v9m0 0 4-4m-4 4-4-4M5 21h14" />
                            </svg>
                            <span>Export CSV</span>
                        </button>
                        <button type="button" class="secondary-btn" id="advancedFilterToggleBtn">
                            <svg viewBox="0 0 24 24"
                                style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                                <path d="M3 5h18M6 12h12m-9 7h6" />
                            </svg>
                            <span>Advanced</span>
                        </button>
                    </div>
                    <div class="advanced-filters hidden" id="advancedFiltersPanel">
                        <div class="field-group">
                            <label for="genderFilter">Gender</label>
                            <select id="genderFilter">
                                <option value="">All Genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="bloodGroupFilter">Blood Group</label>
                            <select id="bloodGroupFilter">
                                <option value="">All</option>
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                    <option value="<?= $bg ?>"><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label for="minAgeFilter">Min Age</label>
                            <input type="number" id="minAgeFilter" min="0" placeholder="0" />
                        </div>
                        <div class="field-group">
                            <label for="maxAgeFilter">Max Age</label>
                            <input type="number" id="maxAgeFilter" min="0" placeholder="120" />
                        </div>
                        <div class="field-group">
                            <label for="orderingFilter">Sort By</label>
                            <select id="orderingFilter">
                                <option value="-created_at">Newest</option>
                                <option value="created_at">Oldest</option>
                                <option value="full_name">Full Name</option>
                                <option value="patient_id">Patient ID</option>
                                <option value="dob">Date of Birth</option>
                            </select>
                        </div>
                        <div class="field-group field-actions">
                            <button type="button" class="ghost-btn" id="resetFiltersBtn">Reset Filters</button>
                        </div>
                    </div>
                </section>

                <!-- Table card -->
                <section class="patients-card">
                    <div class="patients-card-header">
                        <div class="tabs" role="tablist">
                            <button type="button" class="tab-btn active" id="allPatientsTab">All Patients</button>
                            <button type="button" class="tab-btn" id="nhisPatientsTab">NHIS Covered</button>
                        </div>
                        <div class="table-summary" id="tableSummary"><?= count($patients) ?> patients loaded</div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Full Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Phone (+233)</th>
                                    <th>NHIS Number</th>
                                    <th>Region</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="patientsTableBody">
                                <!-- Populated by JS on boot -->
                                <tr>
                                    <td colspan="9" class="empty-state">Loading patients…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <div class="footer-caption" id="resultsCaption">&nbsp;</div>
                        <div class="pagination" id="paginationControls"></div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- ADD / EDIT MODAL -->
    <div class="modal-backdrop hidden" id="patientModalBackdrop" aria-hidden="true">
        <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="patientModalTitle">
            <div class="modal-header">
                <div>
                    <h2 id="patientModalTitle">Add New Patient</h2>
                    <p id="patientModalSubtitle">Register a new patient into the hospital management system.</p>
                </div>
                <button type="button" class="icon-btn close-btn" id="closePatientModalBtn" aria-label="Close">×</button>
            </div>
            <div class="form-alert hidden" id="patientFormAlert"></div>
            <!-- FIX: Added 'name' attributes to fields to enable FormData serialization -->
            <form id="patientForm" novalidate>
                <input type="hidden" name="id" id="patientRecordId" />
                <input type="hidden" name="action" id="patientFormAction" value="save" />

                <div class="form-grid">
                    <div class="field-group full-span">
                        <label for="patientIdPreview">Patient ID (Auto-generated)</label>
                        <input type="text" id="patientIdPreview" value="AUTO-GENERATED" readonly />
                    </div>
                    <div class="field-group">
                        <label for="fullNameInput">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" id="fullNameInput" required
                            placeholder="Ama Serwaa Mensah" />
                    </div>
                    <div class="field-group">
                        <label for="phoneInput">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phoneInput" required placeholder="024 123 4567" />
                        <small class="helper-text">Use +233 or 0XX format.</small>
                    </div>
                    <div class="field-group">
                        <label for="dobInput">Date of Birth <span class="required">*</span></label>
                        <input type="date" name="dob" id="dobInput" required />
                    </div>
                    <div class="field-group">
                        <label for="agePreviewInput">Age</label>
                        <input type="number" id="agePreviewInput" readonly placeholder="Auto-calculated" />
                    </div>
                    <div class="field-group">
                        <label for="genderInput">Gender <span class="required">*</span></label>
                        <select name="gender" id="genderInput" required>
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="statusInput">Patient Status <span class="required">*</span></label>
                        <select name="status" id="statusInput" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="deceased">Deceased</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="emailInput">Email Address</label>
                        <input type="email" name="email" id="emailInput" placeholder="ama.mensah@example.com" />
                    </div>
                    <div class="field-group">
                        <label for="regionInput">Region <span class="required">*</span></label>
                        <select name="region" id="regionInput" required>
                            <option value="">Select region</option>
                            <?php foreach ($ghana_regions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group full-span">
                        <label for="addressInput">Residential Address <span class="required">*</span></label>
                        <textarea name="address" id="addressInput" rows="3" required
                            placeholder="28 Kejetia Rd, Kumasi"></textarea>
                    </div>
                    <div class="field-group">
                        <label for="nhisNumberInput">NHIS Number</label>
                        <input type="text" name="nhis_number" id="nhisNumberInput" placeholder="NHIS-2019-04567" />
                    </div>
                    <div class="field-group inline-checkbox-group">
                        <label class="checkbox-row">
                            <input type="checkbox" name="nhis_verified" id="nhisVerifiedInput" />
                            <span>NHIS verified</span>
                        </label>
                    </div>
                    <div class="field-group full-span">
                        <button type="button" class="ghost-btn" id="toggleExtendedFieldsBtn">More patient
                            details</button>
                    </div>
                    <div class="extended-fields hidden full-span" id="extendedFieldsPanel">
                        <div class="extended-grid">
                            <div class="field-group">
                                <label for="bloodGroupInput">Blood Group</label>
                                <select name="blood_group" id="bloodGroupInput">
                                    <option value="">Select blood group</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                        <option value="<?= $bg ?>"><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="maritalStatusInput">Marital Status</label>
                                <select name="marital_status" id="maritalStatusInput">
                                    <option value="">Select</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="occupationInput">Occupation</label>
                                <input type="text" name="occupation" id="occupationInput" placeholder="Trader" />
                            </div>
                            <div class="field-group">
                                <label for="nationalityInput">Nationality</label>
                                <input type="text" name="nationality" id="nationalityInput" placeholder="Ghanaian" />
                            </div>
                        </div>
                    </div>
                    <!-- Assuming backend handles emergency via different table, omitted standard naming tracking here for brevity as they weren't in your PHP earlier -->
                    <div class="full-span emergency-card">
                        <div class="emergency-title">Emergency Contact</div>
                        <div class="emergency-grid">
                            <div class="field-group">
                                <label for="emergencyContactNameInput">Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="emergencyContactNameInput"
                                    placeholder="Kojo Mensah" />
                            </div>
                            <div class="field-group">
                                <label for="emergencyContactRelationshipInput">Relationship</label>
                                <input type="text" name="emergency_contact_relationship"
                                    id="emergencyContactRelationshipInput" placeholder="Brother" />
                            </div>
                            <div class="field-group">
                                <label for="emergencyContactPhoneInput">Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" id="emergencyContactPhoneInput"
                                    placeholder="+233 20 987 6543" />
                            </div>
                            <div class="field-group full-span">
                                <label for="emergencyContactAddressInput">Contact Address</label>
                                <input type="text" name="emergency_contact_address" id="emergencyContactAddressInput"
                                    placeholder="Kumasi, Ghana" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="secondary-btn" id="cancelPatientModalBtn">Cancel</button>
                    <button type="submit" class="primary-btn" id="savePatientBtn">Save Patient</button>
                </div>
            </form>
        </section>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite"></div>

    <script>
        (() => {
            // Seed PATIENTS from PHP
            let PATIENTS = <?= json_encode(array_values($patients), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

            const state = {
                page: 1, pageSize: 10,
                search: '', region: '', status: '', gender: '',
                bloodGroup: '', minAge: '', maxAge: '',
                ordering: '-created_at', nhisCovered: false, editingId: null, openMenuId: null,
            };

            const cap = v => String(v || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            const fmtDate = v => { if (!v) return '—'; const d = new Date(v); return isNaN(d) ? String(v) : d.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }); };
            const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
            const $ = id => document.getElementById(id);

            const calcAge = dob => {
                if (!dob) return '';
                const d = new Date(dob), today = new Date();
                let a = today.getFullYear() - d.getFullYear();
                if (today.getMonth() < d.getMonth() || (today.getMonth() === d.getMonth() && today.getDate() < d.getDate())) a--;
                return Math.max(a, 0);
            };

            window.showToast = (msg, type = 'success') => {
                const el = document.createElement('div');
                el.className = `toast ${type}`; el.textContent = msg;
                $('toastStack').appendChild(el);
                setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(8px)'; el.style.transition = '.25s'; setTimeout(() => el.remove(), 260); }, 3000);
            };

            // ---- Filter & sort ----
            const applyFilters = () => {
                let list = [...PATIENTS];
                const q = state.search.toLowerCase();
                if (q) list = list.filter(p =>
                    (p.full_name || '').toLowerCase().includes(q) ||
                    (p.patient_id || '').toLowerCase().includes(q) ||
                    (p.nhis_number || '').toLowerCase().includes(q) ||
                    (p.phone || '').toLowerCase().includes(q)
                );

                if (state.region) list = list.filter(p => p.region === state.region);
                if (state.status) list = list.filter(p => (p.status || '').toLowerCase() === state.status.toLowerCase());
                if (state.gender) list = list.filter(p => (p.gender || '').toLowerCase() === state.gender.toLowerCase());
                if (state.bloodGroup) list = list.filter(p => p.blood_group === state.bloodGroup);
                if (state.minAge !== '') list = list.filter(p => Number(p.age ?? 0) >= Number(state.minAge));
                if (state.maxAge !== '') list = list.filter(p => Number(p.age ?? 0) <= Number(state.maxAge));
                if (state.nhisCovered) list = list.filter(p => p.nhis_verified && p.nhis_number);

                const dir = state.ordering.startsWith('-') ? -1 : 1;
                const key = state.ordering.replace(/^-/, '');
                list.sort((a, b) => { const av = a[key] ?? '', bv = b[key] ?? ''; return av < bv ? -dir : av > bv ? dir : 0; });
                return list;
            };

            // ---- Global search ----
            const renderGlobalSearch = q => {
                const res = $('globalSearchResults');
                if (!q || q.length < 2) { res.classList.add('hidden'); res.innerHTML = ''; return; }
                const matches = PATIENTS.filter(p =>
                    (p.full_name || '').toLowerCase().includes(q.toLowerCase()) ||
                    (p.patient_id || '').toLowerCase().includes(q.toLowerCase()) ||
                    (p.nhis_number || '').toLowerCase().includes(q.toLowerCase())
                ).slice(0, 8);
                if (!matches.length) { res.innerHTML = '<div class="search-result-item">No patient found.</div>'; res.classList.remove('hidden'); return; }
                res.innerHTML = matches.map(p => `
            <div class="search-result-item" style="cursor:pointer" onclick="highlightPatient(${p.id})">
                <div><strong>${p.full_name}</strong><small>${p.patient_id} • ${p.nhis_number || 'No NHIS'} • ${p.phone || ''}</small></div>
                <span>${p.region || '—'}</span>
            </div>`).join('');
                res.classList.remove('hidden');
            };

            window.highlightPatient = id => {
                $('globalSearchResults').classList.add('hidden');
                $('globalSearchInput').value = '';
                state.search = ''; state.page = 1; renderTable();
                setTimeout(() => {
                    const row = document.querySelector(`[data-pid="${id}"]`);
                    if (row) { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); row.style.background = '#fff9e6'; setTimeout(() => row.style.background = '', 1400); }
                }, 150);
            };

            // ---- Table ----
            const renderTable = () => {
                closeMenus();
                const filtered = applyFilters();
                const total = filtered.length;
                const totalPages = Math.max(1, Math.ceil(total / state.pageSize));
                if (state.page > totalPages) state.page = totalPages;
                const start = (state.page - 1) * state.pageSize, end = start + state.pageSize;
                const page = filtered.slice(start, end);

                $('tableSummary').textContent = `Showing ${Math.min(end, total)} of ${total} patients`;
                $('resultsCaption').textContent = `Showing ${page.length ? start + 1 : 0}–${Math.min(end, total)} of ${total} results`;

                const tbody = $('patientsTableBody');
                if (!page.length) {
                    tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No patients matched the current filters.</td></tr>';
                    $('paginationControls').innerHTML = ''; return;
                }

                tbody.innerHTML = page.map(p => {
                    const gLabel = p.gender === 'M' || (p.gender || '').toLowerCase() === 'male' ? 'Male'
                        : p.gender === 'F' || (p.gender || '').toLowerCase() === 'female' ? 'Female' : 'Other';
                    const statusClass = (p.status || '').toLowerCase();
                    return `<tr data-pid="${p.id}">
                <td><span class="patient-id-link">${p.patient_id || '—'}</span></td>
                <td class="patient-name-cell"><strong>${p.full_name}</strong><small>Reg: ${fmtDate(p.created_at)}</small></td>
                <td>${p.age ?? '—'}</td>
                <td><span class="gender-pill">${gLabel}</span></td>
                <td>${p.phone || '—'}</td>
                <td>${p.nhis_number || '—'}</td>
                <td><span class="region-badge">${p.region || '—'}</span></td>
                <td><span class="status-badge ${statusClass}">${cap(p.status)}</span></td>
                <td class="table-actions-cell">
                    <div class="row-action-wrap">
                        <button type="button" class="menu-btn" onclick="toggleMenu(event,${p.id})">⋮</button>
                        <div class="row-menu hidden" id="menu-${p.id}">
                            <button type="button" class="menu-item" onclick="editPatient(${p.id})">Edit patient</button>
                            <button type="button" class="menu-item danger" onclick="deletePatient(${p.id})">Delete patient</button>
                        </div>
                    </div>
                </td>
            </tr>`;
                }).join('');

                renderPagination(total, totalPages);
            };

            const closeMenus = () => { document.querySelectorAll('.row-menu').forEach(m => m.classList.add('hidden')); state.openMenuId = null; };
            window.toggleMenu = (e, id) => {
                e.stopPropagation();
                const menu = $(`menu-${id}`); if (!menu) return;
                const wasOpen = !menu.classList.contains('hidden');
                closeMenus();
                if (!wasOpen) { menu.classList.remove('hidden'); state.openMenuId = id; }
            };
            document.addEventListener('click', () => closeMenus());

            const renderPagination = (total, totalPages) => {
                const ctrl = $('paginationControls');
                const btn = (label, page, { disabled = false, active = false } = {}) => {
                    const b = document.createElement('button');
                    b.type = 'button'; b.className = `page-btn${active ? ' active' : ''}`; b.textContent = label; b.disabled = disabled;
                    b.addEventListener('click', () => { state.page = page; renderTable(); });
                    return b;
                };
                ctrl.innerHTML = '';
                ctrl.appendChild(btn('Previous', Math.max(1, state.page - 1), { disabled: state.page === 1 }));
                const pages = new Set([1, totalPages, state.page - 1, state.page, state.page + 1].filter(p => p >= 1 && p <= totalPages));
                [...pages].sort((a, b) => a - b).forEach((p, i, arr) => {
                    if (i > 0 && arr[i - 1] !== p - 1) { const s = document.createElement('span'); s.className = 'footer-caption'; s.textContent = '…'; ctrl.appendChild(s); }
                    ctrl.appendChild(btn(String(p), p, { active: p === state.page }));
                });
                ctrl.appendChild(btn('Next', Math.min(totalPages, state.page + 1), { disabled: state.page === totalPages }));
            };

            // ---- CRUD ----
            window.deletePatient = async id => {
                const p = PATIENTS.find(x => x.id === id); if (!p) return;
                if (!confirm(`Delete patient record for ${p.full_name}? This cannot be undone.`)) return;

                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);

                try {
                    const res = await fetch('patients.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        PATIENTS = PATIENTS.filter(x => x.id !== id);
                        showToast('Patient record deleted.', 'success');
                        $('totalPatientsStat').textContent = PATIENTS.length;
                        renderTable();
                    } else {
                        showToast(data.error || 'Delete failed', 'error');
                    }
                } catch (e) {
                    showToast('Server error during deletion.', 'error');
                }
            };

            window.editPatient = id => { const p = PATIENTS.find(x => x.id === id); if (!p) return; closeMenus(); populateForm(p); openModal(); };

            // ---- Modal ----
            const openModal = () => { document.body.classList.add('modal-open'); $('patientModalBackdrop').classList.remove('hidden'); };
            const closeModal = () => { document.body.classList.remove('modal-open'); $('patientModalBackdrop').classList.add('hidden'); resetForm(); };

            const resetForm = () => {
                $('patientForm').reset();
                $('patientRecordId').value = '';
                $('patientFormAction').value = 'save';
                $('patientIdPreview').value = 'AUTO-GENERATED';
                $('agePreviewInput').value = '';
                $('patientModalTitle').textContent = 'Add New Patient';
                $('patientModalSubtitle').textContent = 'Register a new patient into the hospital management system.';
                $('savePatientBtn').textContent = 'Save Patient';
                $('extendedFieldsPanel').classList.add('hidden');
                $('toggleExtendedFieldsBtn').textContent = 'More patient details';
                $('patientFormAlert').className = 'form-alert hidden';
                state.editingId = null;
            };

            const populateForm = p => {
                resetForm(); state.editingId = p.id;
                $('patientRecordId').value = p.id;
                $('patientFormAction').value = 'save';
                $('patientIdPreview').value = p.patient_id || '';
                $('patientModalTitle').textContent = 'Edit Patient';
                $('patientModalSubtitle').textContent = 'Update the patient record and save the changes.';
                $('savePatientBtn').textContent = 'Update Patient';

                $('fullNameInput').value = p.full_name || '';
                $('phoneInput').value = p.phone || '';
                $('dobInput').value = p.dob || p.date_of_birth || '';
                $('agePreviewInput').value = p.age ?? '';
                $('genderInput').value = (p.gender || '').toLowerCase();
                $('statusInput').value = p.status || 'active';
                $('emailInput').value = p.email || '';
                $('regionInput').value = p.region || '';
                $('addressInput').value = p.residential_address || p.address || '';
                $('nhisNumberInput').value = p.nhis_number || '';
                $('nhisVerifiedInput').checked = !!(p.nhis_verified);
                $('bloodGroupInput').value = p.blood_group || '';
                $('maritalStatusInput').value = p.marital_status || '';
                $('occupationInput').value = p.occupation || '';
                $('nationalityInput').value = p.nationality || '';

                if (p.blood_group || p.marital_status || p.occupation) {
                    $('extendedFieldsPanel').classList.remove('hidden');
                    $('toggleExtendedFieldsBtn').textContent = 'Hide extra details';
                }
            };

            const validateForm = () => {
                const name = $('fullNameInput').value.trim();
                const phone = $('phoneInput').value.trim();
                const dob = $('dobInput').value;
                const gender = $('genderInput').value;
                const region = $('regionInput').value;
                const address = $('addressInput').value.trim();
                if (!name || !phone || !dob || !gender || !region || !address) {
                    $('patientFormAlert').className = 'form-alert error';
                    $('patientFormAlert').textContent = 'Please fill in all required fields.';
                    $('patientFormAlert').classList.remove('hidden');
                    return false;
                }
                return true;
            };

            $('patientForm').addEventListener('submit', async e => {
                e.preventDefault();
                if (!validateForm()) return;

                const form = e.target;
                const fd = new FormData(form);

                try {
                    const res = await fetch('patients.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        showToast('Saved successfully', 'success');
                        setTimeout(() => location.reload(), 600);
                    } else {
                        showToast(data.error || 'Save failed', 'error');
                        $('patientFormAlert').className = 'form-alert error';
                        $('patientFormAlert').textContent = data.error || 'Save failed';
                        $('patientFormAlert').classList.remove('hidden');
                    }
                } catch (err) {
                    showToast('Server error during save.', 'error');
                }
            });

            // ---- Export CSV ----
            $('exportCsvBtn').addEventListener('click', () => {
                const filtered = applyFilters();
                if (!filtered.length) { showToast('No results to export.', 'error'); return; }
                const headers = ['patient_id', 'full_name', 'age', 'gender', 'phone', 'nhis_number', 'region', 'status', 'dob'];
                const rows = filtered.map(p => headers.map(h => `"${String(p[h] ?? '').replace(/"/g, '""')}"`).join(','));
                const csv = [headers.join(','), ...rows].join('\n');
                const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' })); a.download = 'patients.csv'; a.click(); URL.revokeObjectURL(a.href);
                showToast('CSV exported successfully.', 'success');
            });

            // ---- Wire up filter controls ----
            $('globalSearchInput').addEventListener('input', debounce(e => renderGlobalSearch(e.target.value)));
            document.addEventListener('click', e => { if (!e.target.closest('.top-search-wrap')) $('globalSearchResults').classList.add('hidden'); });

            $('tableFilterInput').addEventListener('input', debounce(e => { state.search = e.target.value; state.page = 1; renderTable(); }));
            $('regionFilter').addEventListener('change', e => { state.region = e.target.value; state.page = 1; renderTable(); });
            $('statusFilter').addEventListener('change', e => { state.status = e.target.value; state.page = 1; renderTable(); });
            $('genderFilter').addEventListener('change', e => { state.gender = e.target.value; state.page = 1; renderTable(); });
            $('bloodGroupFilter').addEventListener('change', e => { state.bloodGroup = e.target.value; state.page = 1; renderTable(); });
            $('minAgeFilter').addEventListener('input', debounce(e => { state.minAge = e.target.value; state.page = 1; renderTable(); }));
            $('maxAgeFilter').addEventListener('input', debounce(e => { state.maxAge = e.target.value; state.page = 1; renderTable(); }));
            $('orderingFilter').addEventListener('change', e => { state.ordering = e.target.value; state.page = 1; renderTable(); });

            $('advancedFilterToggleBtn').addEventListener('click', () => $('advancedFiltersPanel').classList.toggle('hidden'));
            $('resetFiltersBtn').addEventListener('click', () => {
                state.search = ''; state.region = ''; state.status = ''; state.gender = '';
                state.bloodGroup = ''; state.minAge = ''; state.maxAge = ''; state.ordering = '-created_at'; state.nhisCovered = false; state.page = 1;
                ['tableFilterInput', 'regionFilter', 'statusFilter', 'genderFilter', 'bloodGroupFilter', 'minAgeFilter', 'maxAgeFilter'].forEach(id => { const el = $(id); if (el) el.value = ''; });
                $('orderingFilter').value = '-created_at';
                $('allPatientsTab').classList.add('active'); $('nhisPatientsTab').classList.remove('active');
                renderTable();
            });

            $('allPatientsTab').addEventListener('click', () => { state.nhisCovered = false; state.page = 1; $('allPatientsTab').classList.add('active'); $('nhisPatientsTab').classList.remove('active'); renderTable(); });
            $('nhisPatientsTab').addEventListener('click', () => { state.nhisCovered = true; state.page = 1; $('nhisPatientsTab').classList.add('active'); $('allPatientsTab').classList.remove('active'); renderTable(); });

            $('openCreateModalBtn').addEventListener('click', () => { resetForm(); openModal(); });
            $('closePatientModalBtn').addEventListener('click', closeModal);
            $('cancelPatientModalBtn').addEventListener('click', closeModal);
            $('patientModalBackdrop').addEventListener('click', e => { if (e.target === $('patientModalBackdrop')) closeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && !$('patientModalBackdrop').classList.contains('hidden')) closeModal(); });

            $('dobInput').addEventListener('change', () => { $('agePreviewInput').value = calcAge($('dobInput').value); });
            $('toggleExtendedFieldsBtn').addEventListener('click', () => {
                const hidden = $('extendedFieldsPanel').classList.toggle('hidden');
                $('toggleExtendedFieldsBtn').textContent = hidden ? 'More patient details' : 'Hide extra details';
            });

            // Boot
            renderTable();
        })();
    </script>
</body>

</html>