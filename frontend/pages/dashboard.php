<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$host = "localhost";
$db   = "anitrack";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$uid = $_SESSION['user_id'];

// ── USER INFO ──
$stmt = $conn->prepare("SELECT first_name, last_name, username, email, phone, user_type FROM users WHERE id = ?");
$stmt->bind_param("i", $uid); $stmt->execute();
$userData = $stmt->get_result()->fetch_assoc(); $stmt->close();
$displayName = htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']);
$userType    = htmlspecialchars($userData['user_type']);
$username    = htmlspecialchars($userData['username']);
$initials    = strtoupper(substr($userData['first_name'],0,1) . substr($userData['last_name'],0,1));

// ── STATS: Sales ──
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt FROM sales WHERE user_id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$salesStats = $stmt->get_result()->fetch_assoc(); $stmt->close();

// ── STATS: This month ──
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) as monthly, COUNT(*) as monthly_cnt FROM sales WHERE user_id=? AND MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())");
$stmt->bind_param("i",$uid); $stmt->execute();
$monthlySales = $stmt->get_result()->fetch_assoc(); $stmt->close();

// ── STATS: Inventory ──
$stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN quantity<=low_stock AND quantity>0 THEN 1 ELSE 0 END) as low FROM inventory WHERE user_id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$invStats = $stmt->get_result()->fetch_assoc(); $stmt->close();

// ── STATS: Customers ──
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM customers WHERE user_id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$custStats = $stmt->get_result()->fetch_assoc(); $stmt->close();

// ── RECENT TRANSACTIONS (last 5) ──
$stmt = $conn->prepare("SELECT s.product_name, s.total_amount, s.sale_date, c.full_name AS customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 5");
$stmt->bind_param("i",$uid); $stmt->execute();
$recentSales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// ── TOP PRODUCTS ──
$stmt = $conn->prepare("SELECT product_name, SUM(quantity) as total_qty, SUM(total_amount) as total_revenue FROM sales WHERE user_id=? GROUP BY product_name ORDER BY total_qty DESC LIMIT 5");
$stmt->bind_param("i",$uid); $stmt->execute();
$topProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// ── LOW STOCK ITEMS ──
$stmt = $conn->prepare("SELECT name, quantity, unit, low_stock FROM inventory WHERE user_id=? AND quantity <= low_stock ORDER BY quantity ASC LIMIT 4");
$stmt->bind_param("i",$uid); $stmt->execute();
$lowStock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>ANI-TRACK | Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root {
  --green-dark:#1b3a1f; --green-mid:#2e7d32; --green-main:#43a047;
  --green-light:#66bb6a; --green-pale:#a5d6a7; --green-bg:#c8e6c9;
  --green-surface:#e8f5e9; --text-dark:#1a2e1b; --text-mid:#4a5e4b;
  --text-light:#8aaa8b; --shadow:0 4px 24px rgba(27,58,31,0.10);
  --shadow-lg:0 8px 40px rgba(27,58,31,0.14); --radius:14px; --sidebar-w:240px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f7f0;min-height:100vh;display:flex;color:var(--text-dark);}
