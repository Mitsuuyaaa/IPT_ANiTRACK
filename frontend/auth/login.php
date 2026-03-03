<?php
session_start();
$host = "localhost";
$db   = "anitrack";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($username)) { $errors['username'] = "Username is required"; }
    if (empty($password)) { $errors['password'] = "Password is required"; }
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                header("Location: dashboard.php");
                exit;
            } else { $errors['password'] = "Incorrect password"; }
        } else { $errors['username'] = "User not found"; }
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
.card { display: flex; width: 700px; max-width: 95vw; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.12); position: relative; }
.left { flex: 1; background: #fff; padding: 40px 36px; }
.logo { font-size: 20px; font-weight: 800; color: #1b3a1f; margin-bottom: 18px; }
.logo span { color: #43a047; }
.login-label { font-size: 15px; font-weight: 700; color: #43a047; }
.login-sub { font-size: 12px; color: #aaa; margin-bottom: 18px; }
.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px; }
.field input { width: 100%; padding: 9px 12px; border: 1.5px solid #cde8ce; border-radius: 7px; font-size: 13px; font-family: 'Poppins', sans-serif; background: #f7fbf7; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.field input:focus { border-color: #43a047; background: #fff; box-shadow: 0 0 0 3px rgba(67,160,71,0.1); }
.field input.error { border-color: #e53935; }
.field input.valid { border-color: #66bb6a; }
.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 38px; }
.pw-wrap button { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #bbb; display: flex; }
.pw-wrap button:hover { color: #43a047; }
.pw-wrap button svg { width: 15px; height: 15px; }
.err { font-size: 11px; color: #e53935; min-height: 14px; display: block; margin-top: 3px; }
.options { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.remember { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #666; cursor: pointer; }
.remember input { display: none; }
.box { width: 14px; height: 14px; border: 1.5px solid #ccc; border-radius: 3px; background: #f7fbf7; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: background 0.2s, border-color 0.2s; }
.remember input:checked + .box { background: #43a047; border-color: #43a047; }
.remember input:checked + .box::after { content: ''; display: block; width: 8px; height: 4px; border-left: 2px solid #fff; border-bottom: 2px solid #fff; transform: rotate(-45deg) translateY(-1px); }
.forgot { font-size: 12px; font-weight: 600; color: #43a047; }
.forgot:hover { color: #1b3a1f; }
.btn-login { width: 100%; padding: 11px; background: linear-gradient(135deg, #66bb6a, #2e7d32); color: #fff; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(67,160,71,0.35); transition: opacity 0.2s, transform 0.15s; }
.btn-login:hover { opacity: 0.92; transform: translateY(-1px); }
.btn-login:active { transform: scale(0.98); }
.btn-login:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.spinner { display: none; width: 15px; height: 15px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.signup-text { text-align: center; margin-top: 14px; font-size: 12px; color: #aaa; }
.signup-text a { color: #43a047; font-weight: 700; }
.signup-text a:hover { color: #1b3a1f; }
.right { width: 250px; flex-shrink: 0; background: linear-gradient(160deg, #1b3a1f, #2e7d32, #43a047); padding: 30px 24px; display: flex; flex-direction: column; align-items: center; justify-content: space-between; position: relative; overflow: hidden; }
.right::before { content: ''; position: absolute; top: -60px; right: -60px; width: 180px; height: 180px; background: rgba(255,255,255,0.05); border-radius: 50%; }
.right::after { content: ''; position: absolute; bottom: -70px; left: -40px; width: 200px; height: 200px; background: rgba(255,255,255,0.04); border-radius: 50%; }
.badge { align-self: flex-end; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 800; color: #fff; z-index: 1; }
.badge span { color: #a5d6a7; }
.right-body { text-align: center; z-index: 1; }
.right-body h2 { font-size: 15px; font-weight: 800; color: #fff; margin-bottom: 10px; line-height: 1.3; }
.right-body h2 span { color: #a5d6a7; }
.right-body p { font-size: 10.5px; color: rgba(255,255,255,0.65); line-height: 1.7; margin-bottom: 16px; }
.btn-signup { padding: 8px 24px; border: 1.5px solid rgba(255,255,255,0.45); border-radius: 40px; background: transparent; color: #fff; font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
.btn-signup:hover { background: rgba(255,255,255,0.1); }
.tagline { font-size: 9px; color: rgba(255,255,255,0.3); font-style: italic; z-index: 1; }
.toast { position: fixed; bottom: 22px; left: 50%; transform: translateX(-50%) translateY(50px); background: #1b3a1f; color: #fff; padding: 10px 22px; border-radius: 50px; font-size: 12px; opacity: 0; z-index: 100; transition: transform 0.3s ease, opacity 0.3s; pointer-events: none; white-space: nowrap; }
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
@keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
.shake { animation: shake 0.4s ease !important; }
@media (max-width: 560px) { .right { display: none; } .left { padding: 32px 22px; } }
</style>
</head>
<body>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>
<div class="page">
  <div class="card">
    <div class="left">
      <div class="logo">ANI<span>TRACK</span></div>
      <p class="login-label">Login</p>
      <p class="login-sub">Welcome back! Please login to your account.</p>
      <form id="loginForm" method="POST" action="login.php">
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" id="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"/>
          <span class="err" id="usernameErr"><?php echo $errors['username'] ?? ''; ?></span>
        </div>
        <div class="field">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="password" placeholder="Enter your password"/>
            <button type="button" id="togglePw">
              <svg id="eyeShow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eyeHide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.9 18.9 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
            </button>
          </div>
          <span class="err" id="passwordErr"><?php echo $errors['password'] ?? ''; ?></span>
        </div>
        <div class="options">
          <label class="remember">
            <input type="checkbox" id="remember"/>
            <span class="box"></span>
            Remember Me
          </label>
          <a href="#" id="forgotLink" class="forgot">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-login" id="loginBtn">
          <span id="btnText">LOGIN</span>
          <span id="btnSpinner" class="spinner"></span>
        </button>
      </form>
      <p class="signup-text">Don't have an account? <a href="register.php">Sign up</a></p>
    </div>
    <div class="right">
      <div class="badge">ANI<span>TRACK</span></div>
      <div class="right-body">
        <h2>Welcome to <span>ANI-TRACK</span></h2>
        <p>Transform the way you manage your farm sales. ANI-TRACK helps Filipino farmers and market vendors digitally record and track their daily transactions with ease. No more lost notebooks, calculation errors, or unclear income reports. Join thousands of farmers who have modernized their sales management.</p>
        <a href="register.php"><button type="button" class="btn-signup">SIGN UP</button></a>
      </div>
      <p class="tagline">Track Your Harvest, Grow Your Business</p>
    </div>
  </div>
</div>
<div class="toast" id="toast"></div>
<script>
'use strict';
const loginForm = document.getElementById('loginForm');
const usernameInput = document.getElementById('username');
const passwordInput = document.getElementById('password');
const usernameErr = document.getElementById('usernameErr');
const passwordErr = document.getElementById('passwordErr');
const togglePwBtn = document.getElementById('togglePw');
const eyeShow = document.getElementById('eyeShow');
const eyeHide = document.getElementById('eyeHide');
const loginBtn = document.getElementById('loginBtn');
const btnText = document.getElementById('btnText');
const btnSpinner = document.getElementById('btnSpinner');
const toastEl = document.getElementById('toast');
const forgotLink = document.getElementById('forgotLink');
let toastTimer = null;
let usernameTouched = false;
let passwordTouched = false;
function showToast(msg, ms = 2800) { clearTimeout(toastTimer); toastEl.textContent = msg; toastEl.classList.add('show'); toastTimer = setTimeout(() => toastEl.classList.remove('show'), ms); }
function setError(input, errEl, msg) { input.classList.add('error'); input.classList.remove('valid'); errEl.textContent = msg; }
function setValid(input, errEl) { input.classList.remove('error'); input.classList.add('valid'); errEl.textContent = ''; }
function validateUsername() {
  const val = usernameInput.value.trim();
  if (!val) { setError(usernameInput, usernameErr, 'Username is required.'); return false; }
  if (val.length < 3) { setError(usernameInput, usernameErr, 'At least 3 characters.'); return false; }
  if (/\s/.test(val)) { setError(usernameInput, usernameErr, 'No spaces allowed.'); return false; }
  setValid(usernameInput, usernameErr); return true;
}
function validatePassword() {
  const val = passwordInput.value;
  if (!val) { setError(passwordInput, passwordErr, 'Password is required.'); return false; }
  if (val.length < 6) { setError(passwordInput, passwordErr, 'At least 6 characters.'); return false; }
  setValid(passwordInput, passwordErr); return true;
}
usernameInput.addEventListener('blur', () => { usernameTouched = true; validateUsername(); });
usernameInput.addEventListener('input', () => { if (usernameTouched) validateUsername(); });
passwordInput.addEventListener('blur', () => { passwordTouched = true; validatePassword(); });
passwordInput.addEventListener('input', () => { if (passwordTouched) validatePassword(); });
togglePwBtn.addEventListener('click', () => {
  const show = passwordInput.type === 'password';
  passwordInput.type = show ? 'text' : 'password';
  eyeShow.style.display = show ? 'none' : 'block';
  eyeHide.style.display = show ? 'block' : 'none';
  passwordInput.focus();
});
loginForm.addEventListener('submit', (e) => {
  usernameTouched = true; passwordTouched = true;
  if (!validateUsername() || !validatePassword()) {
    e.preventDefault();
    const card = document.querySelector('.card');
    card.classList.add('shake');
    card.addEventListener('animationend', () => card.classList.remove('shake'), { once: true });
    return;
  }
  btnText.style.display = 'none';
  btnSpinner.style.display = 'inline-block';
  loginBtn.disabled = true;
});
forgotLink.addEventListener('click', (e) => {
  e.preventDefault();
  const u = usernameInput.value.trim();
  showToast(u ? `Reset link sent for "${u}" ✉️` : 'Enter your username first.', 3000);
  if (!u) usernameInput.focus();
});
</script>
</body>
</html>