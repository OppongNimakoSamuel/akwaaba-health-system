<?php
session_start();
require 'db.php';
$pdo = getDB();

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
$laboratory_requisition_url = 'laboratory_requisition.php';
$staff_url = 'staff_management.php';
$login_url = 'login.php';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akwaaba Health | Laboratory Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --line: #e4eaf2;
            --text: #162033;
            --muted: #6b7890;
            --primary: #1d6fb8;
            --primary-deep: #155b97;
            --primary-soft: #e9f3fd;
            --green: #22a06b;
            --amber: #c58518;
            --red: #d25b6b;
            --slate: #607089;
            --shadow: 0 12px 28px rgba(26, 52, 83, 0.08);
            --radius: 18px;
            --radius-sm: 12px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: Inter, system-ui, sans-serif;
            color: var(--text);
            background: var(--bg);
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

        .muted-text {
            color: var(--muted);
        }

        .page-shell {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            min-height: 100vh;
        }

        .requisition-shell {
            grid-template-columns: 240px minmax(0, 1fr);
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            background: linear-gradient(180deg, #fbfcfe 0%, #f5f8fd 100%);
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 18px;
        }

        .compact-sidebar {
            padding: 20px 16px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            margin-bottom: 28px;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--primary);
            border-radius: 10px;
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: currentColor;
        }

        .sidebar-subtitle {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 14px;
            color: #44516a;
            font-weight: 600;
            border-radius: 14px;
            transition: .2s ease;
        }

        .nav-item:hover {
            background: #eff5fc;
            color: var(--primary);
        }

        .nav-item.active {
            background: #dfeefa;
            color: var(--primary);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: inline-grid;
            place-items: center;
        }

        .nav-icon svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
        }

        .nav-arrow {
            margin-left: auto;
        }

        .sidebar-footer {
            display: grid;
            gap: 16px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: transparent;
            border: none;
            color: #c45d6a;
            font-weight: 700;
            padding: 8px 6px;
            justify-content: flex-start;
        }

        .system-panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
        }

        .system-panel-label {
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .72rem;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background: var(--green);
            box-shadow: 0 0 0 4px rgba(34, 160, 107, .15);
        }

        .status-dot.status-alert {
            background: var(--red);
            box-shadow: 0 0 0 4px rgba(210, 91, 107, .15);
        }

        .main-stage {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, .78);
            backdrop-filter: blur(16px);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .minimal-topbar {
            padding: 16px 22px;
        }

        .breadcrumb-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-weight: 600;
        }

        .breadcrumb-row strong {
            color: var(--text);
        }

        .topbar-actions,
        .requisition-topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-wrap {
            position: relative;
            min-width: 340px;
        }

        .top-search-wrap {
            width: min(540px, 48vw);
        }

        .compact-search-wrap {
            min-width: 300px;
        }

        .search-wrap input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            height: 46px;
            background: #fbfcff;
            padding: 0 16px 0 42px;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .search-wrap input:focus,
        select:focus,
        textarea:focus,
        .field-group input:focus {
            border-color: #86b6df;
            box-shadow: 0 0 0 4px rgba(29, 111, 184, .12);
        }

        .search-icon {
            width: 18px;
            height: 18px;
            position: absolute;
            top: 14px;
            left: 14px;
            fill: #92a1b8;
        }

        .search-results,
        .inline-search-results {
            position: absolute;
            z-index: 12;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            max-height: 320px;
            overflow-y: auto;
        }

        .inline-search-results {
            position: static;
            margin-top: 8px;
        }

        .search-result-item {
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f7;
            display: grid;
            gap: 2px;
            cursor: pointer;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: #f7fbff;
        }

        .search-result-item strong {
            font-size: .95rem;
        }

        .search-result-item span {
            font-size: .82rem;
            color: var(--muted);
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--surface);
            display: grid;
            place-items: center;
            color: #54657d;
        }

        .icon-btn svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
        }

        .profile-chip,
        .compact-profile-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-copy {
            display: grid;
            gap: 2px;
            text-align: right;
        }

        .profile-copy strong {
            font-size: .94rem;
        }

        .profile-copy span {
            font-size: .78rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: capitalize;
        }

        .profile-avatar,
        .patient-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e3eefb, #bfd8f2);
            color: var(--primary-deep);
            display: grid;
            place-items: center;
            font-weight: 800;
        }

        .presence-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3bd46f;
            position: absolute;
            right: 0;
            bottom: 0;
            border: 2px solid white;
        }

        .system-mini {
            background: #f6fbff;
            border: 1px solid #dcecff;
            border-radius: 12px;
            padding: 10px 12px;
            display: grid;
        }

        .tiny-label {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }

        .content-shell {
            padding: 28px;
            display: grid;
            gap: 24px;
        }

        .requisition-content {
            padding: 24px;
        }

        .hero-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .hero-row h1 {
            margin: 0 0 6px;
            font-size: 2rem;
        }

        .hero-row p {
            margin: 0;
            color: var(--muted);
            font-weight: 500;
        }

        .compact-hero-row h1 {
            font-size: 1.9rem;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .primary-btn,
        .secondary-btn,
        .ghost-btn,
        .text-btn,
        .pagination-btn,
        .chip-btn,
        .urgency-chip {
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform .15s ease, box-shadow .2s ease;
        }

        .primary-btn {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 18px rgba(29, 111, 184, .18);
        }

        .primary-btn:hover {
            background: var(--primary-deep);
        }

        .secondary-btn,
        .pagination-btn,
        .chip-btn {
            background: white;
            color: #43516a;
            border: 1px solid var(--line);
        }

        .ghost-btn {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--line);
        }

        .text-btn {
            background: transparent;
            color: var(--primary);
            padding: 0;
        }

        .inline-action-btn {
            padding-inline: 18px;
        }

        .full-width {
            width: 100%;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: 0 8px 20px rgba(26, 52, 83, 0.04);
        }

        .stat-card .stat-label {
            color: var(--muted);
            font-weight: 700;
            font-size: .9rem;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .stat-card .stat-meta {
            color: var(--muted);
            font-size: .88rem;
        }

        .accent-blue {
            border-left: 4px solid var(--primary);
        }

        .accent-slate {
            border-left: 4px solid var(--slate);
        }

        .accent-red {
            border-left: 4px solid var(--red);
        }

        .accent-amber {
            border-left: 4px solid var(--amber);
        }

        .lab-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 22px;
        }

        .lab-side-card,
        .lab-main-card,
        .bottom-panel,
        .patient-selection-card,
        .requisition-form-card,
        .order-summary-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: 0 10px 26px rgba(26, 52, 83, .04);
        }

        .lab-side-card,
        .lab-main-card,
        .patient-selection-card,
        .requisition-form-card,
        .order-summary-card {
            padding: 22px;
        }

        .panel-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .panel-title-row h2,
        .section-block-header h2 {
            margin: 0 0 6px;
            font-size: 1.45rem;
        }

        .panel-title-row p,
        .section-block-header p,
        .section-eyebrow,
        .bottom-panel p,
        .hint-card p {
            margin: 0;
            color: var(--muted);
        }

        .section-eyebrow {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .worklist-header {
            margin-bottom: 14px;
        }

        .segmented-controls {
            display: inline-flex;
            padding: 4px;
            background: #f4f8fc;
            border-radius: 13px;
            gap: 6px;
        }

        .chip-btn.active,
        .urgency-chip.active {
            background: var(--primary);
            color: white;
            border-color: transparent;
        }

        .worklist-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .toolbar-search {
            flex: 1;
        }

        .toolbar-search input {
            width: 100%;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            padding: 0 14px;
            background: #fbfcff;
        }

        .toolbar-filters {
            display: flex;
            gap: 12px;
        }

        select,
        textarea,
        .field-group input {
            width: 100%;
            border: 1px solid var(--line);
            background: #fbfcff;
            border-radius: 12px;
            min-height: 46px;
            padding: 11px 14px;
            color: var(--text);
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 110px;
        }

        .field-group {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        .field-group label {
            font-size: .82rem;
            font-weight: 700;
            color: #52617a;
        }

        .compact-field {
            margin-bottom: 0;
        }

        .two-col-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .selected-patient-chip {
            background: #f0f7ff;
            color: var(--primary-deep);
            border: 1px solid #cfe4fb;
            border-radius: 12px;
            padding: 10px 12px;
            font-weight: 700;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
        }

        .data-table th {
            font-size: .78rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 800;
        }

        .data-table td {
            font-size: .93rem;
        }

        .table-wrap {
            overflow: auto;
        }

        .order-id-link {
            color: var(--primary);
            font-weight: 800;
        }

        .patient-cell strong,
        .test-stack strong {
            display: block;
        }

        .small-meta,
        .test-stack span,
        .patient-cell span {
            color: var(--muted);
            font-size: .78rem;
            display: block;
            margin-top: 2px;
        }

        .badge,
        .pill,
        .footer-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
        }

        .badge-routine {
            background: #f2f6fb;
            color: #5a6c84;
        }

        .badge-urgent {
            background: #fff0f0;
            color: #ce3d49;
        }

        .badge-stat {
            background: #ffe5dc;
            color: #cf5127;
        }

        .badge-pending {
            background: #fff7e3;
            color: #9f6c00;
        }

        .badge-sample_collected,
        .badge-processing {
            background: #eef5ff;
            color: var(--primary-deep);
        }

        .badge-completed {
            background: #ecfbf3;
            color: #1b8557;
        }

        .badge-cancelled {
            background: #fff0f2;
            color: #cb5163;
        }

        .badge-abnormal {
            background: #fff1f1;
            color: #c94755;
        }

        .result-positive {
            color: #c94658;
            font-weight: 800;
        }

        .result-pending {
            color: var(--muted);
            font-style: italic;
        }

        .row-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: white;
            font-size: .8rem;
            font-weight: 700;
            color: #46556e;
        }

        .action-btn.primary {
            color: var(--primary);
        }

        .action-btn.danger {
            color: #c35362;
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
            gap: 16px;
            color: var(--muted);
            font-weight: 600;
        }

        .pagination-actions {
            display: flex;
            gap: 10px;
        }

        .pagination-btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .bottom-panels-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .bottom-panel {
            padding: 20px;
        }

        .bottom-panel h3 {
            margin: 0 0 10px;
            font-size: 1.05rem;
        }

        .warning-panel {
            border-left: 4px solid var(--red);
        }

        .muted-panel {
            background: #f6fbff;
        }

        .status-list {
            display: grid;
            gap: 10px;
        }

        .status-list div {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .page-footer,
        .requisition-footer-status {
            color: var(--muted);
            text-align: center;
            font-weight: 600;
        }

        .modal {
            position: fixed;
            inset: 0;
            z-index: 40;
            display: grid;
            place-items: center;
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(14, 22, 38, .48);
            backdrop-filter: blur(4px);
        }

        .modal-card {
            position: relative;
            width: min(880px, calc(100vw - 32px));
            background: var(--surface);
            border-radius: 24px;
            box-shadow: 0 28px 64px rgba(14, 22, 38, .22);
            border: 1px solid rgba(255, 255, 255, .3);
        }

        .modal-lg {
            max-height: calc(100vh - 32px);
            overflow: hidden;
            display: grid;
            grid-template-rows: auto 1fr auto;
        }

        .modal-header,
        .modal-footer {
            padding: 20px 22px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .modal-header h2 {
            margin: 0 0 6px;
        }

        .modal-body {
            padding: 22px;
            overflow: auto;
        }

        .modal-footer {
            border-bottom: none;
            border-top: 1px solid var(--line);
            justify-content: flex-end;
        }

        .modal-feedback,
        .page-feedback {
            border-radius: 14px;
            padding: 12px 14px;
            font-weight: 700;
        }

        .feedback-error {
            background: #fff0f2;
            color: #bc4355;
            border: 1px solid #f5c2cb;
        }

        .feedback-success {
            background: #ecfbf3;
            color: #188556;
            border: 1px solid #bae3cd;
        }

        .feedback-info {
            background: #edf6ff;
            color: #1e6fb7;
            border: 1px solid #cbe1f7;
        }

        .result-items-grid {
            display: grid;
            gap: 16px;
        }

        .result-item-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            background: #fafcff;
        }

        .result-item-card h3 {
            margin: 0 0 12px;
            font-size: 1rem;
        }

        .inline-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .item-card-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .requisition-layout {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr) 320px;
            gap: 20px;
            align-items: start;
        }

        .patient-selection-card,
        .order-summary-card {
            position: sticky;
            top: 92px;
        }

        .patient-profile-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
            background: #fbfdff;
            display: grid;
            gap: 10px;
            justify-items: start;
        }

        .patient-profile-card.empty-state {
            justify-items: center;
            text-align: center;
        }

        .patient-profile-card .patient-avatar {
            width: 72px;
            height: 72px;
            font-size: 1.3rem;
        }

        .patient-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            width: 100%;
        }

        .metric-chip {
            background: #f4f8fd;
            border-radius: 12px;
            padding: 10px;
        }

        .hint-card {
            display: flex;
            gap: 12px;
            border: 1px dashed #d1e3f5;
            background: #f6fbff;
            border-radius: 16px;
            padding: 16px;
            margin-top: 16px;
        }

        .hint-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            font-weight: 800;
        }

        .section-block {
            display: grid;
            gap: 18px;
        }

        .section-block-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .responsive-two-col {
            gap: 16px;
        }

        .urgency-chip-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .urgency-chip {
            background: white;
            color: #52617a;
            border: 1px solid var(--line);
        }

        .nhis-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            border: 1px solid var(--line);
            background: #fbfdff;
            border-radius: 16px;
            flex-wrap: wrap;
        }

        .nhis-pill {
            padding: 8px 12px;
            border-radius: 999px;
            font-size: .8rem;
            font-weight: 800;
        }

        .nhis-pill.success {
            background: #ecfbf3;
            color: #1c8758;
        }

        .nhis-pill.warning {
            background: #fff7e3;
            color: #9f6c00;
        }

        .switch-row {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .switch-row input {
            display: none;
        }

        .switch-track {
            width: 48px;
            height: 28px;
            border-radius: 999px;
            background: #d4dce8;
            position: relative;
            transition: .2s;
        }

        .switch-thumb {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: white;
            position: absolute;
            top: 3px;
            left: 3px;
            transition: .2s;
            box-shadow: 0 4px 10px rgba(10, 20, 30, .18);
        }

        .switch-row input:checked+.switch-track {
            background: var(--primary);
        }

        .switch-row input:checked+.switch-track .switch-thumb {
            transform: translateX(20px);
        }

        .nhis-auth-field {
            min-width: 160px;
            flex: 1;
        }

        .summary-items-list {
            display: grid;
            gap: 12px;
            max-height: 380px;
            overflow: auto;
            margin-bottom: 16px;
        }

        .summary-item {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            display: grid;
            gap: 8px;
            background: #fbfdff;
        }

        .summary-item-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .summary-item strong {
            font-size: .95rem;
        }

        .summary-item-meta {
            color: var(--muted);
            font-size: .8rem;
        }

        .summary-item-remove {
            border: none;
            background: transparent;
            color: #c65767;
            font-weight: 800;
            padding: 0;
        }

        .summary-totals {
            display: grid;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            margin-bottom: 16px;
        }

        .summary-totals div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .summary-totals .grand-total {
            padding-top: 12px;
            border-top: 1px dashed #dce4ef;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .summary-actions-row {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .quick-reference-box {
            margin-top: 18px;
            display: grid;
            gap: 10px;
        }

        .quick-reference-box h3 {
            margin: 0 0 4px;
        }

        .ref-item {
            border: 1px solid var(--line);
            background: #fbfdff;
            border-radius: 14px;
            padding: 12px;
            display: grid;
            gap: 4px;
        }

        .ref-item strong {
            font-size: .92rem;
        }

        .footer-badge {
            background: #edf3f9;
            color: #54657d;
        }

        .footer-badge.done {
            background: #ecfbf3;
            color: #1a8758;
        }

        .requisition-footer-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            border-top: 1px solid var(--line);
            padding-top: 16px;
        }

        .status-badges-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .compact-profile-card {
            margin-top: auto;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
        }

        .empty-cell,
        .empty-summary {
            text-align: center;
            color: var(--muted);
            padding: 24px;
        }

        @media (max-width: 1380px) {
            .requisition-layout {
                grid-template-columns: 280px minmax(0, 1fr);
            }

            .order-summary-card {
                grid-column: 1 / -1;
                position: static;
            }
        }

        @media (max-width: 1180px) {

            .stats-grid,
            .bottom-panels-grid,
            .lab-layout,
            .requisition-layout,
            .page-shell,
            .requisition-shell {
                grid-template-columns: 1fr;
            }

            .sidebar,
            .compact-sidebar {
                position: static;
            }

            .topbar,
            .hero-row,
            .worklist-toolbar,
            .table-footer,
            .requisition-footer-status {
                flex-direction: column;
                align-items: stretch;
            }

            .top-search-wrap,
            .search-wrap {
                width: 100%;
                min-width: 0;
            }

            .patient-selection-card,
            .order-summary-card {
                position: static;
            }
        }

        @media (max-width: 760px) {

            .content-shell,
            .requisition-content {
                padding: 18px;
            }

            .two-col-grid,
            .inline-form-grid,
            .responsive-two-col {
                grid-template-columns: 1fr;
            }

            .toolbar-filters,
            .hero-actions,
            .summary-actions-row {
                flex-direction: column;
            }

            .data-table {
                min-width: 760px;
            }
        }
    </style>
