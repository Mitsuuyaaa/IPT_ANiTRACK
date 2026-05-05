<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $t = $_SESSION['user_type'] ?? '';
    if ($t === 'admin')  { header("Location: ../admin/dashboard.php");    exit; }
    if ($t === 'Vendor') { header("Location: ../pages/marketplace.php");  exit; }
    header("Location: ../pages/dashboard.php"); exit;
}

$host = "localhost"; $db = "anitrack"; $user = "root"; $pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$error = '';
$error_type = 'error'; // 'error' | 'pending' | 'rejected'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter your username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, username, password, user_type, status, avatar FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($userRow && password_verify($password, $userRow['password'])) {
            if ($userRow['status'] === 'pending') {
                $error = "Your account is pending admin approval. Please wait.";
                $error_type = 'pending';
            } elseif ($userRow['status'] === 'rejected') {
                $error = "Your account was not approved. Please contact support.";
                $error_type = 'rejected';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $userRow['id'];
                $_SESSION['username']  = $userRow['username'];
                $_SESSION['user_type'] = $userRow['user_type'];
                $_SESSION['fullname']  = $userRow['first_name'] . ' ' . $userRow['last_name'];
                $_SESSION['name']      = $userRow['first_name'] . ' ' . $userRow['last_name'];
                $_SESSION['avatar']    = $userRow['avatar'] ?? '';
                if ($userRow['user_type'] === 'admin')  { header("Location: ../admin/dashboard.php");   exit; }
                if ($userRow['user_type'] === 'Vendor') { header("Location: ../pages/marketplace.php"); exit; }
                header("Location: ../pages/dashboard.php"); exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>ANI-TRACK | Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root {
  --g1:#0d2211; --g2:#1b3a1f; --g3:#2e7d32; --g4:#43a047;
  --g5:#66bb6a; --g6:#a5d6a7; --g7:#c8e6c9; --g8:#e8f5e9;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { min-height: 100vh; }

body {
  font-family: 'Poppins', sans-serif;
  background: var(--g1);
  color: #fff;
  overflow-x: hidden;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='1'/%3E%3C/svg%3E");
  opacity: 0.04; pointer-events: none; z-index: 1000;
}

.bg-layer {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 70% 60% at 80% 40%, rgba(46,125,50,0.2) 0%, transparent 60%),
    radial-gradient(ellipse 50% 70% at 10% 80%, rgba(27,58,31,0.5) 0%, transparent 55%),
    linear-gradient(135deg, #0d2211 0%, #162c1a 40%, #1b3a1f 70%, #0f2813 100%);
}

.bg-circle { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
.bc1 { width: 600px; height: 600px; border: 1px solid rgba(165,214,167,0.05); top: 50%; right: -150px; transform: translateY(-50%); animation: slowspin 30s linear infinite; }
.bc2 { width: 400px; height: 400px; border: 1px solid rgba(165,214,167,0.07); top: 50%; right: 0; transform: translateY(-50%); animation: slowspin 20s linear infinite reverse; }
@keyframes slowspin { to { transform: translateY(-50%) rotate(360deg); } }

.leaf { position: fixed; opacity: 0; animation: floatLeaf linear infinite; pointer-events: none; z-index: 0; }
@keyframes floatLeaf { 0%{opacity:0;transform:translateY(100vh) rotate(0deg) scale(0.5);} 10%{opacity:0.5;} 90%{opacity:0.2;} 100%{opacity:0;transform:translateY(-20vh) rotate(720deg) scale(1);} }

nav {
  position: relative; z-index: 10;
  padding: 20px 60px;
  display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(to bottom, rgba(13,34,17,0.95) 0%, transparent 100%);
}
.nav-logo { font-family:'Poppins',sans-serif; font-size:22px; font-weight:800; color:#fff; letter-spacing:2px; text-decoration:none; }
.nav-logo span { color: var(--g6); }
.nav-btn { padding:10px 26px; border-radius:50px; font-family:'Poppins',sans-serif; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.25s; letter-spacing:0.5px; }
.nav-btn.outline { background:transparent; border:1.5px solid rgba(255,255,255,0.35); color:rgba(255,255,255,0.85); }
.nav-btn.outline:hover { border-color:var(--g6); color:var(--g6); background:rgba(165,214,167,0.08); }
.nav-btn.solid { background:var(--g4); border:1.5px solid var(--g4); color:#fff; box-shadow:0 4px 20px rgba(67,160,71,0.4); }
.nav-btn.solid:hover { background:var(--g5); border-color:var(--g5); transform:translateY(-1px); box-shadow:0 6px 24px rgba(67,160,71,0.5); }

.main {
  flex: 1; position: relative; z-index: 1;
  display: flex; align-items: center; justify-content: center;
  padding: 40px 24px 60px;
}

.login-wrap {
  display: flex; width: 860px; max-width: 98vw;
  border-radius: 24px; overflow: hidden;
  border: 1px solid rgba(165,214,167,0.1);
  box-shadow: 0 30px 80px rgba(0,0,0,0.5);
  animation: fadeUp 0.7s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(28px);} to{opacity:1;transform:translateY(0);} }

.login-left {
  width: 320px; flex-shrink: 0;
  background: linear-gradient(160deg, #1b3a1f 0%, #0d2211 100%);
  border-right: 1px solid rgba(165,214,167,0.08);
  padding: 50px 36px;
  display: flex; flex-direction: column; justify-content: space-between;
  position: relative; overflow: hidden;
}
.login-left::before { content:''; position:absolute; width:280px; height:280px; border-radius:50%; background:radial-gradient(circle,rgba(67,160,71,0.12) 0%,transparent 70%); top:-80px; right:-80px; }
.login-left::after  { content:''; position:absolute; width:200px; height:200px; border-radius:50%; background:radial-gradient(circle,rgba(46,125,50,0.08) 0%,transparent 70%); bottom:-60px; left:-40px; }

.left-brand { z-index:1; }
.brand-name { font-family:'Poppins',sans-serif; font-size:22px; font-weight:800; color:#fff; letter-spacing:2px; }
.brand-name span { color: var(--g6); }
.brand-sub { font-size:10px; font-weight:600; letter-spacing:2.5px; color:rgba(255,255,255,0.3); text-transform:uppercase; margin-top:3px; }

.left-body { z-index:1; }
.left-eyebrow {
  display:inline-flex; align-items:center; gap:6px;
  background:rgba(165,214,167,0.08); border:1px solid rgba(165,214,167,0.2);
  border-radius:50px; padding:5px 12px;
  font-size:10px; font-weight:600; color:var(--g6); letter-spacing:1.5px; text-transform:uppercase;
  margin-bottom:16px;
}
.left-eyebrow::before { content:''; width:5px; height:5px; border-radius:50%; background:var(--g5); animation:pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.5;transform:scale(1.4);} }

.left-title { font-family:'Playfair Display',serif; font-size:28px; font-weight:900; line-height:1.15; color:#fff; margin-bottom:12px; }
.left-title em { font-style:italic; color:var(--g5); }
.left-desc { font-size:12px; color:rgba(255,255,255,0.45); line-height:1.8; margin-bottom:22px; }

.feat-list { display:flex; flex-direction:column; gap:10px; }
.feat-item { display:flex; align-items:center; gap:10px; font-size:12px; color:rgba(255,255,255,0.6); }
.feat-dot { width:20px; height:20px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--g4),var(--g3)); display:flex; align-items:center; justify-content:center; }
.feat-dot svg { width:11px; height:11px; color:#fff; }

.left-footer { z-index:1; font-size:11px; color:rgba(255,255,255,0.2); font-style:italic; }

.login-right {
  flex: 1;
  background: linear-gradient(145deg, #0f1f12, #162c1a);
  padding: 50px 44px;
  display: flex; flex-direction: column; justify-content: center;
}

.form-eyebrow { font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:var(--g5); margin-bottom:10px; }
.form-title { font-family:'Playfair Display',serif; font-size:30px; font-weight:900; color:#fff; margin-bottom:6px; line-height:1.15; }
.form-title em { font-style:italic; color:var(--g5); }
.form-sub { font-size:13px; color:rgba(255,255,255,0.35); margin-bottom:30px; }

/* Alert styles */
.alert-box {
  display:flex; align-items:flex-start; gap:10px;
  padding:12px 16px; border-radius:10px; font-size:13px;
  margin-bottom:20px; animation:fadeUp 0.3s ease;
}
.alert-box svg { width:16px; height:16px; flex-shrink:0; margin-top:1px; }
.alert-box.error   { background:rgba(229,57,53,0.1);  border:1px solid rgba(229,57,53,0.25);  color:#ef9a9a; }
.alert-box.pending { background:rgba(251,140,0,0.1);  border:1px solid rgba(251,140,0,0.3);   color:#ffcc80; }
.alert-box.rejected{ background:rgba(229,57,53,0.12); border:1px solid rgba(229,57,53,0.3);   color:#ef9a9a; }

.form-field { margin-bottom:18px; }
.form-field label { display:block; font-size:11px; font-weight:600; color:rgba(255,255,255,0.4); letter-spacing:1px; text-transform:uppercase; margin-bottom:8px; }
.input-wrap { position:relative; }
.input-wrap svg.ico { position:absolute; left:14px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:rgba(255,255,255,0.2); pointer-events:none; }
.input-wrap input {
  width:100%; padding:13px 14px 13px 42px;
  background:rgba(255,255,255,0.05); border:1.5px solid rgba(255,255,255,0.09);
  border-radius:10px; font-size:14px; font-family:'Poppins',sans-serif; color:#fff; outline:none;
  transition:border-color 0.2s,background 0.2s,box-shadow 0.2s;
}
.input-wrap input::placeholder { color:rgba(255,255,255,0.18); }
.input-wrap input:focus { border-color:var(--g4); background:rgba(67,160,71,0.07); box-shadow:0 0 0 4px rgba(67,160,71,0.12); }
.input-wrap input:hover:not(:focus) { border-color:rgba(255,255,255,0.18); }

.toggle-pw { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:rgba(255,255,255,0.22); padding:4px; border-radius:6px; transition:color 0.2s; }
.toggle-pw:hover { color:var(--g5); }
.toggle-pw svg { width:16px; height:16px; }

.btn-login {
  width:100%; padding:14px;
  background:linear-gradient(135deg,var(--g5),var(--g3));
  color:#fff; border:none; border-radius:10px;
  font-family:'Poppins',sans-serif; font-size:14px; font-weight:700; letter-spacing:1px;
  cursor:pointer; box-shadow:0 6px 24px rgba(67,160,71,0.35);
  transition:all 0.25s; position:relative; overflow:hidden; margin-top:4px;
}
.btn-login::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,0.12),transparent); opacity:0; transition:opacity 0.25s; }
.btn-login:hover { transform:translateY(-2px); box-shadow:0 10px 32px rgba(67,160,71,0.5); }
.btn-login:hover::before { opacity:1; }
.btn-login:active { transform:translateY(0); }

.divider { display:flex; align-items:center; gap:12px; margin:20px 0; color:rgba(255,255,255,0.18); font-size:12px; }
.divider::before,.divider::after { content:''; flex:1; height:1px; background:rgba(255,255,255,0.07); }

.err-msg { font-size:11px; color:#f48fb1; margin-top:5px; display:block; min-height:14px; }
.register-link { text-align:center; font-size:13px; color:rgba(255,255,255,0.35); }
.register-link a { color:var(--g5); font-weight:700; text-decoration:none; transition:color 0.2s; position:relative; }
.register-link a::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:1.5px; background:var(--g5); transition:width 0.3s; }
.register-link a:hover { color:var(--g6); }
.register-link a:hover::after { width:100%; }

@media(max-width:700px) { .login-left{display:none;} .login-right{padding:36px 24px;} nav{padding:16px 20px;} }
</style>
</head>
<body>

<div class="bg-layer"></div>
<div class="bc1 bg-circle"></div>
<div class="bc2 bg-circle"></div>
<svg class="leaf" style="width:16px;left:6%;animation-duration:16s;animation-delay:0s;" viewBox="0 0 24 24" fill="rgba(165,214,167,0.5)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
<svg class="leaf" style="width:12px;left:20%;animation-duration:20s;animation-delay:5s;" viewBox="0 0 24 24" fill="rgba(102,187,106,0.4)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
<svg class="leaf" style="width:20px;left:3%;animation-duration:24s;animation-delay:10s;" viewBox="0 0 24 24" fill="rgba(165,214,167,0.3)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>

<nav>
  <a href="../../index.php" class="nav-logo">ANI<span>TRACK</span></a>
  <div style="display:flex;gap:10px;">
    <a href="login.php"    class="nav-btn outline">Sign In</a>
    <a href="register.php" class="nav-btn solid">Get Started</a>
  </div>
</nav>

<div class="main">
  <div class="login-wrap">

    <div class="login-left">
      <div class="left-brand">
        <div class="brand-name">ANI<span>TRACK</span></div>
        <div class="brand-sub">Farm Sales Tracker</div>
      </div>
      <div class="left-body">
        <div class="left-eyebrow">🌾 For Filipino Farmers</div>
        <h2 class="left-title">Welcome<br><em>Back.</em></h2>
        <p class="left-desc">Sign in to access your dashboard, track your sales, and manage your inventory.</p>
        <div class="feat-list">
          <div class="feat-item"><div class="feat-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg></div>Real-time sales tracking</div>
          <div class="feat-item"><div class="feat-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg></div>Inventory management</div>
          <div class="feat-item"><div class="feat-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg></div>Customer records</div>
          <div class="feat-item"><div class="feat-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg></div>Low stock alerts</div>
        </div>
      </div>
      <div class="left-footer">Track Your Harvest, Grow Your Business</div>
    </div>

    <div class="login-right">
      <div class="form-eyebrow">Welcome Back</div>
      <h1 class="form-title">Sign In to<br><em>ANI-TRACK</em></h1>
      <p class="form-sub">Enter your credentials to continue.</p>

      <?php if ($error): ?>
      <div class="alert-box <?php echo $error_type; ?>">
        <?php if ($error_type === 'pending'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="on" id="loginForm" novalidate>
        <div class="form-field">
          <label>Username or Email</label>
          <div class="input-wrap">
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" name="username" id="usernameField" placeholder="Enter username or email" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" autocomplete="username"/>
          </div>
          <span class="err-msg" id="usernameErr"></span>
        </div>
        <div class="form-field">
          <label>Password</label>
          <div class="input-wrap">
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password" id="pwField" placeholder="Enter your password" autocomplete="current-password"/>
            <button type="button" class="toggle-pw" onclick="togglePw()">
              <svg id="eyeShow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eyeHide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.9 18.9 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
            </button>
          </div>
          <span class="err-msg" id="passwordErr"></span>
        </div>
        <button type="submit" class="btn-login">SIGN IN</button>
      </form>

      <div class="divider">or</div>
      <div class="register-link">Don't have an account? <a href="register.php">Create one free</a></div>
    </div>

  </div>
</div>

<script>
function togglePw() {
  const f = document.getElementById('pwField');
  const show = f.type === 'password';
  f.type = show ? 'text' : 'password';
  document.getElementById('eyeShow').style.display = show ? 'none' : 'block';
  document.getElementById('eyeHide').style.display = show ? 'block' : 'none';
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  const username = document.getElementById('usernameField');
  const password = document.getElementById('pwField');
  const usernameErr = document.getElementById('usernameErr');
  const passwordErr = document.getElementById('passwordErr');
  let valid = true;
  if (!username.value.trim()) {
    usernameErr.textContent = 'Username or email is required';
    username.style.borderColor = 'rgba(229,57,53,0.5)';
    valid = false;
  } else {
    usernameErr.textContent = '';
    username.style.borderColor = '';
  }
  if (!password.value.trim()) {
    passwordErr.textContent = 'Password is required';
    password.style.borderColor = 'rgba(229,57,53,0.5)';
    valid = false;
  } else {
    passwordErr.textContent = '';
    password.style.borderColor = '';
  }
  if (!valid) e.preventDefault();
});

['usernameField','pwField'].forEach(id => {
  document.getElementById(id).addEventListener('input', function() {
    const errId = id === 'usernameField' ? 'usernameErr' : 'passwordErr';
    document.getElementById(errId).textContent = '';
    this.style.borderColor = '';
  });
});
</script>
</body>
</html>