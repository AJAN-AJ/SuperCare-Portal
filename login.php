<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (strpos($username, "scs/") !== 0) {
        $error = "Username must start with scs/";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {
                $_SESSION["user_id"]  = $user["id"];
                $_SESSION["role"]     = $user["role"];
                $_SESSION["branch_id"] = $user["branch_id"];

                if ($user["role"] == "admin") {
                    header("Location: admin/dashboard.php");
                    exit();
                } elseif ($user["role"] == "teller") {
                    if ($user["profile_completed"] == 0) {
                        header("Location: staff/profile_setup.php");
                        exit();
                    }
                    if ($user["approved"] == 0) {
                        header("Location: staff/profile_pending.php");
                        exit();
                    }
                    header("Location: staff/dashboard.php");
                    exit();
                }
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperCare Solutions — Login</title>
    <link rel="icon" type="image/png" href="logoicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0a0f1e;
            --surface:   #111827;
            --border:    #1f2d45;
            --accent:    #3b82f6;
            --accent-glow: rgba(59,130,246,0.25);
            --text:      #f1f5f9;
            --muted:     #64748b;
            --error:     #f87171;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 400;
        }

        /* Animated background grid */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(59,130,246,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59,130,246,0.04) 1px, transparent 1px);
            background-size: 48px 48px;
            animation: gridScroll 20s linear infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes gridScroll {
            from { background-position: 0 0; }
            to   { background-position: 48px 48px; }
        }

        /* Glowing orb */
        body::after {
            content: '';
            position: fixed;
            top: -20%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(59,130,246,0.12) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.03) inset,
                0 24px 64px rgba(0,0,0,0.5),
                0 0 80px var(--accent-glow);
            animation: cardIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Logo area */
        .logo-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
            animation: fadeUp 0.5s 0.1s cubic-bezier(0.16,1,0.3,1) both;
        }

        .logo-icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(59,130,246,0.3);
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 1.6rem;
            letter-spacing: -0.02em;
            color: var(--text);
            line-height: 1;
            margin-bottom: 0.3rem;
        }

        .brand-sub {
            font-size: 0.75rem;
            color: var(--muted);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
            margin-bottom: 1.75rem;
        }

        /* Error */
        .error-box {
            background: rgba(248,113,113,0.08);
            border: 1px solid rgba(248,113,113,0.25);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            color: var(--error);
            margin-bottom: 1.25rem;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        /* Form */
        form { animation: fadeUp 0.5s 0.2s cubic-bezier(0.16,1,0.3,1) both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .field { margin-bottom: 1.1rem; }

        .field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.45rem;
        }

        .input-wrap { position: relative; }

        .input-wrap .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
            display: flex;
        }

        .input-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.8rem 1rem 0.8rem 2.75rem;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .input-wrap input::placeholder { color: var(--muted); }

        .input-wrap input:focus {
            border-color: var(--accent);
            background: rgba(59,130,246,0.06);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        /* Hint tag */
        .hint {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(59,130,246,0.1);
            border: 1px solid rgba(59,130,246,0.2);
            color: #93c5fd;
            font-size: 0.7rem;
            border-radius: 6px;
            padding: 0.25rem 0.6rem;
            margin-top: 0.45rem;
            letter-spacing: 0.03em;
        }

        /* Submit */
        .btn {
            width: 100%;
            margin-top: 0.5rem;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.02em;
            box-shadow: 0 4px 20px rgba(59,130,246,0.35);
            transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            border-radius: inherit;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(59,130,246,0.45);
            filter: brightness(1.05);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 12px rgba(59,130,246,0.3);
        }

        /* Footer note */
        .footer-note {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.72rem;
            color: var(--muted);
            letter-spacing: 0.04em;
            animation: fadeUp 0.5s 0.3s cubic-bezier(0.16,1,0.3,1) both;
        }

        @media (max-width: 480px) {
            .card { padding: 2rem 1.25rem; border-radius: 16px; }
            .brand-name { font-size: 1.4rem; }
        }

        /* ── Loading overlay ── */
        #loader {
            position: fixed;
            inset: 0;
            background: var(--bg);
            z-index: 999;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }

        #loader.active { display: flex; }

        .loader-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            animation: logoPulse 1.4s ease-in-out infinite;
            filter: drop-shadow(0 0 20px rgba(59,130,246,0.6));
        }

        @keyframes logoPulse {
            0%, 100% { transform: scale(1);   opacity: 1;    filter: drop-shadow(0 0 20px rgba(59,130,246,0.6)); }
            50%       { transform: scale(1.08); opacity: 0.85; filter: drop-shadow(0 0 36px rgba(59,130,246,0.9)); }
        }

        .loader-ring {
            width: 52px;
            height: 52px;
            border: 3px solid rgba(59,130,246,0.15);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loader-text {
            font-family: "DM Sans", sans-serif;
            font-size: 0.82rem;
            color: var(--muted);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            animation: blink 1.4s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 0.5; }
            50%       { opacity: 1; }
        }

    </style>
</head>
<body>

<!-- Loading overlay -->
<div id="loader">
    <img src="logoicon.png" alt="SuperCare" class="loader-logo">
    <div class="loader-ring"></div>
    <p class="loader-text">Signing in&hellip;</p>
</div>

<div class="page">
    <div class="card">

        <!-- Logo -->
        <div class="logo-wrap">
            <img src="logoicon.png" alt="SuperCare Logo" style="width:64px;height:64px;object-fit:contain;margin-bottom:1rem;filter:drop-shadow(0 8px 24px rgba(59,130,246,0.4));">
            <div class="brand-name">SuperCare</div>
            <div class="brand-sub">Solutions Portal</div>
        </div>

        <div class="divider"></div>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="error-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST">
            <div class="field">
                <label>Username</label>
                <div class="input-wrap">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" opacity=".6">
                            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                        </svg>
                    </span>
                    <input type="text" name="username" placeholder="scs/username" required
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
                <div class="hint">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                    Must begin with <strong>scs/</strong>
                </div>
            </div>

            <div class="field">
                <label>Password</label>
                <div class="input-wrap">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" opacity=".6">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </span>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <p class="footer-note">© <?= date('Y') ?> SuperCare Solutions · Secure Access</p>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const u = document.querySelector('input[name="username"]').value.trim();
        const p = document.querySelector('input[name="password"]').value.trim();
        if (u && p) {
            document.getElementById('loader').classList.add('active');
        }
    });
</script>
</body>
</html>