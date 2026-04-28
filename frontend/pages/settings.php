<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed: ".$conn->connect_error); }
$uid = $_SESSION['user_id'];

$errors = []; $success = '';

// ── Handle profile photo upload ──
function handleAvatarUpload($uid, $existingPhoto='') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) return $existingPhoto;
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) return $existingPhoto;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($_FILES['avatar']['type'], $allowed)) return $existingPhoto;
    if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) return $existingPhoto;
    $uploadDir = '../../uploads/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $uid . '_' . uniqid() . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
        if ($existingPhoto && file_exists('../../' . $existingPhoto)) unlink('../../' . $existingPhoto);
        return 'uploads/avatars/' . $filename;
    }
    return $existingPhoto;
}

// ── Handle form submissions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Edit Profile
    if ($action === 'profile') {
        $first  = trim($_POST['first_name'] ?? '');
        $last   = trim($_POST['last_name']  ?? '');
        $email  = trim($_POST['email']      ?? '');
        $phone  = trim($_POST['phone']      ?? '');

        if (empty($first))        $errors[] = "First name is required.";
        if (empty($last))         $errors[] = "Last name is required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("ssssi", $first, $last, $email, $phone, $uid);
            $stmt->execute(); $stmt->close();
            $success = "Profile updated successfully.";
        }
    }

    // Profile Photo
    if ($action === 'avatar') {
        $curStmt = $conn->prepare("SELECT avatar FROM users WHERE id=?");
        $curStmt->bind_param("i", $uid); $curStmt->execute();
        $curRow  = $curStmt->get_result()->fetch_assoc(); $curStmt->close();
        $newAvatar = handleAvatarUpload($uid, $curRow['avatar'] ?? ''); // ✅ FIXED: $uid passed
        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $stmt->bind_param("si", $newAvatar, $uid);
        $stmt->execute(); $stmt->close();
        $success = "Profile photo updated.";
    }

    // Farm Info
    if ($action === 'farm') {
        $farmName     = trim($_POST['farm_name']     ?? '');
        $farmLocation = trim($_POST['farm_location'] ?? '');
        $farmType     = trim($_POST['farm_type']     ?? '');
        $farmSize     = trim($_POST['farm_size']     ?? '');
        $farmDesc     = trim($_POST['farm_desc']     ?? '');

        $stmt = $conn->prepare("UPDATE users SET farm_name=?, farm_location=?, farm_type=?, farm_size=?, farm_desc=? WHERE id=?");
        $stmt->bind_param("sssssi", $farmName, $farmLocation, $farmType, $farmSize, $farmDesc, $uid);
        $stmt->execute(); $stmt->close();
        $success = "Farm information updated.";
    }
}

// ── Fetch current user data ──
$stmt = $conn->prepare("SELECT first_name, last_name, username, email, phone, user_type, avatar, farm_name, farm_location, farm_type, farm_size, farm_desc FROM users WHERE id=?");
$stmt->bind_param("i", $uid); $stmt->execute();
$userData = $stmt->get_result()->fetch_assoc(); $stmt->close();
$conn->close();

$displayName = htmlspecialchars($userData['first_name'].' '.$userData['last_name']);
$userType    = htmlspecialchars($userData['user_type']);
$initials    = strtoupper(substr($userData['first_name'],0,1).substr($userData['last_name'],0,1));

$farmTypes = ['Crop Farm','Livestock Farm','Poultry Farm','Mixed Farm','Dairy Farm','Aquaculture','Orchard','Vegetable Farm','Herb Farm','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --green-dark:#1b3a1f;--green-mid:#2e7d32;--green-main:#43a047;
  --green-light:#66bb6a;--green-pale:#a5d6a7;--green-bg:#c8e6c9;
  --green-surface:#e8f5e9;--text-dark:#1a2e1b;--text-mid:#4a5e4b;
  --text-light:#8aaa8b;--shadow:0 4px 24px rgba(27,58,31,0.10);
  --shadow-lg:0 8px 40px rgba(27,58,31,0.14);--sidebar-w:240px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f7f0;min-height:100vh;display:flex;color:var(--text-dark);}