/* SIDEBAR */
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:linear-gradient(175deg,var(--green-dark) 0%,var(--green-mid) 55%,var(--green-main) 100%);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;overflow:hidden;}
.sidebar::before{content:'';position:absolute;width:280px;height:280px;background:rgba(255,255,255,0.04);border-radius:50%;top:-80px;right:-80px;pointer-events:none;}
.sidebar::after{content:'';position:absolute;width:200px;height:200px;background:rgba(255,255,255,0.03);border-radius:50%;bottom:-60px;left:-60px;pointer-events:none;}
.sidebar-logo{padding:28px 24px 20px;font-size:20px;font-weight:800;color:#fff;letter-spacing:1px;z-index:1;border-bottom:1px solid rgba(255,255,255,0.08);}
.sidebar-logo span{color:var(--green-pale);}
.sidebar-logo small{display:block;font-size:10px;font-weight:400;color:rgba(255,255,255,0.4);letter-spacing:2px;margin-top:2px;}
.sidebar-user{padding:18px 24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.08);z-index:1;}
.avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--green-dark);flex-shrink:0;}
.sidebar-user-info .name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sidebar-user-info .role{font-size:10px;color:var(--green-pale);font-weight:500;}
.sidebar-nav{flex:1;padding:16px 12px;z-index:1;overflow-y:auto;}
.nav-section{margin-bottom:20px;}
.nav-section-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;padding:0 12px;margin-bottom:6px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);transition:background .2s,color .2s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,0.14);color:#fff;font-weight:600;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;opacity:.8;}
.nav-item.active svg{opacity:1;}
.sidebar-footer{padding:16px 12px;z-index:1;border-top:1px solid rgba(255,255,255,0.08);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.6);transition:background .2s,color .2s;text-decoration:none;width:100%;background:none;border:none;font-family:'Poppins',sans-serif;}
.logout-btn:hover{background:rgba(229,57,53,0.15);color:#ef9a9a;}
.logout-btn svg{width:17px;height:17px;}
/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0e8;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(27,58,31,0.06);}
.topbar-left h1{font-size:18px;font-weight:700;color:var(--text-dark);}
.topbar-left p{font-size:12px;color:var(--text-light);margin-top:1px;}
.topbar-right{display:flex;align-items:center;gap:14px;}
.notif-btn{width:36px;height:36px;border-radius:50%;background:var(--green-surface);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--green-main);position:relative;transition:background .2s;}
.notif-btn:hover{background:var(--green-bg);}
.notif-btn svg{width:17px;height:17px;}
.notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;background:#e53935;border-radius:50%;border:1.5px solid #fff;}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--green-dark);cursor:pointer;}
/* CONTENT */
.content{padding:28px 32px;flex:1;}
.welcome-banner{background:linear-gradient(120deg,var(--green-dark) 0%,var(--green-mid) 50%,var(--green-main) 100%);border-radius:var(--radius);padding:28px 32px;display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;position:relative;overflow:hidden;}
.welcome-banner::before{content:'';position:absolute;width:240px;height:240px;background:rgba(255,255,255,0.05);border-radius:50%;top:-80px;right:120px;}
.welcome-banner::after{content:'';position:absolute;width:160px;height:160px;background:rgba(255,255,255,0.04);border-radius:50%;bottom:-50px;right:-30px;}
.welcome-text h2{font-size:20px;font-weight:700;color:#fff;margin-bottom:4px;}
.welcome-text h2 span{color:var(--green-pale);}
.welcome-text p{font-size:12px;color:rgba(255,255,255,0.65);}
.welcome-badge{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:10px;padding:10px 20px;text-align:center;z-index:1;flex-shrink:0;}
.welcome-badge .badge-type{font-size:10px;color:rgba(255,255,255,0.5);font-weight:500;letter-spacing:1px;text-transform:uppercase;}
.welcome-badge .badge-val{font-size:16px;font-weight:700;color:var(--green-pale);}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:28px;}
.stat-card{background:#fff;border-radius:var(--radius);padding:20px 22px;box-shadow:var(--shadow);position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);}
.stat-card::after{content:'';position:absolute;width:80px;height:80px;border-radius:50%;top:-20px;right:-20px;opacity:.08;}
.stat-card.green::after{background:var(--green-main);}
.stat-card.teal::after{background:#00897b;}
.stat-card.orange::after{background:#fb8c00;}
.stat-card.blue::after{background:#1e88e5;}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
.stat-icon svg{width:20px;height:20px;}
.stat-icon.green{background:#e8f5e9;color:var(--green-main);}
.stat-icon.teal{background:#e0f2f1;color:#00897b;}
.stat-icon.orange{background:#fff3e0;color:#fb8c00;}
.stat-icon.blue{background:#e3f2fd;color:#1e88e5;}
.stat-label{font-size:11px;color:var(--text-light);font-weight:500;margin-bottom:4px;}
.stat-value{font-size:24px;font-weight:700;color:var(--text-dark);line-height:1;}
.stat-sub{font-size:11px;color:var(--text-light);margin-top:4px;}
.stat-trend{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:600;margin-top:6px;}
.stat-trend.up{color:var(--green-main);}
.stat-trend svg{width:12px;height:12px;}
.two-col{display:grid;grid-template-columns:1fr 360px;gap:20px;margin-bottom:28px;}
.panel{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.panel-header{padding:18px 22px;border-bottom:1px solid #f0f7f0;display:flex;align-items:center;justify-content:space-between;}
.panel-header h3{font-size:14px;font-weight:700;color:var(--text-dark);}
.panel-header a,.panel-header button{font-size:12px;color:var(--green-main);font-weight:600;text-decoration:none;background:none;border:none;cursor:pointer;font-family:'Poppins',sans-serif;}
.panel-body{padding:18px 22px;}
.tx-list{display:flex;flex-direction:column;gap:10px;}
.tx-item{display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:10px;background:#fafffe;border:1px solid #eef6ee;transition:background .2s;}
.tx-item:hover{background:var(--green-surface);}
.tx-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#e8f5e9;color:var(--green-main);}
.tx-icon svg{width:17px;height:17px;}
.tx-info{flex:1;min-width:0;}
.tx-name{font-size:13px;font-weight:600;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tx-sub{font-size:11px;color:var(--text-light);margin-top:1px;}
.tx-amount{font-size:14px;font-weight:700;color:var(--green-main);white-space:nowrap;}
.empty-msg{text-align:center;padding:30px 0;color:var(--text-light);font-size:13px;}
.empty-msg svg{width:40px;height:40px;opacity:.25;margin:0 auto 10px;display:block;}
.actions-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.action-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:18px 12px;border-radius:11px;background:var(--green-surface);border:1.5px solid #d0ead0;cursor:pointer;transition:background .2s,border-color .2s,transform .15s;font-family:'Poppins',sans-serif;text-decoration:none;}
.action-btn:hover{background:var(--green-bg);border-color:var(--green-pale);transform:translateY(-2px);}
.action-btn svg{width:22px;height:22px;color:var(--green-mid);}
.action-btn span{font-size:11px;font-weight:600;color:var(--text-mid);text-align:center;}
.profile-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.profile-banner{height:70px;background:linear-gradient(120deg,var(--green-dark),var(--green-main));position:relative;}
.profile-banner::after{content:'';position:absolute;width:120px;height:120px;background:rgba(255,255,255,0.05);border-radius:50%;top:-30px;right:-20px;}
.profile-avatar-wrap{padding:0 22px;margin-top:-24px;margin-bottom:12px;}
.profile-avatar-big{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:var(--green-dark);border:3px solid #fff;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
.profile-info{padding:0 22px 18px;}
.profile-name{font-size:15px;font-weight:700;color:var(--text-dark);}
.profile-role{font-size:11px;color:var(--green-main);font-weight:600;margin-bottom:14px;}
.profile-detail{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12px;color:var(--text-mid);}
.profile-detail svg{width:14px;height:14px;color:var(--green-main);flex-shrink:0;}
.bottom-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.low-stock-list{display:flex;flex-direction:column;gap:10px;}
.low-stock-item{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fffbf0;border:1px solid #ffe082;border-radius:9px;}
.low-stock-name{font-size:13px;font-weight:600;color:var(--text-dark);}
.low-stock-hint{font-size:11px;color:var(--text-light);}
.badge-out{background:#ffebee;color:#c62828;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;}
.badge-low{background:#fff3e0;color:#e65100;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap;}
.top-prod-list{display:flex;flex-direction:column;gap:12px;}
.top-prod-item{display:flex;align-items:center;gap:12px;}
.top-prod-rank{width:22px;height:22px;border-radius:50%;background:var(--green-surface);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--green-mid);flex-shrink:0;}
.top-prod-info{flex:1;min-width:0;}
.top-prod-name{font-size:12px;font-weight:600;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.top-prod-qty{font-size:11px;color:var(--text-light);}
.top-prod-val{font-size:13px;font-weight:700;color:var(--green-mid);white-space:nowrap;}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr);}.two-col{grid-template-columns:1fr;}}
@media(max-width:780px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;}.bottom-row{grid-template-columns:1fr;}.topbar{padding:14px 18px;}.content{padding:20px 18px;}}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">ANI<span>TRACK</span><small>FARM SALES TRACKER</small></div>
  <div class="sidebar-user">
    <div class="avatar"><?php echo $initials; ?></div>
    <div class="sidebar-user-info">
      <div class="name"><?php echo $displayName; ?></div>
      <div class="role"><?php echo $userType; ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-label">Main</div>
      <a href="dashboard.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
      </a>
      <a href="sales.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Sales
      </a>
      <a href="inventory.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Inventory
      </a>
      <a href="customers.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Customers
      </a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">Account</div>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Settings
      </a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-left"><h1>Dashboard</h1><p id="topbarDate"></p></div>
    <div class="topbar-right">
      <button class="notif-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if(($invStats['low']??0) > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
      <div class="topbar-avatar"><?php echo $initials; ?></div>
    </div>
  </header>

  <div class="content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="welcome-text">
        <h2>Good day, <span><?php echo htmlspecialchars($userData['first_name']); ?>!</span> 👋</h2>
        <p>Here's a live summary of your ANI-TRACK activity.</p>
      </div>
      <div class="welcome-badge">
        <div class="badge-type">Account Type</div>
        <div class="badge-val"><?php echo $userType; ?></div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card green">
        <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value">₱<?php echo number_format($salesStats['total'],2); ?></div>
        <div class="stat-trend up">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18,15 12,9 6,15"/></svg>
          ₱<?php echo number_format($monthlySales['monthly'],2); ?> this month
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
        <div class="stat-label">Products Listed</div>
        <div class="stat-value"><?php echo (int)($invStats['total']??0); ?></div>
        <div class="stat-sub"><?php echo (int)($invStats['low']??0); ?> low stock item<?php echo ($invStats['low']??0)!=1?'s':''; ?></div>
      </div>
      <div class="stat-card orange">
        <div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="stat-label">Customers</div>
        <div class="stat-value"><?php echo (int)($custStats['total']??0); ?></div>
        <div class="stat-sub">Registered buyers</div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <div class="stat-label">Transactions</div>
        <div class="stat-value"><?php echo (int)($salesStats['cnt']??0); ?></div>
        <div class="stat-sub"><?php echo (int)($monthlySales['monthly_cnt']??0); ?> this month</div>
      </div>
    </div>

    <!-- Two Col -->
    <div class="two-col">
      <!-- Recent Transactions -->
      <div class="panel">
        <div class="panel-header">
          <h3>Recent Transactions</h3>
          <a href="sales.php">View all</a>
        </div>
        <div class="panel-body">
          <div class="tx-list">
            <?php if (empty($recentSales)): ?>
            <div class="empty-msg">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              <p>No transactions yet.</p>
              <p style="font-size:11px;margin-top:4px;">Record your first sale to see it here.</p>
            </div>
            <?php else: ?>
            <?php foreach($recentSales as $tx): ?>
            <div class="tx-item">
              <div class="tx-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
              <div class="tx-info">
                <div class="tx-name"><?php echo htmlspecialchars($tx['product_name']); ?></div>
                <div class="tx-sub"><?php echo $tx['customer_name'] ? htmlspecialchars($tx['customer_name']) : 'Walk-in'; ?> · <?php echo date('M d, Y', strtotime($tx['sale_date'])); ?></div>
              </div>
              <div class="tx-amount">₱<?php echo number_format($tx['total_amount'],2); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right col -->
      <div style="display:flex;flex-direction:column;gap:18px;">
        <!-- Quick Actions -->
        <div class="panel">
          <div class="panel-header"><h3>Quick Actions</h3></div>
          <div class="panel-body">
            <div class="actions-grid">
              <a href="sales.php" class="action-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>New Sale</span>
              </a>
              <a href="inventory.php" class="action-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                <span>Add Product</span>
              </a>
              <a href="customers.php" class="action-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                <span>Add Customer</span>
              </a>
              <a href="sales.php" class="action-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                <span>View Sales</span>
              </a>
            </div>
          </div>
        </div>

        <!-- Profile -->
        <div class="profile-card">
          <div class="profile-banner"></div>
          <div class="profile-avatar-wrap"><div class="profile-avatar-big"><?php echo $initials; ?></div></div>
          <div class="profile-info">
            <div class="profile-name"><?php echo $displayName; ?></div>
            <div class="profile-role"><?php echo $userType; ?></div>
            <div class="profile-detail">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <?php echo $username; ?>
            </div>
            <div class="profile-detail">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <?php echo htmlspecialchars($userData['email']); ?>
            </div>
            <div class="profile-detail">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.15 6.15l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              <?php echo htmlspecialchars($userData['phone']); ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="bottom-row">
      <!-- Low Stock Alerts -->
      <div class="panel">
        <div class="panel-header"><h3>⚠️ Low Stock Alerts</h3><a href="inventory.php">Manage</a></div>
        <div class="panel-body">
          <?php if (empty($lowStock)): ?>
          <div class="empty-msg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            <p>All stock levels are good! ✅</p>
          </div>
          <?php else: ?>
          <div class="low-stock-list">
            <?php foreach($lowStock as $item): ?>
            <div class="low-stock-item">
              <div>
                <div class="low-stock-name"><?php echo htmlspecialchars($item['name']); ?></div>
                <div class="low-stock-hint">Alert at: <?php echo number_format($item['low_stock'],2).' '.$item['unit']; ?></div>
              </div>
              <?php if($item['quantity'] == 0): ?>
                <span class="badge-out">Out of Stock</span>
              <?php else: ?>
                <span class="badge-low"><?php echo number_format($item['quantity'],2).' '.$item['unit']; ?> left</span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Products -->
      <div class="panel">
        <div class="panel-header"><h3>Top Products</h3><a href="sales.php">View all</a></div>
        <div class="panel-body">
          <?php if (empty($topProducts)): ?>
          <div class="empty-msg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            <p>No sales data yet.</p>
          </div>
          <?php else: ?>
          <div class="top-prod-list">
            <?php foreach($topProducts as $i=>$p): ?>
            <div class="top-prod-item">
              <div class="top-prod-rank"><?php echo $i+1; ?></div>
              <div class="top-prod-info">
                <div class="top-prod-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                <div class="top-prod-qty"><?php echo number_format($p['total_qty'],2); ?> units sold</div>
              </div>
              <div class="top-prod-val">₱<?php echo number_format($p['total_revenue'],2); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const d = new Date();
document.getElementById('topbarDate').textContent = d.toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
</script>
</body>
</html>