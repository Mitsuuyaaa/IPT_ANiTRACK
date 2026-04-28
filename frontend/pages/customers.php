<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed: ".$conn->connect_error); }

$uid    = $_SESSION['user_id'];
$errors = []; $success = '';

// ── DELETE ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=? AND user_id=?");
    $stmt->bind_param("ii",$id,$uid); $stmt->execute(); $stmt->close();
    $success = "Customer deleted.";
}

// ── ADD / EDIT ──
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'',['add','edit'])) {
    $action    = $_POST['action'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $address   = trim($_POST['address']   ?? '');
    $notes     = trim($_POST['notes']     ?? '');
    if (empty($full_name)) { $errors[] = "Full name is required."; }
    if (!empty($email) && !filter_var($email,FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email."; }
    if (empty($errors)) {
        if ($action==='add') {
            $stmt = $conn->prepare("INSERT INTO customers (user_id,full_name,email,phone,address,notes) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssss",$uid,$full_name,$email,$phone,$address,$notes);
            $stmt->execute(); $stmt->close();
            $success = "Customer added successfully.";
        } else {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE customers SET full_name=?,email=?,phone=?,address=?,notes=? WHERE id=? AND user_id=?");
            $stmt->bind_param("sssssii",$full_name,$email,$phone,$address,$notes,$id,$uid);
            $stmt->execute(); $stmt->close();
            $success = "Customer updated successfully.";
        }
    }
}

// ── FETCH ──
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM customers WHERE user_id=?";
$params = [$uid]; $types = "i";
if ($search) { $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)"; $like="%$search%"; $params[]=$like;$params[]=$like;$params[]=$like; $types.="sss"; }
$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types,...$params); $stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$total = count($customers);

// ── Fetch user info for sidebar (includes avatar) ──
$stmt = $conn->prepare("SELECT first_name, last_name, username, email, phone, user_type, avatar FROM users WHERE id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$userData = $stmt->get_result()->fetch_assoc(); $stmt->close();
$conn->close();

$displayName = htmlspecialchars($userData['first_name'].' '.$userData['last_name']);
$userType    = htmlspecialchars($userData['user_type']);
$initials    = strtoupper(substr($userData['first_name'],0,1).substr($userData['last_name'],0,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Customers</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--green-dark:#1b3a1f;--green-mid:#2e7d32;--green-main:#43a047;--green-light:#66bb6a;--green-pale:#a5d6a7;--green-bg:#c8e6c9;--green-surface:#e8f5e9;--text-dark:#1a2e1b;--text-mid:#4a5e4b;--text-light:#8aaa8b;--shadow:0 4px 24px rgba(27,58,31,0.10);--shadow-lg:0 8px 40px rgba(27,58,31,0.14);--radius:14px;--sidebar-w:240px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f7f0;min-height:100vh;display:flex;color:var(--text-dark);}
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:linear-gradient(175deg,var(--green-dark) 0%,var(--green-mid) 55%,var(--green-main) 100%);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;overflow:hidden;}
.sidebar::before{content:'';position:absolute;width:280px;height:280px;background:rgba(255,255,255,0.04);border-radius:50%;top:-80px;right:-80px;pointer-events:none;}
.sidebar::after{content:'';position:absolute;width:200px;height:200px;background:rgba(255,255,255,0.03);border-radius:50%;bottom:-60px;left:-60px;pointer-events:none;}
.sidebar-logo{padding:28px 24px 20px;font-size:20px;font-weight:800;color:#fff;letter-spacing:1px;z-index:1;border-bottom:1px solid rgba(255,255,255,0.08);}
.sidebar-logo span{color:var(--green-pale);}
.sidebar-logo small{display:block;font-size:10px;font-weight:400;color:rgba(255,255,255,0.4);letter-spacing:2px;margin-top:2px;}
.sidebar-user{padding:18px 24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.08);z-index:1;}
.avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--green-dark);flex-shrink:0;overflow:hidden;}
.avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.sidebar-user-info .name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sidebar-user-info .role{font-size:10px;color:var(--green-pale);font-weight:500;}
.sidebar-nav{flex:1;padding:16px 12px;z-index:1;overflow-y:auto;}
.nav-section{margin-bottom:20px;}
.nav-section-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;padding:0 12px;margin-bottom:6px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);transition:background .2s,color .2s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,0.14);color:#fff;font-weight:600;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;opacity:0.8;}
.nav-item.active svg{opacity:1;}
.sidebar-footer{padding:16px 12px;z-index:1;border-top:1px solid rgba(255,255,255,0.08);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.6);transition:background .2s,color .2s;text-decoration:none;width:100%;background:none;border:none;font-family:'Poppins',sans-serif;}
.logout-btn:hover{background:rgba(229,57,53,0.15);color:#ef9a9a;}
.logout-btn svg{width:17px;height:17px;}
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0e8;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(27,58,31,0.06);}
.topbar-left h1{font-size:18px;font-weight:700;color:var(--text-dark);}
.topbar-left p{font-size:12px;color:var(--text-light);margin-top:1px;}
.topbar-right{display:flex;align-items:center;gap:14px;}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--green-dark);cursor:pointer;overflow:hidden;}
.topbar-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.content{padding:28px 32px;flex:1;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;}
.page-header h1{font-size:20px;font-weight:700;color:var(--text-dark);}
.page-header p{font-size:12px;color:var(--text-light);margin-top:2px;}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:9px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(67,160,71,0.3);transition:opacity .2s,transform .15s;text-decoration:none;}
.btn-primary:hover{opacity:.9;transform:translateY(-1px);}
.btn-primary svg{width:16px;height:16px;}
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.stat-mini{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px;}
.stat-mini-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-mini-icon svg{width:20px;height:20px;}
.stat-mini-icon.green{background:#e8f5e9;color:var(--green-main);}
.stat-mini-icon.orange{background:#fff3e0;color:#fb8c00;}
.stat-mini-icon.blue{background:#e3f2fd;color:#1e88e5;}
.stat-mini-label{font-size:11px;color:var(--text-light);font-weight:500;}
.stat-mini-val{font-size:22px;font-weight:700;color:var(--text-dark);line-height:1.1;}
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
.search-wrap{position:relative;flex:1;max-width:340px;}
.search-wrap svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-light);}
.search-wrap input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid #d8eed8;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s;}
.search-wrap input:focus{border-color:var(--green-main);background:#fff;}
.table-wrap{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--green-surface);padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
tbody td{padding:13px 16px;font-size:13px;color:var(--text-dark);border-bottom:1px solid #f0f7f0;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#fafffe;}
.action-btns{display:flex;gap:6px;}
.btn-icon{width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;}
.btn-icon svg{width:14px;height:14px;}
.btn-icon.edit{background:#e8f5e9;color:var(--green-mid);}
.btn-icon.edit:hover{background:#c8e6c9;}
.btn-icon.del{background:#ffebee;color:#e53935;}
.btn-icon.del:hover{background:#ffcdd2;}
.empty-state{text-align:center;padding:50px 20px;color:var(--text-light);}
.empty-state svg{width:48px;height:48px;opacity:.25;margin-bottom:12px;}
.empty-state p{font-size:13px;}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal{background:#fff;border-radius:16px;padding:28px 30px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);position:relative;max-height:90vh;overflow-y:auto;}
.modal h2{font-size:16px;font-weight:700;color:var(--text-dark);margin-bottom:18px;}
.modal .close-btn{position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:var(--text-light);}
.modal .close-btn svg{width:18px;height:18px;}
.form-field{margin-bottom:14px;}
.form-field label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px;}
.form-field input,.form-field textarea{width:100%;padding:9px 12px;border:1.5px solid #cde8ce;border-radius:7px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s;resize:vertical;}
.form-field input:focus,.form-field textarea:focus{border-color:var(--green-main);background:#fff;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-actions{display:flex;gap:10px;margin-top:4px;}
.btn-cancel{flex:1;padding:10px;background:#f5f5f5;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;color:#777;}
.btn-cancel:hover{background:#ebebeb;}
.btn-save{flex:2;padding:10px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;}
.alert{padding:11px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.alert.success{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}
.alert.error{background:#ffebee;border:1px solid #ef9a9a;color:#c62828;}
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
      <a href="customers.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Customers</a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">Account</div>
      <a href="settings.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Settings</a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-left"><h1>Customers</h1><p id="topbarDate"></p></div>
    <div class="topbar-right">
      <div class="topbar-avatar">
        <?php if(!empty($userData['avatar'])): ?>
          <img src="../../<?php echo htmlspecialchars($userData['avatar']); ?>" alt="Avatar"/>
        <?php else: echo $initials; endif; ?>
      </div>
    </div>
  </header>

  <div class="content">

    <?php if ($success): ?>
    <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert error"><?php echo implode('<br>',$errors); ?></div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat-mini">
        <div class="stat-mini-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div><div class="stat-mini-label">Total Customers</div><div class="stat-mini-val"><?php echo $total; ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="stat-mini-label">Added This Month</div><div class="stat-mini-val"><?php
          $mo = array_filter($customers, fn($c)=>date('Y-m',strtotime($c['created_at']))==date('Y-m'));
          echo count($mo);
        ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <div><div class="stat-mini-label">With Email</div><div class="stat-mini-val"><?php echo count(array_filter($customers,fn($c)=>!empty($c['email']))); ?></div></div>
      </div>
    </div>

    <div class="page-header">
      <div><h1>Customer List</h1><p><?php echo $total; ?> customer<?php echo $total!=1?'s':''; ?> found</p></div>
      <button class="btn-primary" onclick="openModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Customer
      </button>
    </div>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <div class="search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" placeholder="Search customers…" value="<?php echo htmlspecialchars($search); ?>"/>
        </div>
        <button type="submit" class="btn-primary" style="padding:9px 18px;">Search</button>
        <?php if($search): ?><a href="customers.php" class="btn-primary" style="background:#f0f0f0;color:#555;box-shadow:none;">Clear</a><?php endif; ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Added</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
          <tr><td colspan="7">
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <p>No customers yet. Add your first customer!</p>
            </div>
          </td></tr>
          <?php else: ?>
          <?php foreach($customers as $i=>$c): ?>
          <tr>
            <td><?php echo $i+1; ?></td>
            <td><strong><?php echo htmlspecialchars($c['full_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($c['email'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($c['phone'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($c['address'] ?: '—'); ?></td>
            <td><?php echo date('M d, Y',strtotime($c['created_at'])); ?></td>
            <td>
              <div class="action-btns">
                <button class="btn-icon edit" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($c)); ?>)" title="Edit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <form method="POST" onsubmit="return confirm('Delete this customer?');" style="display:inline;">
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="id" value="<?php echo $c['id']; ?>"/>
                  <button type="submit" class="btn-icon del" title="Delete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3,6 5,6 21,6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- Modal -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <button class="close-btn" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <h2 id="modalTitle">Add Customer</h2>
    <form method="POST">
      <input type="hidden" name="action" id="formAction" value="add"/>
      <input type="hidden" name="id" id="formId" value=""/>
      <div class="form-field">
        <label>Full Name *</label>
        <input type="text" name="full_name" id="f_name" placeholder="Juan dela Cruz" required/>
      </div>
      <div class="form-row">
        <div class="form-field">
          <label>Email</label>
          <input type="email" name="email" id="f_email" placeholder="email@example.com"/>
        </div>
        <div class="form-field">
          <label>Phone</label>
          <input type="text" name="phone" id="f_phone" placeholder="09X XXX XXXX"/>
        </div>
      </div>
      <div class="form-field">
        <label>Address</label>
        <input type="text" name="address" id="f_address" placeholder="Barangay, Municipality, Province"/>
      </div>
      <div class="form-field">
        <label>Notes</label>
        <textarea name="notes" id="f_notes" rows="3" placeholder="Optional notes…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-save">Save Customer</button>
      </div>
    </form>
  </div>
</div>

<script>
const d = new Date();
document.getElementById('topbarDate').textContent = d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
function openModal(){ document.getElementById('modalBg').classList.add('open'); }
function closeModal(){ document.getElementById('modalBg').classList.remove('open'); resetModal(); }
function resetModal(){ document.getElementById('modalTitle').textContent='Add Customer'; document.getElementById('formAction').value='add'; document.getElementById('formId').value=''; document.getElementById('f_name').value=''; document.getElementById('f_email').value=''; document.getElementById('f_phone').value=''; document.getElementById('f_address').value=''; document.getElementById('f_notes').value=''; }
function editCustomer(c){ document.getElementById('modalTitle').textContent='Edit Customer'; document.getElementById('formAction').value='edit'; document.getElementById('formId').value=c.id; document.getElementById('f_name').value=c.full_name; document.getElementById('f_email').value=c.email||''; document.getElementById('f_phone').value=c.phone||''; document.getElementById('f_address').value=c.address||''; document.getElementById('f_notes').value=c.notes||''; openModal(); }
document.getElementById('modalBg').addEventListener('click',function(e){ if(e.target===this) closeModal(); });
</script>
</body>
</html>