/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:linear-gradient(175deg,var(--green-dark) 0%,var(--green-mid) 55%,var(--green-main) 100%);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;overflow:hidden;}
.sidebar::before{content:'';position:absolute;width:280px;height:280px;background:rgba(255,255,255,0.04);border-radius:50%;top:-80px;right:-80px;pointer-events:none;}
.sidebar::after{content:'';position:absolute;width:200px;height:200px;background:rgba(255,255,255,0.03);border-radius:50%;bottom:-60px;left:-60px;pointer-events:none;}
.sidebar-logo{padding:28px 24px 20px;font-size:20px;font-weight:800;color:#fff;letter-spacing:1px;z-index:1;border-bottom:1px solid rgba(255,255,255,0.08);}
.sidebar-logo span{color:var(--green-pale);}
.sidebar-logo small{display:block;font-size:10px;font-weight:400;color:rgba(255,255,255,0.4);letter-spacing:2px;margin-top:2px;}
.sidebar-user{padding:18px 24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.08);z-index:1;}
.avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--green-dark);flex-shrink:0;overflow:hidden;}
.avatar img{width:100%;height:100%;object-fit:cover;}
.sidebar-user-info .name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sidebar-user-info .role{font-size:10px;color:var(--green-pale);font-weight:500;}
.sidebar-nav{flex:1;padding:16px 12px;z-index:1;overflow-y:auto;}
.nav-section{margin-bottom:20px;}
.nav-section-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;padding:0 12px;margin-bottom:6px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);transition:background .2s,color .2s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,0.14);color:#fff;font-weight:600;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;opacity:.8;}
.nav-item.active svg{opacity:1;}
.sidebar-footer{padding:16px 12px;z-index:1;border-top:1px solid rgba(255,255,255,0.08);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.6);transition:background .2s,color .2s;text-decoration:none;width:100%;background:none;border:none;font-family:'Poppins',sans-serif;}
.logout-btn:hover{background:rgba(229,57,53,0.15);color:#ef9a9a;}
.logout-btn svg{width:17px;height:17px;}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0e8;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(27,58,31,0.06);}
.topbar-left h1{font-size:18px;font-weight:700;color:var(--text-dark);}
.topbar-left p{font-size:12px;color:var(--text-light);margin-top:1px;}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--green-dark);cursor:pointer;overflow:hidden;}
.topbar-avatar img{width:100%;height:100%;object-fit:cover;}
.content{padding:28px 32px;flex:1;}

