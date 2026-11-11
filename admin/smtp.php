<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    header("location:../");
    exit;
}

// Fetch current SMTP settings for display
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT * FROM tbl_smtp LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
    $configured = ($smtp && !empty($smtp['server']) && $smtp['server'] != 'smtp server here');

    // Check if updated_at column exists
    $colCheck = $pdo->query("SHOW COLUMNS FROM tbl_smtp LIKE 'updated_at'");
    $hasUpdatedAt = ($colCheck->rowCount() > 0);
    $lastUpdated = ($hasUpdatedAt && !empty($smtp['updated_at'])) ? date('Y-m-d H:i', strtotime($smtp['updated_at'])) : 'Not available';
} catch (PDOException $e) {
    $smtp = null;
    $configured = false;
    $lastUpdated = 'Error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - SMTP Configuration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        /* Ultra‑realistic glassmorphism with refined details */
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --primary-dark: #3451d1;
            --success: #06d6a0;
            --success-dark: #05b588;
            --warning: #ffb703;
            --warning-dark: #e6a502;
            --danger: #ef476f;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --border-radius: 24px;
            --card-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: 1px solid rgba(255, 255, 255, 0.5);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            --text-primary: #1e293b;
            --text-secondary: #475569;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Subtle noise texture */
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.005' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.08'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 1;
        }

        .app-content {
            position: relative;
            z-index: 2;
            background: transparent;
            padding: 1.5rem 2rem;
        }

        /* Glass cards with refined shadows and border */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow), inset 0 1px 2px rgba(255,255,255,0.3);
            border: var(--glass-border);
            transition: var(--transition);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        /* Status card specific */
        .status-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }

        .status-icon {
            width: 64px;
            height: 64px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.05);
        }

        .status-content {
            flex: 1;
        }

        .status-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.3px;
            margin-bottom: 0.2rem;
        }

        .status-value {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .badge {
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .badge-success {
            background: var(--success);
            color: white;
        }

        .badge-warning {
            background: var(--warning);
            color: white;
        }

        .last-updated {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .last-updated i {
            margin-right: 0.3rem;
        }

        /* Main form card */
        .form-card {
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-title i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        /* Provider preset */
        .preset-section {
            margin-bottom: 2rem;
        }

        .preset-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preset-select {
            background: rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 40px;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            color: var(--dark);
            transition: var(--transition);
            cursor: pointer;
        }

        .preset-select:hover {
            background: rgba(255,255,255,0.8);
            border-color: var(--primary);
        }

        .preset-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
        }

        /* Form fields */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: var(--primary);
        }

        .help-icon {
            color: var(--gray);
            font-size: 0.9rem;
            cursor: help;
            transition: color 0.2s;
        }

        .help-icon:hover {
            color: var(--primary);
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            background: rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 40px;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            color: var(--dark);
            transition: var(--transition);
        }

        .form-control:hover {
            background: rgba(255,255,255,0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
        }

        /* Password input group */
        .input-group {
            display: flex;
            align-items: center;
        }

        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
        }

        .input-group-text {
            background: rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.6);
            border-left: none;
            border-top-right-radius: 40px;
            border-bottom-right-radius: 40px;
            padding: 0.75rem 1.2rem;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .input-group-text:hover {
            color: var(--primary);
            background: rgba(255,255,255,0.8);
        }

        /* Port quick buttons */
        .port-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }

        .port-btn {
            background: rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 40px;
            padding: 0.4rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: var(--transition);
            cursor: pointer;
        }

        .port-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(67,97,238,0.2);
        }

        /* Validation feedback */
        .validation-feedback {
            min-height: 1.5rem;
            margin-top: 0.3rem;
            font-size: 0.85rem;
        }

        .valid-feedback {
            color: var(--success);
        }

        .invalid-feedback {
            color: var(--danger);
        }

        /* Action buttons */
        .action-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            border-radius: 40px;
            padding: 0.7rem 1.8rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(67,97,238,0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(6,214,160,0.3);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: var(--warning-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255,183,3,0.3);
        }

        /* Summary card */
        .summary-card {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            padding: 1.5rem 2rem;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .summary-content {
            flex: 1;
        }

        .summary-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .summary-item .label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
        }

        .summary-item .value {
            font-weight: 600;
            color: var(--dark);
            word-break: break-word;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .app-content { padding: 1rem; }
            .status-card { flex-direction: column; align-items: flex-start; }
            .summary-card { flex-direction: column; }
            .action-bar { justify-content: center; }
        }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <!-- Status Card -->
    <div class="glass-card status-card">
        <div class="status-icon"><i class="bi bi-envelope-paper"></i></div>
        <div class="status-content">
            <div class="status-title">SMTP Configuration Status</div>
            <div class="status-value">
                <?php if ($configured): ?>
                    <span class="badge badge-success"><i class="bi bi-check-circle me-1"></i> Configured</span>
                <?php else: ?>
                    <span class="badge badge-warning"><i class="bi bi-exclamation-triangle me-1"></i> Not Configured</span>
                <?php endif; ?>
                <span class="last-updated"><i class="bi bi-clock"></i> Last updated: <?php echo $lastUpdated; ?></span>
            </div>
        </div>
    </div>

    <!-- Main Form Card -->
    <div class="glass-card form-card">
        <div class="form-title">
            <i class="bi bi-gear-wide-connected"></i>
            Mail Server Configuration
        </div>

        <!-- Provider Presets -->
        <div class="preset-section">
            <div class="preset-label">
                <i class="bi bi-stars"></i> Quick Provider Presets
                <span class="help-icon" data-bs-toggle="tooltip" title="Select your email provider to auto‑fill common settings"><i class="bi bi-question-circle"></i></span>
            </div>
            <select class="preset-select" id="providerPreset" style="width: 100%;">
                <option value="">-- Choose a provider --</option>
                <option value="gmail">Gmail</option>
                <option value="outlook">Outlook / Office 365</option>
                <option value="yahoo">Yahoo Mail</option>
            </select>
        </div>

        <form method="POST" autocomplete="OFF" action="admin/core/update_smtp" id="smtpForm">
            <?php if ($smtp): ?>
                <!-- Server -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-server"></i> SMTP Server
                        <span class="help-icon" data-bs-toggle="tooltip" title="The outgoing mail server address (e.g., smtp.gmail.com)"><i class="bi bi-question-circle"></i></span>
                    </label>
                    <input type="text" name="mail_server" id="mail_server" value="<?php echo htmlspecialchars($smtp['server']); ?>" class="form-control" placeholder="e.g. smtp.gmail.com">
                    <div class="validation-feedback" id="serverFeedback"></div>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-person-circle"></i> SMTP Username
                        <span class="help-icon" data-bs-toggle="tooltip" title="Usually your full email address"><i class="bi bi-question-circle"></i></span>
                    </label>
                    <input type="text" name="mail_username" id="mail_username" value="<?php echo htmlspecialchars($smtp['username']); ?>" class="form-control" placeholder="your-email@gmail.com">
                    <div class="validation-feedback" id="usernameFeedback"></div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-lock"></i> SMTP Password
                        <span class="help-icon" data-bs-toggle="tooltip" title="Your email password or app‑specific password"><i class="bi bi-question-circle"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="password" name="mail_password" id="mail_password" value="<?php echo htmlspecialchars($smtp['password']); ?>" class="form-control" placeholder="********">
                        <span class="input-group-text" onclick="togglePassword('mail_password', this)"><i class="bi bi-eye"></i></span>
                    </div>
                </div>

                <!-- Port with quick buttons -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-plug"></i> SMTP Port
                        <span class="help-icon" data-bs-toggle="tooltip" title="Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)"><i class="bi bi-question-circle"></i></span>
                    </label>
                    <input type="text" name="mail_port" id="mail_port" value="<?php echo htmlspecialchars($smtp['port']); ?>" class="form-control" placeholder="e.g. 587">
                    <div class="port-buttons">
                        <button type="button" class="port-btn" onclick="setPort(587, 'tls')">587 (TLS)</button>
                        <button type="button" class="port-btn" onclick="setPort(465, 'ssl')">465 (SSL)</button>
                        <button type="button" class="port-btn" onclick="setPort(25, 'none')">25 (None)</button>
                    </div>
                    <div class="validation-feedback" id="portFeedback"></div>
                </div>

                <!-- Security -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-shield-lock"></i> Security Connection
                        <span class="help-icon" data-bs-toggle="tooltip" title="Encryption protocol to use"><i class="bi bi-question-circle"></i></span>
                    </label>
                    <select class="form-control" name="mail_security" id="mail_security" required style="appearance: none;">
                        <option value="" disabled>Select One</option>
                        <option value="ssl" <?php if ($smtp['encryption'] == 'ssl') echo 'selected'; ?>>SSL</option>
                        <option value="tls" <?php if ($smtp['encryption'] == 'tls') echo 'selected'; ?>>TLS</option>
                        <option value="none" <?php if ($smtp['encryption'] == 'none') echo 'selected'; ?>>None</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="action-bar">
                    <button type="button" class="btn btn-secondary" onclick="copyConfig()"><i class="bi bi-clipboard"></i> Copy Config</button>
                    <button type="button" class="btn btn-warning" onclick="resetToDefaults()"><i class="bi bi-arrow-counterclockwise"></i> Reset Defaults</button>
                    <button type="submit" name="submit" value="1" class="btn btn-primary" id="submitBtn"><i class="bi bi-check-circle"></i> Update Settings</button>
                    <button type="submit" name="submit" value="1" class="btn btn-success" id="submitAndCloseBtn" formaction="admin/core/update_smtp?redirect=dashboard"><i class="bi bi-check-circle"></i> Save & Close</button>
                </div>
            <?php else: ?>
                <p class="text-danger">Could not load SMTP settings.</p>
            <?php endif; ?>
        </form>
    </div>

    <!-- Configuration Summary Card -->
    <?php if ($smtp): ?>
    <div class="glass-card summary-card">
        <div class="summary-icon"><i class="bi bi-info-lg"></i></div>
        <div class="summary-content">
            <div class="summary-title">Current Configuration</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="label">Server</span>
                    <span class="value"><?php echo htmlspecialchars($smtp['server']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Username</span>
                    <span class="value"><?php echo htmlspecialchars($smtp['username']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Password</span>
                    <span class="value">••••••••</span>
                </div>
                <div class="summary-item">
                    <span class="label">Port</span>
                    <span class="value"><?php echo htmlspecialchars($smtp['port']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Security</span>
                    <span class="value"><?php echo strtoupper(htmlspecialchars($smtp['encryption'])); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Toggle password visibility
    function togglePassword(fieldId, el) {
        const field = document.getElementById(fieldId);
        const icon = el.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    // Reset to defaults
    function resetToDefaults() {
        document.getElementById('mail_server').value = 'smtp server here';
        document.getElementById('mail_username').value = 'smtp username here';
        document.getElementById('mail_password').value = 'smtp password here';
        document.getElementById('mail_port').value = '587';
        document.getElementById('mail_security').value = 'tls';
        Swal.fire({
            icon: 'info',
            title: 'Defaults Loaded',
            text: 'Placeholder values have been filled. Click Update Settings to save.',
            timer: 2000,
            showConfirmButton: false
        });
        validateAll();
    }

    // Provider presets
    document.getElementById('providerPreset').addEventListener('change', function() {
        const preset = this.value;
        if (preset === 'gmail') {
            document.getElementById('mail_server').value = 'smtp.gmail.com';
            document.getElementById('mail_port').value = '587';
            document.getElementById('mail_security').value = 'tls';
        } else if (preset === 'outlook') {
            document.getElementById('mail_server').value = 'smtp-mail.outlook.com';
            document.getElementById('mail_port').value = '587';
            document.getElementById('mail_security').value = 'tls';
        } else if (preset === 'yahoo') {
            document.getElementById('mail_server').value = 'smtp.mail.yahoo.com';
            document.getElementById('mail_port').value = '465';
            document.getElementById('mail_security').value = 'ssl';
        }
        validateAll();
    });

    // Set port and security
    function setPort(port, security) {
        document.getElementById('mail_port').value = port;
        if (security !== 'none') {
            document.getElementById('mail_security').value = security;
        }
        validatePort();
    }

    // Validation
    function validateServer() {
        const server = document.getElementById('mail_server').value.trim();
        const feedback = document.getElementById('serverFeedback');
        if (server === '') {
            feedback.innerHTML = '<span class="invalid-feedback">Server cannot be empty</span>';
            return false;
        } else {
            feedback.innerHTML = '<span class="valid-feedback">✓ Looks good</span>';
            return true;
        }
    }

    function validateUsername() {
        const username = document.getElementById('mail_username').value.trim();
        const feedback = document.getElementById('usernameFeedback');
        if (username === '') {
            feedback.innerHTML = '<span class="invalid-feedback">Username cannot be empty</span>';
            return false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(username) && username !== 'smtp username here') {
            feedback.innerHTML = '<span class="invalid-feedback">Enter a valid email address</span>';
            return false;
        } else {
            feedback.innerHTML = '<span class="valid-feedback">✓ Valid email</span>';
            return true;
        }
    }

    function validatePort() {
        const port = document.getElementById('mail_port').value.trim();
        const feedback = document.getElementById('portFeedback');
        if (port === '') {
            feedback.innerHTML = '<span class="invalid-feedback">Port cannot be empty</span>';
            return false;
        } else if (!/^\d+$/.test(port)) {
            feedback.innerHTML = '<span class="invalid-feedback">Port must be numeric</span>';
            return false;
        } else {
            feedback.innerHTML = '<span class="valid-feedback">✓ Valid port</span>';
            return true;
        }
    }

    function validateAll() {
        validateServer();
        validateUsername();
        validatePort();
    }

    document.getElementById('mail_server').addEventListener('input', validateServer);
    document.getElementById('mail_username').addEventListener('input', validateUsername);
    document.getElementById('mail_port').addEventListener('input', validatePort);

    // Copy config to clipboard
    function copyConfig() {
        const config = {
            server: document.getElementById('mail_server').value,
            username: document.getElementById('mail_username').value,
            password: '********',
            port: document.getElementById('mail_port').value,
            security: document.getElementById('mail_security').value
        };
        navigator.clipboard.writeText(JSON.stringify(config, null, 2)).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Configuration copied to clipboard (password masked).',
                timer: 1500,
                showConfirmButton: false
            });
        }).catch(() => {
            Swal.fire('Error', 'Could not copy to clipboard', 'error');
        });
    }

    // Optional: prevent form submission if validation fails
    document.getElementById('smtpForm').addEventListener('submit', function(e) {
        if (!validateServer() || !validateUsername() || !validatePort()) {
            e.preventDefault();
            Swal.fire('Validation Error', 'Please correct the errors before saving.', 'error');
        }
    });
</script>
</body>
</html>