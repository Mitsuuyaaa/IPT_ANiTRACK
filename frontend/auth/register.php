<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $t = $_SESSION['user_type'] ?? '';
    if ($t === 'admin')  { header("Location: ../admin/dashboard.php");   exit; }
    if ($t === 'Vendor') { header("Location: ../pages/marketplace.php"); exit; }
    header("Location: ../pages/dashboard.php"); exit;
}

$host = "localhost"; $db = "anitrack"; $user = "root"; $pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$errors = []; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['first_name']  ?? '');
    $lastname  = trim($_POST['last_name']   ?? '');
    $username  = trim($_POST['username']    ?? '');
    $email     = trim($_POST['email']       ?? '');
    $phone     = trim($_POST['phone']       ?? '');
    $type      = trim($_POST['user_type']   ?? '');
    $password  = $_POST['password']         ?? '';
    $confirm   = $_POST['confirm']          ?? '';
    $terms     = isset($_POST['terms']);

    if (empty($firstname))  { $errors['first_name'] = "First name is required"; }
    if (empty($lastname))   { $errors['last_name']  = "Last name is required"; }
    if (empty($username))   { $errors['username']   = "Username is required"; }
    elseif (strlen($username) < 3) { $errors['username'] = "At least 3 characters"; }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = "Valid email is required"; }
    if (empty($phone))      { $errors['phone']      = "Phone number is required"; }
    if (empty($type))       { $errors['user_type']  = "Please select a type"; }
    if (empty($password))   { $errors['password']   = "Password is required"; }
    elseif (strlen($password) < 6) { $errors['password'] = "At least 6 characters"; }
    if ($password !== $confirm) { $errors['confirm'] = "Passwords do not match"; }
    if (!$terms)            { $errors['terms']      = "You must agree to the terms"; }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['username'] = "Username or email already exists";
        } else {
            $stmt->close();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // status = 'pending' — admin must approve before user can log in
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, phone, user_type, password, status) VALUES (?,?,?,?,?,?,?,'pending')");
            $stmt->bind_param("sssssss", $firstname, $lastname, $username, $email, $phone, $type, $hashed);
            if ($stmt->execute()) { $success = true; }
            else { $errors['general'] = "Registration failed. Please try again."; }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>ANI-TRACK | Sign Up</title>
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
  display: flex;
  flex-direction: column;
  align-items: stretch;
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
  flex-shrink: 0;
}
.nav-logo { font-family:'Poppins',sans-serif; font-size:22px; font-weight:800; color:#fff; letter-spacing:2px; text-decoration:none; }
.nav-logo span { color: var(--g6); }
.nav-btn { padding:10px 26px; border-radius:50px; font-family:'Poppins',sans-serif; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.25s; letter-spacing:0.5px; }
.nav-btn.outline { background:transparent; border:1.5px solid rgba(255,255,255,0.35); color:rgba(255,255,255,0.85); }
.nav-btn.outline:hover { border-color:var(--g6); color:var(--g6); background:rgba(165,214,167,0.08); }
.nav-btn.solid { background:var(--g4); border:1.5px solid var(--g4); color:#fff; box-shadow:0 4px 20px rgba(67,160,71,0.4); }
.nav-btn.solid:hover { background:var(--g5); border-color:var(--g5); transform:translateY(-1px); }

.page-body {
  position: relative; z-index: 1;
  padding: 40px 24px 60px;
  display: flex; justify-content: center;
}

.register-wrap {
  display: flex; width: 920px; max-width: 98vw;
  border-radius: 24px; overflow: hidden;
  border: 1px solid rgba(165,214,167,0.1);
  box-shadow: 0 30px 80px rgba(0,0,0,0.5);
  animation: fadeUp 0.7s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(28px);} to{opacity:1;transform:translateY(0);} }

.reg-left {
  width: 300px; flex-shrink: 0;
  background: linear-gradient(160deg, #1b3a1f 0%, #0d2211 100%);
  border-right: 1px solid rgba(165,214,167,0.08);
  padding: 50px 32px;
  display: flex; flex-direction: column; justify-content: space-between;
  position: relative; overflow: hidden;
}
.reg-left::before { content:''; position:absolute; width:260px; height:260px; border-radius:50%; background:radial-gradient(circle,rgba(67,160,71,0.12) 0%,transparent 70%); top:-70px; right:-70px; }
.reg-left::after  { content:''; position:absolute; width:180px; height:180px; border-radius:50%; background:radial-gradient(circle,rgba(46,125,50,0.08) 0%,transparent 70%); bottom:-50px; left:-30px; }

.left-brand { z-index:1; }
.brand-name { font-family:'Poppins',sans-serif; font-size:20px; font-weight:800; color:#fff; letter-spacing:2px; }
.brand-name span { color: var(--g6); }
.brand-sub { font-size:10px; font-weight:600; letter-spacing:2px; color:rgba(255,255,255,0.3); text-transform:uppercase; margin-top:3px; }

.left-body { z-index:1; }
.left-eyebrow { display:inline-flex; align-items:center; gap:6px; background:rgba(165,214,167,0.08); border:1px solid rgba(165,214,167,0.2); border-radius:50px; padding:5px 12px; font-size:10px; font-weight:600; color:var(--g6); letter-spacing:1.5px; text-transform:uppercase; margin-bottom:16px; }
.left-eyebrow::before { content:''; width:5px; height:5px; border-radius:50%; background:var(--g5); animation:pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:0.5;transform:scale(1.4);} }

.left-title { font-family:'Playfair Display',serif; font-size:26px; font-weight:900; line-height:1.2; color:#fff; margin-bottom:12px; }
.left-title em { font-style:italic; color:var(--g5); }
.left-desc { font-size:12px; color:rgba(255,255,255,0.45); line-height:1.8; margin-bottom:22px; }

.steps-list { display:flex; flex-direction:column; gap:14px; }
.step-item { display:flex; align-items:flex-start; gap:12px; }
.step-num { width:24px; height:24px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,var(--g4),var(--g3)); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; margin-top:1px; }
.step-text strong { display:block; font-size:12px; color:#fff; font-weight:600; }
.step-text span { font-size:11px; color:rgba(255,255,255,0.4); }

.left-footer { z-index:1; font-size:11px; color:rgba(255,255,255,0.2); font-style:italic; }

.reg-right {
  flex: 1;
  background: linear-gradient(145deg, #0f1f12, #162c1a);
  padding: 44px 44px 50px;
}

.form-eyebrow { font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:var(--g5); margin-bottom:8px; }
.form-title { font-family:'Playfair Display',serif; font-size:26px; font-weight:900; color:#fff; margin-bottom:4px; line-height:1.2; }
.form-title em { font-style:italic; color:var(--g5); }
.form-sub { font-size:13px; color:rgba(255,255,255,0.35); margin-bottom:28px; }

.alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:18px; display:flex; align-items:flex-start; gap:8px; }
.alert svg { width:16px; height:16px; flex-shrink:0; margin-top:1px; }
.alert.error   { background:rgba(229,57,53,0.1);  border:1px solid rgba(229,57,53,0.25);  color:#ef9a9a; }
.alert.success { background:rgba(67,160,71,0.1);  border:1px solid rgba(67,160,71,0.25);  color:var(--g6); }
.alert.pending { background:rgba(251,140,0,0.1);  border:1px solid rgba(251,140,0,0.3);   color:#ffcc80; }
.alert.success a { color:var(--g5); font-weight:700; }

/* Success screen */
.success-screen { text-align:center; padding:20px 10px; }
.success-screen .s-icon { font-size:56px; margin-bottom:16px; }
.success-screen h2 { font-family:'Playfair Display',serif; font-size:24px; font-weight:900; color:#fff; margin-bottom:10px; }
.success-screen p { font-size:13px; color:rgba(255,255,255,0.5); line-height:1.8; margin-bottom:20px; }
.success-screen .pending-pill {
  display:inline-flex; align-items:center; gap:8px;
  background:rgba(251,140,0,0.1); border:1px solid rgba(251,140,0,0.3);
  color:#ffcc80; padding:8px 20px; border-radius:50px;
  font-size:12px; font-weight:600; margin-bottom:24px;
}
.success-screen .pending-pill::before { content:''; width:7px; height:7px; border-radius:50%; background:#fb8c00; animation:pulse 2s ease-in-out infinite; }
.btn-back {
  display:inline-block; padding:12px 32px;
  background:linear-gradient(135deg,var(--g5),var(--g3));
  color:#fff; border-radius:10px; font-family:'Poppins',sans-serif;
  font-size:13px; font-weight:700; text-decoration:none;
  box-shadow:0 6px 24px rgba(67,160,71,0.35);
  transition:all 0.25s;
}
.btn-back:hover { transform:translateY(-2px); box-shadow:0 10px 32px rgba(67,160,71,0.5); }

.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-field { margin-bottom:14px; }
.form-field label { display:block; font-size:10px; font-weight:600; color:rgba(255,255,255,0.4); letter-spacing:1px; text-transform:uppercase; margin-bottom:7px; }

.input-wrap { position:relative; }
.input-wrap svg.ico { position:absolute; left:13px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:rgba(255,255,255,0.2); pointer-events:none; }
.input-wrap input,
.input-wrap select {
  width:100%; padding:12px 13px 12px 38px;
  background:rgba(255,255,255,0.05); border:1.5px solid rgba(255,255,255,0.09);
  border-radius:10px; font-size:13px; font-family:'Poppins',sans-serif; color:#fff; outline:none;
  transition:border-color 0.2s,background 0.2s,box-shadow 0.2s;
  appearance:none;
}
.input-wrap input::placeholder { color:rgba(255,255,255,0.18); }
.input-wrap select option { background:#1b3a1f; color:#fff; }
.input-wrap input:focus,
.input-wrap select:focus { border-color:var(--g4); background:rgba(67,160,71,0.07); box-shadow:0 0 0 4px rgba(67,160,71,0.12); }
.input-wrap input:hover:not(:focus),
.input-wrap select:hover:not(:focus) { border-color:rgba(255,255,255,0.18); }
.input-wrap input.err-field,
.input-wrap select.err-field { border-color:rgba(229,57,53,0.5); }

.pw-wrap { position:relative; }
.pw-wrap input { padding-right:44px; }
.toggle-pw { position:absolute; right:11px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:rgba(255,255,255,0.22); padding:4px; border-radius:6px; transition:color 0.2s; }
.toggle-pw:hover { color:var(--g5); }
.toggle-pw svg { width:15px; height:15px; }

.err-msg { font-size:11px; color:#f48fb1; margin-top:5px; display:block; min-height:14px; }

.terms-row { display:flex; align-items:flex-start; gap:10px; margin-bottom:16px; cursor:pointer; }
.terms-row input[type="checkbox"] { display:none; }
.check-box { width:18px; height:18px; border:1.5px solid rgba(255,255,255,0.2); border-radius:5px; flex-shrink:0; display:flex; align-items:center; justify-content:center; margin-top:2px; transition:all 0.2s; background:rgba(255,255,255,0.04); }
.terms-row input:checked + .check-box { background:var(--g4); border-color:var(--g4); }
.terms-row input:checked + .check-box::after { content:''; display:block; width:10px; height:6px; border-left:2px solid #fff; border-bottom:2px solid #fff; transform:rotate(-45deg) translateY(-1px); }
.terms-label { font-size:13px; color:rgba(255,255,255,0.5); line-height:1.5; }
.terms-label a { color:var(--g5); font-weight:600; text-decoration:none; }
.terms-label a:hover { color:var(--g6); }

.btn-register {
  width:100%; padding:14px;
  background:linear-gradient(135deg,var(--g5),var(--g3));
  color:#fff; border:none; border-radius:10px;
  font-family:'Poppins',sans-serif; font-size:14px; font-weight:700; letter-spacing:1px;
  cursor:pointer; box-shadow:0 6px 24px rgba(67,160,71,0.35);
  transition:all 0.25s; position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:center; gap:10px;
  margin-top:4px;
}
.btn-register::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,0.12),transparent); opacity:0; transition:opacity 0.25s; }
.btn-register:hover { transform:translateY(-2px); box-shadow:0 10px 32px rgba(67,160,71,0.5); }
.btn-register:hover::before { opacity:1; }
.btn-register:active { transform:translateY(0); }
.btn-register:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
.spinner { display:none; width:16px; height:16px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.7s linear infinite; }
@keyframes spin { to{transform:rotate(360deg);} }

.signin-text { text-align:center; margin-top:16px; font-size:13px; color:rgba(255,255,255,0.35); }
.signin-text a { color:var(--g5); font-weight:700; text-decoration:none; }
.signin-text a:hover { color:var(--g6); }

@media(max-width:760px) { .reg-left{display:none;} .reg-right{padding:36px 22px;} nav{padding:16px 20px;} .form-row{grid-template-columns:1fr;} }
</style>
</head>
<body>

<div class="bg-layer"></div>
<div class="bc1 bg-circle"></div>
<div class="bc2 bg-circle"></div>
<svg class="leaf" style="width:16px;left:6%;animation-duration:16s;animation-delay:0s;" viewBox="0 0 24 24" fill="rgba(165,214,167,0.5)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
<svg class="leaf" style="width:13px;left:18%;animation-duration:21s;animation-delay:5s;" viewBox="0 0 24 24" fill="rgba(102,187,106,0.4)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>
<svg class="leaf" style="width:20px;left:4%;animation-duration:25s;animation-delay:11s;" viewBox="0 0 24 24" fill="rgba(165,214,167,0.25)"><path d="M17 8C8 10 5.9 16.17 3.82 21.34L5.71 22l1-2.3A4.49 4.49 0 0 0 8 20C19 20 22 3 22 3c-1 2-8 1-8 1 6-5 10.67 2 10.67 2C22.96 7.2 17 8 17 8z"/></svg>

<nav>
  <a href="../../index.php" class="nav-logo">ANI<span>TRACK</span></a>
  <div style="display:flex;gap:10px;">
    <a href="login.php"    class="nav-btn outline">Sign In</a>
    <a href="register.php" class="nav-btn solid">Get Started</a>
  </div>
</nav>

<div class="page-body">
  <div class="register-wrap">

    <!-- Left -->
    <div class="reg-left">
      <div class="left-brand">
        <div class="brand-name">ANI<span>TRACK</span></div>
        <div class="brand-sub">Farm Sales Tracker</div>
      </div>
      <div class="left-body">
        <div class="left-eyebrow">🌾 Get Started</div>
        <h2 class="left-title">Join<br><em>ANI-TRACK</em><br>Today.</h2>
        <p class="left-desc">Create your free account and start managing your farm sales digitally in minutes.</p>
        <div class="steps-list">
          <div class="step-item">
            <div class="step-num">1</div>
            <div class="step-text"><strong>Create your account</strong><span>Fill in the form on the right</span></div>
          </div>
          <div class="step-item">
            <div class="step-num">2</div>
            <div class="step-text"><strong>Wait for approval</strong><span>Admin verifies your account</span></div>
          </div>
          <div class="step-item">
            <div class="step-num">3</div>
            <div class="step-text"><strong>Start using ANI-TRACK</strong><span>Track sales & manage inventory</span></div>
          </div>
        </div>
      </div>
      <div class="left-footer">Track Your Harvest, Grow Your Business</div>
    </div>

    <!-- Right -->
    <div class="reg-right">
      <div class="form-eyebrow">Create Account</div>
      <h1 class="form-title">Sign Up for<br><em>ANI-TRACK</em></h1>
      <p class="form-sub">Fill in your details below to get started — it's free.</p>

      <?php if (!empty($errors['general'])): ?>
      <div class="alert error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php echo htmlspecialchars($errors['general']); ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <!-- ── SUCCESS / PENDING SCREEN ── -->
      <div class="success-screen">
        <div class="s-icon">🌾</div>
        <h2>Account Submitted!</h2>
        <p>Your account has been created and is now <strong style="color:#ffcc80;">waiting for admin approval</strong>.<br>You will be able to log in once your account is verified.</p>
        <div class="pending-pill">⏳ Pending Approval</div><br/>
        <a href="login.php" class="btn-back">← Back to Sign In</a>
      </div>

      <?php else: ?>
      <!-- ── REGISTRATION FORM ── -->
      <form id="regForm" method="POST" action="register.php">

        <div class="form-row">
          <div class="form-field">
            <label>First Name</label>
            <div class="input-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" name="first_name" id="first_name" placeholder="First name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" class="<?php echo isset($errors['first_name'])?'err-field':''; ?>"/>
            </div>
            <span class="err-msg" id="firstnameErr"><?php echo $errors['first_name'] ?? ''; ?></span>
          </div>
          <div class="form-field">
            <label>Last Name</label>
            <div class="input-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" name="last_name" id="last_name" placeholder="Last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" class="<?php echo isset($errors['last_name'])?'err-field':''; ?>"/>
            </div>
            <span class="err-msg" id="lastnameErr"><?php echo $errors['last_name'] ?? ''; ?></span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label>Username</label>
            <div class="input-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
              <input type="text" name="username" id="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" class="<?php echo isset($errors['username'])?'err-field':''; ?>"/>
            </div>
            <span class="err-msg" id="usernameErr"><?php echo $errors['username'] ?? ''; ?></span>
          </div>
          <div class="form-field">
            <label>Email</label>
            <div class="input-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <input type="email" name="email" id="email" placeholder="your@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" class="<?php echo isset($errors['email'])?'err-field':''; ?>"/>
            </div>
            <span class="err-msg" id="emailErr"><?php echo $errors['email'] ?? ''; ?></span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label>Phone</label>
            <div class="input-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.15 6.15l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              <input type="text" name="phone" id="phone" placeholder="09X XXX XXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" class="<?php echo isset($errors['phone'])?'err-field':''; ?>"/>
            </div>
            <span class="err-msg" id="phoneErr"><?php echo $errors['phone'] ?? ''; ?></span>
          </div>
          <div class="form-field">
            <label>Account Type</label>
            <div class="input-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <select name="user_type" id="user_type" class="<?php echo isset($errors['user_type'])?'err-field':''; ?>">
                <option value="" disabled <?php echo empty($_POST['user_type'])?'selected':''; ?>>Farmer or Vendor</option>
                <option value="Farmer" <?php echo (($_POST['user_type']??'')==='Farmer')?'selected':''; ?>>🌾 Farmer</option>
                <option value="Vendor" <?php echo (($_POST['user_type']??'')==='Vendor')?'selected':''; ?>>🛒 Vendor</option>
              </select>
            </div>
            <span class="err-msg" id="typeErr"><?php echo $errors['user_type'] ?? ''; ?></span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label>Password</label>
            <div class="input-wrap pw-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="password" id="password" placeholder="Min. 6 characters" class="<?php echo isset($errors['password'])?'err-field':''; ?>"/>
              <button type="button" class="toggle-pw" onclick="togglePw('password','e1s','e1h')">
                <svg id="e1s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="e1h" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.9 18.9 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <span class="err-msg" id="passwordErr"><?php echo $errors['password'] ?? ''; ?></span>
          </div>
          <div class="form-field">
            <label>Confirm Password</label>
            <div class="input-wrap pw-wrap">
              <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="confirm" id="confirm" placeholder="Repeat password" class="<?php echo isset($errors['confirm'])?'err-field':''; ?>"/>
              <button type="button" class="toggle-pw" onclick="togglePw('confirm','e2s','e2h')">
                <svg id="e2s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="e2h" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.9 18.9 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <span class="err-msg" id="confirmErr"><?php echo $errors['confirm'] ?? ''; ?></span>
          </div>
        </div>

        <label class="terms-row">
          <input type="checkbox" name="terms" id="terms" <?php echo isset($_POST['terms'])?'checked':''; ?>>
          <span class="check-box"></span>
          <span class="terms-label">I agree to the <a href="#">Terms and Privacy Policy</a></span>
        </label>
        <span class="err-msg" id="termsErr" style="margin-bottom:10px;display:block;"><?php echo $errors['terms'] ?? ''; ?></span>

        <button type="submit" class="btn-register" id="regBtn">
          <span id="btnText">CREATE ACCOUNT</span>
          <span id="btnSpinner" class="spinner"></span>
        </button>
      </form>
      <?php endif; ?>

      <p class="signin-text">Already have an account? <a href="login.php">Sign in</a></p>
    </div>

  </div>
</div>

<script>
function togglePw(inputId, showId, hideId) {
  const f = document.getElementById(inputId);
  const show = f.type === 'password';
  f.type = show ? 'text' : 'password';
  document.getElementById(showId).style.display = show ? 'none' : 'block';
  document.getElementById(hideId).style.display = show ? 'block' : 'none';
}

const form = document.getElementById('regForm');
if (form) {
  form.addEventListener('submit', function(e) {
    let valid = true;
    const fields = [
      ['first_name','firstnameErr','First name is required'],
      ['last_name','lastnameErr','Last name is required'],
      ['username','usernameErr','Username is required'],
      ['email','emailErr','Valid email is required'],
      ['phone','phoneErr','Phone is required'],
      ['password','passwordErr','Password is required'],
      ['confirm','confirmErr','Please confirm password'],
    ];
    fields.forEach(([id, errId, msg]) => {
      const el = document.getElementById(id);
      if (!el.value.trim()) {
        document.getElementById(errId).textContent = msg;
        el.classList.add('err-field');
        valid = false;
      } else {
        document.getElementById(errId).textContent = '';
        el.classList.remove('err-field');
      }
    });
    const pw = document.getElementById('password').value;
    const cf = document.getElementById('confirm').value;
    if (pw && cf && pw !== cf) {
      document.getElementById('confirmErr').textContent = 'Passwords do not match';
      document.getElementById('confirm').classList.add('err-field');
      valid = false;
    }
    const type = document.getElementById('user_type').value;
    if (!type) {
      document.getElementById('typeErr').textContent = 'Please select an account type';
      document.getElementById('user_type').classList.add('err-field');
      valid = false;
    } else {
      document.getElementById('typeErr').textContent = '';
      document.getElementById('user_type').classList.remove('err-field');
    }
    if (!document.getElementById('terms').checked) {
      document.getElementById('termsErr').textContent = 'You must agree to the terms';
      valid = false;
    } else {
      document.getElementById('termsErr').textContent = '';
    }
    if (!valid) { e.preventDefault(); return; }
    document.getElementById('btnText').style.display = 'none';
    document.getElementById('btnSpinner').style.display = 'inline-block';
    document.getElementById('regBtn').disabled = true;
  });
}
</script>
</body>
</html>