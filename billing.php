<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getDB();

// ---- Auto-create tables if missing to prevent SQL errors ----
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id VARCHAR(50) UNIQUE,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            nhis_number VARCHAR(100),
            region VARCHAR(100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) UNIQUE,
            patient_id VARCHAR(255) NOT NULL,
            nhis_coverage_status VARCHAR(50) DEFAULT 'none',
            nhis_coverage_percent DECIMAL(5,2) DEFAULT 0,
            nhis_auth_number VARCHAR(100),
            payment_method VARCHAR(50) DEFAULT 'cash',
            payment_status VARCHAR(50) DEFAULT 'pending',
            gross_subtotal DECIMAL(10,2) DEFAULT 0,
            nhis_deduction DECIMAL(10,2) DEFAULT 0,
            total_payable DECIMAL(10,2) DEFAULT 0,
            amount_paid DECIMAL(10,2) DEFAULT 0,
            clinical_billing_notes TEXT,
            due_date DATE,
            paid_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            service_id VARCHAR(100),
            description VARCHAR(255) NOT NULL,
            quantity INT DEFAULT 1,
            unit_price DECIMAL(10,2) DEFAULT 0,
            line_total DECIMAL(10,2) DEFAULT 0,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    error_log("DB auto-create error: " . $e->getMessage());
}

session_start();
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

