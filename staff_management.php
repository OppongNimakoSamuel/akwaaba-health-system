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
$full_name = htmlspecialchars($user['full_name']);
$role = htmlspecialchars($user['role']);
$hospital_name = 'Akwaaba Health';

$dashboard_url = 'dashboard.php';
$patients_url = 'patients.php';
$patient_detail_base_url = 'patients.php?id=';
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
    <title>Akwaaba Health | Staff & Access Control</title>
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
            --shadow: 0 20px 45px rgba(28, 56, 92, 0.08);
            --radius-lg: 22px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
            color: var(--text);
        }

        body.modal-open {
            overflow: hidden;
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

        svg {
            width: 1em;
            height: 1em;
            fill: currentColor;
        }

        .hidden {
            display: none !important;
        }

        .page-shell {
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.9);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(10px);
            padding: 22px 18px;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            background: linear-gradient(135deg, #2f86cc 0%, #205f96 100%);
        }

        .sidebar-brand strong {
            font-size: 1.2rem;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 18px 6px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 14px;
            border-radius: 16px;
            text-decoration: none;
            color: #4b5a70;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .nav-item.active {
            box-shadow: inset 0 0 0 1px rgba(39, 117, 182, 0.06);
        }

        .nav-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: inherit;
        }

        .nav-arrow {
            margin-left: auto;
            font-size: 1.25rem;
        }

        .sidebar-footer {
            padding: 16px 6px 6px;
            border-top: 1px solid var(--border);
        }

        .logout-btn,
        .system-panel,
        .primary-btn,
        .secondary-btn,
        .ghost-btn,
        .icon-btn,
        .tab-btn,
        .page-btn,
        .menu-btn,
        .menu-item {
            border: none;
            background: none;
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
            transition: .2s;
        }

        .logout-btn:hover {
            background: #fff1f1;
        }

        .system-panel {
            margin-top: 14px;
            padding: 14px;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .system-panel-label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .system-panel-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            font-weight: 600;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #d0d7e4;
        }

        .status-active {
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(62, 182, 107, 0.12);
        }

        .main-stage {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 30px;
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: #617188;
            background: #ffffff;
            border: 1px solid var(--border);
            box-shadow: 0 6px 18px rgba(29, 36, 51, 0.05);
            transition: .2s;
        }

        .icon-btn:hover {
            color: var(--primary);
            border-color: rgba(39, 117, 182, 0.25);
        }

        .search-wrap {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7e8aa0;
            font-size: 1rem;
        }

        .top-search-wrap {
            width: min(520px, 100%);
        }

        .search-wrap input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(248, 250, 253, 0.92);
            color: var(--text);
            outline: none;
            transition: 0.2s ease;
        }

        .search-wrap input:focus,
        .form-grid input:focus,
        .form-grid select:focus {
            border-color: rgba(39, 117, 182, 0.45);
            box-shadow: 0 0 0 4px rgba(39, 117, 182, 0.12);
            background: #ffffff;
        }

        .search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 8px;
            z-index: 30;
            max-height: 340px;
            overflow-y: auto;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 12px;
            border-radius: 12px;
            color: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .search-result-item:hover {
            background: var(--primary-soft);
        }

        .search-result-item small {
            display: block;
            color: var(--muted);
            margin-top: 4px;
        }

        .profile-chip {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(29, 36, 51, 0.05);
        }

        .profile-copy {
            display: flex;
            flex-direction: column;
            text-align: right;
        }

        .profile-copy strong {
            font-size: 0.95rem;
        }

        .profile-copy span {
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #ffffff;
            background: linear-gradient(135deg, #93b8dd 0%, #407cb6 100%);
        }

        .presence-dot {
            position: absolute;
            right: 10px;
            bottom: 10px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            background: var(--success);
        }

        .content-shell {
            padding: 30px;
        }

        .hero-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .hero-row h1 {
            margin: 0 0 8px;
            font-size: 2.3rem;
            letter-spacing: -0.03em;
        }

        .hero-row p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
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
            transition: 0.2s ease;
            cursor: pointer;
        }

        .primary-btn {
            padding: 13px 18px;
            color: #ffffff;
            background: linear-gradient(135deg, #2f86cc 0%, #246ea9 100%);
            box-shadow: 0 14px 28px rgba(39, 117, 182, 0.24);
        }

        .primary-btn:hover {
            background: linear-gradient(135deg, #327ebd 0%, #1f6299 100%);
        }

        .secondary-btn,
        .ghost-btn,
        .page-btn,
        .tab-btn,
        .menu-btn {
            padding: 12px 16px;
            background: #ffffff;
            border: 1px solid var(--border);
            color: #536276;
        }

        .secondary-btn:hover,
        .ghost-btn:hover,
        .page-btn:hover,
        .tab-btn:hover,
        .menu-btn:hover {
            border-color: rgba(39, 117, 182, 0.28);
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(229, 234, 241, 0.95);
            box-shadow: var(--shadow);
            padding: 20px 22px;
            border-radius: 20px;
        }

        .stat-label {
            color: #7c899b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .stat-value {
            margin-top: 12px;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .stat-meta {
            margin-top: 8px;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .stat-green .stat-value {
            color: var(--success);
        }

        .stat-purple .stat-value {
            color: #8b5cf6;
        }

        .stat-red .stat-value {
            color: var(--danger);
        }

        .tab-strip {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .tab-strip .tab-btn {
            background: transparent;
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            color: var(--muted);
            border-bottom: 2px solid transparent;
            border-radius: 0;
            box-shadow: none;
            border-top: 1px solid transparent;
            border-left: 1px solid transparent;
            border-right: 1px solid transparent;
        }

        .tab-strip .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .permissions-layout {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .role-list-card {
            flex: 1 1 300px;
        }

        .permissions-card {
            flex: 2 1 500px;
        }

        .card-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .split-stacked {
            align-items: flex-start;
        }

        .card-title-row h2 {
            margin: 0 0 6px;
            font-size: 1.3rem;
        }

        .card-title-row p {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .role-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }

        .role-card {
            text-align: left;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 100%;
            border-left: 4px solid transparent;
        }

        .role-card:hover {
            border-color: rgba(39, 117, 182, 0.25);
            background: #fcfdff;
        }

        .role-card.active {
            border-left-color: var(--primary);
            border-color: var(--primary);
            background: var(--primary-soft);
            box-shadow: 0 4px 12px rgba(39, 117, 182, 0.08);
        }

        .role-card strong {
            font-size: 1.05rem;
        }

        .role-card small {
            color: var(--muted);
            font-size: 0.85rem;
        }

        .panel-actions {
            display: flex;
            gap: 10px;
        }

        .table-shell {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .data-table th,
        .data-table td {
            padding: 16px 14px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
            vertical-align: middle;
        }

        .data-table th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .permissions-table .flag-name {
            font-weight: 600;
            color: #44516a;
        }

        .permissions-table .flag-check {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .permissions-table .permission-desc {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .security-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #f8fbff;
            border: 1px solid #cbe0f4;
            border-radius: 16px;
            margin-top: 24px;
        }

        .security-banner strong {
            font-size: 1.05rem;
            display: block;
            margin-bottom: 4px;
            color: var(--primary-dark);
        }

        .security-banner p {
            margin: 0;
            color: #5b6f8a;
            font-size: 0.95rem;
        }

        .toolbar-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .toolbar-row select {
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: rgba(250, 252, 255, 0.94);
            outline: none;
        }

        .compact-search {
            flex: 1;
            min-width: 250px;
        }

        .compact-search input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border-radius: 14px;
            border: 1px solid var(--border);
            outline: none;
        }

        .compact-search .search-icon {
            left: 14px;
        }

        .table-placeholder {
            text-align: center;
            color: var(--muted);
            padding: 30px !important;
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 18px;
            gap: 16px;
            margin-top: 10px;
        }

        .footer-caption {
            font-size: 0.92rem;
            color: var(--muted);
            font-weight: 600;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
        }

        .page-btn.active {
            background: var(--primary-soft);
            border-color: rgba(39, 117, 182, 0.2);
            color: var(--primary);
        }

        .staff-name-cell strong {
            display: block;
            margin-bottom: 4px;
        }

        .staff-name-cell small {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .status-badge {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .status-badge.active {
            background: #eefaf2;
            color: #1c8c4f;
        }

        .status-badge.inactive {
            background: #f4f6f9;
            color: #7a8698;
        }

        .permission-count-badge {
            display: inline-flex;
            padding: 6px 10px;
            background: #eef2f6;
            color: #516174;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .row-action-wrap {
            position: relative;
            text-align: right;
        }

        .menu-btn {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .row-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 180px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 8px;
            z-index: 12;
        }

        .row-menu .menu-item {
            width: 100%;
            justify-content: flex-start;
            padding: 10px 12px;
            border-radius: 12px;
            color: #516174;
            font-size: .9rem;
        }

        .row-menu .menu-item.danger {
            color: var(--danger);
        }

        .row-menu .menu-item:hover {
            background: var(--primary-soft);
        }

        .audit-actor strong {
            display: block;
        }

        .audit-actor small {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .audit-badge {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .audit-risk-low {
            background: #eefaf2;
            color: #1c8c4f;
        }

        .audit-risk-medium {
            background: #fff6e5;
            color: #b27b14;
        }

        .audit-risk-high {
            background: #fff1f1;
            color: #c94b4b;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 50;
        }

        .modal-card {
            width: min(980px, 100%);
            max-height: 92vh;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 28px;
            padding: 0;
            position: relative;
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0 0 6px;
            font-size: 1.6rem;
        }

        .modal-header p {
            margin: 0;
            color: var(--muted);
        }

        .close-btn {
            font-size: 1.3rem;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            position: sticky;
            bottom: 0;
            background: rgba(255, 255, 255, 0.97);
        }

        .form-alert {
            margin: 18px 28px 0;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .form-alert.error {
            background: #fff2f2;
            color: #b53737;
            border: 1px solid #f1c4c4;
        }

        .form-alert.success {
            background: #effaf3;
            color: #1f8a4e;
            border: 1px solid #c4ead2;
        }

        #staffForm {
            padding: 22px 28px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-field label {
            font-size: 0.88rem;
            font-weight: 600;
            color: #4b5a70;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #ffffff;
            color: var(--text);
            outline: none;
            transition: 0.2s ease;
        }

        .readonly-field input {
            background: #f1f4f8;
            color: var(--muted);
            cursor: not-allowed;
        }

        .required {
            color: var(--danger);
        }

        .field-error {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 4px;
            display: block;
            min-height: 14px;
        }

        .hint-text {
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 4px;
            display: block;
        }

        .section-divider {
            height: 1px;
            background: var(--border);
            margin: 24px 0;
        }

        .card-light {
            background: #fbfdff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .section-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .section-row h3 {
            margin: 0 0 6px;
            font-size: 1.15rem;
        }

        .section-row p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .permissions-check-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .check-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #ffffff;
            cursor: pointer;
            transition: 0.2s;
        }

        .check-card:hover {
            border-color: rgba(39, 117, 182, 0.4);
            background: #f8fafc;
        }

        .check-card input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .security-strip {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .toggle-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #ffffff;
            cursor: pointer;
        }

        .toggle-row input {
            width: 44px;
            height: 24px;
            appearance: none;
            background: #d4dce8;
            border-radius: 12px;
            position: relative;
            cursor: pointer;
            outline: none;
            transition: 0.2s;
        }

        .toggle-row input::before {
            content: "";
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ffffff;
            position: absolute;
            top: 3px;
            left: 3px;
            transition: 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .toggle-row input:checked {
            background: var(--success);
        }

        .toggle-row input:checked::before {
            transform: translateX(20px);
        }

        .toast-stack {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            padding: 14px 20px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-left: 4px solid var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .toast.success {
            border-left-color: var(--success);
            color: #1c8c4f;
        }

        .toast.error {
            border-left-color: var(--danger);
            color: #b53737;
        }

        .toast.info {
            border-left-color: var(--primary);
            color: #1d6fb8;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media(max-width: 900px) {

            .stats-grid,
            .permissions-layout {
                grid-template-columns: 1fr;
            }

            .form-grid,
            .permissions-check-grid,
            .toggle-grid {
                grid-template-columns: 1fr;
            }

            .page-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }
    </style>
</head>

<body data-hospital-name="<?= htmlspecialchars($hospital_name) ?>" data-user-name="<?= $full_name ?>"
    data-user-role="<?= $role ?>" data-dashboard-url="<?= $dashboard_url ?>" data-patients-url="<?= $patients_url ?>"
    data-patient-detail-base-url="<?= $patient_detail_base_url ?>" data-appointments-url="<?= $appointments_url ?>"
    data-clinical-url="<?= $clinical_url ?>" data-billing-url="<?= $billing_url ?>"
    data-laboratory-url="<?= $laboratory_url ?>" data-staff-url="<?= $staff_url ?>" data-login-url="<?= $login_url ?>"
    data-dashboard-api-url="/api/v1/dashboard/" data-patients-search-api-url="/api/v1/patients/search/"
    data-staff-api-url="/api/v1/staff/" data-staff-stats-api-url="/api/v1/staff/stats/"
    data-staff-audit-api-url="/api/v1/staff/audit/" data-token-refresh-url="/api/v1/auth/token/refresh/"
    data-logout-api-url="/api/v1/auth/logout/" data-page-size="25">

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
                        <strong>
                            <?= htmlspecialchars($hospital_name) ?>
                        </strong>
                    </div>
                </div>
                <nav class="nav-list">
                    <a href="<?= $dashboard_url ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path d="M4 13h6V4H4Zm10 7h6V11h-6ZM4 20h6v-5H4Zm10-9h6V4h-6Z" />
                            </svg>
                        </span><span>Dashboard</span>
                    </a>
                    <a href="<?= $patients_url ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M16 11c1.66 0 3-1.79 3-4s-1.34-4-3-4-3 1.79-3 4 1.34 4 3 4Zm-8 0c1.66 0 3-1.79 3-4S9.66 3 8 3 5 4.79 5 7s1.34 4 3 4Zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V20h6v-3.5c0-2.33-4.67-3.5-7-3.5Z" />
                            </svg>
                        </span><span>Patients</span>
                    </a>
                    <a href="<?= $appointments_url ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V9h14Z" />
                            </svg>
                        </span><span>Appointments</span>
                    </a>
                    <a href="<?= $clinical_url ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5" />
                                <path d="M8 13h8M8 17h8M8 9h3" />
                            </svg>
                        </span><span>Clinical (EHR)</span>
                    </a>
                    <a href="<?= $billing_url ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M20 6H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2Zm0 10H4V8h16ZM6 12h4" />
                            </svg>
                        </span><span>Billing &amp; NHIS</span>
                    </a>
                    <a href="<?= $laboratory_url ?>" class="nav-item">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M9 2v5.59l-4.7 8.14A3 3 0 0 0 6.9 20h10.2a3 3 0 0 0 2.6-4.27L15 7.59V2Zm2 2h2v4.12l4.98 8.63a1 1 0 0 1-.87 1.5H6.9a1 1 0 0 1-.87-1.5L11 8.12Z" />
                            </svg>
                        </span><span>Laboratory</span>
                    </a>
                    <a href="<?= $staff_url ?>" class="nav-item active">
                        <span class="nav-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M12 8a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5Zm7.5-1a2.5 2.5 0 1 0-2.45-3h-.05a4.94 4.94 0 0 1 0 5.97h.05A2.5 2.5 0 0 0 19.5 9ZM21 18v-2c0-1.64-1.2-3.06-3.03-4 1.11.95 1.78 2.06 1.78 3.29V18Z" />
                            </svg>
                        </span><span>Staff Management</span><span class="nav-arrow">›</span>
                    </a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <button type="button" class="logout-btn" id="logoutBtn">
                    <span aria-hidden="true">↪</span><span>Logout</span>
                </button>
                <div class="system-panel">
                    <div class="system-panel-label">System Status</div>
                    <div class="system-panel-row">
                        <span class="status-dot" id="statusDot"></span>
                        <span id="systemStatusText">Checking service health…</span>
                    </div>
                    <div class="system-panel-meta" id="ghsStatusText"
                        style="font-size:.7rem; color:var(--muted); margin-top:4px;">
                        GHS server status unknown
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
                            <strong id="profileName">
                                <?= $full_name ?>
                            </strong>
                            <span id="profileRole">
                                <?= ucfirst($role) ?>
                            </span>
                        </div>
                        <div class="profile-avatar" id="profileAvatar">
                            <?= strtoupper(substr($full_name, 0, 1)) ?>
                        </div>
                        <span class="presence-dot"></span>
                    </div>
                </div>
            </header>

            <main class="content-shell">
                <section class="hero-row">
                    <div>
                        <h1>Staff &amp; Access Control</h1>
                        <p>
                            Manage hospital staff accounts, define role presets, and monitor
                            system security.
                        </p>
                    </div>
                    <button type="button" class="primary-btn" id="openCreateModalBtn">
                        <svg viewBox="0 0 24 24">
                            <path d="M11 5h2v14h-2zM5 11h14v2H5z" />
                        </svg><span>Add New Staff Member</span>
                    </button>
                </section>

                <section class="stats-grid" aria-label="Staff statistics">
                    <article class="stat-card">
                        <div class="stat-label">Total Staff Users</div>
                        <div class="stat-value" id="totalStaffStat">0</div>
                        <div class="stat-meta" id="totalStaffMeta">
                            Loaded from staff registry
                        </div>
                    </article>
                    <article class="stat-card stat-green">
                        <div class="stat-label">Active Roles</div>
                        <div class="stat-value" id="activeRolesStat">0</div>
                        <div class="stat-meta" id="activeRolesMeta">
                            Role templates in use
                        </div>
                    </article>
                    <article class="stat-card stat-purple">
                        <div class="stat-label">Online Now</div>
                        <div class="stat-value" id="onlineNowStat">0</div>
                        <div class="stat-meta" id="onlineNowMeta">
                            Active accounts on this page
                        </div>
                    </article>
                    <article class="stat-card stat-red">
                        <div class="stat-label">Critical Alerts</div>
                        <div class="stat-value" id="criticalAlertsStat">0</div>
                        <div class="stat-meta" id="criticalAlertsMeta">
                            Recent security signals
                        </div>
                    </article>
                </section>

                <section class="tab-strip" role="tablist" aria-label="Staff page sections">
                    <button type="button" class="tab-btn active" data-tab="permissions">Role Permissions</button>
                    <button type="button" class="tab-btn" data-tab="directory">Staff Directory</button>
                    <button type="button" class="tab-btn" data-tab="audit">Security Audit</button>
                </section>

                <section class="tab-panel active" data-panel="permissions">
                    <div class="permissions-layout">
                        <article class="card role-list-card">
                            <div class="card-title-row">
                                <div>
                                    <h2>Defined Roles</h2>
                                    <p>UI role presets based on documented staff roles.</p>
                                </div>
                            </div>
                            <div class="role-list" id="roleTemplateList"></div>
                            <button type="button" class="secondary-btn full-width" id="createCustomRoleBtn">
                                + Create Custom Role
                            </button>
                        </article>

                        <article class="card permissions-card">
                            <div class="card-title-row">
                                <div>
                                    <h2 id="permissionPanelTitle">Administrator Permissions</h2>
                                    <p id="permissionPanelSubtitle">
                                        Granular access flags available for staff profiles.
                                    </p>
                                </div>
                                <div class="panel-actions">
                                    <button type="button" class="ghost-btn" id="resetTemplateBtn">
                                        Reset Template
                                    </button>
                                    <button type="button" class="primary-btn" style="padding:10px 14px;"
                                        id="useTemplateBtn">
                                        Use for New Staff
                                    </button>
                                </div>
                            </div>
                            <div class="table-shell">
                                <table class="data-table permissions-table">
                                    <thead>
                                        <tr>
                                            <th>Permission Flag</th>
                                            <th>Enabled</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody id="permissionsTableBody"></tbody>
                                </table>
                            </div>
                        </article>
                    </div>

                    <div class="security-banner">
                        <div>
                            <strong>Security Best Practices</strong>
                            <p>
                                Role changes should be reviewed carefully. Staff permissions
                                are stored per user profile, and administrative actions are
                                captured in the audit trail.
                            </p>
                        </div>
                        <button type="button" class="secondary-btn" id="viewPolicyBtn">
                            View Full Policy
                        </button>
                    </div>
                </section>

                <section class="tab-panel" data-panel="directory">
                    <article class="card">
                        <div class="card-title-row split-stacked">
                            <div>
                                <h2>Staff Directory</h2>
                                <p>Live staff records from the administrator module.</p>
                            </div>
                            <div class="toolbar-row">
                                <div class="search-wrap compact-search">
                                    <svg class="search-icon" viewBox="0 0 24 24">
                                        <path
                                            d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                                    </svg>
                                    <input type="search" id="staffFilterInput"
                                        placeholder="Filter staff on this page by name, ID or email..." />
                                </div>
                                <select id="staffRoleFilter">
                                    <option value="">All Roles</option>
                                </select>
                                <select id="staffStatusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <button type="button" class="secondary-btn" id="refreshStaffBtn">Refresh</button>
                            </div>
                        </div>
                        <div class="table-shell">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Full Name</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Region</th>
                                        <th>Status</th>
                                        <th>Permissions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="staffTableBody">
                                    <tr>
                                        <td colspan="8" class="table-placeholder">Loading staff records...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <div class="footer-caption" id="staffResultsCaption">Showing 0 results</div>
                            <div class="pagination-controls" id="staffPaginationControls"></div>
                        </div>
                    </article>
                </section>

                <section class="tab-panel" data-panel="audit">
                    <article class="card">
                        <div class="card-title-row split-stacked">
                            <div>
                                <h2>Security Audit</h2>
                                <p>Recent security and access events from the staff audit endpoint.</p>
                            </div>
                            <div class="toolbar-row">
                                <div class="search-wrap compact-search">
                                    <svg class="search-icon" viewBox="0 0 24 24">
                                        <path
                                            d="M10 4a6 6 0 1 0 3.87 10.58l4.77 4.77 1.41-1.41-4.77-4.77A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z" />
                                    </svg>
                                    <input type="search" id="auditFilterInput"
                                        placeholder="Filter audit log by actor, action or description..." />
                                </div>
                                <button type="button" class="secondary-btn" id="refreshAuditBtn">Refresh Audit</button>
                            </div>
                        </div>
                        <div class="table-shell">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Actor</th>
                                        <th>Action</th>
                                        <th>Resource</th>
                                        <th>Department</th>
                                        <th>Region</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody id="auditTableBody">
                                    <tr>
                                        <td colspan="7" class="table-placeholder">Loading audit records...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <div class="footer-caption" id="auditResultsCaption">Showing 0 events</div>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>

    <div class="modal-overlay hidden" id="staffModalOverlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="staffModalTitle">
            <div class="modal-header">
                <div>
                    <h2 id="staffModalTitle">Register New Staff Member</h2>
                    <p id="staffModalSubtitle">Create a staff profile with the documented staff fields and permission
                        flags.</p>
                </div>
                <button type="button" class="icon-btn close-btn" id="closeStaffModalBtn"
                    aria-label="Close dialog">✕</button>
            </div>

            <form id="staffForm" novalidate>
                <input type="hidden" id="staffRecordId" value="" />
                <div class="form-grid">
                    <div class="form-field readonly-field">
                        <label for="staffIdPreview">Staff ID</label>
                        <input type="text" id="staffIdPreview" value="AUTO-GENERATED" readonly />
                        <small class="hint-text">Read-only in the API reference.</small>
                    </div>
                    <div class="form-field">
                        <label for="fullNameInput">Full Name <span class="required">*</span></label>
                        <input type="text" id="fullNameInput" name="full_name" placeholder="e.g. Dr. Ama Danquah"
                            required />
                        <small class="field-error" data-error-for="full_name"></small>
                    </div>
                    <div class="form-field">
                        <label for="roleInput">Role <span class="required">*</span></label>
                        <select id="roleInput" name="role" required></select>
                        <small class="field-error" data-error-for="role"></small>
                    </div>
                    <div class="form-field">
                        <label for="departmentInput">Department <span class="required">*</span></label>
                        <input type="text" id="departmentInput" name="department" placeholder="e.g. Outpatient"
                            required />
                        <small class="field-error" data-error-for="department"></small>
                    </div>
                    <div class="form-field">
                        <label for="emailInput">Email Address <span class="required">*</span></label>
                        <input type="email" id="emailInput" name="email" placeholder="staff@akwaabahealth.gh"
                            required />
                        <small class="field-error" data-error-for="email"></small>
                    </div>
                    <div class="form-field">
                        <label for="phoneInput">Phone Number</label>
                        <input type="tel" id="phoneInput" name="phone" placeholder="+233 20 000 0000" />
                        <small class="field-error" data-error-for="phone"></small>
                    </div>
                    <div class="form-field">
                        <label for="regionInput">Region <span class="required">*</span></label>
                        <select id="regionInput" name="region" required></select>
                        <small class="field-error" data-error-for="region"></small>
                    </div>
                    <div class="form-field">
                        <label for="employmentStartDateInput">Employment Start Date</label>
                        <input type="date" id="employmentStartDateInput" name="employment_start_date" />
                        <small class="field-error" data-error-for="employment_start_date"></small>
                    </div>
                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label for="profilePhotoInput">Profile Photo</label>
                        <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" />
                        <small class="field-error" data-error-for="profile_photo"></small>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="card-light">
                    <div class="section-row">
                        <div>
                            <h3>Permissions &amp; Access</h3>
                            <p>Flags map directly to documented staff permission fields.</p>
                        </div>
                        <button type="button" class="ghost-btn" id="applyRoleTemplateBtn">
                            Apply selected role template
                        </button>
                    </div>
                    <div class="permissions-check-grid">
                        <label class="check-card"><input type="checkbox" id="canViewPatientRecordsInput"
                                name="can_view_patient_records" /><span>View Patient Records</span></label>
                        <label class="check-card"><input type="checkbox" id="canEditVitalsInput"
                                name="can_edit_vitals" /><span>Edit Vitals</span></label>
                        <label class="check-card"><input type="checkbox" id="canPrescribeMedsInput"
                                name="can_prescribe_meds" /><span>Prescribe Meds</span></label>
                        <label class="check-card"><input type="checkbox" id="canAccessBillingInput"
                                name="can_access_billing" /><span>Billing Access</span></label>
                        <label class="check-card"><input type="checkbox" id="canAccessLabReportingInput"
                                name="can_access_lab_reporting" /><span>Lab Reporting</span></label>
                        <label class="check-card"><input type="checkbox" id="canAccessAdminSettingsInput"
                                name="can_access_admin_settings" /><span>Admin Settings</span></label>
                    </div>
                </div>

                <div class="card-light security-strip">
                    <div class="section-row">
                        <div>
                            <h3>Security</h3>
                            <p>These flags are also part of the documented staff profile.</p>
                        </div>
                    </div>
                    <div class="toggle-grid">
                        <label class="toggle-row"><span>MFA Enabled</span><input type="checkbox" id="mfaEnabledInput"
                                name="mfa_enabled" /></label>
                        <label class="toggle-row"><span>Active Account</span><input type="checkbox" id="isActiveInput"
                                name="is_active" checked /></label>
                        <label class="toggle-row"><span>Must Change Password</span><input type="checkbox"
                                id="mustChangePasswordInput" name="must_change_password" checked /></label>
                    </div>
                </div>

                <div class="form-alert hidden" id="staffFormAlert"></div>

                <div class="modal-footer">
                    <button type="button" class="secondary-btn" id="cancelStaffBtn">Cancel</button>
                    <button type="submit" class="primary-btn" id="saveStaffBtn"><span>Create Staff
                            Profile</span></button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>

    <script>
        (() => {
            const body = document.body;
            const config = {
                hospitalName: body.dataset.hospitalName || 'Akwaaba Health',
                userName: body.dataset.userName || 'Dr. Kofi Mensah',
                userRole: body.dataset.userRole || 'administrator',
                loginUrl: body.dataset.loginUrl || '/login/',
                dashboardApiUrl: body.dataset.dashboardApiUrl || '/api/v1/dashboard/',
                patientSearchApiUrl: body.dataset.patientsSearchApiUrl || '/api/v1/patients/search/',
                staffApiUrl: body.dataset.staffApiUrl || '/api/v1/staff/',
                staffStatsApiUrl: body.dataset.staffStatsApiUrl || '/api/v1/staff/stats/',
                staffAuditApiUrl: body.dataset.staffAuditApiUrl || '/api/v1/staff/audit/',
                tokenRefreshUrl: body.dataset.tokenRefreshUrl || '/api/v1/auth/token/refresh/',
                logoutApiUrl: body.dataset.logoutApiUrl || '/api/v1/auth/logout/',
                patientDetailBaseUrl: body.dataset.patientDetailBaseUrl || '/patients/',
                pageSize: Number(body.dataset.pageSize || 25)
            };

            const permissionMeta = [
                { key: 'can_view_patient_records', label: 'can_view_patient_records', description: 'Access to patient profile pages and EHR records.' },
                { key: 'can_edit_vitals', label: 'can_edit_vitals', description: 'Permission to update SOAP Objective section vitals.' },
                { key: 'can_prescribe_meds', label: 'can_prescribe_meds', description: 'Permission to create prescriptions.' },
                { key: 'can_access_billing', label: 'can_access_billing', description: 'Access to the billing module and invoice management.' },
                { key: 'can_access_lab_reporting', label: 'can_access_lab_reporting', description: 'Access to lab result recording and pathologist sign-off.' },
                { key: 'can_access_admin_settings', label: 'can_access_admin_settings', description: 'Access to system configuration and staff management.' }
            ];

            const roleChoices = [
                { value: 'administrator', label: 'Administrator' },
                { value: 'doctor', label: 'Doctor' },
                { value: 'nurse', label: 'Nurse' },
                { value: 'receptionist', label: 'Receptionist' },
                { value: 'lab_technician', label: 'Lab Technician' },
                { value: 'pharmacist', label: 'Pharmacist' }
            ];

            const defaultRoleTemplates = {
                administrator: {
                    name: 'Administrator',
                    role: 'administrator',
                    permissions: {
                        can_view_patient_records: true,
                        can_edit_vitals: true,
                        can_prescribe_meds: true,
                        can_access_billing: true,
                        can_access_lab_reporting: true,
                        can_access_admin_settings: true
                    }
                },
                doctor: {
                    name: 'Doctor',
                    role: 'doctor',
                    permissions: {
                        can_view_patient_records: true,
                        can_edit_vitals: true,
                        can_prescribe_meds: true,
                        can_access_billing: false,
                        can_access_lab_reporting: false,
                        can_access_admin_settings: false
                    }
                },
                nurse: {
                    name: 'Nurse',
                    role: 'nurse',
                    permissions: {
                        can_view_patient_records: true,
                        can_edit_vitals: true,
                        can_prescribe_meds: false,
                        can_access_billing: false,
                        can_access_lab_reporting: false,
                        can_access_admin_settings: false
                    }
                },
                receptionist: {
                    name: 'Receptionist',
                    role: 'receptionist',
                    permissions: {
                        can_view_patient_records: false,
                        can_edit_vitals: false,
                        can_prescribe_meds: false,
                        can_access_billing: true,
                        can_access_lab_reporting: false,
                        can_access_admin_settings: false
                    }
                },
                lab_technician: {
                    name: 'Lab Technician',
                    role: 'lab_technician',
                    permissions: {
                        can_view_patient_records: false,
                        can_edit_vitals: false,
                        can_prescribe_meds: false,
                        can_access_billing: false,
                        can_access_lab_reporting: true,
                        can_access_admin_settings: false
                    }
                },
                pharmacist: {
                    name: 'Pharmacist',
                    role: 'pharmacist',
                    permissions: {
                        can_view_patient_records: false,
                        can_edit_vitals: false,
                        can_prescribe_meds: false,
                        can_access_billing: false,
                        can_access_lab_reporting: false,
                        can_access_admin_settings: false
                    }
                }
            };

            const regions = [
                'Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern', 'Greater Accra', 'North East',
                'Northern', 'Oti', 'Savannah', 'Upper East', 'Upper West', 'Volta', 'Western', 'Western North'
            ];

            const els = {
                profileName: document.getElementById('profileName'),
                profileRole: document.getElementById('profileRole'),
                profileAvatar: document.getElementById('profileAvatar'),
                statusDot: document.getElementById('statusDot'),
                systemStatusText: document.getElementById('systemStatusText'),
                ghsStatusText: document.getElementById('ghsStatusText'),
                logoutBtn: document.getElementById('logoutBtn'),
                openCreateModalBtn: document.getElementById('openCreateModalBtn'),
                roleTemplateList: document.getElementById('roleTemplateList'),
                permissionsTableBody: document.getElementById('permissionsTableBody'),
                permissionPanelTitle: document.getElementById('permissionPanelTitle'),
                permissionPanelSubtitle: document.getElementById('permissionPanelSubtitle'),
                resetTemplateBtn: document.getElementById('resetTemplateBtn'),
                useTemplateBtn: document.getElementById('useTemplateBtn'),
                createCustomRoleBtn: document.getElementById('createCustomRoleBtn'),
                viewPolicyBtn: document.getElementById('viewPolicyBtn'),
                totalStaffStat: document.getElementById('totalStaffStat'),
                totalStaffMeta: document.getElementById('totalStaffMeta'),
                activeRolesStat: document.getElementById('activeRolesStat'),
                activeRolesMeta: document.getElementById('activeRolesMeta'),
                onlineNowStat: document.getElementById('onlineNowStat'),
                onlineNowMeta: document.getElementById('onlineNowMeta'),
                criticalAlertsStat: document.getElementById('criticalAlertsStat'),
                criticalAlertsMeta: document.getElementById('criticalAlertsMeta'),
                tabButtons: [...document.querySelectorAll('.tab-btn')],
                tabPanels: [...document.querySelectorAll('.tab-panel')],
                staffFilterInput: document.getElementById('staffFilterInput'),
                staffRoleFilter: document.getElementById('staffRoleFilter'),
                staffStatusFilter: document.getElementById('staffStatusFilter'),
                refreshStaffBtn: document.getElementById('refreshStaffBtn'),
                staffTableBody: document.getElementById('staffTableBody'),
                staffResultsCaption: document.getElementById('staffResultsCaption'),
                staffPaginationControls: document.getElementById('staffPaginationControls'),
                auditFilterInput: document.getElementById('auditFilterInput'),
                refreshAuditBtn: document.getElementById('refreshAuditBtn'),
                auditTableBody: document.getElementById('auditTableBody'),
                auditResultsCaption: document.getElementById('auditResultsCaption'),
                globalPatientSearchInput: document.getElementById('globalPatientSearchInput'),
                globalSearchResults: document.getElementById('globalSearchResults'),
                staffModalOverlay: document.getElementById('staffModalOverlay'),
                closeStaffModalBtn: document.getElementById('closeStaffModalBtn'),
                cancelStaffBtn: document.getElementById('cancelStaffBtn'),
                staffForm: document.getElementById('staffForm'),
                staffRecordId: document.getElementById('staffRecordId'),
                staffIdPreview: document.getElementById('staffIdPreview'),
                fullNameInput: document.getElementById('fullNameInput'),
                roleInput: document.getElementById('roleInput'),
                departmentInput: document.getElementById('departmentInput'),
                emailInput: document.getElementById('emailInput'),
                phoneInput: document.getElementById('phoneInput'),
                regionInput: document.getElementById('regionInput'),
                employmentStartDateInput: document.getElementById('employmentStartDateInput'),
                profilePhotoInput: document.getElementById('profilePhotoInput'),
                canViewPatientRecordsInput: document.getElementById('canViewPatientRecordsInput'),
                canEditVitalsInput: document.getElementById('canEditVitalsInput'),
                canPrescribeMedsInput: document.getElementById('canPrescribeMedsInput'),
                canAccessBillingInput: document.getElementById('canAccessBillingInput'),
                canAccessLabReportingInput: document.getElementById('canAccessLabReportingInput'),
                canAccessAdminSettingsInput: document.getElementById('canAccessAdminSettingsInput'),
                mfaEnabledInput: document.getElementById('mfaEnabledInput'),
                isActiveInput: document.getElementById('isActiveInput'),
                mustChangePasswordInput: document.getElementById('mustChangePasswordInput'),
                applyRoleTemplateBtn: document.getElementById('applyRoleTemplateBtn'),
                staffModalTitle: document.getElementById('staffModalTitle'),
                staffModalSubtitle: document.getElementById('staffModalSubtitle'),
                saveStaffBtn: document.getElementById('saveStaffBtn'),
                staffFormAlert: document.getElementById('staffFormAlert'),
                toastStack: document.getElementById('toastStack')
            };

            const state = {
                activeTab: 'permissions',
                selectedTemplateKey: 'administrator',
                roleTemplates: {},
                staff: [],
                filteredStaff: [],
                staffPage: 1,
                staffCount: 0,
                auditLogs: [],
                filteredAuditLogs: [],
                openMenuId: null,
                appliedTemplateForCreate: 'administrator'
            };

            const formatNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));
            const capitalize = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (match) => match.toUpperCase());
            const debounce = (fn, delay = 300) => {
                let timer;
                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => fn(...args), delay);
                };
            };
            const readCookie = (name) => {
                const parts = document.cookie ? document.cookie.split('; ') : [];
                for (const part of parts) {
                    const [key, ...value] = part.split('=');
                    if (key === name) return decodeURIComponent(value.join('='));
                }
                return '';
            };
            const preferredStorage = () => sessionStorage.getItem('akwaaba_remember') === 'true' ? localStorage : sessionStorage;
            const readStorage = (key) => sessionStorage.getItem(key) || localStorage.getItem(key);
            const saveAccessToken = (access) => access && preferredStorage().setItem('akwaaba_access', access);
            const getAccessToken = () => readStorage('akwaaba_access');
            const getRefreshToken = () => readStorage('akwaaba_refresh');

            const showToast = (message, type = 'success') => {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.textContent = message;
                els.toastStack.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(8px)';
                    toast.style.transition = '0.25s ease';
                    setTimeout(() => toast.remove(), 260);
                }, 3200);
            };

            const setAlert = (message) => {
                if (!message) {
                    els.staffFormAlert.className = 'form-alert hidden';
                    els.staffFormAlert.textContent = '';
                    return;
                }
                els.staffFormAlert.className = 'form-alert';
                els.staffFormAlert.textContent = message;
            };

            const clearErrors = () => {
                document.querySelectorAll('[data-error-for]').forEach((element) => { element.textContent = ''; });
                setAlert('');
            };

            const renderFieldErrors = (payload) => {
                clearErrors();
                if (!payload || typeof payload !== 'object') return;
                let generic = '';
                Object.entries(payload).forEach(([field, value]) => {
                    const message = Array.isArray(value) ? value.join(' ') : typeof value === 'object' ? Object.values(value).flat().join(' ') : String(value);
                    const target = document.querySelector(`[data-error-for="${field}"]`);
                    if (target) target.textContent = message;
                    else generic = generic ? `${generic} ${message}` : message;
                });
                if (generic) setAlert(generic);
            };

            const refreshAccessToken = async () => {
                const refresh = getRefreshToken();
                if (!refresh) return false;
                const response = await fetch(config.tokenRefreshUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ refresh })
                });
                if (!response.ok) return false;
                const data = await response.json();
                if (!data.access) return false;
                saveAccessToken(data.access);
                return true;
            };

            const apiFetch = async (url, options = {}, allowRefresh = true) => {
                const headers = new Headers(options.headers || {});
                headers.set('Accept', 'application/json');
                const token = getAccessToken();
                if (token) headers.set('Authorization', `Bearer ${token}`);
                const csrf = readCookie('csrftoken');
                if (csrf && !headers.has('X-CSRFToken')) headers.set('X-CSRFToken', csrf);
                const response = await fetch(url, { ...options, headers });
                if (response.status === 401 && allowRefresh && getRefreshToken()) {
                    const refreshed = await refreshAccessToken();
                    if (refreshed) return apiFetch(url, options, false);
                }
                return response;
            };

            const hydrateRoleTemplates = () => {
                let stored = {};
                try { stored = JSON.parse(localStorage.getItem('akwaaba_staff_role_templates') || '{}'); } catch (_error) { stored = {}; }
                state.roleTemplates = { ...defaultRoleTemplates, ...stored };
            };

            const saveRoleTemplates = () => {
                const customTemplates = Object.fromEntries(Object.entries(state.roleTemplates).filter(([key]) => !(key in defaultRoleTemplates)));
                localStorage.setItem('akwaaba_staff_role_templates', JSON.stringify(customTemplates));
            };

            const fillSelects = () => {
                els.roleInput.innerHTML = roleChoices.map(({ value, label }) => `<option value="${value}">${label}</option>`).join('');
                els.staffRoleFilter.innerHTML = '<option value="">All Roles</option>' + roleChoices.map(({ value, label }) => `<option value="${value}">${label}</option>`).join('');
                els.regionInput.innerHTML = '<option value="">Select region</option>' + regions.map((region) => `<option value="${region}">${region}</option>`).join('');
            };

            const getTemplate = (key) => state.roleTemplates[key] || defaultRoleTemplates.administrator;

            const renderRoleTemplates = () => {
                const entries = Object.entries(state.roleTemplates);
                els.roleTemplateList.innerHTML = entries.map(([key, template]) => {
                    const enabledCount = permissionMeta.filter((item) => template.permissions[item.key]).length;
                    return `
                <button type="button" class="role-card ${state.selectedTemplateKey === key ? 'active' : ''}" data-role-template="${key}">
                    <strong>${template.name}</strong>
                    <small>${capitalize(template.role || key)} · ${enabledCount} permission${enabledCount === 1 ? '' : 's'} enabled</small>
                </button>
            `;
                }).join('');
            };

            const renderPermissionsTable = () => {
                const template = getTemplate(state.selectedTemplateKey);
                els.permissionPanelTitle.textContent = `${template.name} Permissions`;
                els.permissionPanelSubtitle.textContent = `Preset for ${capitalize(template.role || state.selectedTemplateKey)} staff. Stored permissions still save per user profile.`;
                els.permissionsTableBody.innerHTML = permissionMeta.map((item) => `
            <tr>
                <td><span class="flag-name">${item.label}</span></td>
                <td><input class="flag-check" type="checkbox" data-permission-key="${item.key}" ${template.permissions[item.key] ? 'checked' : ''}></td>
                <td class="permission-desc">${item.description}</td>
            </tr>
        `).join('');
            };

            const updateTemplateFromTable = () => {
                const template = getTemplate(state.selectedTemplateKey);
                permissionMeta.forEach((item) => {
                    const checkbox = document.querySelector(`.flag-check[data-permission-key="${item.key}"]`);
                    template.permissions[item.key] = Boolean(checkbox?.checked);
                });
                saveRoleTemplates();
                renderRoleTemplates();
            };

            const applyTemplateToForm = (key = state.selectedTemplateKey) => {
                const template = getTemplate(key);
                els.roleInput.value = template.role || key;
                els.canViewPatientRecordsInput.checked = Boolean(template.permissions.can_view_patient_records);
                els.canEditVitalsInput.checked = Boolean(template.permissions.can_edit_vitals);
                els.canPrescribeMedsInput.checked = Boolean(template.permissions.can_prescribe_meds);
                els.canAccessBillingInput.checked = Boolean(template.permissions.can_access_billing);
                els.canAccessLabReportingInput.checked = Boolean(template.permissions.can_access_lab_reporting);
                els.canAccessAdminSettingsInput.checked = Boolean(template.permissions.can_access_admin_settings);
                state.appliedTemplateForCreate = key;
                showToast(`${template.name} template applied.`, 'info');
            };

            const buildPatientDetailUrl = (patient) => {
                const identifier = patient.id || patient.patient_id || '';
                return config.patientDetailBaseUrl + identifier;
            };

            const setStatusPanel = (systemStatus, ghsServer) => {
                const online = String(systemStatus || '').toLowerCase() === 'online';
                els.statusDot.className = `status-dot ${online ? 'status-active' : ''}`;
                els.systemStatusText.textContent = online ? 'Server Online' : capitalize(systemStatus || 'unknown');
                els.ghsStatusText.textContent = `GHS server: ${capitalize(ghsServer || 'unknown')}`;
            };

            const fetchSystemStatus = async () => {
                try {
                    const response = await apiFetch(config.dashboardApiUrl);
                    if (!response.ok) throw new Error('status');
                    const data = await response.json();
                    setStatusPanel(data.system_status, data.ghs_server);
                } catch (_error) {
                    setStatusPanel('unknown', 'unknown');
                }
            };

            const scoreAuditRisk = (log) => {
                const combined = `${log.action || ''} ${log.description || ''}`.toLowerCase();
                if (/delete|failed|lock|forbid|denied|critical|error/.test(combined)) return 'high';
                if (/password|role_change|logout|login|mfa/.test(combined)) return 'medium';
                return 'low';
            };

            const normalizeStaffStats = (payload) => {
                const fromApi = payload || {};
                const total = Number(fromApi.total_staff_users || fromApi.total_staff || fromApi.count || state.staffCount || state.staff.length || 0);
                const activeRoles = Number(fromApi.active_roles || fromApi.roles_count || new Set(state.staff.map((item) => item.role).filter(Boolean)).size || Object.keys(state.roleTemplates).length);
                const onlineNow = Number(fromApi.online_now || fromApi.active_accounts || state.staff.filter((item) => item.is_active).length || 0);
                const criticalAlerts = Number(fromApi.critical_alerts || fromApi.security_alerts || state.auditLogs.filter((log) => scoreAuditRisk(log) === 'high').length || 0);
                return { total, activeRoles, onlineNow, criticalAlerts };
            };

            const renderStats = (stats) => {
                els.totalStaffStat.textContent = formatNumber(stats.total);
                els.totalStaffMeta.textContent = `${formatNumber(stats.total)} profiles in registry`;
                els.activeRolesStat.textContent = formatNumber(stats.activeRoles);
                els.activeRolesMeta.textContent = `${formatNumber(stats.activeRoles)} distinct roles found`;
                els.onlineNowStat.textContent = formatNumber(stats.onlineNow);
                els.onlineNowMeta.textContent = 'Calculated from active staff accounts';
                els.criticalAlertsStat.textContent = formatNumber(stats.criticalAlerts);
                els.criticalAlertsMeta.textContent = 'Derived from recent audit activity';
            };

            const buildStaffListUrl = (page = state.staffPage) => {
                const url = new URL(config.staffApiUrl, window.location.origin);
                url.searchParams.set('page', String(page));
                url.searchParams.set('page_size', String(config.pageSize));
                return url.toString();
            };

            const renderPagination = () => {
                const totalPages = Math.max(1, Math.ceil(state.staffCount / config.pageSize));
                els.staffPaginationControls.innerHTML = '';
                const addButton = (label, page, opts = {}) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `page-btn ${opts.active ? 'active' : ''}`;
                    button.textContent = label;
                    button.disabled = opts.disabled;
                    button.addEventListener('click', () => {
                        if (opts.disabled || page === state.staffPage) return;
                        state.staffPage = page;
                        fetchStaff();
                    });
                    els.staffPaginationControls.appendChild(button);
                };
                addButton('Previous', Math.max(1, state.staffPage - 1), { disabled: state.staffPage === 1 });
                const pages = new Set([1, totalPages, state.staffPage - 1, state.staffPage, state.staffPage + 1].filter((page) => page >= 1 && page <= totalPages));
                [...pages].sort((a, b) => a - b).forEach((page, index, array) => {
                    if (index > 0 && array[index - 1] !== page - 1) {
                        const span = document.createElement('span');
                        span.className = 'footer-caption';
                        span.textContent = '...';
                        els.staffPaginationControls.appendChild(span);
                    }
                    addButton(String(page), page, { active: page === state.staffPage });
                });
                addButton('Next', Math.min(totalPages, state.staffPage + 1), { disabled: state.staffPage === totalPages });
            };

            const countEnabledPermissions = (staff) => permissionMeta.reduce((count, item) => count + (staff[item.key] ? 1 : 0), 0);

            const closeAllMenus = () => {
                document.querySelectorAll('.row-menu').forEach((menu) => menu.remove());
                state.openMenuId = null;
            };

            const openStaffModal = (staff = null) => {
                clearErrors();
                body.classList.add('modal-open');
                els.staffModalOverlay.classList.remove('hidden');
                els.staffModalOverlay.setAttribute('aria-hidden', 'false');

                if (staff) {
                    els.staffModalTitle.textContent = 'Edit Staff Member';
                    els.staffModalSubtitle.textContent = 'Update profile fields and permission flags for this user.';
                    els.saveStaffBtn.innerHTML = '<span>Save Changes</span>';
                    els.staffRecordId.value = staff.id || '';
                    els.staffIdPreview.value = staff.staff_id || 'AUTO-GENERATED';
                    els.fullNameInput.value = staff.full_name || '';
                    els.roleInput.value = staff.role || 'doctor';
                    els.departmentInput.value = staff.department || '';
                    els.emailInput.value = staff.email || '';
                    els.phoneInput.value = staff.phone || '';
                    els.regionInput.value = staff.region || '';
                    els.employmentStartDateInput.value = (staff.employment_start_date || '').slice(0, 10);

                    els.canViewPatientRecordsInput.checked = Boolean(staff.can_view_patient_records);
                    els.canEditVitalsInput.checked = Boolean(staff.can_edit_vitals);
                    els.canPrescribeMedsInput.checked = Boolean(staff.can_prescribe_meds);
                    els.canAccessBillingInput.checked = Boolean(staff.can_access_billing);
                    els.canAccessLabReportingInput.checked = Boolean(staff.can_access_lab_reporting);
                    els.canAccessAdminSettingsInput.checked = Boolean(staff.can_access_admin_settings);
                    els.mfaEnabledInput.checked = Boolean(staff.mfa_enabled);
                    els.isActiveInput.checked = Boolean(staff.is_active);
                    els.mustChangePasswordInput.checked = Boolean(staff.must_change_password);
                } else {
                    els.staffModalTitle.textContent = 'Register New Staff Member';
                    els.staffModalSubtitle.textContent = 'Create a staff profile with the documented staff fields and permission flags.';
                    els.saveStaffBtn.innerHTML = '<span>Create Staff Profile</span>';
                    els.staffForm.reset();
                    els.staffRecordId.value = '';
                    els.staffIdPreview.value = 'AUTO-GENERATED';
                    els.isActiveInput.checked = true;
                    els.mustChangePasswordInput.checked = true;
                    els.roleInput.value = 'administrator';
                    els.regionInput.value = '';
                    applyTemplateToForm(state.appliedTemplateForCreate || state.selectedTemplateKey);
                }
            };

            const closeStaffModal = () => {
                body.classList.remove('modal-open');
                els.staffModalOverlay.classList.add('hidden');
                els.staffModalOverlay.setAttribute('aria-hidden', 'true');
                clearErrors();
                els.profilePhotoInput.value = '';
            };

            const createMenuButton = (staff) => {
                const wrap = document.createElement('div');
                wrap.className = 'row-action-wrap';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'menu-btn';
                button.textContent = '⋮';
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const menuId = staff.id || staff.staff_id;
                    if (state.openMenuId === menuId) {
                        closeAllMenus();
                        return;
                    }
                    closeAllMenus();
                    state.openMenuId = menuId;
                    const menu = document.createElement('div');
                    menu.className = 'row-menu';
                    menu.innerHTML = `
                <button type="button" class="menu-item" data-action="edit">Edit profile</button>
                <button type="button" class="menu-item" data-action="permissions">Edit permissions</button>
                <button type="button" class="menu-item" data-action="reset">Reset password</button>
                <button type="button" class="menu-item danger" data-action="delete">Delete staff</button>
            `;
                    menu.addEventListener('click', async (menuEvent) => {
                        const action = menuEvent.target.closest('[data-action]')?.dataset.action;
                        if (!action) return;
                        closeAllMenus();
                        if (action === 'edit' || action === 'permissions') {
                            openStaffModal(staff);
                            if (action === 'permissions') showToast('Edit the permission flags and save changes.', 'info');
                        } else if (action === 'reset') {
                            await resetPassword(staff);
                        } else if (action === 'delete') {
                            await deleteStaff(staff);
                        }
                    });
                    wrap.appendChild(menu);
                });
                wrap.appendChild(button);
                return wrap;
            };

            const applyStaffFilters = () => {
                const query = els.staffFilterInput.value.trim().toLowerCase();
                const role = els.staffRoleFilter.value;
                const status = els.staffStatusFilter.value;
                state.filteredStaff = state.staff.filter((item) => {
                    const matchesQuery = !query || [item.staff_id, item.full_name, item.email].filter(Boolean).join(' ').toLowerCase().includes(query);
                    const matchesRole = !role || item.role === role;
                    const matchesStatus = !status || (status === 'active' ? item.is_active : !item.is_active);
                    return matchesQuery && matchesRole && matchesStatus;
                });
                renderStaffTable();
            };

            const renderStaffTable = () => {
                closeAllMenus();
                const rows = state.filteredStaff;
                if (!rows.length) {
                    els.staffTableBody.innerHTML = '<tr><td colspan="8" class="empty-state">No staff records matched the current filters on this page.</td></tr>';
                    els.staffResultsCaption.textContent = `Showing 0 of ${formatNumber(state.staffCount)} staff records`;
                    renderPagination();
                    return;
                }
                els.staffTableBody.innerHTML = '';
                const fragment = document.createDocumentFragment();
                rows.forEach((staff) => {
                    const tr = document.createElement('tr');
                    const permissionCount = countEnabledPermissions(staff);
                    tr.innerHTML = `
                <td><strong>${staff.staff_id || '—'}</strong></td>
                <td class="staff-name-cell"><strong>${staff.full_name || '—'}</strong><small>${staff.email || 'No email'} · ${staff.phone || 'No phone'}</small></td>
                <td>${capitalize(staff.role) || '—'}</td>
                <td>${staff.department || '—'}</td>
                <td>${staff.region || '—'}</td>
                <td><span class="status-badge ${staff.is_active ? 'active' : 'inactive'}">${staff.is_active ? 'Active' : 'Inactive'}</span></td>
                <td><span class="permission-count-badge">${permissionCount} enabled</span></td>
                <td class="table-actions-cell"></td>
            `;
                    tr.querySelector('.table-actions-cell').appendChild(createMenuButton(staff));
                    fragment.appendChild(tr);
                });
                els.staffTableBody.appendChild(fragment);
                els.staffResultsCaption.textContent = `Showing ${formatNumber(rows.length)} of ${formatNumber(state.staffCount)} staff records`;
                renderPagination();
            };

            const fetchStaffStats = async () => {
                try {
                    const response = await apiFetch(config.staffStatsApiUrl);
                    if (!response.ok) throw new Error('stats');
                    const data = await response.json();
                    renderStats(normalizeStaffStats(data));
                } catch (_error) {
                    renderStats(normalizeStaffStats({}));
                }
            };

            const fetchStaff = async () => {
                els.staffTableBody.innerHTML = '<tr><td colspan="8" class="table-placeholder">Loading staff records...</td></tr>';
                try {
                    const response = await apiFetch(buildStaffListUrl());
                    if (!response.ok) throw new Error('staff list');
                    const data = await response.json();
                    state.staffCount = Number(data.count || 0);
                    state.staff = Array.isArray(data.results) ? data.results : Array.isArray(data) ? data : [];
                    applyStaffFilters();
                    renderStats(normalizeStaffStats({}));
                } catch (_error) {
                    els.staffTableBody.innerHTML = '<tr><td colspan="8" class="table-placeholder">Unable to load staff records.</td></tr>';
                    els.staffResultsCaption.textContent = 'Unable to load staff records';
                }
            };

            const normalizeAuditItems = (data) => Array.isArray(data.results) ? data.results : Array.isArray(data) ? data : [];

            const renderAuditTable = () => {
                const query = els.auditFilterInput.value.trim().toLowerCase();
                state.filteredAuditLogs = state.auditLogs.filter((item) => !query || [item.action, item.description, item.resource_type, item.department, item.region, item.patient_name, item.actor?.full_name, item.actor_name].filter(Boolean).join(' ').toLowerCase().includes(query));
                if (!state.filteredAuditLogs.length) {
                    els.auditTableBody.innerHTML = '<tr><td colspan="7" class="empty-state">No audit records matched the current filters.</td></tr>';
                    els.auditResultsCaption.textContent = 'Showing 0 audit events';
                    return;
                }
                els.auditTableBody.innerHTML = state.filteredAuditLogs.map((item) => {
                    const actorName = item.actor?.full_name || item.actor_name || 'System';
                    const risk = scoreAuditRisk(item);
                    return `
                <tr>
                    <td>${item.timestamp ? new Date(item.timestamp).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—'}</td>
                    <td class="audit-actor"><strong>${actorName}</strong><small>${item.ip_address || 'No IP logged'}</small></td>
                    <td><span class="audit-badge audit-risk-${risk}">${capitalize(item.action) || 'Event'}</span></td>
                    <td>${item.resource_type || '—'} ${item.resource_id ? `<small>#${item.resource_id}</small>` : ''}</td>
                    <td>${item.department || '—'}</td>
                    <td>${item.region || '—'}</td>
                    <td>${item.description || '—'}</td>
                </tr>
            `;
                }).join('');
                els.auditResultsCaption.textContent = `Showing ${formatNumber(state.filteredAuditLogs.length)} audit events`;
            };

            const fetchAuditLogs = async () => {
                els.auditTableBody.innerHTML = '<tr><td colspan="7" class="table-placeholder">Loading audit records...</td></tr>';
                try {
                    const response = await apiFetch(config.staffAuditApiUrl);
                    if (!response.ok) throw new Error('audit');
                    const data = await response.json();
                    state.auditLogs = normalizeAuditItems(data);
                    renderAuditTable();
                    renderStats(normalizeStaffStats({}));
                } catch (_error) {
                    els.auditTableBody.innerHTML = '<tr><td colspan="7" class="table-placeholder">Unable to load audit records.</td></tr>';
                    els.auditResultsCaption.textContent = 'Unable to load audit events';
                }
            };

            const searchPatientsGlobally = debounce(async (query) => {
                const trimmed = query.trim();
                if (trimmed.length < 3) {
                    els.globalSearchResults.classList.add('hidden');
                    els.globalSearchResults.innerHTML = '';
                    return;
                }
                const url = new URL(config.patientSearchApiUrl, window.location.origin);
                url.searchParams.set('q', trimmed);
                try {
                    const response = await apiFetch(url.toString());
                    if (!response.ok) throw new Error('patient search');
                    const payload = await response.json();
                    const results = Array.isArray(payload.results) ? payload.results : Array.isArray(payload) ? payload : [];
                    if (!results.length) {
                        els.globalSearchResults.innerHTML = '<div class="search-result-item">No patient matched that search.</div>';
                        els.globalSearchResults.classList.remove('hidden');
                        return;
                    }
                    els.globalSearchResults.innerHTML = results.slice(0, 10).map((patient) => `
                <a class="search-result-item" href="${buildPatientDetailUrl(patient)}">
                    <div><strong>${patient.full_name || patient.patient_id || 'Patient'}</strong><small>${patient.patient_id || '—'} • ${patient.nhis_number || 'No NHIS'} • ${patient.phone || 'No phone'}</small></div>
                    <span>${patient.region || '—'}</span>
                </a>
            `).join('');
                    els.globalSearchResults.classList.remove('hidden');
                } catch (_error) {
                    els.globalSearchResults.innerHTML = '<div class="search-result-item">Unable to search patients right now.</div>';
                    els.globalSearchResults.classList.remove('hidden');
                }
            }, 300);

            const staffEndpoint = (staffId) => {
                const base = config.staffApiUrl.endsWith('/') ? config.staffApiUrl : `${config.staffApiUrl}/`;
                return `${base}${staffId}/`;
            };

            const permissionsEndpoint = (staffId) => `${staffEndpoint(staffId)}permissions/`;
            const resetPasswordEndpoint = (staffId) => `${staffEndpoint(staffId)}reset-password/`;

            const buildFormDataPayload = () => {
                const payload = new FormData();
                payload.append('full_name', els.fullNameInput.value.trim());
                payload.append('role', els.roleInput.value);
                payload.append('department', els.departmentInput.value.trim());
                payload.append('email', els.emailInput.value.trim());
                if (els.phoneInput.value.trim()) payload.append('phone', els.phoneInput.value.trim());
                if (els.regionInput.value) payload.append('region', els.regionInput.value);
                if (els.employmentStartDateInput.value) payload.append('employment_start_date', els.employmentStartDateInput.value);
                if (els.profilePhotoInput.files[0]) payload.append('profile_photo', els.profilePhotoInput.files[0]);
                payload.append('mfa_enabled', String(els.mfaEnabledInput.checked));
                payload.append('is_active', String(els.isActiveInput.checked));
                payload.append('must_change_password', String(els.mustChangePasswordInput.checked));

                permissionMeta.forEach((item) => {
                    const map = {
                        can_view_patient_records: els.canViewPatientRecordsInput,
                        can_edit_vitals: els.canEditVitalsInput,
                        can_prescribe_meds: els.canPrescribeMedsInput,
                        can_access_billing: els.canAccessBillingInput,
                        can_access_lab_reporting: els.canAccessLabReportingInput,
                        can_access_admin_settings: els.canAccessAdminSettingsInput
                    };
                    payload.append(item.key, String(Boolean(map[item.key].checked)));
                });
                return payload;
            };

            const buildPermissionsPayload = () => ({
                can_view_patient_records: els.canViewPatientRecordsInput.checked,
                can_edit_vitals: els.canEditVitalsInput.checked,
                can_prescribe_meds: els.canPrescribeMedsInput.checked,
                can_access_billing: els.canAccessBillingInput.checked,
                can_access_lab_reporting: els.canAccessLabReportingInput.checked,
                can_access_admin_settings: els.canAccessAdminSettingsInput.checked
            });

            const submitStaffForm = async (event) => {
                event.preventDefault();
                clearErrors();
                const editingId = els.staffRecordId.value;
                const method = editingId ? 'PATCH' : 'POST';
                els.saveStaffBtn.disabled = true;
                els.saveStaffBtn.innerHTML = '<span>Saving...</span>';
                try {
                    const response = await apiFetch(editingId ? staffEndpoint(editingId) : config.staffApiUrl, {
                        method,
                        body: buildFormDataPayload()
                    });

                    let payload = {};
                    try { payload = await response.json(); } catch (_error) { payload = {}; }
                    if (!response.ok) {
                        renderFieldErrors(payload);
                        throw new Error('save');
                    }

                    const savedStaffId = payload.id || editingId;
                    if (savedStaffId) {
                        const permissionsResponse = await apiFetch(permissionsEndpoint(savedStaffId), {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(buildPermissionsPayload())
                        });
                        if (!permissionsResponse.ok) {
                            let permissionPayload = {};
                            try { permissionPayload = await permissionsResponse.json(); } catch (_error) { permissionPayload = {}; }
                            renderFieldErrors(permissionPayload);
                            throw new Error('permissions');
                        }
                    }

                    showToast(editingId ? 'Staff profile updated successfully.' : 'Staff profile created successfully.');
                    closeStaffModal();
                    await fetchStaff();
                    await fetchStaffStats();
                } catch (_error) {
                    if (!els.staffFormAlert.textContent) setAlert('Unable to save the staff profile right now.');
                } finally {
                    els.saveStaffBtn.disabled = false;
                    els.saveStaffBtn.innerHTML = editingId ? '<span>Save Changes</span>' : '<span>Create Staff Profile</span>';
                }
            };

            const deleteStaff = async (staff) => {
                const confirmed = window.confirm(`Delete ${staff.full_name || staff.staff_id}? This action removes the staff record.`);
                if (!confirmed) return;
                try {
                    const response = await apiFetch(staffEndpoint(staff.id), { method: 'DELETE' });
                    if (!response.ok) throw new Error('delete');
                    showToast('Staff member deleted.', 'info');
                    await fetchStaff();
                    await fetchAuditLogs();
                } catch (_error) {
                    showToast('Unable to delete staff member.', 'error');
                }
            };

            const resetPassword = async (staff) => {
                try {
                    const response = await apiFetch(resetPasswordEndpoint(staff.id), { method: 'POST' });
                    if (!response.ok) throw new Error('reset');
                    showToast(`Password reset initiated for ${staff.full_name || staff.staff_id}.`, 'info');
                    await fetchAuditLogs();
                } catch (_error) {
                    showToast('Unable to trigger password reset.', 'error');
                }
            };

            const handleLogout = async () => {
                try {
                    const refresh = getRefreshToken();
                    if (refresh) {
                        await apiFetch(config.logoutApiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ refresh })
                        }, false);
                    }
                } catch (_error) {
                    // pass
                } finally {
                    window.location.href = config.loginUrl;
                }
            };

            const setTab = (tabKey) => {
                state.activeTab = tabKey;
                els.tabButtons.forEach((button) => button.classList.toggle('active', button.dataset.tab === tabKey));
                els.tabPanels.forEach((panel) => panel.classList.toggle('active', panel.dataset.panel === tabKey));
            };

            const bindEvents = () => {
                document.addEventListener('click', (event) => {
                    if (!event.target.closest('.row-action-wrap')) closeAllMenus();
                    if (!event.target.closest('.search-wrap')) els.globalSearchResults.classList.add('hidden');
                });

                els.tabButtons.forEach((button) => button.addEventListener('click', () => setTab(button.dataset.tab)));
                els.roleTemplateList.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-role-template]');
                    if (!button) return;
                    state.selectedTemplateKey = button.dataset.roleTemplate;
                    renderRoleTemplates();
                    renderPermissionsTable();
                });
                els.permissionsTableBody.addEventListener('change', updateTemplateFromTable);
                els.resetTemplateBtn.addEventListener('click', () => {
                    const key = state.selectedTemplateKey;
                    if (defaultRoleTemplates[key]) state.roleTemplates[key] = JSON.parse(JSON.stringify(defaultRoleTemplates[key]));
                    else delete state.roleTemplates[key];
                    if (!state.roleTemplates[key]) state.selectedTemplateKey = 'administrator';
                    saveRoleTemplates();
                    renderRoleTemplates();
                    renderPermissionsTable();
                    showToast('Role template reset.', 'info');
                });
                els.useTemplateBtn.addEventListener('click', () => {
                    openStaffModal();
                    applyTemplateToForm(state.selectedTemplateKey);
                });
                els.applyRoleTemplateBtn.addEventListener('click', () => applyTemplateToForm(state.selectedTemplateKey));
                els.createCustomRoleBtn.addEventListener('click', () => {
                    const name = window.prompt('Name your custom role template:');
                    if (!name) return;
                    updateTemplateFromTable();
                    const key = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || `custom_${Date.now()}`;
                    const currentTemplate = getTemplate(state.selectedTemplateKey);
                    state.roleTemplates[key] = {
                        name,
                        role: key,
                        permissions: { ...currentTemplate.permissions }
                    };
                    state.selectedTemplateKey = key;
                    saveRoleTemplates();
                    renderRoleTemplates();
                    renderPermissionsTable();
                    showToast('Custom role template saved locally.', 'success');
                });
                els.viewPolicyBtn.addEventListener('click', () => setTab('audit'));
                els.openCreateModalBtn.addEventListener('click', () => openStaffModal());
                els.closeStaffModalBtn.addEventListener('click', closeStaffModal);
                els.cancelStaffBtn.addEventListener('click', closeStaffModal);
                els.staffModalOverlay.addEventListener('click', (event) => {
                    if (event.target === els.staffModalOverlay) closeStaffModal();
                });
                els.staffForm.addEventListener('submit', submitStaffForm);
                els.roleInput.addEventListener('change', () => {
                    const matchingKey = Object.entries(state.roleTemplates).find(([, template]) => template.role === els.roleInput.value)?.[0];
                    if (matchingKey && !els.staffRecordId.value) {
                        state.selectedTemplateKey = matchingKey;
                        renderRoleTemplates();
                        renderPermissionsTable();
                        applyTemplateToForm(matchingKey);
                    }
                });
                els.staffFilterInput.addEventListener('input', debounce(applyStaffFilters, 180));
                els.staffRoleFilter.addEventListener('change', applyStaffFilters);
                els.staffStatusFilter.addEventListener('change', applyStaffFilters);
                els.refreshStaffBtn.addEventListener('click', fetchStaff);
                els.auditFilterInput.addEventListener('input', debounce(renderAuditTable, 180));
                els.refreshAuditBtn.addEventListener('click', fetchAuditLogs);
                els.globalPatientSearchInput.addEventListener('input', (event) => searchPatientsGlobally(event.target.value));
                els.logoutBtn.addEventListener('click', handleLogout);
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !els.staffModalOverlay.classList.contains('hidden')) closeStaffModal();
                });
            };

            const init = async () => {
                hydrateRoleTemplates();
                fillSelects();
                renderRoleTemplates();
                renderPermissionsTable();
                bindEvents();
                await Promise.all([fetchSystemStatus(), fetchStaff(), fetchAuditLogs()]);
                await fetchStaffStats();
            };

            init();
        })();
    </script>
</body>

</html>