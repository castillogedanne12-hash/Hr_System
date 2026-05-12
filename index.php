<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = time();
    }
    if ($_SESSION['login_attempts'] >= 5) {
        $wait = 300 - (time() - $_SESSION['login_time']);
        if ($wait > 0) {
            $error = 'Too many failed attempts. Please wait ' . ceil($wait / 60) . ' minute(s).';
            goto show_form;
        }
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = time();
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['login_attempts'] = 0;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['login_time'] = time();
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

show_form:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — HRNexus</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#0D0F14;--bg2:#13161E;--bg3:#1A1E29;--border:#2A2F3F;--border2:#333A50;--text:#E8EAF0;--text2:#9BA3BA;--text3:#6B7490;--accent:#F0A500;--accent2:#FFB830;--accent-dim:rgba(240,165,0,0.12);--danger:#EF4444;--danger-dim:rgba(239,68,68,0.12)}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:stretch}
        .login-split{display:flex;width:100%;min-height:100vh}
        .login-brand{flex:0 0 42%;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:space-between;padding:48px;position:relative;overflow:hidden}
        .brand-bg{position:absolute;inset:0;background:radial-gradient(ellipse at 20% 20%,rgba(240,165,0,.07) 0%,transparent 60%),radial-gradient(ellipse at 80% 80%,rgba(59,130,246,.05) 0%,transparent 60%);pointer-events:none}
        .brand-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
        .brand-logo{display:flex;align-items:center;gap:12px;position:relative}
        .logo-mark{width:44px;height:44px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:900;font-size:1.1rem;color:#000}
        .logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:1.4rem;letter-spacing:-.03em}
        .brand-hero{position:relative}
        .brand-tagline{font-family:'Syne',sans-serif;font-size:2.4rem;font-weight:800;line-height:1.15;letter-spacing:-.04em;margin-bottom:18px}
        .brand-tagline span{color:var(--accent)}
        .brand-desc{color:var(--text2);font-size:.9rem;line-height:1.7;max-width:340px}
        .brand-stats{display:flex;gap:28px;position:relative}
        .brand-stat-val{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;color:var(--accent)}
        .brand-stat-lbl{font-size:.72rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-top:2px}
        .login-form-side{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px}
        .login-box{width:100%;max-width:400px}
        .login-heading{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;letter-spacing:-.03em;margin-bottom:6px}
        .login-sub{color:var(--text3);font-size:.87rem;margin-bottom:32px}
        .form-group{margin-bottom:18px}
        label{display:block;font-size:.73rem;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px}
        input[type=text],input[type=password]{width:100%;background:var(--bg2);border:1px solid var(--border2);color:var(--text);padding:11px 15px;border-radius:9px;font-size:.92rem;font-family:'DM Sans',sans-serif;transition:border-color .2s,box-shadow .2s}
        input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-dim)}
        .btn-login{width:100%;background:var(--accent);color:#000;border:none;padding:12px;border-radius:9px;font-size:.92rem;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:background .2s;margin-top:6px}
        .btn-login:hover{background:var(--accent2)}
        .error-msg{background:var(--danger-dim);color:var(--danger);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:11px 14px;font-size:.85rem;margin-bottom:18px;display:flex;align-items:center;gap:8px}
        .login-footer{margin-top:28px;text-align:center;font-size:.75rem;color:var(--text3)}
        @media(max-width:768px){.login-brand{display:none}}
    </style>
</head>
<body>
<div class="login-split">
    <div class="login-brand">
        <div class="brand-bg"></div><div class="brand-grid"></div>
        <div class="brand-logo"><div class="logo-mark">HR</div><div class="logo-name">HRNexus</div></div>
        <div class="brand-hero">
            <div class="brand-tagline">People-first<br><span>HR management</span><br>redefined.</div>
            <div class="brand-desc">A complete human resources platform for modern organizations. Manage employees, payroll, attendance, and more — all in one place.</div>
        </div>
        <div class="brand-stats">
            <div><div class="brand-stat-val">360°</div><div class="brand-stat-lbl">Employee View</div></div>
            <div><div class="brand-stat-val">6+</div><div class="brand-stat-lbl">HR Modules</div></div>
            <div><div class="brand-stat-val">∞</div><div class="brand-stat-lbl">Scalability</div></div>
        </div>
    </div>
    <div class="login-form-side">
        <div class="login-box">
            <div class="login-heading">Welcome back</div>
            <div class="login-sub">Sign in to your HRNexus account</div>
            <?php if ($error): ?>
            <div class="error-msg">✗ <?= h($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter your username" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" autofocus required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-login">Sign In →</button>
            </form>
            <div style="margin-top:20px;text-align:center;font-size:.85rem;color:var(--text3)">No account yet? <a href="register.php" style="color:var(--accent);font-weight:600;text-decoration:none">Create one →</a></div>
            <div class="login-footer">HRNexus v1.0 &nbsp;·&nbsp; © 2025 All rights reserved</div>
        </div>
    </div>
</div>
</body>
</html>