$dashboard_url = 'dashboard.php';
$patients_url = 'patients.php';
$patient_detail_base_url = 'patients.php?patient_id=';
$appointments_url = 'appointments.php';
$clinical_url = 'ehr_record.php';
$billing_url = 'billing.php';
$laboratory_url = 'laboratory.php';
$staff_url = 'staff_management.php';
$login_url = 'login.php';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akwaaba Health | Billing &amp; Insurance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --border: #e6ebf2;
            --text: #182230;
            --muted: #6b7280;
            --primary: #1f6fb5;
            --primary-soft: #e9f3fd;
            --green: #14a44d;
            --green-soft: #ecfbf1;
            --amber: #d97706;
            --amber-soft: #fff6e6;
            --red: #d64949;
            --red-soft: #fff3f3;
            --slate: #475467;
            --slate-soft: #eff3f7;
            --shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        .hidden {
            display: none !important;
        }

        body {
            min-height: 100vh;
        }

        .page-shell {
            display: grid;
            grid-template-columns: 276px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            background: #f7f9fc;
            border-right: 1px solid var(--border);
            padding: 22px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 10px 20px;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1f6fb5, #195f99);
            color: #fff;
            display: grid;
            place-items: center;
        }

        .brand-mark svg {
            width: 28px;
            height: 28px;
            fill: rgba(255, 255, 255, 0.15);
        }

        .brand-mark path {
            stroke: #fff;
        }

        .nav-list {
            display: grid;
            gap: 10px;
            margin-top: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 14px;
            border-radius: 14px;
            color: #556274;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav-item:hover {
            background: #eef3f9;
            color: #1f6fb5;
        }

        .nav-item.active {
            background: #dceaf8;
            color: #1f6fb5;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: inline-flex;
        }

        .nav-icon svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
        }

        .nav-item .nav-arrow {
            margin-left: auto;
            font-size: 24px;
            line-height: 1;
        }

        .sidebar-footer {
            display: grid;
            gap: 18px;
        }

        .logout-btn {
            width: 100%;
            border: 0;
            border-radius: 14px;
            background: transparent;
            color: #cc6464;
            font-weight: 700;
            padding: 14px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout-btn:hover {
            background: #fff0f0;
        }

        .system-panel {
            background: #f1f5fa;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
        }

        .system-panel-label {
            font-size: 12px;
            font-weight: 700;
            color: #7c8798;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 8px;
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #c1c9d6;
        }

        .status-dot.status-active {
            background: #22c55e;
        }

        .status-dot.status-inactive {
            background: #d64949;
        }

        .main-stage {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            justify-content: space-between;
        }

        .search-wrap {
            position: relative;
            width: min(430px, 100%);
        }

        .top-search-wrap {
            flex: 1;
            max-width: 440px;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #8a93a3;
        }

        .search-wrap input {
            width: 100%;
            padding: 14px 16px 14px 42px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fbfcfe;
            outline: none;
        }

        .search-wrap input:focus,
        select:focus,
        textarea:focus {
            border-color: #88b6e3;
            box-shadow: 0 0 0 4px rgba(31, 111, 181, 0.12);
        }

        .search-results {
            position: absolute;
            inset: calc(100% + 8px) 0 auto 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            z-index: 12;
            overflow: hidden;
        }

        .search-result-item {
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-bottom: 1px solid #eff3f7;
        }

        .search-result-item:last-child {
            border-bottom: 0;
        }

        .search-result-item:hover {
            background: #f8fbff;
        }

        .search-result-item strong {
            font-size: 14px;
        }

        .search-result-item span {
            font-size: 12px;
            color: var(--muted);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            display: grid;
            place-items: center;
        }

        .icon-btn svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: #556274;
            stroke-width: 1.8;
        }

        .profile-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            padding: 8px 14px;
            border-left: 1px solid var(--border);
        }

        .profile-copy {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .profile-copy strong {
            font-size: 14px;
        }

        .profile-copy span {
            color: var(--muted);
            font-size: 12px;
        }

        .profile-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #bcd9f5, #7cb0df);
            display: grid;
            place-items: center;
            color: #0d4270;
            font-weight: 800;
        }

        .presence-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #22c55e;
            position: absolute;
            right: 12px;
            bottom: 8px;
            box-shadow: 0 0 0 3px #fff;
        }

        .content-shell {
            padding: 28px;
            display: grid;
            gap: 22px;
        }

        .page-feedback {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #f2d4d4;
            background: #fff5f5;
            color: #a64949;
            font-weight: 600;
        }

        .hero-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .hero-row h1 {
            margin: 0 0 6px;
            font-size: 39px;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .hero-row p {
            margin: 0;
            color: var(--muted);
        }

        .hero-actions {
            display: flex;
            gap: 12px;
        }

        .primary-btn,
        .secondary-btn,
        .draft-btn,
        .text-btn {
            border-radius: 14px;
            border: 1px solid transparent;
            padding: 13px 18px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .primary-btn {
            background: linear-gradient(135deg, #1f6fb5, #1b5e96);
            color: #fff;
            box-shadow: 0 12px 24px rgba(31, 111, 181, 0.18);
        }

        .secondary-btn {
            background: #fff;
            border-color: var(--border);
            color: var(--slate);
        }

        .draft-btn {
            background: #ffeaf2;
            color: #c23a6d;
        }

        .text-btn {
            background: transparent;
            color: var(--primary);
            padding: 0;
            border: 0;
        }

        .primary-btn:hover,
        .secondary-btn:hover,
        .draft-btn:hover {
            transform: translateY(-1px);
        }

        .full-width {
            width: 100%;
        }

        .compact-btn {
            padding: 10px 14px;
            border-radius: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.03);
        }

        .stat-label {
            color: var(--muted);
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .stat-value {
            font-size: 33px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .stat-meta {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .accent-blue {
            box-shadow: inset 4px 0 0 #1f6fb5;
        }

        .accent-green {
            box-shadow: inset 4px 0 0 #14a44d;
        }

        .accent-amber {
            box-shadow: inset 4px 0 0 #d97706;
        }

        .accent-slate {
            box-shadow: inset 4px 0 0 #667085;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.8fr);
            gap: 18px;
            align-items: start;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 22px 22px 0;
        }

        .panel-header h2 {
            margin: 0 0 4px;
            font-size: 24px;
            letter-spacing: -0.02em;
        }

        .panel-header p {
            margin: 0;
            color: var(--muted);
        }

        .analytics-card {
            padding-bottom: 18px;
        }

        .chart-stage {
            padding: 12px 18px 0;
        }

        #revenueChart {
            width: 100%;
            height: auto;
            background: linear-gradient(180deg, #ffffff, #fbfdff);
            border-radius: 20px;
        }

        .chart-legend {
            padding: 8px 22px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-weight: 600;
            font-size: 13px;
        }

        .legend-swatch {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .quick-payment-card {
            padding-bottom: 22px;
        }

        .quick-payment-form {
            padding: 18px 22px 0;
            display: grid;
            gap: 16px;
        }

        .field-group {
            display: grid;
            gap: 8px;
        }

        .field-group>span {
            font-weight: 700;
            font-size: 13px;
            color: #475467;
        }

        .field-group small {
            color: var(--muted);
            font-size: 12px;
        }

        .field-group input,
        .field-group select,
        .field-group textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fff;
            padding: 13px 14px;
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 96px;
        }

        .form-alert {
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #f0d4d4;
            background: #fff4f4;
            color: #a64949;
            font-weight: 600;
            font-size: 14px;
        }

        .form-alert.success {
            background: #f1fdf4;
            border-color: #ccefd7;
            color: #15803d;
        }

        .invoices-panel {
            overflow: hidden;
        }

        .invoices-header {
            padding-bottom: 18px;
        }

        .invoice-toolbar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .inline-search-wrap {
            width: 320px;
        }

        .toolbar-select {
            min-width: 170px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fbfcfe;
            padding: 12px 14px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 780px;
        }

        .data-table th,
        .data-table td {
            padding: 16px 22px;
            border-top: 1px solid #eef2f6;
            text-align: left;
        }

        .data-table th {
            color: #667085;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .invoice-link {
            color: var(--primary);
            font-weight: 700;
        }

        .amount-copy {
            font-weight: 700;
        }

        .patient-copy {
            display: grid;
            gap: 4px;
        }

        .patient-copy small {
            color: var(--muted);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.coverage-full {
            background: #eefaf1;
            color: #15803d;
        }

        .badge.coverage-partial {
            background: #fff7e8;
            color: #b76a09;
        }

        .badge.coverage-none {
            background: #f4f6fa;
            color: #667085;
        }

        .badge.status-paid {
            background: #eefaf1;
            color: #15803d;
        }

        .badge.status-pending {
            background: #fff7e8;
            color: #b76a09;
        }

        .badge.status-partial {
            background: #eef6ff;
            color: #1f6fb5;
        }

        .badge.status-overdue {
            background: #fff1f1;
            color: #d64949;
        }

        .badge.status-waived {
            background: #f5f3ff;
            color: #6d4ce8;
        }

        .row-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .row-icon-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fff;
            display: inline-grid;
            place-items: center;
        }

        .row-icon-btn svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
        }

        .empty-state {
            padding: 28px 22px;
            color: var(--muted);
            display: grid;
            gap: 6px;
        }

        .panel-footer {
            padding: 18px 22px 22px;
            border-top: 1px solid #eef2f6;
        }

        .paginator-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .results-copy {
            color: var(--muted);
            font-weight: 600;
        }

        .paginator-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-chip {
            min-width: 92px;
            text-align: center;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fafbfe;
            font-weight: 700;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(7, 18, 35, 0.45);
            display: grid;
            place-items: center;
            padding: 24px;
            z-index: 20;
        }

        .modal-sheet {
            width: min(1100px, 100%);
            max-height: calc(100vh - 48px);
            overflow: auto;
            background: #fff;
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .large-modal {
            width: min(1180px, 100%);
        }

        .modal-header {
            padding: 24px 28px 20px;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .modal-header h2 {
            margin: 0 0 6px;
            font-size: 30px;
            letter-spacing: -0.03em;
        }

        .modal-header p {
            margin: 0;
            color: var(--muted);
        }

        .modal-close-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            font-size: 26px;
            line-height: 1;
        }

        .invoice-form {
            padding: 24px 28px 28px;
            display: grid;
            gap: 24px;
        }

        .invoice-top-grid {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 20px;
        }

        .selected-patient {
            margin-top: 10px;
            background: #f5faff;
            border: 1px solid #d7e8f8;
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .selected-patient-copy {
            display: grid;
            gap: 4px;
        }

        .selected-patient-copy strong {
            font-size: 14px;
        }

        .selected-patient-copy span {
            color: var(--muted);
            font-size: 12px;
        }

        .line-items-panel {
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 18px;
            background: #fbfcff;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .section-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .section-header span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .line-items-table-wrap {
            overflow-x: auto;
        }

        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .line-items-table th,
        .line-items-table td {
            padding: 12px 10px;
            border-top: 1px solid #e9eef5;
        }

        .line-items-table th {
            font-size: 12px;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .line-items-table td input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }

        .remove-line-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid #f2d0d0;
            background: #fff6f6;
            color: #c84848;
        }

        .invoice-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 22px;
            align-items: start;
        }

        .invoice-form-column {
            display: grid;
            gap: 18px;
        }

        .split-grid {
            display: grid;
            gap: 16px;
        }

        .split-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .split-grid.three-col {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .totals-card {
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 18px;
            background: linear-gradient(180deg, #fcfdff, #f7fbff);
            display: grid;
            gap: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .summary-row span {
            color: var(--muted);
            font-weight: 700;
        }

        .summary-row strong {
            font-size: 18px;
        }

        .summary-row.emphasis {
            padding-top: 10px;
            border-top: 1px dashed #d6e3f2;
        }

        .summary-row.emphasis strong {
            font-size: 28px;
            color: var(--primary);
        }

        .deduction-copy {
            color: #0f9f59;
        }

        .amount-paid-group {
            padding-top: 8px;
            border-top: 1px dashed #d6e3f2;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 1px solid #eef2f6;
            padding-top: 18px;
        }

        .toast-stack {
            position: fixed;
            right: 24px;
            bottom: 24px;
            display: grid;
            gap: 10px;
            z-index: 30;
        }

        .toast {
            padding: 14px 16px;
            border-radius: 14px;
            background: #10243a;
            color: #fff;
            box-shadow: var(--shadow);
            font-weight: 600;
            min-width: 260px;
        }

        .toast.success {
            background: #0f8c4a;
        }

        .toast.error {
            background: #b93838;
        }

        .toast.info {
            background: #1f6fb5;
        }

        @media (max-width: 1280px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .analytics-grid,
            .invoice-main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1080px) {
            .page-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .hero-row,
            .panel-header,
            .invoice-top-grid,
            .split-grid.two-col,
            .split-grid.three-col,
            .paginator-row {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: stretch;
            }

            .hero-actions,
            .invoice-toolbar,
            .topbar {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 720px) {

            .content-shell,
            .topbar {
                padding: 18px;
            }

            .hero-row h1 {
                font-size: 30px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .modal-backdrop {
                padding: 12px;
            }

            .modal-header,
            .invoice-form {
                padding-left: 18px;
                padding-right: 18px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer>* {
                width: 100%;
            }
        }
    </style>
</head>

<body data-hospital-name="<?= htmlspecialchars($hospital_name) ?>" data-user-name="<?= htmlspecialchars($full_name) ?>"
    data-user-role="<?= htmlspecialchars($role) ?>" data-dashboard-url="<?= htmlspecialchars($dashboard_url) ?>"
    data-patients-url="<?= htmlspecialchars($patients_url) ?>"
    data-patient-detail-base-url="<?= htmlspecialchars($patient_detail_base_url) ?>"
    data-appointments-url="<?= htmlspecialchars($appointments_url) ?>"
    data-clinical-url="<?= htmlspecialchars($clinical_url) ?>" data-billing-url="<?= htmlspecialchars($billing_url) ?>"
    data-laboratory-url="<?= htmlspecialchars($laboratory_url) ?>" data-staff-url="<?= htmlspecialchars($staff_url) ?>"
    data-login-url="<?= htmlspecialchars($login_url) ?>" data-page-size="10">

    <div class="page-shell">
        <aside class="sidebar" aria-label="Primary navigation">
            <div>
                <div class="sidebar-brand">
                    <div class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 64 64">
                            <rect x="2" y="2" width="60" height="60" rx="16"></rect>
                            <path d="M13 34h10l4-10 8 22 6-16h10" fill="none" stroke="currentColor" stroke-width="4"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($hospital_name) ?></strong>
                    </div>
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
                    <a href="ehr_record.php" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5" />
                                <path d="M8 13h8M8 17h8M8 9h3" />
                            </svg></span>
                        <span>Clinical (EHR)</span>
                    </a>
                    <a href="billing.php" class="nav-item active">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2Zm0 10H4V8h16ZM6 12h4" />
                            </svg></span>
                        <span>Billing &amp; NHIS</span>
                        <span class="nav-arrow">›</span>
                    </a>
                    <a href="laboratory.php" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Zm2 2h2v4.12l4.98 8.63a1 1 0 0 1-.87 1.5H6.9a1 1 0 0 1-.87-1.5L11 8.12Z" />
                            </svg></span>
                        <span>Laboratory</span>
                    </a>
                    <?php if ($role === 'administrator'): ?>
                        <a href="staff_management.php" class="nav-item" id="staffNavItem">
                            <span class="nav-icon"><svg viewBox="0 0 24 24">
                                    <path
                                        d="M12 8a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5Zm7.5-1a2.5 2.5 0 1 0-2.45-3h-.05a4.94 4.94 0 0 1 0 5.97h.05A2.5 2.5 0 0 0 19.5 9ZM21 18v-2c0-1.64-1.2-3.06-3.03-4 1.11.95 1.78 2.06 1.78 3.29V18Z" />
                                </svg></span>
                            <span>Staff Management</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="sidebar-footer">
                <button type="button" class="logout-btn" id="logoutBtn" onclick="window.location.href='logout.php'">
                    <span aria-hidden="true">↪</span>
                    <span>Logout</span>
                </button>
                <div class="system-panel">
                    <div class="system-panel-label">System Status</div>
                    <div class="system-panel-row">
                        <span class="status-dot status-active" id="systemStatusDot"></span>
                        <span id="systemStatusText">Server Online</span>
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
                    <input type="search" id="globalPatientSearchInput"
                        placeholder="Search patient by Name, ID or NHIS..." autocomplete="off" />
                    <div class="search-results hidden" id="globalSearchResults" role="listbox"
                        aria-label="Patient search results"></div>
                </div>

                <div class="topbar-actions">
                    <button type="button" class="icon-btn" title="Notifications">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5L3 18v1h18v-1Zm-2 1H7v-6a5 5 0 1 1 10 0Z" />
                        </svg>
                    </button>

                    <div class="profile-chip">
                        <div class="profile-copy">
                            <strong id="profileName"><?= $full_name ?></strong>
                            <span id="profileRole"><?= ucfirst($role) ?></span>
                        </div>
                        <div class="profile-avatar" id="profileAvatar"><?= $initials ?></div>
                        <span class="presence-dot"></span>
                    </div>
                </div>
            </header>

            <main class="content-shell">
                <div class="page-feedback hidden" id="pageFeedback"></div>

                <section class="hero-row">
                    <div>
                        <h1>Billing &amp; Insurance</h1>
                        <p>Manage patient invoices, NHIS claims, and hospital revenue.</p>
                    </div>
                    <div class="hero-actions">
                        <button type="button" class="secondary-btn" id="exportCsvBtn">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M12 3v10.59l3.3-3.29 1.4 1.41-5.7 5.7-5.7-5.7 1.4-1.41 3.3 3.29V3ZM5 19h14v2H5Z" />
                            </svg>
                            <span>Export CSV</span>
                        </button>
                        <button type="button" class="primary-btn" id="openInvoiceModalBtn">
                            <svg viewBox="0 0 24 24">
                                <path d="M11 5h2v14h-2zM5 11h14v2H5z" />
                            </svg>
                            <span>Generate New Invoice</span>
                        </button>
                    </div>
                </section>

                <section class="stats-grid" aria-label="Billing summary statistics">
                    <article class="stat-card accent-blue">
                        <div class="stat-label">Total Monthly Revenue</div>
                        <div class="stat-value" id="monthlyRevenueStat">GHS 0.00</div>
                        <div class="stat-meta" id="monthlyRevenueMeta">
                            Paid revenue this month
                        </div>
                    </article>
                    <article class="stat-card accent-green">
                        <div class="stat-label">Pending NHIS Claims</div>
                        <div class="stat-value" id="pendingNhisClaimsStat">GHS 0.00</div>
                        <div class="stat-meta" id="pendingNhisClaimsMeta">
                            Outstanding NHIS claim value
                        </div>
                    </article>
                    <article class="stat-card accent-amber">
                        <div class="stat-label">Outstanding Balances</div>
                        <div class="stat-value" id="outstandingBalancesStat">
                            GHS 0.00
                        </div>
                        <div class="stat-meta" id="outstandingBalancesMeta">
                            Unpaid or partially paid invoices
                        </div>
                    </article>
                    <article class="stat-card accent-slate">
                        <div class="stat-label">Transactions Today</div>
                        <div class="stat-value" id="transactionsTodayStat">0</div>
                        <div class="stat-meta" id="transactionsTodayMeta">
                            Invoices created or paid today
                        </div>
                    </article>
                </section>

                <section class="analytics-grid">
                    <article class="panel analytics-card">
                        <div class="panel-header">
                            <div>
                                <h2>Revenue Distribution</h2>
                                <p>Weekly breakdown by payment method.</p>
                            </div>
                            <button type="button" class="text-btn" id="refreshAnalyticsBtn">
                                Refresh
                            </button>
                        </div>
                        <div class="chart-stage">
                            <canvas id="revenueChart" width="680" height="320"
                                aria-label="Revenue distribution chart"></canvas>
                        </div>
                        <div class="chart-legend" id="chartLegend"></div>
                    </article>

                    <article class="panel quick-payment-card">
                        <div class="panel-header">
                            <div>
                                <h2>Quick Payment Entry</h2>
                                <p>Record a payment for an existing invoice.</p>
                            </div>
                        </div>

                        <form id="quickPaymentForm" class="quick-payment-form">
                            <label class="field-group">
                                <span>Invoice Number</span>
                                <input type="text" id="quickPaymentInvoiceNumber" name="invoice_number"
                                    placeholder="e.g. INV-2023-8801" autocomplete="off" required />
                            </label>

                            <label class="field-group">
                                <span>Payment Mode</span>
                                <select id="quickPaymentMethod" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="momo">MoMo</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="nhis_claim">NHIS Claim</option>
                                </select>
                            </label>

                            <label class="field-group">
                                <span>Amount Received (GHS)</span>
                                <input type="number" id="quickPaymentAmount" name="amount" min="0.01" step="0.01"
                                    placeholder="0.00" required />
                            </label>

                            <label class="field-group">
                                <span>Reference Number</span>
                                <input type="text" id="quickPaymentReference" name="reference_number"
                                    placeholder="Optional MoMo or bank reference" />
                            </label>

                            <div class="form-alert hidden" id="quickPaymentAlert"></div>

                            <button type="submit" class="primary-btn full-width" id="confirmTransactionBtn">
                                Confirm Transaction
                            </button>
                        </form>
                    </article>
                </section>

                <section class="panel invoices-panel">
                    <div class="panel-header invoices-header">
                        <div>
                            <h2>Recent Invoices</h2>
                        </div>
                        <div class="invoice-toolbar">
                            <div class="search-wrap inline-search-wrap">
                                <svg class="search-icon" viewBox="0 0 24 24">
                                    <path
                                        d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                                </svg>
                                <input type="search" id="invoiceSearchInput"
                                    placeholder="Filter by Invoice Number or Patient..." autocomplete="off" />
                            </div>
                            <select id="coverageFilterSelect" class="toolbar-select">
                                <option value="">All Coverage</option>
                                <option value="none">No NHIS</option>
                                <option value="partial">Partial NHIS</option>
                                <option value="full">Full NHIS</option>
                            </select>
                            <select id="paymentStatusFilterSelect" class="toolbar-select">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                                <option value="overdue">Overdue</option>
                                <option value="waived">Waived</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice Number</th>
                                    <th>Patient</th>
                                    <th>Amount (GHS)</th>
                                    <th>NHIS Coverage</th>
                                    <th>Payment Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoiceTableBody"></tbody>
                        </table>
                        <div class="empty-state hidden" id="invoiceEmptyState">
                            <strong>No invoices found.</strong>
                            <span>Adjust the filters or generate a new invoice.</span>
                        </div>
                    </div>

                    <div class="panel-footer paginator-row">
                        <div class="results-copy" id="resultsCopy">
                            Showing 0 of 0 invoices
                        </div>
                        <div class="paginator-actions">
                            <button type="button" class="secondary-btn compact-btn" id="prevPageBtn">
                                Previous
                            </button>
                            <span class="page-chip" id="pageIndicator">Page 1</span>
                            <button type="button" class="secondary-btn compact-btn" id="nextPageBtn">
                                Next
                            </button>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div class="modal-backdrop hidden" id="invoiceModalBackdrop" aria-hidden="true">
        <section class="modal-sheet large-modal" role="dialog" aria-modal="true" aria-labelledby="invoiceModalTitle">
            <header class="modal-header">
                <div>
                    <h2 id="invoiceModalTitle">Generate New Invoice</h2>
                    <p>Fill in details to generate a patient billing statement.</p>
                </div>
                <button type="button" class="modal-close-btn" id="closeInvoiceModalBtn"
                    aria-label="Close invoice modal">×</button>
            </header>

            <form id="invoiceForm" class="invoice-form">
                <div class="invoice-top-grid">
                    <label class="field-group">
                        <span>Invoice Number</span>
                        <input type="text" id="invoiceNumberPreview" readonly />
                        <small>Auto-generated preview. Server returns the final <code>invoice_number</code>.</small>
                    </label>

                    <div class="field-group patient-search-group">
                        <span>Patient Search</span>
                        <div class="search-wrap modal-search-wrap">
                            <svg class="search-icon" viewBox="0 0 24 24">
                                <path
                                    d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                            </svg>
                            <input type="search" id="invoicePatientSearchInput"
                                placeholder="Search patient by name, patient ID or NHIS..." autocomplete="off" />
                            <div class="search-results hidden" id="invoicePatientResults"></div>
                        </div>
                        <div class="selected-patient hidden" id="selectedPatientChip"></div>
                    </div>
                </div>

                <section class="line-items-panel">
                    <div class="section-header">
                        <h3>Billing Items</h3>
                        <span>All amounts in GHS</span>
                    </div>

                    <div class="line-items-table-wrap">
                        <table class="line-items-table">
                            <thead>
                                <tr>
                                    <th>Service ID</th>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Unit Price (GHS)</th>
                                    <th>Line Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsBody"></tbody>
                        </table>
                    </div>

                    <button type="button" class="secondary-btn compact-btn" id="addLineItemBtn">
                        + Add New Item
                    </button>
                </section>

                <div class="invoice-main-grid">
                    <div class="invoice-form-column">
                        <div class="split-grid two-col">
                            <label class="field-group">
                                <span>NHIS Coverage Status</span>
                                <select id="nhisCoverageStatusSelect" name="nhis_coverage_status">
                                    <option value="none">No Coverage</option>
                                    <option value="partial">Partial Coverage</option>
                                    <option value="full">Full Coverage</option>
                                </select>
                            </label>
                            <label class="field-group">
                                <span>Payment Method</span>
                                <select id="invoicePaymentMethodSelect" name="payment_method">
                                    <option value="cash">Cash</option>
                                    <option value="momo">MoMo</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="nhis_claim">NHIS Claim</option>
                                </select>
                            </label>
                        </div>

                        <div class="split-grid three-col">
                            <label class="field-group">
                                <span>NHIS Coverage (%)</span>
                                <input type="number" id="nhisCoveragePercentInput" name="nhis_coverage_percent" min="0"
                                    max="100" step="0.01" value="0" />
                            </label>
                            <label class="field-group">
                                <span>NHIS Auth Number</span>
                                <input type="text" id="nhisAuthNumberInput" name="nhis_auth_number"
                                    placeholder="Optional" />
                            </label>
                            <label class="field-group">
                                <span>Due Date</span>
                                <input type="date" id="invoiceDueDateInput" name="due_date" />
                            </label>
                        </div>

                        <label class="field-group">
                            <span>Clinical / Billing Notes</span>
                            <textarea id="clinicalBillingNotesInput" name="clinical_billing_notes" rows="4"
                                placeholder="Additional information for the accounts department or patient..."></textarea>
                        </label>
                    </div>

                    <aside class="totals-card">
                        <div class="summary-row">
                            <span>Gross Subtotal</span>
                            <strong id="grossSubtotalValue">GHS 0.00</strong>
                        </div>
                        <div class="summary-row">
                            <span>NHIS Deduction</span>
                            <strong class="deduction-copy" id="nhisDeductionValue">-GHS 0.00</strong>
                        </div>
                        <div class="summary-row emphasis">
                            <span>Total Payable</span>
                            <strong id="totalPayableValue">GHS 0.00</strong>
                        </div>
                        <label class="field-group amount-paid-group">
                            <span>Initial Amount Paid</span>
                            <input type="number" id="amountPaidInput" name="amount_paid" min="0" step="0.01"
                                value="0" />
                        </label>
                    </aside>
                </div>

                <div class="form-alert hidden" id="invoiceFormAlert"></div>

                <footer class="modal-footer">
                    <button type="button" class="secondary-btn" id="cancelInvoiceModalBtn">Cancel</button>
                    <button type="button" class="draft-btn" id="saveInvoiceDraftBtn">Save Draft</button>
                    <button type="submit" class="primary-btn" id="submitInvoiceBtn">Generate &amp; Print</button>
                </footer>
            </form>
        </section>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>

    <script>
        (() => {
            const root = document.body;
            const config = {
                hospitalName: root.dataset.hospitalName || 'Akwaaba Health',
                userName: root.dataset.userName || 'Dr. Kofi Mensah',
                userRole: root.dataset.userRole || 'administrator',
                dashboardUrl: root.dataset.dashboardUrl || '/dashboard/',
                patientsUrl: root.dataset.patientsUrl || '/patients/',
                patientDetailBaseUrl: root.dataset.patientDetailBaseUrl || '/patients/',
                appointmentsUrl: root.dataset.appointmentsUrl || '/appointments/',
                clinicalUrl: root.dataset.clinicalUrl || '/clinical/ehr/',
                billingUrl: root.dataset.billingUrl || '/billing/invoices/',
                laboratoryUrl: root.dataset.laboratoryUrl || '/laboratory/orders/',
                staffUrl: root.dataset.staffUrl || '/staff/',
                loginUrl: root.dataset.loginUrl || '/login/',
                dashboardApiUrl: root.dataset.dashboardApiUrl || '/api/v1/dashboard/',
                billingApiUrl: root.dataset.billingApiUrl || '/api/v1/billing/invoices/',
                billingStatsApiUrl: root.dataset.billingStatsApiUrl || '/api/v1/billing/invoices/stats/',
                billingExportApiUrl: root.dataset.billingExportApiUrl || '/api/v1/billing/invoices/export/',
                billingQuickPaymentApiUrl: root.dataset.billingQuickPaymentApiUrl || '/api/v1/billing/invoices/quick-payment/',
                patientSearchApiUrl: root.dataset.patientSearchApiUrl || '/api/v1/patients/search/',
                tokenRefreshUrl: root.dataset.tokenRefreshUrl || '/api/v1/auth/token/refresh/',
                logoutApiUrl: root.dataset.logoutApiUrl || '/api/v1/auth/logout/',
                pageSize: Number(root.dataset.pageSize || 10)
            };

            const els = {
                profileName: document.getElementById('profileName'),
                profileRole: document.getElementById('profileRole'),
                profileAvatar: document.getElementById('profileAvatar'),
                staffNavItem: document.getElementById('staffNavItem'),
                logoutBtn: document.getElementById('logoutBtn'),
                systemStatusDot: document.getElementById('systemStatusDot'),
                systemStatusText: document.getElementById('systemStatusText'),
                globalPatientSearchInput: document.getElementById('globalPatientSearchInput'),
                globalSearchResults: document.getElementById('globalSearchResults'),
                pageFeedback: document.getElementById('pageFeedback'),
                monthlyRevenueStat: document.getElementById('monthlyRevenueStat'),
                monthlyRevenueMeta: document.getElementById('monthlyRevenueMeta'),
                pendingNhisClaimsStat: document.getElementById('pendingNhisClaimsStat'),
                pendingNhisClaimsMeta: document.getElementById('pendingNhisClaimsMeta'),
                outstandingBalancesStat: document.getElementById('outstandingBalancesStat'),
                outstandingBalancesMeta: document.getElementById('outstandingBalancesMeta'),
                transactionsTodayStat: document.getElementById('transactionsTodayStat'),
                transactionsTodayMeta: document.getElementById('transactionsTodayMeta'),
                refreshAnalyticsBtn: document.getElementById('refreshAnalyticsBtn'),
                chartCanvas: document.getElementById('revenueChart'),
                chartLegend: document.getElementById('chartLegend'),
                quickPaymentForm: document.getElementById('quickPaymentForm'),
                quickPaymentInvoiceNumber: document.getElementById('quickPaymentInvoiceNumber'),
                quickPaymentMethod: document.getElementById('quickPaymentMethod'),
                quickPaymentAmount: document.getElementById('quickPaymentAmount'),
                quickPaymentReference: document.getElementById('quickPaymentReference'),
                quickPaymentAlert: document.getElementById('quickPaymentAlert'),
                invoiceSearchInput: document.getElementById('invoiceSearchInput'),
                coverageFilterSelect: document.getElementById('coverageFilterSelect'),
                paymentStatusFilterSelect: document.getElementById('paymentStatusFilterSelect'),
                invoiceTableBody: document.getElementById('invoiceTableBody'),
                invoiceEmptyState: document.getElementById('invoiceEmptyState'),
                resultsCopy: document.getElementById('resultsCopy'),
                prevPageBtn: document.getElementById('prevPageBtn'),
                nextPageBtn: document.getElementById('nextPageBtn'),
                pageIndicator: document.getElementById('pageIndicator'),
                exportCsvBtn: document.getElementById('exportCsvBtn'),
                openInvoiceModalBtn: document.getElementById('openInvoiceModalBtn'),
                invoiceModalBackdrop: document.getElementById('invoiceModalBackdrop'),
                closeInvoiceModalBtn: document.getElementById('closeInvoiceModalBtn'),
                cancelInvoiceModalBtn: document.getElementById('cancelInvoiceModalBtn'),
                invoiceForm: document.getElementById('invoiceForm'),
                invoiceNumberPreview: document.getElementById('invoiceNumberPreview'),
                invoicePatientSearchInput: document.getElementById('invoicePatientSearchInput'),
                invoicePatientResults: document.getElementById('invoicePatientResults'),
                selectedPatientChip: document.getElementById('selectedPatientChip'),
                lineItemsBody: document.getElementById('lineItemsBody'),
                addLineItemBtn: document.getElementById('addLineItemBtn'),
                nhisCoverageStatusSelect: document.getElementById('nhisCoverageStatusSelect'),
                invoicePaymentMethodSelect: document.getElementById('invoicePaymentMethodSelect'),
                nhisCoveragePercentInput: document.getElementById('nhisCoveragePercentInput'),
                nhisAuthNumberInput: document.getElementById('nhisAuthNumberInput'),
                invoiceDueDateInput: document.getElementById('invoiceDueDateInput'),
                clinicalBillingNotesInput: document.getElementById('clinicalBillingNotesInput'),
                grossSubtotalValue: document.getElementById('grossSubtotalValue'),
                nhisDeductionValue: document.getElementById('nhisDeductionValue'),
                totalPayableValue: document.getElementById('totalPayableValue'),
                amountPaidInput: document.getElementById('amountPaidInput'),
                invoiceFormAlert: document.getElementById('invoiceFormAlert'),
                saveInvoiceDraftBtn: document.getElementById('saveInvoiceDraftBtn'),
                submitInvoiceBtn: document.getElementById('submitInvoiceBtn'),
                toastStack: document.getElementById('toastStack')
            };

            const state = {
                invoices: [],
                totalCount: 0,
                currentPage: 1,
                nextUrl: null,
                previousUrl: null,
                search: '',
                coverageFilter: '',
                paymentStatusFilter: '',
                selectedPatient: null,
                lineItems: [],
                chartSeries: []
            };

            const formatCurrency = (value) => `GHS ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const formatNumber = (value) => Number(value || 0).toLocaleString('en-US');
            const lower = (value) => String(value || '').trim().toLowerCase();
            const capitalize = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase());
            const initials = (value) => String(value || '').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'AH';
            const debounce = (fn, wait = 300) => {
                let timeout;
                return (...args) => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => fn(...args), wait);
                };
            };

            function readCookie(name) {
                const cookies = document.cookie ? document.cookie.split('; ') : [];
                for (const part of cookies) {
                    const [key, ...rest] = part.split('=');
                    if (key === name) return decodeURIComponent(rest.join('='));
                }
                return '';
            }

            // Replace async calls with placeholder arrays for default UI display
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.textContent = message;
                els.toastStack.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(8px)';
                    toast.style.transition = '0.25s ease';
                    setTimeout(() => toast.remove(), 250);
                }, 3200);
            }

            function setPageFeedback(message = '', type = 'error') {
                if (!message) {
                    els.pageFeedback.className = 'page-feedback hidden';
                    els.pageFeedback.textContent = '';
                    return;
                }
                els.pageFeedback.className = 'page-feedback';
                if (type === 'info') {
                    els.pageFeedback.style.background = '#eef6ff';
                    els.pageFeedback.style.borderColor = '#d1e3f8';
                    els.pageFeedback.style.color = '#2f6ea0';
                } else {
                    els.pageFeedback.style.background = '#fff4f4';
                    els.pageFeedback.style.borderColor = '#f0d4d4';
                    els.pageFeedback.style.color = '#a64949';
                }
                els.pageFeedback.textContent = message;
            }

            function setInlineAlert(el, message = '', type = 'error') {
                if (!message) {
                    el.className = 'form-alert hidden';
                    el.textContent = '';
                    return;
                }
                el.className = `form-alert ${type}`;
                el.textContent = message;
            }

            function renderUser() {
                // UI uses php defaults provided in header
                els.systemStatusDot.className = 'status-dot status-active';
                els.systemStatusText.textContent = 'Server Online';
            }

            function normalizePatientSummary(patientLike) {
                if (!patientLike) return { id: '', name: 'Unknown patient', patientId: '—', nhis: '—', phone: '' };
                if (typeof patientLike === 'string') return { id: patientLike, name: patientLike, patientId: '—', nhis: '—', phone: '' };
                return {
                    id: patientLike.id || patientLike.uuid || '',
                    name: patientLike.full_name || patientLike.name || patientLike.patient_name || 'Unknown patient',
                    patientId: patientLike.patient_id || patientLike.code || '—',
                    nhis: patientLike.nhis_number || patientLike.nhis || '—',
                    phone: patientLike.phone || ''
                };
            }

            function normalizeInvoice(invoice) {
                const patientObject = invoice.patient_detail || invoice.patient_object || invoice.patient_profile || (typeof invoice.patient === 'object' ? invoice.patient : null);
                const patientSummary = normalizePatientSummary(patientObject || {
                    id: typeof invoice.patient === 'string' ? invoice.patient : invoice.patient_id,
                    full_name: invoice.patient_name,
                    patient_id: invoice.patient_code || invoice.patient_identifier,
                    nhis_number: invoice.patient_nhis_number
                });
                return {
                    id: invoice.id || invoice.uuid || invoice.invoice_number,
                    invoice_number: invoice.invoice_number || invoice.invoice_id || '—',
                    patient: patientSummary,
                    nhis_coverage_status: lower(invoice.nhis_coverage_status || invoice.coverage_status || 'none'),
                    nhis_coverage_percent: Number(invoice.nhis_coverage_percent || invoice.coverage_percent || 0),
                    nhis_auth_number: invoice.nhis_auth_number || '',
                    payment_method: lower(invoice.payment_method || 'cash'),
                    payment_status: lower(invoice.payment_status || 'pending'),
                    gross_subtotal: Number(invoice.gross_subtotal || invoice.subtotal || 0),
                    nhis_deduction: Number(invoice.nhis_deduction || invoice.coverage_deduction || 0),
                    total_payable: Number(invoice.total_payable || invoice.amount || invoice.gross_total || 0),
                    amount_paid: Number(invoice.amount_paid || invoice.paid_amount || 0),
                    clinical_billing_notes: invoice.clinical_billing_notes || '',
                    line_items: Array.isArray(invoice.line_items) ? invoice.line_items : [],
                    due_date: invoice.due_date || '',
                    paid_at: invoice.paid_at || '',
                    created_at: invoice.created_at || invoice.date || '',
                    raw: invoice
                };
            }

            function todayIso() {
                return new Date().toISOString().slice(0, 10);
            }

            function deriveStatsFromInvoices(invoices) {
                const today = todayIso();
                let monthlyRevenue = 0;
                let pendingNhisClaims = 0;
                let outstandingBalances = 0;
                let transactionsToday = 0;
                const groups = {
                    Mon: { card: 0, cash: 0, nhis: 0 },
                    Tue: { card: 0, cash: 0, nhis: 0 },
                    Wed: { card: 0, cash: 0, nhis: 0 },
                    Thu: { card: 0, cash: 0, nhis: 0 },
                    Fri: { card: 0, cash: 0, nhis: 0 }
                };
                const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

                invoices.forEach((invoice) => {
                    const outstanding = Math.max(invoice.total_payable - invoice.amount_paid, 0);
                    if (invoice.payment_status === 'paid') monthlyRevenue += invoice.amount_paid || invoice.total_payable;
                    if (invoice.payment_method === 'nhis_claim' && ['pending', 'partial', 'overdue'].includes(invoice.payment_status)) pendingNhisClaims += outstanding || invoice.total_payable;
                    if (['pending', 'partial', 'overdue'].includes(invoice.payment_status)) outstandingBalances += outstanding || invoice.total_payable;
                    const createdDate = invoice.created_at ? new Date(invoice.created_at) : null;
                    const paidDate = invoice.paid_at ? new Date(invoice.paid_at) : null;
                    if (createdDate && !Number.isNaN(createdDate.getTime()) && createdDate.toISOString().slice(0, 10) === today) transactionsToday += 1;
                    else if (paidDate && !Number.isNaN(paidDate.getTime()) && paidDate.toISOString().slice(0, 10) === today) transactionsToday += 1;

                    const chartDate = createdDate && !Number.isNaN(createdDate.getTime()) ? createdDate : paidDate;
                    if (!chartDate || Number.isNaN(chartDate.getTime())) return;
                    const label = weekdayLabels[chartDate.getDay()];
                    if (!groups[label]) return;
                    const bucket = invoice.payment_method === 'nhis_claim' ? 'nhis' : (['cash', 'momo'].includes(invoice.payment_method) ? 'cash' : 'card');
                    groups[label][bucket] += invoice.total_payable || invoice.amount_paid || 0;
                });

                const chartData = Object.entries(groups).map(([label, values]) => ({
                    label,
                    cash_momo: values.cash,
                    nhis_claim: values.nhis,
                    card_bank: values.card
                }));

                return { monthlyRevenue, pendingNhisClaims, outstandingBalances, transactionsToday, chartData };
            }

            function formatDate(dateLike) {
                if (!dateLike) return '—';
                const date = new Date(dateLike);
                if (Number.isNaN(date.getTime())) return '—';
                return date.toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' });
            }

            function coverageBadge(status) {
                const normalized = lower(status || 'none');
                const map = {
                    none: 'No NHIS',
                    partial: 'Partial Coverage',
                    full: 'Full Coverage'
                };
                return `<span class="badge coverage-${normalized}">${map[normalized] || capitalize(normalized)}</span>`;
            }

            function paymentStatusBadge(status) {
                const normalized = lower(status || 'pending');
                return `<span class="badge status-${normalized}">${capitalize(normalized)}</span>`;
            }

            function invoiceMatchesFilters(invoice) {
                const search = lower(state.search);
                const patientName = lower(invoice.patient.name);
                const patientId = lower(invoice.patient.patientId);
                const invoiceNumber = lower(invoice.invoice_number);
                const matchesSearch = !search || invoiceNumber.includes(search) || patientName.includes(search) || patientId.includes(search) || lower(invoice.patient.nhis).includes(search);
                const matchesCoverage = !state.coverageFilter || invoice.nhis_coverage_status === state.coverageFilter;
                const matchesStatus = !state.paymentStatusFilter || invoice.payment_status === state.paymentStatusFilter;
                return matchesSearch && matchesCoverage && matchesStatus;
            }

            function renderInvoiceTable() {
                const filtered = state.invoices.filter(invoiceMatchesFilters);
                els.invoiceTableBody.innerHTML = '';
                if (!filtered.length) {
                    els.invoiceEmptyState.classList.remove('hidden');
                } else {
                    els.invoiceEmptyState.classList.add('hidden');
                }

                filtered.forEach((invoice) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td><a class="invoice-link" href="${config.billingUrl}?invoice=${encodeURIComponent(invoice.id)}">${invoice.invoice_number}</a></td>
                <td>
                    <div class="patient-copy">
                        <strong>${invoice.patient.name}</strong>
                        <small>${invoice.patient.patientId}</small>
                    </div>
                </td>
                <td><span class="amount-copy">${formatCurrency(invoice.total_payable)}</span></td>
                <td>${coverageBadge(invoice.nhis_coverage_status)}</td>
                <td>${paymentStatusBadge(invoice.payment_status)}</td>
                <td>${formatDate(invoice.created_at || invoice.due_date)}</td>
                <td>
                    <div class="row-actions">
                        <button type="button" class="row-icon-btn" data-action="print" data-id="${invoice.id}" title="Print invoice">
                            <svg viewBox="0 0 24 24"><path d="M7 8V3h10v5M6 17H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/></svg>
                        </button>
                        <button type="button" class="row-icon-btn" data-action="settle" data-number="${invoice.invoice_number}" data-balance="${Math.max(invoice.total_payable - invoice.amount_paid, 0).toFixed(2)}" title="Use quick payment">
                            <svg viewBox="0 0 24 24"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </button>
                    </div>
                </td>
            `;
                    els.invoiceTableBody.appendChild(tr);
                });

                const visibleCount = filtered.length;
                els.resultsCopy.textContent = `Showing ${visibleCount} of ${formatNumber(state.totalCount || visibleCount)} invoices`;
                els.pageIndicator.textContent = `Page ${state.currentPage}`;
                els.prevPageBtn.disabled = !state.previousUrl;
                els.nextPageBtn.disabled = !state.nextUrl;
            }

            async function loadInvoices() {
                // Fallback or empty logic
                renderInvoiceTable();
                loadBillingStats();
            }

            function renderStats(stats) {
                els.monthlyRevenueStat.textContent = formatCurrency(stats.monthlyRevenue);
                els.pendingNhisClaimsStat.textContent = formatCurrency(stats.pendingNhisClaims);
                els.outstandingBalancesStat.textContent = formatCurrency(stats.outstandingBalances);
                els.transactionsTodayStat.textContent = formatNumber(stats.transactionsToday);
            }

            function loadBillingStats() {
                const derived = deriveStatsFromInvoices(state.invoices);
                renderStats(derived);
                drawRevenueChart(derived.chartData);
            }

            function normalizeChartData(data) {
                if (!Array.isArray(data) || !data.length) return [];
                return data.map((item) => ({
                    label: item.label || item.day || item.weekday || item.month || '—',
                    cash_momo: Number(item.cash_momo || item.cash || item.cash_total || item.momo || item.mobile_money || 0),
                    nhis_claim: Number(item.nhis_claim || item.nhis || item.nhis_claims || 0),
                    card_bank: Number(item.card_bank || item.card || item.bank_transfer || item.other || 0)
                }));
            }

            function drawRevenueChart(rawData) {
                const data = normalizeChartData(rawData);
                state.chartSeries = data;
                const canvas = els.chartCanvas;
                const ctx = canvas.getContext('2d');
                const dpr = window.devicePixelRatio || 1;
                const rect = canvas.getBoundingClientRect();
                const width = Math.max(Math.floor(rect.width || canvas.width), 320);
                const height = Math.max(Math.floor(rect.height || canvas.height), 260);
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                ctx.clearRect(0, 0, width, height);

                const colors = [
                    { key: 'cash_momo', label: 'Cash / MoMo', color: '#1f6fb5' },
                    { key: 'nhis_claim', label: 'NHIS Claim', color: '#4ea1dc' },
                    { key: 'card_bank', label: 'Card / Bank', color: '#87d2f0' }
                ];
                els.chartLegend.innerHTML = colors.map((item) => `<span class="legend-item"><span class="legend-swatch" style="background:${item.color}"></span>${item.label}</span>`).join('');

                const fallback = [
                    { label: 'Mon', cash_momo: 0, nhis_claim: 0, card_bank: 0 },
                    { label: 'Tue', cash_momo: 0, nhis_claim: 0, card_bank: 0 },
                    { label: 'Wed', cash_momo: 0, nhis_claim: 0, card_bank: 0 },
                    { label: 'Thu', cash_momo: 0, nhis_claim: 0, card_bank: 0 },
                    { label: 'Fri', cash_momo: 0, nhis_claim: 0, card_bank: 0 }
                ];
                const points = data.length ? data : fallback;
                const maxValue = Math.max(1, ...points.flatMap((item) => colors.map((series) => item[series.key] || 0)));
                const plot = { x: 56, y: 26, width: width - 86, height: height - 72 };
                const groups = points.length;
                const groupWidth = plot.width / groups;
                const barWidth = Math.min(28, groupWidth / 4.2);

                ctx.strokeStyle = '#dfe7f1';
                ctx.fillStyle = '#8a93a3';
                ctx.font = '12px Inter';
                ctx.lineWidth = 1;

                for (let step = 0; step <= 4; step += 1) {
                    const y = plot.y + (plot.height * step / 4);
                    ctx.beginPath();
                    ctx.moveTo(plot.x, y);
                    ctx.lineTo(plot.x + plot.width, y);
                    ctx.stroke();
                    const value = ((maxValue * (4 - step)) / 4);
                    ctx.fillText(formatNumber(Math.round(value)), 10, y + 4);
                }

                points.forEach((item, index) => {
                    const groupX = plot.x + index * groupWidth + groupWidth / 2;
                    colors.forEach((series, seriesIndex) => {
                        const value = item[series.key] || 0;
                        const barHeight = (value / maxValue) * (plot.height - 4);
                        const x = groupX - (barWidth * 1.7) + seriesIndex * (barWidth + 6);
                        const y = plot.y + plot.height - barHeight;
                        const radius = 7;
                        ctx.fillStyle = series.color;
                        roundRect(ctx, x, y, barWidth, barHeight || 2, radius);
                        ctx.fill();
                    });
                    ctx.fillStyle = '#556274';
                    ctx.fillText(item.label, groupX - 12, plot.y + plot.height + 22);
                });
            }

            function roundRect(ctx, x, y, width, height, radius) {
                ctx.beginPath();
                ctx.moveTo(x + radius, y);
                ctx.arcTo(x + width, y, x + width, y + height, radius);
                ctx.arcTo(x + width, y + height, x, y + height, radius);
                ctx.arcTo(x, y + height, x, y, radius);
                ctx.arcTo(x, y, x + width, y, radius);
                ctx.closePath();
            }

            // Modal listeners
            els.openInvoiceModalBtn.addEventListener('click', () => {
                els.invoiceModalBackdrop.classList.remove('hidden');
            });

            els.closeInvoiceModalBtn.addEventListener('click', () => {
                els.invoiceModalBackdrop.classList.add('hidden');
            });

            els.cancelInvoiceModalBtn.addEventListener('click', () => {
                els.invoiceModalBackdrop.classList.add('hidden');
            });

            els.invoiceModalBackdrop.addEventListener('click', (e) => {
                if (e.target === els.invoiceModalBackdrop) {
                    els.invoiceModalBackdrop.classList.add('hidden');
                }
            });

            // Initialize
            renderUser();
            loadInvoices();

        })();
    </script>
</body>

</html>