/* ── ALERTS ── */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:22px;display:flex;align-items:center;gap:10px;}
.alert svg{width:16px;height:16px;flex-shrink:0;}
.alert.success{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}
.alert.error{background:#ffebee;border:1px solid #ef9a9a;color:#c62828;}

/* ── SETTINGS LAYOUT ── */
.settings-grid{display:grid;grid-template-columns:260px 1fr;gap:24px;align-items:start;}

/* ── PROFILE CARD (left) ── */
.profile-card{background:#fff;border-radius:16px;box-shadow:var(--shadow);overflow:hidden;}
.profile-card-hero{background:linear-gradient(135deg,var(--green-mid),var(--green-dark));padding:28px 20px;text-align:center;position:relative;}
.profile-card-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='20' cy='20' r='10'/%3E%3C/g%3E%3C/svg%3E");}
.profile-avatar-wrap{position:relative;display:inline-block;margin-bottom:12px;}
.profile-avatar{width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:var(--green-dark);border:4px solid rgba(255,255,255,0.3);overflow:hidden;position:relative;z-index:1;}
.profile-avatar img{width:100%;height:100%;object-fit:cover;}
.avatar-edit-btn{position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--green-main);border:2px solid #fff;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;transition:background .2s;}
.avatar-edit-btn:hover{background:var(--green-mid);}
.avatar-edit-btn svg{width:12px;height:12px;color:#fff;}
.profile-card-name{font-size:15px;font-weight:700;color:#fff;position:relative;z-index:1;}
.profile-card-role{font-size:11px;color:rgba(255,255,255,0.6);font-weight:500;margin-top:3px;position:relative;z-index:1;}
.profile-card-body{padding:18px 20px;}
.profile-meta-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f3f7f3;}
.profile-meta-row:last-child{border-bottom:none;}
.profile-meta-row svg{width:14px;height:14px;color:var(--green-main);flex-shrink:0;}
.profile-meta-label{font-size:10px;color:var(--text-light);font-weight:600;text-transform:uppercase;letter-spacing:.5px;display:block;}
.profile-meta-val{font-size:12px;color:var(--text-dark);font-weight:500;}

/* ── TAB PANELS (right) ── */
.tabs-wrap{background:#fff;border-radius:16px;box-shadow:var(--shadow);overflow:hidden;}
.tab-nav{display:flex;border-bottom:1px solid #edf3ed;padding:0 6px;}
.tab-btn{padding:14px 18px;font-size:13px;font-weight:600;color:var(--text-light);cursor:pointer;border:none;background:none;font-family:'Poppins',sans-serif;border-bottom:2px solid transparent;transition:color .2s,border-color .2s;margin-bottom:-1px;display:flex;align-items:center;gap:7px;}
.tab-btn svg{width:14px;height:14px;}
.tab-btn:hover{color:var(--green-main);}
.tab-btn.active{color:var(--green-main);border-bottom-color:var(--green-main);}
.tab-panel{display:none;padding:26px 28px;}
.tab-panel.active{display:block;}

/* ── FORM ELEMENTS ── */
.section-title{font-size:14px;font-weight:700;color:var(--text-dark);margin-bottom:4px;}
.section-sub{font-size:12px;color:var(--text-light);margin-bottom:22px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.form-field{display:flex;flex-direction:column;gap:5px;}
.form-field.full{grid-column:1/-1;}
.form-field label{font-size:11px;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.5px;}
.form-field input,
.form-field select,
.form-field textarea{padding:10px 13px;border:1.5px solid #d8eed8;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s,background .2s;color:var(--text-dark);appearance:none;}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus{border-color:var(--green-main);background:#fff;box-shadow:0 0 0 3px rgba(67,160,71,0.08);}
.form-field input:disabled{background:#f0f0f0;color:#aaa;cursor:not-allowed;}
.form-field textarea{resize:vertical;min-height:80px;}
.form-field .hint{font-size:10px;color:var(--text-light);margin-top:2px;}
.form-divider{border:none;border-top:1px solid #edf3ed;margin:22px 0;}
.form-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:6px;}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:9px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(67,160,71,0.28);transition:opacity .2s,transform .15s;}
.btn-primary:hover{opacity:.9;transform:translateY(-1px);}
.btn-primary svg{width:15px;height:15px;}
.btn-secondary{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:#f5f5f5;color:#666;border:none;border-radius:9px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s;}
.btn-secondary:hover{background:#ebebeb;}

/* ── AVATAR UPLOAD PANEL ── */
.avatar-upload-zone{border:2px dashed #cde8ce;border-radius:14px;padding:32px 20px;text-align:center;cursor:pointer;background:#f7fbf7;transition:border-color .2s,background .2s;position:relative;margin-bottom:20px;}
.avatar-upload-zone:hover{border-color:var(--green-main);background:#f0faf0;}
.avatar-upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.avatar-upload-zone svg{width:40px;height:40px;color:var(--green-pale);margin-bottom:10px;}
.avatar-upload-zone p{font-size:13px;color:var(--text-mid);font-weight:600;}
.avatar-upload-zone span{font-size:11px;color:var(--text-light);}
.current-avatar-preview{display:flex;align-items:center;gap:16px;background:var(--green-surface);border-radius:12px;padding:14px 18px;margin-bottom:20px;}
.current-avatar-img{width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
.current-avatar-initials{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:var(--green-dark);border:3px solid #fff;box-shadow:0 2px 10px rgba(0,0,0,0.1);flex-shrink:0;}
.current-avatar-info strong{font-size:13px;color:var(--text-dark);display:block;margin-bottom:3px;}
.current-avatar-info span{font-size:11px;color:var(--text-light);}
#avatarNewPreviewWrap{display:none;margin-bottom:16px;text-align:center;}
#avatarNewPreviewWrap img{width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--green-pale);box-shadow:0 4px 16px rgba(67,160,71,0.2);}
#avatarNewPreviewWrap p{font-size:11px;color:var(--green-main);font-weight:600;margin-top:8px;}

/* ── FARM INFO ICONS ── */
.farm-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:6px;}
.farm-type-opt{border:1.5px solid #d8eed8;border-radius:9px;padding:9px 8px;text-align:center;cursor:pointer;font-size:11px;font-weight:600;color:var(--text-mid);transition:all .2s;background:#f7fbf7;}
.farm-type-opt:hover{border-color:var(--green-main);background:var(--green-surface);color:var(--green-main);}
.farm-type-opt.selected{border-color:var(--green-main);background:var(--green-surface);color:var(--green-main);}
.farm-type-opt span{display:block;font-size:18px;margin-bottom:3px;}

/* ── ACCOUNT INFO ── */
.info-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;}
.info-badge.verified{background:#e8f5e9;color:#2e7d32;}
.info-badge.type{background:#e3f2fd;color:#1565c0;}
.readonly-field{background:#f9fafb;border:1.5px solid #eee;border-radius:8px;padding:10px 13px;font-size:13px;color:#888;font-family:'Poppins',sans-serif;}

@media(max-width:1000px){.settings-grid{grid-template-columns:1fr;}.form-grid{grid-template-columns:1fr;}.form-grid-3{grid-template-columns:1fr 1fr;}}
@media(max-width:700px){.tab-btn span{display:none;}.content{padding:18px;}.tab-panel{padding:18px;}.form-grid-3{grid-template-columns:1fr;}}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">ANI<span>TRACK</span><small>FARM SALES TRACKER</small></div>
  <div class="sidebar-user">
    <div class="avatar">
      <?php if(!empty($userData['avatar'])): ?>
        <img src="../../<?php echo htmlspecialchars($userData['avatar']); ?>" alt="Avatar"/>
      <?php else: echo $initials; endif; ?>
    </div>
    <div class="sidebar-user-info">
      <div class="name"><?php echo $displayName; ?></div>
      <div class="role"><?php echo $userType; ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-label">Main</div>
      <a href="dashboard.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
      <a href="sales.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Sales</a>
      <a href="inventory.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Inventory</a>
      <a href="customers.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Customers</a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">Account</div>
      <a href="settings.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Settings</a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-left"><h1>Settings</h1><p id="topbarDate"></p></div>
    <div class="topbar-right">
      <div class="topbar-avatar">
        <?php if(!empty($userData['avatar'])): ?>
          <img src="../../<?php echo htmlspecialchars($userData['avatar']); ?>" alt="Avatar"/>
        <?php else: echo $initials; endif; ?>
      </div>
    </div>
  </header>

  <div class="content">

    <?php if($success): ?>
    <div class="alert success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20,6 9,17 4,12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if(!empty($errors)): ?>
    <div class="alert error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
    </div>
    <?php endif; ?>

    <div class="settings-grid">

      <!-- ── LEFT: Profile Card ── -->
      <div class="profile-card">
        <div class="profile-card-hero">
          <div class="profile-avatar-wrap">
            <div class="profile-avatar">
              <?php if(!empty($userData['avatar'])): ?>
                <img src="../../<?php echo htmlspecialchars($userData['avatar']); ?>" alt="Profile Photo"/>
              <?php else: echo $initials; endif; ?>
            </div>
            <button class="avatar-edit-btn" onclick="switchTab('avatar')" title="Change photo">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </button>
          </div>
          <div class="profile-card-name"><?php echo $displayName; ?></div>
          <div class="profile-card-role"><?php echo $userType; ?></div>
        </div>
        <div class="profile-card-body">
          <div class="profile-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <div><span class="profile-meta-label">Username</span><span class="profile-meta-val">@<?php echo htmlspecialchars($userData['username']); ?></span></div>
          </div>
          <div class="profile-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <div><span class="profile-meta-label">Email</span><span class="profile-meta-val"><?php echo htmlspecialchars($userData['email']); ?></span></div>
          </div>
          <div class="profile-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.15 6.15l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <div><span class="profile-meta-label">Phone</span><span class="profile-meta-val"><?php echo htmlspecialchars($userData['phone'] ?: '—'); ?></span></div>
          </div>
          <?php if(!empty($userData['farm_name'])): ?>
          <div class="profile-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
            <div><span class="profile-meta-label">Farm</span><span class="profile-meta-val"><?php echo htmlspecialchars($userData['farm_name']); ?></span></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($userData['farm_location'])): ?>
          <div class="profile-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <div><span class="profile-meta-label">Location</span><span class="profile-meta-val"><?php echo htmlspecialchars($userData['farm_location']); ?></span></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── RIGHT: Tab Panels ── -->
      <div class="tabs-wrap">
        <div class="tab-nav">
          <button class="tab-btn active" onclick="switchTab('profile')" id="tab-profile">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Edit Profile</span>
          </button>
          <button class="tab-btn" onclick="switchTab('avatar')" id="tab-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            <span>Profile Photo</span>
          </button>
          <button class="tab-btn" onclick="switchTab('farm')" id="tab-farm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
            <span>Farm Info</span>
          </button>
          <button class="tab-btn" onclick="switchTab('account')" id="tab-account">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Account</span>
          </button>
        </div>

        <!-- ── Tab: Edit Profile ── -->
        <div class="tab-panel active" id="panel-profile">
          <div class="section-title">Personal Information</div>
          <div class="section-sub">Update your name, email and contact number.</div>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile"/>
            <div class="form-grid">
              <div class="form-field">
                <label>First Name *</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" placeholder="Juan" required/>
              </div>
              <div class="form-field">
                <label>Last Name *</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" placeholder="Dela Cruz" required/>
              </div>
              <div class="form-field">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" placeholder="juan@example.com" required/>
              </div>
              <div class="form-field">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" placeholder="+63 9XX XXX XXXX"/>
              </div>
            </div>
            <hr class="form-divider"/>
            <div class="form-field" style="margin-bottom:0;">
              <label>Username</label>
              <div class="readonly-field">@<?php echo htmlspecialchars($userData['username']); ?></div>
              <span class="hint">Username cannot be changed.</span>
            </div>
            <div class="form-actions" style="margin-top:20px;">
              <button type="submit" class="btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
                Save Changes
              </button>
            </div>
          </form>
        </div>

        <!-- ── Tab: Profile Photo ── -->
        <div class="tab-panel" id="panel-avatar">
          <div class="section-title">Profile Photo</div>
          <div class="section-sub">Upload a clear photo of yourself. Max 2MB — JPG, PNG, GIF, WEBP.</div>

          <div class="current-avatar-preview">
            <?php if(!empty($userData['avatar'])): ?>
              <img class="current-avatar-img" src="../../<?php echo htmlspecialchars($userData['avatar']); ?>" alt="Current avatar"/>
            <?php else: ?>
              <div class="current-avatar-initials"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div class="current-avatar-info">
              <strong>Current Photo</strong>
              <span><?php echo !empty($userData['avatar']) ? 'Custom photo uploaded' : 'Using initials placeholder'; ?></span>
            </div>
          </div>

          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="avatar"/>

            <div id="avatarNewPreviewWrap">
              <img id="avatarNewPreview" src="" alt="New preview"/>
              <p>New photo preview</p>
            </div>

            <div class="avatar-upload-zone" id="avatarDropZone">
              <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp"/>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              <p>Click or drag your photo here</p>
              <span>JPG, PNG, GIF or WEBP · max 2MB</span>
            </div>

            <div class="form-actions">
              <button type="button" class="btn-secondary" onclick="clearAvatarPreview()">Cancel</button>
              <button type="submit" class="btn-primary" id="avatarSaveBtn" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Upload Photo
              </button>
            </div>
          </form>
        </div>

        <!-- ── Tab: Farm Info ── -->
        <div class="tab-panel" id="panel-farm">
          <div class="section-title">Farm Information</div>
          <div class="section-sub">Tell us about your farm to personalise your experience.</div>
          <form method="POST">
            <input type="hidden" name="action" value="farm"/>
            <div class="form-grid">
              <div class="form-field">
                <label>Farm Name</label>
                <input type="text" name="farm_name" value="<?php echo htmlspecialchars($userData['farm_name'] ?? ''); ?>" placeholder="e.g. Santos Family Farm"/>
              </div>
              <div class="form-field">
                <label>Location / Barangay</label>
                <input type="text" name="farm_location" value="<?php echo htmlspecialchars($userData['farm_location'] ?? ''); ?>" placeholder="e.g. Brgy. San Isidro, Batangas"/>
              </div>
              <div class="form-field">
                <label>Farm Size</label>
                <input type="text" name="farm_size" value="<?php echo htmlspecialchars($userData['farm_size'] ?? ''); ?>" placeholder="e.g. 2.5 hectares"/>
              </div>
              <div class="form-field">
                <label>Farm Type</label>
                <select name="farm_type">
                  <option value="">— Select type —</option>
                  <?php foreach($farmTypes as $ft): ?>
                    <option value="<?php echo $ft; ?>" <?php echo ($userData['farm_type']??'')===$ft?'selected':''; ?>><?php echo $ft; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-field full">
                <label>Farm Description</label>
                <textarea name="farm_desc" placeholder="Briefly describe your farm, crops, or livestock…"><?php echo htmlspecialchars($userData['farm_desc'] ?? ''); ?></textarea>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
                Save Farm Info
              </button>
            </div>
          </form>
        </div>

        <!-- ── Tab: Account Info ── -->
        <div class="tab-panel" id="panel-account">
          <div class="section-title">Account Details</div>
          <div class="section-sub">Read-only information about your account.</div>

          <div class="form-grid" style="margin-bottom:20px;">
            <div class="form-field">
              <label>Username</label>
              <div class="readonly-field">@<?php echo htmlspecialchars($userData['username']); ?></div>
            </div>
            <div class="form-field">
              <label>Account Type</label>
              <div class="readonly-field" style="display:flex;align-items:center;gap:8px;">
                <span class="info-badge type"><?php echo $userType; ?></span>
              </div>
            </div>
            <div class="form-field">
              <label>Registered Email</label>
              <div class="readonly-field"><?php echo htmlspecialchars($userData['email']); ?></div>
            </div>
            <div class="form-field">
              <label>Status</label>
              <div class="readonly-field" style="display:flex;align-items:center;gap:8px;">
                <span class="info-badge verified">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg>
                  Active
                </span>
              </div>
            </div>
          </div>

          <hr class="form-divider"/>
          <div class="section-title" style="margin-bottom:4px;">Danger Zone</div>
          <p style="font-size:12px;color:var(--text-light);margin-bottom:16px;">These actions are permanent and cannot be undone.</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="../auth/logout.php" style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:#ffebee;color:#c62828;border:1.5px solid #ef9a9a;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;transition:background .2s;"
              onmouseover="this.style.background='#ffcdd2'" onmouseout="this.style.background='#ffebee'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Sign Out
            </a>
          </div>
        </div>

      </div><!-- /tabs-wrap -->
    </div><!-- /settings-grid -->
  </div><!-- /content -->
</div><!-- /main -->

<script>
// Date
const d = new Date();
document.getElementById('topbarDate').textContent = d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

// Tab switching
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  document.getElementById('panel-' + name).classList.add('active');
}

// Avatar preview
document.getElementById('avatarInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('avatarNewPreview').src = e.target.result;
    document.getElementById('avatarNewPreviewWrap').style.display = 'block';
    document.getElementById('avatarDropZone').style.display = 'none';
    document.getElementById('avatarSaveBtn').disabled = false;
  };
  reader.readAsDataURL(file);
});

function clearAvatarPreview() {
  document.getElementById('avatarInput').value = '';
  document.getElementById('avatarNewPreviewWrap').style.display = 'none';
  document.getElementById('avatarDropZone').style.display = 'block';
  document.getElementById('avatarSaveBtn').disabled = true;
}

// Open avatar tab if hash
if (window.location.hash === '#avatar') switchTab('avatar');
</script>
</body>
</html>