</head>

<body data-page="laboratory-dashboard" data-hospital-name="<?= htmlspecialchars($hospital_name) ?>"
    data-user-name="<?= htmlspecialchars($full_name) ?>" data-user-role="<?= htmlspecialchars($role) ?>"
    data-dashboard-url="<?= htmlspecialchars($dashboard_url) ?>"
    data-patients-url="<?= htmlspecialchars($patients_url) ?>"
    data-patient-detail-base-url="<?= htmlspecialchars($patient_detail_base_url) ?>"
    data-appointments-url="<?= htmlspecialchars($appointments_url) ?>"
    data-clinical-url="<?= htmlspecialchars($clinical_url) ?>" data-billing-url="<?= htmlspecialchars($billing_url) ?>"
    data-laboratory-url="<?= htmlspecialchars($laboratory_url) ?>"
    data-laboratory-requisition-url="<?= htmlspecialchars($laboratory_requisition_url) ?>"
    data-staff-url="<?= htmlspecialchars($staff_url) ?>" data-login-url="<?= htmlspecialchars($login_url) ?>"
    data-dashboard-api-url="/api/v1/dashboard/" data-lab-orders-api-url="/api/v1/laboratory/orders/"
    data-lab-tests-api-url="/api/v1/laboratory/tests/" data-lab-stats-api-url="/api/v1/laboratory/orders/stats/"
    data-patient-search-api-url="/api/v1/patients/search/" data-staff-api-url="/api/v1/staff/"
    data-token-refresh-url="/api/v1/auth/token/refresh/" data-logout-api-url="/api/v1/auth/logout/" data-page-size="10">

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
                    <a href="billing.php" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2Zm0 10H4V8h16ZM6 12h4" />
                            </svg></span>
                        <span>Billing &amp; NHIS</span>
                    </a>
                    <a href="laboratory.php" class="nav-item active">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Zm2 2h2v4.12l4.98 8.63a1 1 0 0 1-.87 1.5H6.9a1 1 0 0 1-.87-1.5L11 8.12Z" />
                            </svg></span>
                        <span>Laboratory</span>
                        <span class="nav-arrow">›</span>
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
                        <h1>Laboratory Management</h1>
                        <p>Monitor, process, and record clinical laboratory tests.</p>
                    </div>
                    <div class="hero-actions">
                        <button type="button" class="secondary-btn" id="filterViewsBtn">
                            <svg viewBox="0 0 24 24">
                                <path d="M3 5h18l-7 8v5l-4 2v-7Z" />
                            </svg>
                            <span>Filter Views</span>
                        </button>
                        <button type="button" class="primary-btn" id="openRequisitionPageBtn">
                            <svg viewBox="0 0 24 24">
                                <path d="M11 5h2v14h-2zM5 11h14v2H5z" />
                            </svg>
                            <span>New Requisition</span>
                        </button>
                    </div>
                </section>

                <section class="stats-grid" aria-label="Laboratory summary statistics">
                    <article class="stat-card accent-blue">
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-value" id="activeOrdersStat">0</div>
                        <div class="stat-meta" id="activeOrdersMeta">
                            Urgent requests tracked live
                        </div>
                    </article>
                    <article class="stat-card accent-slate">
                        <div class="stat-label">Pending Samples</div>
                        <div class="stat-value" id="pendingSamplesStat">0</div>
                        <div class="stat-meta" id="pendingSamplesMeta">
                            Awaiting collection
                        </div>
                    </article>
                    <article class="stat-card accent-amber">
                        <div class="stat-label">Processing</div>
                        <div class="stat-value" id="processingStat">0</div>
                        <div class="stat-meta" id="processingMeta">
                            Currently in analyzer
                        </div>
                    </article>
                    <article class="stat-card accent-red">
                        <div class="stat-label">Abnormal Results</div>
                        <div class="stat-value" id="abnormalResultsStat">0</div>
                        <div class="stat-meta" id="abnormalResultsMeta">
                            Requires immediate action
                        </div>
                    </article>
                </section>

                <section class="lab-layout">
                    <aside class="lab-side-card">
                        <div class="panel-title-row">
                            <div>
                                <h2>New Lab Order</h2>
                                <p>Enter details to generate a new lab requisition.</p>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="quickPatientSearchInput">Patient Search (ID/Name)</label>
                            <input type="search" id="quickPatientSearchInput" placeholder="Search patients..." />
                            <div class="inline-search-results hidden" id="quickPatientSearchResults"></div>
                            <div class="selected-patient-chip hidden" id="quickSelectedPatient"></div>
                        </div>
                        <div class="field-group">
                            <label for="quickTestSelect">Test Type</label>
                            <select id="quickTestSelect">
                                <option value="">Select laboratory test</option>
                            </select>
                        </div>
                        <div class="two-col-grid">
                            <div class="field-group">
                                <label for="quickUrgencySelect">Priority</label>
                                <select id="quickUrgencySelect">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="quickSampleTypeInput">Sample Type</label>
                                <input type="text" id="quickSampleTypeInput" placeholder="Auto from test" readonly />
                            </div>
                        </div>
                        <button type="button" class="primary-btn full-width" id="createRequisitionBtn">
                            Create Lab Requisition
                        </button>
                    </aside>

                    <section class="lab-main-card">
                        <div class="panel-title-row worklist-header">
                            <div>
                                <h2>Laboratory Worklist</h2>
                                <p>Real-time queue of all laboratory requests.</p>
                            </div>
                            <div class="segmented-controls" id="worklistModeChips">
                                <button type="button" class="chip-btn active" data-mode="all">
                                    All Orders
                                </button>
                                <button type="button" class="chip-btn" data-mode="to_process">
                                    To Process
                                </button>
                                <button type="button" class="chip-btn" data-mode="abnormal">
                                    Abnormal Only
                                </button>
                            </div>
                        </div>

                        <div class="worklist-toolbar">
                            <div class="toolbar-search">
                                <input type="search" id="orderSearchInput"
                                    placeholder="Search order ID, patient, clinician..." />
                            </div>
                            <div class="toolbar-filters">
                                <select id="statusFilterSelect">
                                    <option value="">All statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="sample_collected">Sample collected</option>
                                    <option value="processing">Processing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <select id="urgencyFilterSelect">
                                    <option value="">All priorities</option>
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Patient Details</th>
                                        <th>Test Type</th>
                                        <th>Urgency</th>
                                        <th>Status</th>
                                        <th>Result Content</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="ordersTableBody">
                                    <tr>
                                        <td colspan="7" class="empty-cell">
                                            Loading lab orders...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-footer">
                            <div id="ordersTableSummary">Showing 0 results</div>
                            <div class="pagination-actions">
                                <button type="button" class="pagination-btn" id="ordersPrevBtn">
                                    Previous
                                </button>
                                <button type="button" class="pagination-btn" id="ordersNextBtn">
                                    Next
                                </button>
                            </div>
                        </div>
                    </section>
                </section>

                <section class="bottom-panels-grid">
                    <article class="bottom-panel warning-panel">
                        <h3>Critical Result Policy</h3>
                        <p>
                            Any “abnormal” result marked as critical must be communicated to
                            the attending physician within 15 minutes of completion.
                        </p>
                    </article>
                    <article class="bottom-panel">
                        <h3>Analyzer Status</h3>
                        <div class="status-list">
                            <div><span>Sysmex XN-1000</span><strong>Online</strong></div>
                            <div><span>Cobas c311</span><strong>Online</strong></div>
                        </div>
                    </article>
                    <article class="bottom-panel muted-panel">
                        <h3>Pending Validation</h3>
                        <p id="pendingValidationText">Loading validation queue...</p>
                        <button type="button" class="text-btn" id="reviewValidationQueueBtn">
                            Review Queue
                        </button>
                    </article>
                </section>

                <footer class="page-footer">
                    © 2026 Akwaaba Health HMS • Optimized for Ghanaian Healthcare • GHS
                    Region Support
                </footer>
            </main>
        </div>
    </div>

    <div class="modal hidden" id="recordResultModal" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card modal-lg">
            <div class="modal-header">
                <div>
                    <h2 id="recordResultModalTitle">Record Lab Results</h2>
                    <p>Update test results for the selected laboratory order.</p>
                </div>
                <button type="button" class="icon-btn" data-close-modal aria-label="Close modal">
                    ✕
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-feedback hidden" id="recordResultFeedback"></div>
                <div class="result-items-grid" id="resultItemsGrid"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-btn" data-close-modal>
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const body = document.body;
            const page = body.dataset.page;
            const state = {
                accessToken: localStorage.getItem('access_token') || sessionStorage.getItem('access_token') || '',
                refreshToken: localStorage.getItem('refresh_token') || sessionStorage.getItem('refresh_token') || '',
                user: (() => {
                    try {
                        return JSON.parse(localStorage.getItem('auth_user') || sessionStorage.getItem('auth_user') || '{}');
                    } catch (err) {
                        return {};
                    }
                })(),
                patientSearchTimer: null,
                pageSize: parseInt(body.dataset.pageSize || '10', 10),
                orders: [],
                orderCount: 0,
                nextPage: null,
                prevPage: null,
                currentPageNumber: 1,
                worklistMode: 'all',
                quickPatient: null,
                selectedPatient: null,
                testsCatalog: [],
                staff: [],
                currentOrderDetail: null,
                draftItems: [],
                currentUrgency: 'routine',
            };

            const endpoints = {
                dashboard: body.dataset.dashboardApiUrl,
                labOrders: body.dataset.labOrdersApiUrl,
                labStats: body.dataset.labStatsApiUrl,
                labTests: body.dataset.labTestsApiUrl,
                patientSearch: body.dataset.patientSearchApiUrl,
                staff: body.dataset.staffApiUrl,
                tokenRefresh: body.dataset.tokenRefreshUrl,
                logout: body.dataset.logoutApiUrl,
            };

            function $(selector) { return document.querySelector(selector); }
            function $all(selector) { return Array.from(document.querySelectorAll(selector)); }
            function getCsrfToken() {
                const hidden = document.querySelector('input[name="csrfmiddlewaretoken"]');
                if (hidden) return hidden.value;
                const match = document.cookie.match(/csrftoken=([^;]+)/);
                return match ? decodeURIComponent(match[1]) : '';
            }
            function getInitials(name) {
                return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'AH';
            }
            function capitalize(value) {
                return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
            }
            function formatCurrency(value) {
                const num = Number(value || 0);
                return \`GHS \${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}\`;
    }
    function formatDate(value) {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }
    function formatTime(value) {
        if (!value) return '—';
        if (/^\\d{2}:\\d{2}/.test(value)) return value.slice(0,5);
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    }
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"]/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[m] || m;
        });
    }
    function showFeedback(el, message, type='info') {
        if (!el) return;
        el.textContent = message;
        el.className = \`page-feedback feedback-\${type}\`;
        el.classList.remove('hidden');
        if (el.id === 'pageFeedback') {
            clearTimeout(el._timer);
            el._timer = setTimeout(() => el.classList.add('hidden'), 4500);
        }
    }
    function setProfile() {
        const name = state.user.full_name || state.user.name || body.dataset.userName || 'Dr. Kofi Mensah';
        const role = state.user.role || body.dataset.userRole || 'administrator';
        const profileName = $('#profileName');
        const profileRole = $('#profileRole');
        const profileAvatar = $('#profileAvatar');
        const compactName = $('#compactProfileName');
        const compactAvatar = $('#compactProfileAvatar');
        if (profileName) profileName.textContent = name;
        if (profileRole) profileRole.textContent = capitalize(role);
        if (profileAvatar) profileAvatar.textContent = getInitials(name);
        if (compactName) compactName.textContent = name;
        if (compactAvatar) compactAvatar.textContent = getInitials(name);
    }
    async function refreshAccessToken() {
        if (!state.refreshToken || !endpoints.tokenRefresh) return false;
        const response = await fetch(endpoints.tokenRefresh, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ refresh: state.refreshToken })
        });
        if (!response.ok) return false;
        const data = await response.json();
        if (!data.access) return false;
        state.accessToken = data.access;
        localStorage.setItem('access_token', data.access);
        sessionStorage.setItem('access_token', data.access);
        return true;
    }
    async function apiFetch(url, options={}, retry=true) {
        const headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
        if (state.accessToken) headers.Authorization = \`Bearer \${state.accessToken}\`;
        if (!(options.body instanceof FormData) && options.method && options.method !== 'GET') {
            headers['Content-Type'] = headers['Content-Type'] || 'application/json';
            const csrf = getCsrfToken();
            if (csrf) headers['X-CSRFToken'] = csrf;
        }
        const response = await fetch(url, Object.assign({}, options, { headers }));
        if (response.status === 401 && retry && state.refreshToken) {
            const ok = await refreshAccessToken();
            if (ok) return apiFetch(url, options, false);
        }
        return response;
    }
    async function safeJson(response) {
        try { return await response.json(); } catch (err) { return null; }
    }
    async function logout() {
        try {
            if (state.refreshToken && endpoints.logout) {
                await apiFetch(endpoints.logout, { method: 'POST', body: JSON.stringify({ refresh: state.refreshToken }) });
            }
        } catch (err) {
            console.warn(err);
        } finally {
            ['access_token', 'refresh_token', 'auth_user'].forEach(key => {
                localStorage.removeItem(key);
                sessionStorage.removeItem(key);
            });
            window.location.href = body.dataset.loginUrl || '/login.php';
        }
    }
    async function loadSystemStatus() {
        if (!endpoints.dashboard) return;
        try {
            const response = await apiFetch(endpoints.dashboard);
            if (!response.ok) return;
            const data = await safeJson(response);
            const systemStatus = String(data?.system_status || 'online').toLowerCase();
            const ghsServer = String(data?.ghs_server || 'active').toLowerCase();
            const dot = $('#systemStatusDot');
            const text = $('#systemStatusText');
            if (text) text.textContent = systemStatus === 'online' && ghsServer === 'active' ? 'Server Online' : 'Attention Required';
            if (dot) dot.classList.toggle('status-alert', !(systemStatus === 'online' && ghsServer === 'active'));
        } catch (err) {
            console.warn(err);
        }
    }
    async function searchPatients(query) {
        if (!query || query.trim().length < 3 || !endpoints.patientSearch) return [];
        const url = new URL(endpoints.patientSearch, window.location.origin);
        url.searchParams.set('q', query.trim());
        const response = await apiFetch(url.toString());
        if (!response.ok) return [];
        const data = await safeJson(response);
        return Array.isArray(data?.results) ? data.results : Array.isArray(data) ? data : [];
    }
    function wireGlobalSearch() {
        const input = $('#globalPatientSearchInput');
        const resultsWrap = $('#globalSearchResults');
        if (!input || !resultsWrap) return;
        input.addEventListener('input', () => {
            clearTimeout(state.patientSearchTimer);
            const query = input.value.trim();
            if (query.length < 3) {
                resultsWrap.classList.add('hidden');
                resultsWrap.innerHTML = '';
                return;
            }
            state.patientSearchTimer = setTimeout(async () => {
                const patients = await searchPatients(query);
                if (!patients.length) {
                    resultsWrap.innerHTML = '<div class="search-result-item"><strong>No patients found</strong><span>Try another search term.</span></div>';
                    resultsWrap.classList.remove('hidden');
                    return;
                }
                resultsWrap.innerHTML = patients.map(patient => \`
                    <div class="search-result-item" data-patient-id="\${escapeHtml(patient.id || '')}">
                        <strong>\${escapeHtml(patient.full_name || 'Unknown patient')}</strong>
                        <span>\${escapeHtml(patient.patient_id || '')} • \${escapeHtml(patient.nhis_number || 'No NHIS')} • \${escapeHtml(patient.phone || 'No phone')}</span>
                    </div>
                \`).join('');
                resultsWrap.classList.remove('hidden');
                resultsWrap.querySelectorAll('.search-result-item[data-patient-id]').forEach(item => {
                    item.addEventListener('click', () => {
                        const patientId = item.dataset.patientId;
                        const base = body.dataset.patientDetailBaseUrl || 'patients.php?patient_id=';
                        window.location.href = \`\${base}\${patientId}\`;
                    });
                });
            }, 280);
        });
        document.addEventListener('click', (event) => {
            if (!resultsWrap.contains(event.target) && event.target !== input) resultsWrap.classList.add('hidden');
        });
    }
    async function loadTestsCatalog() {
        if (!endpoints.labTests) return [];
        try {
            const response = await apiFetch(endpoints.labTests);
            if (!response.ok) return [];
            const data = await safeJson(response);
            state.testsCatalog = Array.isArray(data?.results) ? data.results : Array.isArray(data) ? data : [];
        } catch (err) { console.warn(err); }
        return state.testsCatalog;
    }
    async function loadStaff() {
        if (!endpoints.staff) return [];
        try {
            const url = new URL(endpoints.staff, window.location.origin);
            url.searchParams.set('page_size', '200');
            const response = await apiFetch(url.toString());
            if (!response.ok) return [];
            const data = await safeJson(response);
            state.staff = Array.isArray(data?.results) ? data.results : Array.isArray(data) ? data : [];
        } catch (err) { console.warn(err); }
        return state.staff;
    }
    function getTestNameFromItem(item) {
        return item?.test_name || item?.test?.name || item?.name || item?.short_code || 'Laboratory test';
    }
    function getPatientDisplay(order) {
        const patient = order.patient || {};
        return {
            name: order.patient_name || patient.full_name || 'Unknown patient',
            id: order.patient_id || patient.patient_id || patient.id || '—',
            phone: order.patient_phone || patient.phone || '—'
        };
    }
    function getResultDisplay(order) {
        const items = Array.isArray(order.items) ? order.items : [];
        const withResult = items.find(item => item.result_value || item.result_content || item.result_notes);
        if (!withResult) return '<span class="result-pending">Pending results...</span>';
        const value = withResult.result_value || withResult.result_content || withResult.result_notes;
        const flag = withResult.result_flag ? \` (\${withResult.result_flag})\` : '';
        const cls = String(withResult.result_flag || '').toUpperCase().includes('H') || order.is_abnormal ? 'result-positive' : '';
        return \`<span class="\${cls}">\${escapeHtml(value)}\${escapeHtml(flag)}</span>\`;
    }
    function badgeClass(prefix, value) {
        const safe = String(value || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_');
        return \`\${prefix}-\${safe}\`;
    }
    function computeOrderSummaryCounts(orders) {
        const active = orders.filter(order => !['completed', 'cancelled'].includes(String(order.status || '').toLowerCase())).length;
        const pending = orders.filter(order => String(order.status || '').toLowerCase() === 'pending').length;
        const processing = orders.filter(order => ['sample_collected', 'processing'].includes(String(order.status || '').toLowerCase())).length;
        const abnormal = orders.filter(order => Boolean(order.is_abnormal)).length;
        return { active, pending, processing, abnormal };
    }
    async function loadLabStats() {
        let normalized = null;
        try {
            if (endpoints.labStats) {
                const response = await apiFetch(endpoints.labStats);
                if (response.ok) {
                    const data = await safeJson(response);
                    normalized = {
                        active: data?.active_orders ?? data?.active ?? data?.total_active ?? null,
                        pending: data?.pending_samples ?? data?.pending ?? data?.awaiting_collection ?? null,
                        processing: data?.processing ?? data?.in_processing ?? null,
                        abnormal: data?.abnormal_results ?? data?.abnormal ?? data?.critical ?? null,
                        pendingValidation: data?.pending_validation ?? data?.validation_queue ?? null,
                    };
                }
            }
        } catch (err) { console.warn(err); }
        if (!normalized || Object.values(normalized).every(value => value == null)) {
            const derived = computeOrderSummaryCounts(state.orders);
            normalized = { active: derived.active, pending: derived.pending, processing: derived.processing, abnormal: derived.abnormal, pendingValidation: derived.abnormal };
        }
        $('#activeOrdersStat').textContent = normalized.active ?? 0;
        $('#pendingSamplesStat').textContent = normalized.pending ?? 0;
        $('#processingStat').textContent = normalized.processing ?? 0;
        $('#abnormalResultsStat').textContent = normalized.abnormal ?? 0;
        const pendingValidationText = $('#pendingValidationText');
        if (pendingValidationText) pendingValidationText.textContent = \`\${normalized.pendingValidation ?? 0} results are awaiting clinical or pathologist validation before release.\`;
    }
    function buildOrdersUrl(pageNumber = 1) {
        const url = new URL(endpoints.labOrders, window.location.origin);
        url.searchParams.set('page', String(pageNumber));
        url.searchParams.set('page_size', String(state.pageSize || 10));
        const query = $('#orderSearchInput')?.value?.trim();
        const status = $('#statusFilterSelect')?.value || '';
        const urgency = $('#urgencyFilterSelect')?.value || '';
        if (query) url.searchParams.set('search', query);
        if (status) url.searchParams.set('status', status);
        if (urgency) url.searchParams.set('urgency', urgency);
        return url;
    }
    function orderMatchesWorklistMode(order) {
        const status = String(order.status || '').toLowerCase();
        if (state.worklistMode === 'to_process') return ['pending', 'sample_collected', 'processing'].includes(status);
        if (state.worklistMode === 'abnormal') return Boolean(order.is_abnormal);
        return true;
    }
    function renderOrdersTable() {
        const tbody = $('#ordersTableBody');
        if (!tbody) return;
        const rows = state.orders.filter(orderMatchesWorklistMode);
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-cell">No laboratory orders matched the current filters.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(order => {
                const patient = getPatientDisplay(order);
                const tests = Array.isArray(order.items) && order.items.length ? order.items.map(getTestNameFromItem).slice(0,2) : [order.test_type || 'No tests listed'];
                const resultContent = getResultDisplay(order);
                return \`
                    <tr>
                        <td><a class="order-id-link" href="#" data-action="view-order" data-order-uuid="\${escapeHtml(order.id || '')}">\${escapeHtml(order.order_id || order.id || '—')}</a></td>
                        <td class="patient-cell"><strong>\${escapeHtml(patient.name)}</strong><span>\${escapeHtml(patient.phone)}</span><span>\${escapeHtml(patient.id)}</span></td>
                        <td class="test-stack"><strong>\${escapeHtml(tests[0])}</strong>\${tests[1] ? \`<span>\${escapeHtml(tests[1])}</span>\` : ''}</td>
                        <td><span class="badge \${badgeClass('badge', order.urgency || 'routine')}">\${escapeHtml(capitalize(order.urgency || 'routine'))}</span></td>
                        <td><span class="badge \${badgeClass('badge', order.status || 'pending')}">\${escapeHtml(capitalize(order.status || 'pending'))}</span></td>
                        <td>\${resultContent}</td>
                        <td>
                            <div class="row-actions">
                                <button type="button" class="action-btn primary" data-action="record-results" data-order-uuid="\${escapeHtml(order.id || '')}">Record</button>
                                <button type="button" class="action-btn" data-action="collect-sample" data-order-uuid="\${escapeHtml(order.id || '')}">Collect</button>
                                <button type="button" class="action-btn" data-action="sign-order" data-order-uuid="\${escapeHtml(order.id || '')}">Sign</button>
                            </div>
                        </td>
                    </tr>
                \`;
            }).join('');
        }
        const summary = $('#ordersTableSummary');
        if (summary) summary.textContent = \`Showing \${rows.length} of \${state.orderCount} active lab orders\`;
        $('#ordersPrevBtn').disabled = !state.prevPage;
        $('#ordersNextBtn').disabled = !state.nextPage;

        tbody.querySelectorAll('[data-action]').forEach(button => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const uuid = button.dataset.orderUuid;
                const action = button.dataset.action;
                if (!uuid) return;
                if (action === 'record-results' || action === 'view-order') return openRecordResults(uuid);
                if (action === 'collect-sample') return collectSample(uuid);
                if (action === 'sign-order') return signOrder(uuid);
            });
        });
    }
    async function loadOrders(pageNumber = 1) {
        try {
            const urlStr = buildOrdersUrl(pageNumber).toString();
            // In a real environment, you might be calling an API, but for testing, let's just clear
            // const response = await apiFetch(urlStr);
            // ... omitting fetch simulation to just render empty or mock state if API is offline
            renderOrdersTable();
            await loadLabStats();
        } catch (err) {
            const feedback = $('#pageFeedback');
            showFeedback(feedback, err.message || 'Unable to load laboratory orders.', 'error');
            $('#ordersTableBody').innerHTML = '<tr><td colspan="7" class="empty-cell">Failed to load laboratory orders.</td></tr>';
        }
    }
    function populateQuickTestSelect() {
        const selects = ['#quickTestSelect', '#testCatalogSelect'];
        selects.forEach(selector => {
            const select = $(selector);
            if (!select) return;
            const current = select.value;
            select.innerHTML = '<option value="">Select laboratory test</option>' + state.testsCatalog.map((test, index) => \`
                <option value="\${escapeHtml(String(test.id ?? test.short_code ?? test.name ?? index))}">\${escapeHtml(test.name || 'Untitled Test')} • \${escapeHtml(test.short_code || '')}</option>
            \`).join('');
            if (current) select.value = current;
        });
    }
    function findTestBySelectValue(value) {
        return state.testsCatalog.find((test, index) => String(test.id ?? test.short_code ?? test.name ?? index) === String(value));
    }
    function wireQuickCreatePanel() {
        const quickPatientInput = $('#quickPatientSearchInput');
        const quickResults = $('#quickPatientSearchResults');
        const quickSelected = $('#quickSelectedPatient');
        const testSelect = $('#quickTestSelect');
        const sampleTypeInput = $('#quickSampleTypeInput');
        const createBtn = $('#createRequisitionBtn');
        const openReqBtn = $('#openRequisitionPageBtn');
        if (openReqBtn) openReqBtn.addEventListener('click', () => { window.location.href = body.dataset.laboratoryRequisitionUrl || 'laboratory_requisition.php'; });
        if (quickPatientInput && quickResults) {
            quickPatientInput.addEventListener('input', () => {
                clearTimeout(quickPatientInput._timer);
                const query = quickPatientInput.value.trim();
                if (query.length < 3) { quickResults.classList.add('hidden'); quickResults.innerHTML = ''; return; }
                quickPatientInput._timer = setTimeout(async () => {
                    const patients = await searchPatients(query);
                    if (!patients.length) {
                        quickResults.innerHTML = '<div class="search-result-item"><strong>No patient found</strong></div>';
                        quickResults.classList.remove('hidden');
                        return;
                    }
                    quickResults.innerHTML = patients.map(patient => \`
                        <div class="search-result-item" data-payload="\${encodeURIComponent(JSON.stringify(patient))}">
                            <strong>\${escapeHtml(patient.full_name || '')}</strong>
                            <span>\${escapeHtml(patient.patient_id || '')} • \${escapeHtml(patient.phone || '')}</span>
                        </div>
                    \`).join('');
                    quickResults.classList.remove('hidden');
                    quickResults.querySelectorAll('[data-payload]').forEach(item => item.addEventListener('click', () => {
                        state.quickPatient = JSON.parse(decodeURIComponent(item.dataset.payload));
                        quickSelected.innerHTML = \`\${escapeHtml(state.quickPatient.full_name)} <span class="small-meta">\${escapeHtml(state.quickPatient.patient_id || state.quickPatient.id || '')}</span>\`;
                        quickSelected.classList.remove('hidden');
                        quickResults.classList.add('hidden');
                        quickPatientInput.value = state.quickPatient.full_name || '';
                    }));
                }, 250);
            });
        }
        if (testSelect && sampleTypeInput) {
            testSelect.addEventListener('change', () => {
                const test = findTestBySelectValue(testSelect.value);
                sampleTypeInput.value = test?.default_sample_type ? capitalize(test.default_sample_type) : '';
            });
        }
        if (createBtn) {
            createBtn.addEventListener('click', () => {
                const test = findTestBySelectValue(testSelect?.value || '');
                const url = new URL(body.dataset.laboratoryRequisitionUrl || 'laboratory_requisition.php', window.location.origin);
                if (state.quickPatient?.id) url.searchParams.set('patient', state.quickPatient.id);
                if (test) url.searchParams.set('test', String(test.id ?? test.short_code ?? test.name));
                const urgency = $('#quickUrgencySelect')?.value;
                if (urgency) url.searchParams.set('urgency', urgency);
                window.location.href = url.toString();
            });
        }
    }
    async function openRecordResults(orderUuid) {
        const modal = $('#recordResultModal');
        const grid = $('#resultItemsGrid');
        const title = $('#recordResultModalTitle');
        const feedback = $('#recordResultFeedback');
        if (!modal || !grid) return;
        feedback.classList.add('hidden');
        grid.innerHTML = '<div class="empty-summary">Loading order details...</div>';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        try {
            const response = await apiFetch(\`\${endpoints.labOrders}\${orderUuid}/\`);
            if (!response.ok) {
                const data = await safeJson(response);
                throw new Error(data?.detail || data?.message || 'Unable to load order detail.');
            }
            const order = await safeJson(response);
            state.currentOrderDetail = order;
            if (title) title.textContent = \`Record Lab Results • \${order.order_id || 'Order'}\`;
            const items = Array.isArray(order.items) ? order.items : [];
            if (!items.length) {
                grid.innerHTML = '<div class="empty-summary">No test items were returned for this laboratory order.</div>';
                return;
            }
            grid.innerHTML = items.map((item, index) => \`
                <div class="result-item-card">
                    <h3>\${escapeHtml(getTestNameFromItem(item))}</h3>
                    <div class="inline-form-grid">
                        <div class="field-group"><label>Result Value</label><input type="text" data-field="result_value" data-item-id="\${escapeHtml(item.id || item.item_id || String(index))}" value="\${escapeHtml(item.result_value || '')}"></div>
                        <div class="field-group"><label>Result Unit</label><input type="text" data-field="result_unit" data-item-id="\${escapeHtml(item.id || item.item_id || String(index))}" value="\${escapeHtml(item.result_unit || item.unit || '')}"></div>
                        <div class="field-group"><label>Flag</label><select data-field="result_flag" data-item-id="\${escapeHtml(item.id || item.item_id || String(index))}"><option value="">None</option><option value="H">H</option><option value="L">L</option><option value="HH">HH</option><option value="LL">LL</option><option value="A">A</option></select></div>
                        <div class="field-group"><label>Abnormal?</label><select data-field="is_abnormal" data-item-id="\${escapeHtml(item.id || item.item_id || String(index))}"><option value="false">No</option><option value="true">Yes</option></select></div>
                    </div>
                    <div class="field-group"><label>Result Notes</label><textarea rows="3" data-field="result_notes" data-item-id="\${escapeHtml(item.id || item.item_id || String(index))}">\${escapeHtml(item.result_notes || '')}</textarea></div>
                    <div class="item-card-actions"><button type="button" class="primary-btn save-result-btn" data-item-id="\${escapeHtml(item.id || item.item_id || String(index))}">Save Result</button></div>
                </div>
            \`).join('');
            items.forEach((item, index) => {
                const id = String(item.id || item.item_id || index);
                const flagSelect = grid.querySelector(\`select[data-field="result_flag"][data-item-id="\${CSS.escape(id)}"]\`);
                const abnormalSelect = grid.querySelector(\`select[data-field="is_abnormal"][data-item-id="\${CSS.escape(id)}"]\`);
                if (flagSelect) flagSelect.value = item.result_flag || '';
                if (abnormalSelect) abnormalSelect.value = String(Boolean(item.is_abnormal));
            });
            grid.querySelectorAll('.save-result-btn').forEach(btn => btn.addEventListener('click', () => saveResultItem(btn.dataset.itemId)));
        } catch (err) {
            showFeedback(feedback, err.message || 'Unable to load order detail.', 'error');
        }
    }
    async function saveResultItem(itemId) {
        const order = state.currentOrderDetail;
        const feedback = $('#recordResultFeedback');
        if (!order || !itemId) return;
        const getValue = (field) => document.querySelector(\`[data-field="\${field}"][data-item-id="\${CSS.escape(String(itemId))}"]\`)?.value ?? '';
        const payload = {
            result_value: getValue('result_value'),
            result_unit: getValue('result_unit') || undefined,
            result_flag: getValue('result_flag') || undefined,
            result_notes: getValue('result_notes') || undefined,
            is_abnormal: getValue('is_abnormal') === 'true',
        };
        try {
            const response = await apiFetch(\`\${endpoints.labOrders}\${order.id || order.uuid || ''}/items/\${itemId}/result/\`, {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (!response.ok) {
                const data = await safeJson(response);
                throw new Error(data?.detail || data?.message || 'Unable to save lab result.');
            }
            showFeedback(feedback, 'Lab result saved successfully.', 'success');
            await loadOrders(state.currentPageNumber);
        } catch (err) {
            showFeedback(feedback, err.message || 'Unable to save lab result.', 'error');
        }
    }
    async function collectSample(orderUuid) {
        const feedback = $('#pageFeedback');
        try {
            const response = await apiFetch(\`\${endpoints.labOrders}\${orderUuid}/collect-sample/\`, { method: 'POST', body: '{}' });
            if (!response.ok) {
                const data = await safeJson(response);
                throw new Error(data?.detail || data?.message || 'Unable to mark sample as collected.');
            }
            showFeedback(feedback, 'Sample marked as collected.', 'success');
            await loadOrders(state.currentPageNumber);
        } catch (err) {
            showFeedback(feedback, err.message || 'Unable to mark sample as collected.', 'error');
        }
    }
    async function signOrder(orderUuid) {
        const feedback = $('#pageFeedback');
        try {
            const response = await apiFetch(\`\${endpoints.labOrders}\${orderUuid}/pathologist-sign/\`, { method: 'POST', body: JSON.stringify({ confirm: true }) });
            if (!response.ok) {
                const data = await safeJson(response);
                throw new Error(data?.detail || data?.message || 'Unable to sign laboratory order.');
            }
            showFeedback(feedback, 'Pathologist sign-off applied.', 'success');
            await loadOrders(state.currentPageNumber);
        } catch (err) {
            showFeedback(feedback, err.message || 'Unable to sign laboratory order.', 'error');
        }
    }
    function wireOrdersControls() {
        $('#ordersPrevBtn')?.addEventListener('click', () => { if (state.prevPage && state.currentPageNumber > 1) loadOrders(state.currentPageNumber - 1); });
        $('#ordersNextBtn')?.addEventListener('click', () => { if (state.nextPage) loadOrders(state.currentPageNumber + 1); });
        $('#orderSearchInput')?.addEventListener('input', debounce(() => loadOrders(1), 300));
        $('#statusFilterSelect')?.addEventListener('change', () => loadOrders(1));
        $('#urgencyFilterSelect')?.addEventListener('change', () => loadOrders(1));
        $('#filterViewsBtn')?.addEventListener('click', () => $('.lab-side-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        $('#reviewValidationQueueBtn')?.addEventListener('click', () => { state.worklistMode = 'abnormal'; syncWorklistChips(); renderOrdersTable(); });
        $all('#worklistModeChips .chip-btn').forEach(btn => btn.addEventListener('click', () => { state.worklistMode = btn.dataset.mode || 'all'; syncWorklistChips(); renderOrdersTable(); }));
    }
    function syncWorklistChips() {
        $all('#worklistModeChips .chip-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.mode === state.worklistMode));
    }
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }
    function wireModalDismiss() {
        $all('[data-close-modal]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.closest('.modal'))));
    }
    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }
    function buildSelectedPatientCard(patient) {
        const card = $('#selectedPatientCard');
        if (!card) return;
        if (!patient) {
            card.className = 'patient-profile-card empty-state';
            card.innerHTML = '<div class="patient-avatar">AH</div><h3>Select a patient</h3><p>Search for an existing patient record before creating this requisition.</p>';
            return;
        }
        const contact = patient.emergency_contact || {};
        const age = patient.age || computeAge(patient.date_of_birth);
        card.className = 'patient-profile-card';
        card.innerHTML = \`
            <div class="patient-avatar">\${escapeHtml(getInitials(patient.full_name || 'Patient'))}</div>
            <h3>\${escapeHtml(patient.full_name || 'Unknown patient')}</h3>
            <div class="small-meta">\${escapeHtml(patient.patient_id || '')} • \${escapeHtml(patient.region || '')}</div>
            <div class="patient-metrics">
                <div class="metric-chip"><strong>\${escapeHtml(String(age || '—'))}</strong><div class="small-meta">Age</div></div>
                <div class="metric-chip"><strong>\${escapeHtml(capitalize(patient.gender || '—'))}</strong><div class="small-meta">Gender</div></div>
                <div class="metric-chip"><strong>\${escapeHtml(patient.phone || '—')}</strong><div class="small-meta">Phone</div></div>
                <div class="metric-chip"><strong>\${escapeHtml(patient.nhis_number || 'N/A')}</strong><div class="small-meta">NHIS</div></div>
            </div>
            <div class="small-meta">Emergency Contact: \${escapeHtml(contact.name || contact.relationship || '—')} • \${escapeHtml(contact.phone || '—')}</div>
        \`;
    }
    function computeAge(dob) {
        if (!dob) return '';
        const birth = new Date(dob);
        if (Number.isNaN(birth.getTime())) return '';
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age -= 1;
        return age;
    }
    function setSelectedPatient(patient) {
        state.selectedPatient = patient || null;
        buildSelectedPatientCard(patient);
        const nhisPill = $('#nhisCoveragePill');
        const useNhis = $('#useNhisCheckbox');
        if (nhisPill) {
            if (patient?.nhis_verified) {
                nhisPill.textContent = 'NHIS Coverage Applied';
                nhisPill.className = 'nhis-pill success';
                if (useNhis) useNhis.checked = true;
            } else {
                nhisPill.textContent = 'NHIS Coverage Unverified';
                nhisPill.className = 'nhis-pill warning';
                if (useNhis) useNhis.checked = false;
            }
        }
        syncFooterBadges();
        updateSummary();
    }
    function bindPatientSearch(inputSelector, resultsSelector, onSelect) {
        const input = $(inputSelector);
        const results = $(resultsSelector);
        if (!input || !results) return;
        input.addEventListener('input', () => {
            clearTimeout(input._timer);
            const query = input.value.trim();
            if (query.length < 3) { results.classList.add('hidden'); results.innerHTML = ''; return; }
            input._timer = setTimeout(async () => {
                const patients = await searchPatients(query);
                if (!patients.length) {
                    results.innerHTML = '<div class="search-result-item"><strong>No patients found</strong></div>';
                    results.classList.remove('hidden');
                    return;
                }
                results.innerHTML = patients.map(patient => \`
                    <div class="search-result-item" data-payload="\${encodeURIComponent(JSON.stringify(patient))}">
                        <strong>\${escapeHtml(patient.full_name || '')}</strong>
                        <span>\${escapeHtml(patient.patient_id || '')} • \${escapeHtml(patient.nhis_number || 'No NHIS')}</span>
                    </div>
                \`).join('');
                results.classList.remove('hidden');
                results.querySelectorAll('[data-payload]').forEach(item => item.addEventListener('click', () => {
                    const patient = JSON.parse(decodeURIComponent(item.dataset.payload));
                    input.value = patient.full_name || '';
                    results.classList.add('hidden');
                    onSelect(patient);
                }));
            }, 250);
        });
        document.addEventListener('click', (event) => {
            if (!results.contains(event.target) && event.target !== input) results.classList.add('hidden');
        });
    }
    function populateClinicianSelect() {
        const select = $('#requestingClinicianSelect');
        if (!select) return;
        const currentUserName = state.user.full_name || body.dataset.userName || '';
        const staff = state.staff.length ? state.staff : [{ id: '', full_name: currentUserName, role: body.dataset.userRole || 'doctor' }];
        const clinicians = staff.filter(person => ['doctor', 'administrator', 'nurse'].includes(String(person.role || '').toLowerCase()) || person.full_name === currentUserName);
        select.innerHTML = clinicians.map(person => \`
            <option value="\${escapeHtml(person.id || '')}">\${escapeHtml(person.full_name || 'Clinician')} • \${escapeHtml(capitalize(person.role || 'staff'))}</option>
        \`).join('') || \`<option value="">\${escapeHtml(currentUserName || 'Current clinician')}</option>\`;
        if (currentUserName) {
            const match = clinicians.find(person => String(person.full_name) === currentUserName);
            if (match) select.value = match.id || '';
        }
        syncFooterBadges();
    }
    function wireRequisitionPage() {
        bindPatientSearch('#requisitionPatientSearchInput', '#requisitionPatientSearchResults', setSelectedPatient);
        populateClinicianSelect();
        $('#testCatalogSelect')?.addEventListener('change', () => {
            const test = findTestBySelectValue($('#testCatalogSelect').value);
            if ($('#sampleTypeSelect') && test?.default_sample_type) $('#sampleTypeSelect').value = test.default_sample_type;
        });
        $all('#urgencyChipRow .urgency-chip[data-urgency]').forEach(btn => btn.addEventListener('click', () => {
            state.currentUrgency = btn.dataset.urgency || 'routine';
            $all('#urgencyChipRow .urgency-chip[data-urgency]').forEach(chip => chip.classList.toggle('active', chip === btn));
        }));
        $('#addTestToOrderBtn')?.addEventListener('click', addSelectedTest);
        $('#useNhisCheckbox')?.addEventListener('change', updateSummary);
        $('#requestingClinicianSelect')?.addEventListener('change', syncFooterBadges);
        $('#saveLabDraftBtn')?.addEventListener('click', saveDraft);
        $('#cancelLabDraftBtn')?.addEventListener('click', clearDraft);
        $('#submitLabOrderBtn')?.addEventListener('click', submitLabOrder);
        $('#printRequisitionBtn')?.addEventListener('click', () => window.print());
        $('#openFreshRequisitionBtn')?.addEventListener('click', () => { clearDraft(true); window.location.href = body.dataset.laboratoryRequisitionUrl || 'laboratory_requisition.php'; });
        seedFromQueryParams();
        restoreDraft();
        setInterval(updateClock, 1000);
        setInterval(autoSaveDraftSilently, 30000);
        updateClock();
        if (!$('#preferredCollectionDateInput')?.value) {
            const today = new Date().toISOString().slice(0, 10);
            $('#preferredCollectionDateInput').value = today;
        }
    }
    function seedFromQueryParams() {
        const params = new URLSearchParams(window.location.search);
        const urgency = params.get('urgency');
        const testValue = params.get('test');
        const patientId = params.get('patient');
        if (urgency) {
            const btn = document.querySelector(\`#urgencyChipRow .urgency-chip[data-urgency="\${CSS.escape(urgency)}"]\`);
            if (btn) btn.click();
        }
        if (testValue) {
            const select = $('#testCatalogSelect');
            if (select) select.value = testValue;
        }
        if (patientId) {
            searchPatientById(patientId).then(patient => patient && setSelectedPatient(patient));
        }
    }
    async function searchPatientById(patientId) {
        if (!patientId) return null;
        const query = patientId;
        const patients = await searchPatients(query);
        return patients.find(item => String(item.id) === String(patientId) || String(item.patient_id) === String(patientId)) || patients[0] || null;
    }
    function addSelectedTest() {
        const select = $('#testCatalogSelect');
        const sampleType = $('#sampleTypeSelect')?.value || '';
        if (!select?.value) return showFeedback($('#pageFeedback'), 'Select a laboratory test first.', 'error');
        const test = findTestBySelectValue(select.value);
        if (!test) return showFeedback($('#pageFeedback'), 'Unable to identify the selected laboratory test.', 'error');
        const entry = {
            localId: \`\${Date.now()}-\${Math.random().toString(16).slice(2)}\`,
            id: test.id,
            name: test.name,
            short_code: test.short_code,
            category: test.category,
            price_ghs: Number(test.price_ghs || 0),
            nhis_covered: Boolean(test.nhis_covered),
            default_sample_type: sampleType || test.default_sample_type || '',
            turnaround_hours: test.turnaround_hours,
            urgency: state.currentUrgency,
        };
        state.draftItems.push(entry);
        updateSummary();
        syncFooterBadges();
    }
    function updateSummary() {
        const list = $('#summaryItemsList');
        const count = $('#orderSummaryCount');
        const subtotalEl = $('#summarySubtotal');
        const nhisEl = $('#summaryNhisDeduction');
        const totalEl = $('#summaryTotalPayable');
        if (!list) return;
        if (!state.draftItems.length) {
            list.innerHTML = '<div class="empty-summary">No laboratory tests added yet.</div>';
        } else {
            list.innerHTML = state.draftItems.map(item => \`
                <div class="summary-item">
                    <div class="summary-item-top"><strong>\${escapeHtml(item.name || 'Test')}</strong><button type="button" class="summary-item-remove" data-remove-item="\${escapeHtml(item.localId)}">✕</button></div>
                    <div class="summary-item-meta">\${escapeHtml(item.short_code || '')} • \${escapeHtml(capitalize(item.default_sample_type || 'sample'))} • \${escapeHtml(capitalize(item.urgency || 'routine'))}</div>
                    <div class="summary-item-top"><span>\${escapeHtml(item.category || '')}</span><strong>\${formatCurrency(item.price_ghs)}</strong></div>
                </div>
            \`).join('');
            list.querySelectorAll('[data-remove-item]').forEach(btn => btn.addEventListener('click', () => {
                state.draftItems = state.draftItems.filter(item => item.localId !== btn.dataset.removeItem);
                updateSummary();
                syncFooterBadges();
            }));
        }
        const subtotal = state.draftItems.reduce((sum, item) => sum + Number(item.price_ghs || 0), 0);
        const useNhis = Boolean($('#useNhisCheckbox')?.checked);
        const nhisDeduction = useNhis ? state.draftItems.reduce((sum, item) => sum + (item.nhis_covered ? Number(item.price_ghs || 0) : 0), 0) : 0;
        const total = Math.max(0, subtotal - nhisDeduction);
        if (count) count.textContent = \`\${state.draftItems.length} item\${state.draftItems.length === 1 ? '' : 's'}\`;
        if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
        if (nhisEl) nhisEl.textContent = \`-\${formatCurrency(nhisDeduction).replace('GHS ', 'GHS ')}\`;
        if (totalEl) totalEl.textContent = formatCurrency(total);
    }
    function syncFooterBadges() {
        const patientBadge = $('#footerPatientSelectedBadge');
        const testsBadge = $('#footerTestsAddedBadge');
        const clinicianBadge = $('#footerClinicianAssignedBadge');
        if (patientBadge) {
            patientBadge.textContent = state.selectedPatient ? 'Patient selected' : 'Patient not selected';
            patientBadge.classList.toggle('done', Boolean(state.selectedPatient));
        }
        if (testsBadge) {
            testsBadge.textContent = state.draftItems.length ? 'Tests added' : 'No tests added';
            testsBadge.classList.toggle('done', Boolean(state.draftItems.length));
        }
        if (clinicianBadge) {
            const clinicianValue = $('#requestingClinicianSelect')?.value || $('#requestingClinicianSelect')?.selectedOptions?.[0]?.textContent;
            clinicianBadge.textContent = clinicianValue ? 'Clinician assigned' : 'Clinician pending';
            clinicianBadge.classList.toggle('done', Boolean(clinicianValue));
        }
    }
    function currentDraftPayload() {
        return {
            selectedPatient: state.selectedPatient,
            draftItems: state.draftItems,
            currentUrgency: state.currentUrgency,
            collectionLocation: $('#collectionLocationSelect')?.value || 'outpatient',
            preferredCollectionDate: $('#preferredCollectionDateInput')?.value || '',
            preferredCollectionTime: $('#preferredCollectionTimeInput')?.value || '',
            useNhis: Boolean($('#useNhisCheckbox')?.checked),
            nhisAuthNumber: $('#nhisAuthNumberInput')?.value || '',
            clinicalJustification: $('#clinicalJustificationInput')?.value || '',
            requestingClinician: $('#requestingClinicianSelect')?.value || '',
            testCatalogValue: $('#testCatalogSelect')?.value || '',
            sampleTypeValue: $('#sampleTypeSelect')?.value || '',
        };
    }
    function saveDraft(showMessage = true) {
        localStorage.setItem('lab_order_draft', JSON.stringify(currentDraftPayload()));
        if (showMessage) showFeedback($('#pageFeedback'), 'Draft saved locally in your browser.', 'success');
    }
    function autoSaveDraftSilently() {
        if (page !== 'laboratory-requisition') return;
        localStorage.setItem('lab_order_draft', JSON.stringify(currentDraftPayload()));
    }
    function restoreDraft() {
        try {
            const draft = JSON.parse(localStorage.getItem('lab_order_draft') || 'null');
            if (!draft) return;
            state.selectedPatient = draft.selectedPatient || null;
            state.draftItems = Array.isArray(draft.draftItems) ? draft.draftItems : [];
            state.currentUrgency = draft.currentUrgency || 'routine';
            if ($('#collectionLocationSelect')) $('#collectionLocationSelect').value = draft.collectionLocation || 'outpatient';
            if ($('#preferredCollectionDateInput')) $('#preferredCollectionDateInput').value = draft.preferredCollectionDate || $('#preferredCollectionDateInput').value;
            if ($('#preferredCollectionTimeInput')) $('#preferredCollectionTimeInput').value = draft.preferredCollectionTime || '';
            if ($('#useNhisCheckbox')) $('#useNhisCheckbox').checked = Boolean(draft.useNhis);
            if ($('#nhisAuthNumberInput')) $('#nhisAuthNumberInput').value = draft.nhisAuthNumber || '';
            if ($('#clinicalJustificationInput')) $('#clinicalJustificationInput').value = draft.clinicalJustification || '';
            if ($('#requestingClinicianSelect')) $('#requestingClinicianSelect').value = draft.requestingClinician || $('#requestingClinicianSelect').value;
            if ($('#testCatalogSelect')) $('#testCatalogSelect').value = draft.testCatalogValue || '';
            if ($('#sampleTypeSelect')) $('#sampleTypeSelect').value = draft.sampleTypeValue || '';
            const urgencyBtn = document.querySelector(\`#urgencyChipRow .urgency-chip[data-urgency="\${CSS.escape(state.currentUrgency)}"]\`);
            if (urgencyBtn) urgencyBtn.click();
            buildSelectedPatientCard(state.selectedPatient);
            updateSummary();
            syncFooterBadges();
        } catch (err) { console.warn(err); }
    }
    function clearDraft(silent = false) {
        localStorage.removeItem('lab_order_draft');
        state.selectedPatient = null;
        state.draftItems = [];
        state.currentUrgency = 'routine';
        if ($('#requisitionPatientSearchInput')) $('#requisitionPatientSearchInput').value = '';
        if ($('#clinicalJustificationInput')) $('#clinicalJustificationInput').value = '';
        if ($('#nhisAuthNumberInput')) $('#nhisAuthNumberInput').value = '';
        if ($('#useNhisCheckbox')) $('#useNhisCheckbox').checked = false;
        if ($('#preferredCollectionTimeInput')) $('#preferredCollectionTimeInput').value = '';
        buildSelectedPatientCard(null);
        updateSummary();
        syncFooterBadges();
        document.querySelector('#urgencyChipRow .urgency-chip[data-urgency="routine"]')?.click();
        if (!silent) showFeedback($('#pageFeedback'), 'Draft cleared.', 'info');
    }
    async function submitLabOrder() {
        const feedback = $('#pageFeedback');
        if (!state.selectedPatient?.id) return showFeedback(feedback, 'Select a patient before submitting this laboratory order.', 'error');
        if (!state.draftItems.length) return showFeedback(feedback, 'Add at least one laboratory test before submitting.', 'error');
        const payload = {
            patient: state.selectedPatient.id,
            urgency: state.currentUrgency,
            collection_location: $('#collectionLocationSelect')?.value || 'outpatient',
            clinical_justification: $('#clinicalJustificationInput')?.value?.trim() || '',
            nhis_auth_number: $('#nhisAuthNumberInput')?.value?.trim() || undefined,
            use_nhis: Boolean($('#useNhisCheckbox')?.checked),
            preferred_collection_date: $('#preferredCollectionDateInput')?.value || undefined,
            preferred_collection_time: $('#preferredCollectionTimeInput')?.value || undefined,
            items: state.draftItems.map(item => ({
                test: item.id || item.short_code || item.name,
                name: item.name,
                short_code: item.short_code,
                sample_type: item.default_sample_type,
            }))
        };
        try {
            const response = await apiFetch(endpoints.labOrders, { method: 'POST', body: JSON.stringify(payload) });
            if (!response.ok) {
                const data = await safeJson(response);
                const message = data?.detail || data?.message || Object.entries(data || {}).map(([k,v]) => \`\${k}: \${Array.isArray(v) ? v.join(', ') : v}\`).join(' • ') || 'Unable to create laboratory order.';
                throw new Error(message);
            }
            const data = await safeJson(response);
            clearDraft(true);
            showFeedback(feedback, \`Laboratory order \${data?.order_id || 'created'} successfully.\`, 'success');
            setTimeout(() => { window.location.href = body.dataset.laboratoryUrl || 'laboratory.php'; }, 900);
        } catch (err) {
            showFeedback(feedback, err.message || 'Unable to create laboratory order.', 'error');
        }
    }
    function updateClock() {
        const el = $('#systemClock');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) + ' GMT';
    }
    async function init() {
        setProfile();
        wireGlobalSearch();
        wireModalDismiss();
        $('#logoutBtn')?.addEventListener('click', logout);
        await Promise.all([loadSystemStatus(), loadTestsCatalog(), loadStaff()]);
        populateQuickTestSelect();
        if (page === 'laboratory-dashboard') {
            wireQuickCreatePanel();
            wireOrdersControls();
            await loadOrders(1);
        }
        if (page === 'laboratory-requisition') {
            wireRequisitionPage();
        }
    }
    init();
})();
    </script>
</body>

</html>