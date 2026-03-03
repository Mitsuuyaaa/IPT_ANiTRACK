<?php
session_start();
$host = "localhost";
$db   = "anitrack";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$errors = [];
$success = false;
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
    if (empty($username))   { $errors['username']  = "Username is required"; }
    elseif (strlen($username) < 3) { $errors['username'] = "At least 3 characters"; }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = "Valid email is required"; }
    if (empty($phone))      { $errors['phone']     = "Phone number is required"; }
    if (empty($type))       { $errors['user_type'] = "Please select a type"; }
    if (empty($password))   { $errors['password']  = "Password is required"; }
    elseif (strlen($password) < 6) { $errors['password'] = "At least 6 characters"; }
    if ($password !== $confirm) { $errors['confirm'] = "Passwords do not match"; }
    if (!$terms)            { $errors['terms']     = "You must agree to the terms"; }
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['username'] = "Username or email already exists";
        } else {
            $stmt->close();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, phone, user_type, password) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssss", $firstname, $lastname, $username, $email, $phone, $type, $hashed);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors['general'] = "Registration failed. Please try again.";
            }
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Poppins', sans-serif; background: #c8e6c9; min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
body::before { content: ''; position: fixed; width: 420px; height: 420px; background: #a5d6a7; border-radius: 50%; top: -120px; left: -120px; z-index: 0; }
body::after { content: ''; position: fixed; width: 320px; height: 320px; background: #a5d6a7; border-radius: 50%; bottom: -80px; right: -80px; z-index: 0; }
.blob { position: fixed; border-radius: 50%; background: #81c784; z-index: 0; }
.blob-1 { width: 180px; height: 180px; top: 55%; left: -60px; }
.blob-2 { width: 220px; height: 220px; top: -60px; right: 80px; }
.blob-3 { width: 140px; height: 140px; bottom: 60px; right: 200px; opacity: 0.5; }
a { text-decoration: none; }
.page { position: relative; z-index: 1; padding: 20px; width: 100%; display: flex; justify-content: center; }
.card { display: flex; width: 820px; max-width: 95vw; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.12); }

.left { width: 280px; flex-shrink: 0; background: linear-gradient(160deg, #1b3a1f, #2e7d32, #43a047); padding: 36px 28px; display: flex; flex-direction: column; align-items: center; justify-content: space-between; position: relative; overflow: hidden; }
.left::before { content: ''; position: absolute; top: -60px; right: -60px; width: 180px; height: 180px; background: rgba(255,255,255,0.05); border-radius: 50%; }
.left::after { content: ''; position: absolute; bottom: -70px; left: -40px; width: 200px; height: 200px; background: rgba(255,255,255,0.04); border-radius: 50%; }

.badge { align-self: flex-start; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25); border-radius: 8px; padding: 5px 12px; font-size: 13px; font-weight: 800; color: #fff; z-index: 1; }
.badge span { color: #a5d6a7; }

.left-body { text-align: center; z-index: 1; }
.left-body h2 { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 12px; line-height: 1.3; }
.left-body h2 span { color: #a5d6a7; }
.left-body p { font-size: 10.5px; color: rgba(255,255,255,0.65); line-height: 1.7; margin-bottom: 20px; }
.btn-signin { padding: 9px 28px; border: 1.5px solid rgba(255,255,255,0.5); border-radius: 40px; background: transparent; color: #fff; font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 700; cursor: pointer; transition: background 0.2s; display: inline-block; }
.btn-signin:hover { background: rgba(255,255,255,0.12); }

.tagline { font-size: 9px; color: rgba(255,255,255,0.3); font-style: italic; z-index: 1; }

.right { flex: 1; background: #fff; padding: 32px 36px; overflow-y: auto; }

.form-title { font-size: 22px; font-weight: 800; color: #43a047; margin-bottom: 2px; }
.form-sub { font-size: 12px; color: #aaa; margin-bottom: 22px; }

.row { display: flex; gap: 14px; }
.row .field { flex: 1; }

.field { margin-bottom: 13px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px; }
.field input, .field select {
  width: 100%; padding: 9px 12px;
  border: 1.5px solid #cde8ce; border-radius: 7px;
  font-size: 13px; font-family: 'Poppins', sans-serif;
  background: #f7fbf7; outline: none; color: #333;
  transition: border-color 0.2s, box-shadow 0.2s;
  appearance: none;
}
.field input:focus, .field select:focus { border-color: #43a047; background: #fff; box-shadow: 0 0 0 3px rgba(67,160,71,0.1); }
.field input.error, .field select.error { border-color: #e53935; }
.field input.valid, .field select.valid { border-color: #66bb6a; }

.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 38px; }
.pw-wrap button { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #bbb; display: flex; }
.pw-wrap button:hover { color: #43a047; }
.pw-wrap button svg { width: 15px; height: 15px; }

.err { font-size: 11px; color: #e53935; min-height: 13px; display: block; margin-top: 3px; }

.terms-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
.terms-row input { display: none; }
.terms-box { width: 14px; height: 14px; border: 1.5px solid #ccc; border-radius: 3px; background: #f7fbf7; flex-shrink: 0; display: flex; align-items: center; justify-content: center; margin-top: 1px; cursor: pointer; transition: background 0.2s, border-color 0.2s; }
.terms-row input:checked + .terms-box { background: #43a047; border-color: #43a047; }
.terms-row input:checked + .terms-box::after { content: ''; display: block; width: 8px; height: 4px; border-left: 2px solid #fff; border-bottom: 2px solid #fff; transform: rotate(-45deg) translateY(-1px); }
.terms-label { font-size: 12px; color: #666; cursor: pointer; line-height: 1.5; }
.terms-label a { color: #43a047; font-weight: 600; }

.btn-register { width: 100%; padding: 11px; background: linear-gradient(135deg, #66bb6a, #2e7d32); color: #fff; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(67,160,71,0.35); transition: opacity 0.2s, transform 0.15s; }
.btn-register:hover { opacity: 0.92; transform: translateY(-1px); }
.btn-register:active { transform: scale(0.98); }
.btn-register:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.spinner { display: none; width: 15px; height: 15px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.signin-text { text-align: center; margin-top: 12px; font-size: 12px; color: #aaa; }
.signin-text a { color: #43a047; font-weight: 700; }
.signin-text a:hover { color: #1b3a1f; }

.success-box { background: #e8f5e9; border: 1.5px solid #66bb6a; border-radius: 10px; padding: 18px 20px; text-align: center; margin-bottom: 18px; }
.success-box h3 { font-size: 15px; font-weight: 700; color: #2e7d32; margin-bottom: 4px; }
.success-box p { font-size: 12px; color: #555; }
.success-box a { color: #43a047; font-weight: 700; }

.err-general { background: #ffebee; border: 1.5px solid #e53935; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #c62828; margin-bottom: 14px; }

@keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
.shake { animation: shake 0.4s ease !important; }

@media (max-width: 620px) { .left { display: none; } .right { padding: 28px 20px; } .row { flex-direction: column; gap: 0; } }
</style>
</head>
<body>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<div class="page">
  <div class="card">

    <div class="left">
      <div class="badge">ANI<span>TRACK</span></div>
      <div class="left-body">
        <h2>Welcome to <span>ANI-TRACK</span></h2>
        <p>Transform the way you manage your farm sales. ANI-TRACK helps Filipino farmers and market vendors digitally record and track their daily transactions with ease. No more lost notebooks, calculation errors, or unclear income reports. Join thousands of farmers who have modernized their sales management.</p>
        <a href="login.php" class="btn-signin">SIGN IN</a>
      </div>
      <p class="tagline">Track Your Harvest, Grow Your Business</p>
    </div>

    <div class="right">
      <h2 class="form-title">Sign Up</h2>
      <p class="form-sub">Create your account to get started.</p>

      <?php if ($success): ?>
      <div class="success-box">
        <h3>🎉 Registration Successful!</h3>
        <p>Your account has been created. <a href="login.php">Click here to login</a></p>
      </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
      <div class="err-general"><?php echo $errors['general']; ?></div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form id="registerForm" method="POST" action="register.php">
        <div class="row">
          <div class="field">
            <label>First Name</label>
            <input type="text" name="first_name" id="first_name" placeholder="First Name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"/>
            <span class="err" id="firstnameErr"><?php echo $errors['first_name'] ?? ''; ?></span>
          </div>
          <div class="field">
            <label>Last Name</label>
            <input type="text" name="last_name" id="last_name" placeholder="Last Name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"/>
            <span class="err" id="lastnameErr"><?php echo $errors['last_name'] ?? ''; ?></span>
          </div>
        </div>

        <div class="field">
          <label>Username</label>
          <input type="text" name="username" id="username" placeholder="Create Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"/>
          <span class="err" id="usernameErr"><?php echo $errors['username'] ?? ''; ?></span>
        </div>

        <div class="field">
          <label>Email</label>
          <input type="email" name="email" id="email" placeholder="your.email@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"/>
          <span class="err" id="emailErr"><?php echo $errors['email'] ?? ''; ?></span>
        </div>

        <div class="row">
          <div class="field">
            <label>Phone</label>
            <input type="text" name="phone" id="phone" placeholder="09X XXX XXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"/>
            <span class="err" id="phoneErr"><?php echo $errors['phone'] ?? ''; ?></span>
          </div>
          <div class="field">
            <label>Type *</label>
            <select name="user_type" id="user_type">
              <option value="" disabled <?php echo empty($_POST['user_type']) ? 'selected' : ''; ?>>Vendor or Farmer</option>
              <option value="Farmer" <?php echo (($_POST['user_type'] ?? '') === 'Farmer') ? 'selected' : ''; ?>>Farmer</option>
              <option value="Vendor" <?php echo (($_POST['user_type'] ?? '') === 'Vendor') ? 'selected' : ''; ?>>Vendor</option>
            </select>
            <span class="err" id="typeErr"><?php echo $errors['user_type'] ?? ''; ?></span>
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Password</label>
            <div class="pw-wrap">
              <input type="password" name="password" id="password" placeholder="Enter Password"/>
              <button type="button" id="togglePw1">
                <svg id="eye1Show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="eye1Hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.9 18.9 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <span class="err" id="passwordErr"><?php echo $errors['password'] ?? ''; ?></span>
          </div>
          <div class="field">
            <label>Confirm Password</label>
            <div class="pw-wrap">
              <input type="password" name="confirm" id="confirm" placeholder="Confirm Password"/>
              <button type="button" id="togglePw2">
                <svg id="eye2Show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="eye2Hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.9 18.9 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
            <span class="err" id="confirmErr"><?php echo $errors['confirm'] ?? ''; ?></span>
          </div>
        </div>

        <label class="terms-row">
          <input type="checkbox" name="terms" id="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>/>
          <span class="terms-box"></span>
          <span class="terms-label">I agree to the <a href="#">Terms and Privacy Policy</a></span>
        </label>
        <span class="err" id="termsErr" style="margin-bottom:10px;"><?php echo $errors['terms'] ?? ''; ?></span>

        <button type="submit" class="btn-register" id="registerBtn">
          <span id="btnText">SIGN UP</span>
          <span id="btnSpinner" class="spinner"></span>
        </button>
      </form>
      <?php endif; ?>

      <p class="signin-text">Already have an account? <a href="login.php">Sign in</a></p>
    </div>

  </div>
</div>

<script>
'use strict';
const form       = document.getElementById('registerForm');
const firstnameI = document.getElementById('first_name');
const lastnameI  = document.getElementById('last_name');
const usernameI  = document.getElementById('username');
const emailI     = document.getElementById('email');
const phoneI     = document.getElementById('phone');
const typeI      = document.getElementById('user_type');
const passwordI  = document.getElementById('password');
const confirmI   = document.getElementById('confirm');
const termsI     = document.getElementById('terms');
const registerBtn = document.getElementById('registerBtn');
const btnText    = document.getElementById('btnText');
const btnSpinner = document.getElementById('btnSpinner');

function markError(input, errId, msg) {
  if (!input) return;
  input.classList.add('error'); input.classList.remove('valid');
  document.getElementById(errId).textContent = msg;
}
function markValid(input, errId) {
  if (!input) return;
  input.classList.remove('error'); input.classList.add('valid');
  document.getElementById(errId).textContent = '';
}

function v(input, errId, checks) {
  for (const [cond, msg] of checks) { if (cond) { markError(input, errId, msg); return false; } }
  markValid(input, errId); return true;
}

function toggleEye(btn, showId, hideId, inputId) {
  if (!btn) return;
  btn.addEventListener('click', () => {
    const inp = document.getElementById(inputId);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    document.getElementById(showId).style.display = show ? 'none' : 'block';
    document.getElementById(hideId).style.display = show ? 'block' : 'none';
  });
}
toggleEye(document.getElementById('togglePw1'), 'eye1Show', 'eye1Hide', 'password');
toggleEye(document.getElementById('togglePw2'), 'eye2Show', 'eye2Hide', 'confirm');

if (form) {
  form.addEventListener('submit', (e) => {
    const ok = [
      v(firstnameI, 'firstnameErr', [[!firstnameI.value.trim(), 'First name is required']]),
      v(lastnameI,  'lastnameErr',  [[!lastnameI.value.trim(),  'Last name is required']]),
      v(usernameI,  'usernameErr',  [[!usernameI.value.trim(), 'Username is required'], [usernameI.value.trim().length < 3, 'At least 3 characters']]),
      v(emailI,     'emailErr',     [[!emailI.value.trim(), 'Email is required'], [!/\S+@\S+\.\S+/.test(emailI.value), 'Enter a valid email']]),
      v(phoneI,     'phoneErr',     [[!phoneI.value.trim(), 'Phone is required']]),
      v(typeI,      'typeErr',      [[!typeI.value, 'Please select a type']]),
      v(passwordI,  'passwordErr',  [[!passwordI.value, 'Password is required'], [passwordI.value.length < 6, 'At least 6 characters']]),
      v(confirmI,   'confirmErr',   [[!confirmI.value, 'Please confirm password'], [confirmI.value !== passwordI.value, 'Passwords do not match']]),
    ];
    const termsOk = termsI.checked;
    if (!termsOk) document.getElementById('termsErr').textContent = 'You must agree to the terms.';
    else document.getElementById('termsErr').textContent = '';
    if (!ok.every(Boolean) || !termsOk) {
      e.preventDefault();
      const card = document.querySelector('.card');
      card.classList.add('shake');
      card.addEventListener('animationend', () => card.classList.remove('shake'), { once: true });
      return;
    }
    btnText.style.display = 'none';
    btnSpinner.style.display = 'inline-block';
    registerBtn.disabled = true;
  });
}
</script>
</body>
</html>