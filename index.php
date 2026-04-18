<?php
session_start();
require 'db.php';
$pdo = getDB();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Invalid request.";
    } else {

        $staff_id = $_POST["staff_id"] ?? "";
        $password = $_POST["password"] ?? "";
        $mfa_token = $_POST["mfa_token"] ?? "";

        // STEP 1: CHECK USER
        $sql = "SELECT * FROM staff WHERE id = :staff_id";
        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':staff_id' => $staff_id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = "Invalid Staff ID or Password";
        } else {

            // STEP 2: IF NO TOKEN → GENERATE
            if (empty($mfa_token)) {

                $token = rand(100000, 999999);
                $expires = date("Y-m-d H:i:s", strtotime("+5 minutes"));

                $stmt = $conn->prepare("INSERT INTO mfa_tokens (staff_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $staff_id, $token, $expires);
                $stmt->execute();

                $error = "Your MFA Code: $token";

            } else {

                // STEP 3: VERIFY TOKEN
                $stmt = $conn->prepare("
                    SELECT * FROM mfa_tokens 
                    WHERE staff_id = ? 
                    AND token = ? 
                    AND used = 0 
                    AND expires_at > NOW()
                    ORDER BY id DESC LIMIT 1
                ");

                $stmt->bind_param("ss", $staff_id, $mfa_token);
                $stmt->execute();
                $result = $stmt->get_result();
                $validToken = $result->fetch_assoc();

                if ($validToken) {

                    // mark used
                    $stmt = $conn->prepare("UPDATE mfa_tokens SET used = 1 WHERE id = ?");
                    $stmt->bind_param("i", $validToken['id']);
                    $stmt->execute();

                    session_regenerate_id(true);
                    $_SESSION["user"] = $staff_id;

                    header("Location: dashboard.php");
                    exit();

                } else {
                    $error = "Invalid or expired MFA token";
                }
            }
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Akwaaba Health | Staff Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f2230;
            --panel: rgba(255, 255, 255, 0.94);
            --panel-edge: rgba(255, 255, 255, 0.55);
            --primary: #2d73ad;
            --primary-strong: #1f5f93;
            --primary-soft: rgba(45, 115, 173, 0.12);
            --text: #1f2937;
            --muted: #6b7280;
            --line: #d8e0e7;
            --danger: #cb3a31;
            --success: #33b56d;
            --warning: #f0b429;
            --shadow: 0 16px 44px rgba(3, 22, 35, 0.22);
            --radius-xl: 22px;
            --radius-lg: 16px;
            --radius-md: 12px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text);
            background: var(--bg);
        }

        body {
            min-height: 100vh;
        }

        .page-shell {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            isolation: isolate;
            padding: 22px;
        }

        .bg-layer,
        .bg-overlay {
            position: absolute;
            inset: 0;
        }

        .bg-layer {
            background: radial-gradient(circle at 14% 50%, rgba(255, 255, 255, 0.52), transparent 20%), radial-gradient(circle at 82% 38%, rgba(255, 255, 255, 0.3), transparent 18%), linear-gradient(115deg, #dce8ef 0%, #c7d9e4 17%, #b4c9d7 34%, #dfe9ef 52%, #b6ccd8 68%, #dce8ef 100%);
            filter: blur(10px);
            transform: scale(1.05);
        }

        .bg-overlay {
            background: linear-gradient(180deg, rgba(12, 31, 45, 0.1), rgba(9, 22, 33, 0.24));
        }

        .brand-header,
        .system-banner,
        .content-wrap,
        .status-pill {
            position: relative;
            z-index: 1;
        }

        .brand-header {
            text-align: center;
            margin: 0 auto 24px;
        }

        .brand-header h1 {
            margin: 0;
            font-size: clamp(1.8rem, 2.8vw, 2.85rem);
            letter-spacing: 0.03em;
            line-height: 1;
        }

        .brand-strong {
            color: #fff;
            font-weight: 800;
        }

        .brand-light {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
            font-style: italic;
            margin-left: 0.25rem;
        }

        .brand-underline {
            display: inline-block;
            width: 74px;
            height: 4px;
            background: var(--primary);
            border-radius: 999px;
            margin-top: 10px;
        }

        .system-banner {
            position: absolute;
            top: 42px;
            right: 38px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            width: min(335px, calc(100vw - 44px));
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(207, 221, 232, 0.9);
            box-shadow: 0 18px 32px rgba(8, 34, 52, 0.18);
        }

        .banner-icon-wrap {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #edf5fb;
            color: var(--primary);
            flex-shrink: 0;
        }

        .banner-copy strong {
            display: block;
            font-size: 0.97rem;
            margin-bottom: 4px;
        }

        .banner-copy p {
            margin: 0;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.45;
        }

        .banner-close {
            margin-left: auto;
            border: 0;
            background: transparent;
            font-size: 1.1rem;
            color: #6d7784;
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }

        .content-wrap {
            min-height: calc(100vh - 140px);
            display: grid;
            place-items: center;
            padding: 18px 0 140px;
        }

        .login-card {
            width: min(100%, 430px);
            background: var(--panel);
            border: 1px solid var(--panel-edge);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            padding: 28px 22px 24px;
            backdrop-filter: blur(6px);
        }

        .card-head {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 26px;
        }

        .logo-badge {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
        }

        .logo-mark {
            width: 100%;
            height: 100%;
            color: #fff;
        }

        .logo-mark circle {
            fill: var(--primary);
        }

        .product-name {
            margin: 1px 0 10px;
            color: var(--primary);
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
        }

        .portal-title {
            margin: 0 0 7px;
            font-size: 1.08rem;
            font-weight: 800;
        }

        .portal-subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .flash {
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.92rem;
            border: 1px solid transparent;
            margin-bottom: 16px;
        }

        .flash-error {
            background: rgba(203, 58, 49, 0.09);
            color: #87261f;
            border-color: rgba(203, 58, 49, 0.15);
        }

        .flash-success {
            background: rgba(51, 181, 109, 0.1);
            color: #206440;
            border-color: rgba(51, 181, 109, 0.18);
        }

        .flash-warning {
            background: rgba(240, 180, 41, 0.12);
            color: #8a6411;
            border-color: rgba(240, 180, 41, 0.18);
        }

        .flash-info {
            background: rgba(45, 115, 173, 0.1);
            color: #17466d;
            border-color: rgba(45, 115, 173, 0.16);
        }

        .auth-form {
            display: grid;
            gap: 16px;
        }

        .field-block {
            display: grid;
            gap: 8px;
        }

        .field-block label,
        .label-row {
            font-size: 0.94rem;
            font-weight: 600;
        }

        .label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            height: 52px;
            padding: 0 48px 0 16px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.85);
            font: inherit;
            color: var(--text);
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .input-wrap input:focus {
            border-color: rgba(45, 115, 173, 0.72);
            box-shadow: 0 0 0 4px rgba(45, 115, 173, 0.12);
            background: #fff;
        }

        .input-wrap input::placeholder {
            color: #9aa6b2;
        }

        .ghost-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.78rem;
            cursor: pointer;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(162, 176, 187, 0.7), transparent);
            margin: 2px 0;
        }

        .mfa-heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.94rem;
        }

        .checkbox-row {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.94rem;
            color: #4b5563;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-row input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        .submit-btn {
            height: 56px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(180deg, var(--primary), var(--primary-strong));
            color: #fff;
            font: inherit;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 12px 20px rgba(45, 115, 173, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: transform 0.16s ease, box-shadow 0.16s ease, opacity 0.16s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 15px 24px rgba(45, 115, 173, 0.22);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .btn-loader {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #fff;
            border-radius: 50%;
            display: none;
            animation: spin 0.7s linear infinite;
        }

        .submit-btn.is-loading .btn-loader {
            display: inline-block;
        }

        .submit-btn.is-loading .btn-icon {
            display: none;
        }

        .submit-btn:disabled {
            opacity: 0.8;
            cursor: wait;
        }

        .status-pill {
            position: absolute;
            right: 28px;
            bottom: 30px;
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(211, 221, 229, 0.88);
            border-radius: 999px;
            box-shadow: 0 14px 26px rgba(6, 29, 44, 0.14);
            color: #334155;
            font-size: 0.92rem;
        }

        .status-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-separator {
            width: 1px;
            align-self: stretch;
            background: rgba(203, 214, 223, 0.95);
        }

        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
            background: #94a3b8;
        }

        .status-active {
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(51, 181, 109, 0.13);
        }

        .status-warning {
            background: var(--warning);
        }

        .status-offline {
            background: var(--danger);
        }

        .hidden {
            display: none !important;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 980px) {
            .system-banner {
                position: static;
                margin: 0 auto 20px;
            }

            .content-wrap {
                min-height: unset;
                padding-bottom: 170px;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                padding: 16px;
            }

            .login-card {
                padding: 22px 16px 20px;
                border-radius: 18px;
            }

            .product-name {
                font-size: 1.7rem;
            }

            .system-banner {
                width: 100%;
            }

            .status-pill {
                position: static;
                width: 100%;
                justify-content: center;
                border-radius: 18px;
                margin-top: 18px;
            }

            .status-separator {
                display: none;
            }

            .content-wrap {
                padding-bottom: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="page-shell">
        <div class="bg-layer"></div>
        <div class="bg-overlay"></div>

        <header class="brand-header">
            <h1><span class="brand-strong">AKWAABA</span><span class="brand-light">HEALTH</span></h1>
            <span class="brand-underline"></span>
        </header>

        <!-- FIX: Added id="systemBanner" and id="closeBanner" so JS can find these elements -->
        <aside class="system-banner" id="systemBanner">
            <div class="banner-icon-wrap">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        d="M10 2a8 8 0 100 16A8 8 0 0010 2zm.75 4.75a.75.75 0 00-1.5 0v3.5a.75.75 0 001.5 0v-3.5zm-.75 7a.875.875 0 100-1.75.875.875 0 000 1.75z" />
                </svg>
            </div>
            <div class="banner-copy">
                <strong>System Notice</strong>
                <p>Use your assigned Staff ID and password. Contact IT if you need access help.</p>
            </div>
            <button class="banner-close" id="closeBanner" aria-label="Close banner">&times;</button>
        </aside>

        <main class="content-wrap">
            <section class="login-card">
                <div class="card-head">
                    <div class="logo-badge">
                        <svg viewBox="0 0 64 64" class="logo-mark">
                            <circle cx="32" cy="32" r="32"></circle>
                            <path d="M12 34h10l4-10 8 22 6-16h12" fill="none" stroke="currentColor" stroke-width="4"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="product-name">Akwaaba Health</h2>
                        <h3 class="portal-title">Hospital Management System</h3>
                        <p class="portal-subtitle">Secure staff portal for clinicians and administration</p>
                    </div>
                </div>

                <!-- FIX: htmlspecialchars() prevents XSS on error output -->
                <?php if ($error): ?>
                    <div class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <!-- FIX: Added id="apiFeedback" so JS showFeedback() has a target element -->
                <div id="apiFeedback" class="flash flash-info hidden" role="alert"></div>

                <form method="POST" class="auth-form" id="loginForm">
                    <!-- FIX: CSRF token field — rendered from session, checked on POST -->
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="field-block">
                        <label for="id_staff_id">Staff ID</label>
                        <div class="input-wrap">
                            <input type="text" name="staff_id" id="id_staff_id" autocomplete="username" required>
                        </div>
                    </div>

                    <div class="field-block">
                        <div class="label-row">
                            <label for="id_password">Password</label>
                        </div>
                        <div class="input-wrap">
                            <input type="password" name="password" id="id_password" autocomplete="current-password"
                                required>
                            <!-- FIX: Removed onclick="togglePassword()" — JS event listener handles this via id="togglePassword" -->
                            <button type="button" class="ghost-toggle" id="togglePassword"
                                aria-label="Show password">Show</button>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="field-block">
                        <div class="mfa-heading"><span>Multi-Factor Token</span></div>
                        <div class="input-wrap">
                            <input type="text" name="mfa_token" id="id_mfa_token" maxlength="6"
                                autocomplete="one-time-code" inputmode="numeric">
                        </div>
                    </div>

                    <!-- FIX: Added id="id_stay_logged_in" so JS can read .checked -->
                    <label class="checkbox-row">
                        <input type="checkbox" name="stay_logged_in" id="id_stay_logged_in">
                        <span>Stay logged in for 24 hours</span>
                    </label>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span class="btn-text">Access Dashboard</span>
                        <span class="btn-icon">→</span>
                        <span class="btn-loader"></span>
                    </button>
                </form>
            </section>
        </main>

        <!-- FIX: Added IDs to all status elements so JS updateStatusUI() can target them -->
        <div class="status-pill">
            <span class="status-item">🌍 <span id="regionText">Greater Accra, GH</span></span>
            <span class="status-separator"></span>
            <span class="status-item">
                <span class="status-dot status-active" id="serverDot"></span>
                <span id="serverText">GHS Server: Active</span>
            </span>
            <span class="status-separator"></span>
            <span class="status-item">
                <span class="status-dot status-active" id="systemDot"></span>
                <span id="systemText">System: Online</span>
            </span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const body = document.body;
            const banner = document.getElementById('systemBanner');
            const closeBanner = document.getElementById('closeBanner');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('id_password');
            const staffIdInput = document.getElementById('id_staff_id');
            const mfaTokenInput = document.getElementById('id_mfa_token');
            const stayLoggedInInput = document.getElementById('id_stay_logged_in');
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const apiFeedback = document.getElementById('apiFeedback');
            const regionText = document.getElementById('regionText');
            const serverText = document.getElementById('serverText');
            const systemText = document.getElementById('systemText');
            const serverDot = document.getElementById('serverDot');
            const systemDot = document.getElementById('systemDot');

            const apiMode = String(body.dataset.apiMode || 'false').toLowerCase() === 'true';
            const loginApiUrl = body.dataset.loginApiUrl || '';
            const dashboardApiUrl = body.dataset.dashboardApiUrl || '';
            const serverStatusUrl = body.dataset.serverStatusUrl || '';
            const redirectUrl = body.dataset.redirectUrl || '/dashboard/';
            const passwordChangeUrl = body.dataset.passwordChangeUrl || '/password/change/';
            const csrfToken = document.querySelector('[name=csrfmiddlewaretoken]')?.value || '';

            const getStorage = () => stayLoggedInInput?.checked ? window.localStorage : window.sessionStorage;

            const setLoading = (isLoading) => {
                if (!submitBtn) return;
                submitBtn.classList.toggle('is-loading', isLoading);
                submitBtn.disabled = isLoading;
            };

            const showFeedback = (message, level = 'info') => {
                if (!apiFeedback) return;
                apiFeedback.className = `flash flash-${level}`;
                apiFeedback.textContent = message;
                apiFeedback.classList.remove('hidden');
            };

            const clearFeedback = () => {
                if (!apiFeedback) return;
                apiFeedback.textContent = '';
                apiFeedback.className = 'flash flash-info hidden';
            };

            const titleCase = (value) => String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

            const applyDotState = (element, state) => {
                if (!element) return;
                element.className = 'status-dot';
                const s = String(state || '').toLowerCase();
                if (['active', 'online', 'ok', 'healthy', 'up'].includes(s)) element.classList.add('status-active');
                else if (['warning', 'degraded', 'slow', 'maintenance'].includes(s)) element.classList.add('status-warning');
                else if (s) element.classList.add('status-offline');
            };

            const updateStatusUI = ({ region, ghsServer, systemStatus }) => {
                if (regionText && region) regionText.textContent = region;
                if (serverText && ghsServer) { serverText.textContent = `GHS Server: ${titleCase(ghsServer)}`; applyDotState(serverDot, ghsServer); }
                if (systemText && systemStatus) { systemText.textContent = `System: ${titleCase(systemStatus)}`; applyDotState(systemDot, systemStatus); }
            };

            const getStoredAccessToken = () =>
                window.localStorage.getItem('akwaaba_access') || window.sessionStorage.getItem('akwaaba_access');

            const storeAuth = (payload) => {
                const storage = getStorage();
                if (payload.access) storage.setItem('akwaaba_access', payload.access);
                if (payload.refresh) storage.setItem('akwaaba_refresh', payload.refresh);
                if (payload.user) storage.setItem('akwaaba_user', JSON.stringify(payload.user));
            };

            const fetchDashboardStatus = async () => {
                const token = getStoredAccessToken();
                if (!dashboardApiUrl || !token) return false;
                try {
                    const response = await fetch(dashboardApiUrl, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
                    if (!response.ok) return false;
                    const data = await response.json();
                    updateStatusUI({ region: data.region || body.dataset.defaultRegion, ghsServer: data.ghs_server || body.dataset.defaultGhsServer, systemStatus: data.system_status || body.dataset.defaultSystemStatus });
                    return true;
                } catch { return false; }
            };

            const fetchPublicStatus = async () => {
                if (!serverStatusUrl) { updateStatusUI({ region: body.dataset.defaultRegion, ghsServer: body.dataset.defaultGhsServer, systemStatus: body.dataset.defaultSystemStatus }); return; }
                try {
                    const response = await fetch(serverStatusUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('Status check failed');
                    const data = await response.json();
                    updateStatusUI({ region: data.region || body.dataset.defaultRegion, ghsServer: data.ghs_server || data.status || body.dataset.defaultGhsServer, systemStatus: data.system_status || data.system || body.dataset.defaultSystemStatus });
                } catch {
                    updateStatusUI({ region: body.dataset.defaultRegion, ghsServer: body.dataset.defaultGhsServer, systemStatus: body.dataset.defaultSystemStatus });
                }
            };

            if (closeBanner && banner) {
                closeBanner.addEventListener('click', () => banner.classList.add('hidden'));
            }

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', () => {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    togglePassword.textContent = isPassword ? 'Hide' : 'Show';
                    togglePassword.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                });
            }

            if (staffIdInput) {
                staffIdInput.addEventListener('blur', () => { staffIdInput.value = staffIdInput.value.trim().toUpperCase(); });
            }

            if (mfaTokenInput) {
                mfaTokenInput.addEventListener('input', () => { mfaTokenInput.value = mfaTokenInput.value.replace(/\D/g, '').slice(0, 6); });
            }

            if (form && submitBtn) {
                form.addEventListener('submit', async (event) => {
                    const staffId = staffIdInput?.value.trim().toUpperCase() || '';
                    const password = passwordInput?.value.trim() || '';
                    const mfaToken = mfaTokenInput?.value.trim() || '';
                    clearFeedback();

                    if (!staffId) { event.preventDefault(); staffIdInput?.focus(); showFeedback('Staff ID is required.', 'error'); return; }
                    if (!password) { event.preventDefault(); passwordInput?.focus(); showFeedback('Password is required.', 'error'); return; }
                    if (mfaToken && mfaToken.length !== 6) { event.preventDefault(); mfaTokenInput?.focus(); showFeedback('MFA token must be a 6-digit code.', 'error'); return; }

                    if (!apiMode || !loginApiUrl) { setLoading(true); return; }

                    event.preventDefault();
                    setLoading(true);

                    try {
                        const payload = { staff_id: staffId, password, stay_logged_in: Boolean(stayLoggedInInput?.checked) };
                        if (mfaToken) payload.mfa_token = mfaToken;

                        const response = await fetch(loginApiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRFToken': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify(payload)
                        });

                        let data = {};
                        try { data = await response.json(); } catch { data = {}; }

                        if (response.ok && data.mfa_required) { showFeedback(data.message || 'MFA is required. Enter your 6-digit authenticator code to continue.', 'warning'); mfaTokenInput?.focus(); return; }
                        if (response.ok && data.access && data.refresh) {
                            storeAuth(data);
                            if (data.must_change_password) { showFeedback('Login successful. You must change your password before continuing.', 'warning'); setTimeout(() => window.location.assign(passwordChangeUrl), 600); return; }
                            showFeedback('Login successful. Redirecting...', 'success');
                            const nextUrl = form.querySelector('input[name="next"]')?.value?.trim();
                            setTimeout(() => window.location.assign(nextUrl || redirectUrl), 400);
                            return;
                        }
                        if ([401, 403, 423, 429].includes(response.status)) { showFeedback(data.message || 'Unable to log in with the provided credentials.', 'error'); return; }
                        if (data && typeof data === 'object') { const firstError = Object.values(data).flat().find(Boolean); showFeedback(String(firstError || 'Login failed. Please try again.'), 'error'); return; }
                        showFeedback('Login failed. Please try again.', 'error');
                    } catch {
                        showFeedback('Unable to reach the authentication service. Please try again.', 'error');
                    } finally {
                        setLoading(false);
                    }
                });
            }

            fetchDashboardStatus().then(used => { if (!used) fetchPublicStatus(); });
        });
    </script>
</body>

</html>