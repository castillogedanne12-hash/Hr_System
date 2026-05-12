<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';
    $role      = 'hr'; // default role for new accounts

    if (!$username || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = 'Username already taken. Please choose another.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
               ->execute([$username, $hash, $role]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — HRNexus</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#0D0F14;--bg2:#13161E;--bg3:#1A1E29;
            --border:#2A2F3F;--border2:#333A50;
            --text:#E8EAF0;--text2:#9BA3BA;--text3:#6B7490;
            --accent:#F0A500;--accent2:#FFB830;--accent-dim:rgba(240,165,0,0.12);
            --danger:#EF4444;--danger-dim:rgba(239,68,68,0.12);
            --success:#22C55E;--success-dim:rgba(34,197,94,0.12);
        }
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:stretch}
        .split{display:flex;width:100%;min-height:100vh}

        /* Left brand panel */
        .brand{flex:0 0 42%;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:space-between;padding:48px;position:relative;overflow:hidden}
        .brand-bg{position:absolute;inset:0;background:radial-gradient(ellipse at 80% 20%,rgba(240,165,0,0.08) 0%,transparent 55%),radial-gradient(ellipse at 20% 80%,rgba(34,197,94,0.05) 0%,transparent 55%);pointer-events:none}
        .brand-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
        .logo{display:flex;align-items:center;gap:12px;position:relative}
        .logo-mark{width:44px;height:44px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:900;font-size:1.1rem;color:#000}
        .logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:1.4rem;letter-spacing:-.03em}
        .brand-body{position:relative}
        .brand-title{font-family:'Syne',sans-serif;font-size:2.2rem;font-weight:800;line-height:1.15;letter-spacing:-.04em;margin-bottom:18px}
        .brand-title span{color:var(--accent)}
        .brand-desc{color:var(--text2);font-size:.88rem;line-height:1.75}
        .brand-perks{display:flex;flex-direction:column;gap:12px;position:relative}
        .perk{display:flex;align-items:center;gap:12px;font-size:.85rem;color:var(--text2)}
        .perk-icon{width:32px;height:32px;border-radius:8px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--accent);font-size:.95rem}

        /* Right form panel */
        .form-side{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px}
        .box{width:100%;max-width:420px}
        .box-title{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;letter-spacing:-.03em;margin-bottom:6px}
        .box-sub{color:var(--text3);font-size:.87rem;margin-bottom:30px}

        .form-group{margin-bottom:16px}
        label{display:block;font-size:.72rem;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px}
        .input-wrap{position:relative}
        input[type=text],input[type=password]{width:100%;background:var(--bg2);border:1px solid var(--border2);color:var(--text);padding:11px 15px;border-radius:9px;font-size:.92rem;font-family:'DM Sans',sans-serif;transition:border-color .2s,box-shadow .2s}
        input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-dim)}
        .input-hint{font-size:.72rem;color:var(--text3);margin-top:5px}

        .strength-bar{height:4px;border-radius:99px;background:var(--bg3);margin-top:8px;overflow:hidden;transition:all .3s}
        .strength-fill{height:100%;border-radius:99px;transition:width .3s,background .3s;width:0}

        .btn{width:100%;padding:12px;border-radius:9px;font-size:.92rem;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;transition:background .2s;margin-top:4px}
        .btn-primary{background:var(--accent);color:#000}
        .btn-primary:hover{background:var(--accent2)}

        .alert{padding:13px 16px;border-radius:9px;font-size:.85rem;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px}
        .alert-error{background:var(--danger-dim);color:var(--danger);border:1px solid rgba(239,68,68,.25)}
        .alert-success{background:var(--success-dim);color:var(--success);border:1px solid rgba(34,197,94,.25)}
        .alert-icon{font-size:1rem;flex-shrink:0;margin-top:1px}

        .divider{display:flex;align-items:center;gap:12px;margin:20px 0}
        .divider-line{flex:1;height:1px;background:var(--border)}
        .divider-text{font-size:.75rem;color:var(--text3)}

        .signin-link{text-align:center;font-size:.85rem;color:var(--text3)}
        .signin-link a{color:var(--accent);font-weight:600;text-decoration:none}
        .signin-link a:hover{color:var(--accent2)}

        .footer{margin-top:28px;text-align:center;font-size:.72rem;color:var(--text3)}

        /* Success state */
        .success-box{text-align:center;padding:32px 0}
        .success-icon{width:64px;height:64px;background:var(--success-dim);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 18px;border:2px solid rgba(34,197,94,.3)}
        .success-title{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;margin-bottom:8px}
        .success-desc{color:var(--text3);font-size:.87rem;line-height:1.6;margin-bottom:24px}

        @media(max-width:768px){.brand{display:none}}
    </style>
</head>
<body>
<div class="split">
    <!-- Brand -->
    <div class="brand">
        <div class="brand-bg"></div>
        <div class="brand-grid"></div>
        <div class="logo">
            <div class="logo-mark">HR</div>
            <div class="logo-name">HRNexus</div>
        </div>
        <div class="brand-body">
            <div class="brand-title">Join your<br>team on<br><span>HRNexus</span></div>
            <div class="brand-desc">Create your account and start managing your organization's most valuable asset — your people.</div>
        </div>
        <div class="brand-perks">
            <div class="perk"><div class="perk-icon">◎</div>Manage employees and departments</div>
            <div class="perk"><div class="perk-icon">◷</div>Track attendance and leave requests</div>
            <div class="perk"><div class="perk-icon">◆</div>Generate payroll and payslips</div>
            <div class="perk"><div class="perk-icon">◉</div>Post company-wide announcements</div>
        </div>
    </div>

    <!-- Form -->
    <div class="form-side">
        <div class="box">

        <?php if ($success): ?>
            <!-- Success State -->
            <div class="success-box">
                <div class="success-icon">✓</div>
                <div class="success-title">Account created!</div>
                <div class="success-desc">Your HRNexus account is ready. You can now sign in with your credentials.</div>
                <a href="index.php" style="display:inline-flex;align-items:center;gap:8px;background:var(--accent);color:#000;padding:12px 28px;border-radius:9px;font-weight:700;font-size:.92rem;text-decoration:none">
                    Sign In →
                </a>
            </div>

        <?php else: ?>
            <div class="box-title">Create account</div>
            <div class="box-sub">Sign up to access HRNexus</div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">✗</span>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="e.g. john_doe" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" autofocus required minlength="3">
                    <div class="input-hint">Letters, numbers, underscores only. Min. 3 characters.</div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="passwordInput" placeholder="Choose a strong password" autocomplete="new-password" required minlength="6" oninput="checkStrength(this.value)">
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <div class="input-hint" id="strengthText">Min. 6 characters</div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" id="confirmInput" placeholder="Re-enter your password" autocomplete="new-password" required oninput="checkMatch()">
                    <div class="input-hint" id="matchText"></div>
                </div>

                <button type="submit" class="btn btn-primary">Create Account →</button>
            </form>

            <div class="divider">
                <div class="divider-line"></div>
                <div class="divider-text">already have an account?</div>
                <div class="divider-line"></div>
            </div>

            <div class="signin-link">
                <a href="index.php">← Sign in instead</a>
            </div>

            <div class="footer">HRNexus v1.0 &nbsp;·&nbsp; © 2025 All rights reserved</div>
        <?php endif; ?>

        </div>
    </div>
</div>

<script>
function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;

    const levels = [
        { w: '0%',   bg: 'transparent', label: 'Min. 6 characters' },
        { w: '25%',  bg: '#EF4444',     label: 'Weak' },
        { w: '50%',  bg: '#F59E0B',     label: 'Fair' },
        { w: '75%',  bg: '#3B82F6',     label: 'Good' },
        { w: '100%', bg: '#22C55E',     label: 'Strong 💪' },
    ];
    const lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 4)];
    fill.style.width = lvl.w;
    fill.style.background = lvl.bg;
    text.textContent = val.length === 0 ? 'Min. 6 characters' : lvl.label;
    text.style.color = lvl.bg || 'var(--text3)';
}

function checkMatch() {
    const pw  = document.getElementById('passwordInput').value;
    const cfm = document.getElementById('confirmInput').value;
    const txt = document.getElementById('matchText');
    if (!cfm) { txt.textContent = ''; return; }
    if (pw === cfm) {
        txt.textContent = '✓ Passwords match';
        txt.style.color = '#22C55E';
    } else {
        txt.textContent = '✗ Passwords do not match';
        txt.style.color = '#EF4444';
    }
}
</script>
</body>
</html>
