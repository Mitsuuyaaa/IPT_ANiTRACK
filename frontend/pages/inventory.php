<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed: ".$conn->connect_error); }

$uid = $_SESSION['user_id'];
$errors=[]; $success='';

// ── DELETE ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    $id=(int)$_POST['id'];
    $stmt=$conn->prepare("DELETE FROM inventory WHERE id=? AND user_id=?");
    $stmt->bind_param("ii",$id,$uid); $stmt->execute(); $stmt->close();
    $success="Product deleted.";
}

// ── ADD / EDIT ──
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'',['add','edit'])) {
    $action   = $_POST['action'];
    $name     = trim($_POST['name']        ?? '');
    $category = trim($_POST['category']    ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit     = trim($_POST['unit']        ?? 'kg');
    $price    = floatval($_POST['price']   ?? 0);
    $low      = floatval($_POST['low_stock']?? 10);
    $desc     = trim($_POST['description'] ?? '');
    if (empty($name))     { $errors[]="Product name is required."; }
    if ($price < 0)       { $errors[]="Price cannot be negative."; }
    if ($quantity < 0)    { $errors[]="Quantity cannot be negative."; }
    if (empty($errors)) {
        if ($action==='add') {
            $stmt=$conn->prepare("INSERT INTO inventory (user_id,name,category,quantity,unit,price,low_stock,description) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issdsdds",$uid,$name,$category,$quantity,$unit,$price,$low,$desc);
            $stmt->execute(); $stmt->close();
            $success="Product added successfully.";
        } else {
            $id=(int)$_POST['id'];
            $stmt=$conn->prepare("UPDATE inventory SET name=?,category=?,quantity=?,unit=?,price=?,low_stock=?,description=? WHERE id=? AND user_id=?");
            $stmt->bind_param("ssdsddsi i",$name,$category,$quantity,$unit,$price,$low,$desc,$id,$uid);
            // fix bind
            $stmt->close();
            $stmt=$conn->prepare("UPDATE inventory SET name=?,category=?,quantity=?,unit=?,price=?,low_stock=?,description=? WHERE id=? AND user_id=?");
            $stmt->bind_param("ssdsddsii",$name,$category,$quantity,$unit,$price,$low,$desc,$id,$uid);
            $stmt->execute(); $stmt->close();
            $success="Product updated successfully.";
        }
    }
}

// ── FETCH ──
$search=trim($_GET['q']??'');
$filter=trim($_GET['filter']??'all');
$sql="SELECT * FROM inventory WHERE user_id=?";
$params=[$uid]; $types="i";
if ($search) { $sql.=" AND (name LIKE ? OR category LIKE ?)"; $like="%$search%"; $params[]=$like;$params[]=$like; $types.="ss"; }
if ($filter==='low')  { $sql.=" AND quantity <= low_stock"; }
if ($filter==='out')  { $sql.=" AND quantity = 0"; }
$sql.=" ORDER BY created_at DESC";
$stmt=$conn->prepare($sql);
$stmt->bind_param($types,...$params); $stmt->execute();
$products=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// counts
$allStmt=$conn->prepare("SELECT COUNT(*) as total, SUM(quantity*price) as value, SUM(CASE WHEN quantity<=low_stock AND quantity>0 THEN 1 ELSE 0 END) as low_count, SUM(CASE WHEN quantity=0 THEN 1 ELSE 0 END) as out_count FROM inventory WHERE user_id=?");
$allStmt->bind_param("i",$uid); $allStmt->execute();
$stats=$allStmt->get_result()->fetch_assoc(); $allStmt->close();

// Fetch user
$stmt=$conn->prepare("SELECT first_name,last_name,username,email,phone,user_type FROM users WHERE id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$userData=$stmt->get_result()->fetch_assoc(); $stmt->close();
$displayName=htmlspecialchars($userData['first_name'].' '.$userData['last_name']);
$userType=htmlspecialchars($userData['user_type']);

$categories=['Vegetables','Fruits','Grains','Livestock','Poultry','Dairy','Herbs','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Inventory</title>
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
.avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--green-dark);flex-shrink:0;}
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
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--green-dark);cursor:pointer;}
.content{padding:28px 32px;flex:1;}
/* Page specific */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;}
.page-header h1{font-size:20px;font-weight:700;color:var(--text-dark);}
.page-header p{font-size:12px;color:var(--text-light);margin-top:2px;}
.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:9px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(67,160,71,0.3);transition:opacity .2s,transform .15s;text-decoration:none;}
.btn-primary:hover{opacity:.9;transform:translateY(-1px);}
.btn-primary svg{width:16px;height:16px;}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-mini{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px;}
.stat-mini-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-mini-icon svg{width:20px;height:20px;}
.stat-mini-icon.green{background:#e8f5e9;color:var(--green-main);}
.stat-mini-icon.orange{background:#fff3e0;color:#fb8c00;}
.stat-mini-icon.red{background:#ffebee;color:#e53935;}
.stat-mini-icon.blue{background:#e3f2fd;color:#1e88e5;}
.stat-mini-label{font-size:11px;color:var(--text-light);font-weight:500;}
.stat-mini-val{font-size:20px;font-weight:700;color:var(--text-dark);line-height:1.2;}
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;max-width:300px;}
.search-wrap svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-light);}
.search-wrap input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid #d8eed8;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s;}
.search-wrap input:focus{border-color:var(--green-main);background:#fff;}
.filter-tabs{display:flex;gap:6px;}
.filter-tab{padding:8px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #d8eed8;background:#fff;color:var(--text-mid);text-decoration:none;transition:all .2s;}
.filter-tab:hover,.filter-tab.active{background:var(--green-main);color:#fff;border-color:var(--green-main);}
.table-wrap{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--green-surface);padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
tbody td{padding:13px 16px;font-size:13px;color:var(--text-dark);border-bottom:1px solid #f0f7f0;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#fafffe;}
.stock-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.stock-badge.ok{background:#e8f5e9;color:#2e7d32;}
.stock-badge.low{background:#fff3e0;color:#e65100;}
.stock-badge.out{background:#ffebee;color:#c62828;}
.cat-tag{display:inline-block;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;background:var(--green-surface);color:var(--green-mid);}
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
.modal{background:#fff;border-radius:16px;padding:28px 30px;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);position:relative;max-height:90vh;overflow-y:auto;}
.modal h2{font-size:16px;font-weight:700;color:var(--text-dark);margin-bottom:18px;}
.modal .close-btn{position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:var(--text-light);}
.modal .close-btn svg{width:18px;height:18px;}
.form-field{margin-bottom:14px;}
.form-field label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px;}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:9px 12px;border:1.5px solid #cde8ce;border-radius:7px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s;appearance:none;}
.form-field input:focus,.form-field select:focus,.form-field textarea:focus{border-color:var(--green-main);background:#fff;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-row-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;}
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
    <div class="avatar"><?php echo strtoupper(substr($userData['first_name'],0,1).substr($userData['last_name'],0,1)); ?></div>
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
      <a href="inventory.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Inventory</a>
      <a href="customers.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Customers</a>
    </div>
    <div class="nav-section">
      <div class="nav-section-label">Account</div>
      <a href="#" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>Settings</a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-left"><h1>Inventory</h1><p id="topbarDate"></p></div>
    <div class="topbar-right">
      <div class="topbar-avatar"><?php echo strtoupper(substr($userData['first_name'],0,1).substr($userData['last_name'],0,1)); ?></div>
    </div>
  </header>

  <div class="content">

    <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert error"><?php echo implode('<br>',$errors); ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-mini">
        <div class="stat-mini-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
        <div><div class="stat-mini-label">Total Products</div><div class="stat-mini-val"><?php echo (int)($stats['total']??0); ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div><div class="stat-mini-label">Inventory Value</div><div class="stat-mini-val">₱<?php echo number_format($stats['value']??0,2); ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div><div class="stat-mini-label">Low Stock</div><div class="stat-mini-val"><?php echo (int)($stats['low_count']??0); ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
        <div><div class="stat-mini-label">Out of Stock</div><div class="stat-mini-val"><?php echo (int)($stats['out_count']??0); ?></div></div>
      </div>
    </div>

    <div class="page-header">
      <div><h1>Product List</h1><p><?php echo count($products); ?> product<?php echo count($products)!=1?'s':''; ?></p></div>
      <button class="btn-primary" onclick="openModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Product
      </button>
    </div>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <div class="search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" placeholder="Search products…" value="<?php echo htmlspecialchars($search); ?>"/>
          <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"/>
        </div>
        <button type="submit" class="btn-primary" style="padding:9px 16px;">Search</button>
      </form>
      <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?php echo $filter==='all'?'active':''; ?>">All</a>
        <a href="?filter=low" class="filter-tab <?php echo $filter==='low'?'active':''; ?>">Low Stock</a>
        <a href="?filter=out" class="filter-tab <?php echo $filter==='out'?'active':''; ?>">Out of Stock</a>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Product Name</th><th>Category</th><th>Quantity</th><th>Unit Price</th><th>Total Value</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
          <tr><td colspan="8"><div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            <p>No products found. Add your first product!</p>
          </div></td></tr>
          <?php else: ?>
          <?php foreach($products as $i=>$p): 
            $status = $p['quantity']==0 ? 'out' : ($p['quantity']<=$p['low_stock'] ? 'low' : 'ok');
            $statusLabel = ['ok'=>'In Stock','low'=>'Low Stock','out'=>'Out of Stock'][$status];
          ?>
          <tr>
            <td><?php echo $i+1; ?></td>
            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong><?php if($p['description']): ?><br><span style="font-size:11px;color:var(--text-light);"><?php echo htmlspecialchars(substr($p['description'],0,40)).(strlen($p['description'])>40?'…':''); ?></span><?php endif; ?></td>
            <td><span class="cat-tag"><?php echo htmlspecialchars($p['category']?:'—'); ?></span></td>
            <td><?php echo number_format($p['quantity'],2).' '.$p['unit']; ?></td>
            <td>₱<?php echo number_format($p['price'],2); ?></td>
            <td>₱<?php echo number_format($p['quantity']*$p['price'],2); ?></td>
            <td><span class="stock-badge <?php echo $status; ?>"><?php echo $statusLabel; ?></span></td>
            <td>
              <div class="action-btns">
                <button class="btn-icon edit" onclick="editProduct(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="Edit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <form method="POST" onsubmit="return confirm('Delete this product?');" style="display:inline;">
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="id" value="<?php echo $p['id']; ?>"/>
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

<!-- MODAL -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <button class="close-btn" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <h2 id="modalTitle">Add Product</h2>
    <form method="POST">
      <input type="hidden" name="action" id="formAction" value="add"/>
      <input type="hidden" name="id" id="formId" value=""/>
      <div class="form-field"><label>Product Name *</label><input type="text" name="name" id="f_name" placeholder="e.g. Tomatoes" required/></div>
      <div class="form-row">
        <div class="form-field"><label>Category</label>
          <select name="category" id="f_category">
            <option value="">— Select —</option>
            <?php foreach($categories as $cat): ?><option value="<?php echo $cat; ?>"><?php echo $cat; ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label>Unit</label>
          <select name="unit" id="f_unit">
            <option value="kg">kg</option><option value="g">g</option><option value="piece">piece</option><option value="bundle">bundle</option><option value="sack">sack</option><option value="liter">liter</option><option value="dozen">dozen</option><option value="tray">tray</option>
          </select>
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-field"><label>Quantity *</label><input type="number" name="quantity" id="f_qty" placeholder="0" step="0.01" min="0" required/></div>
        <div class="form-field"><label>Unit Price (₱) *</label><input type="number" name="price" id="f_price" placeholder="0.00" step="0.01" min="0" required/></div>
        <div class="form-field"><label>Low Stock Alert</label><input type="number" name="low_stock" id="f_low" placeholder="10" step="0.01" min="0"/></div>
      </div>
      <div class="form-field"><label>Description</label><textarea name="description" id="f_desc" rows="2" placeholder="Optional…"></textarea></div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-save">Save Product</button>
      </div>
    </form>
  </div>
</div>

<script>
const d=new Date(); document.getElementById('topbarDate').textContent=d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
function openModal(){document.getElementById('modalBg').classList.add('open');}
function closeModal(){document.getElementById('modalBg').classList.remove('open');resetModal();}
function resetModal(){document.getElementById('modalTitle').textContent='Add Product';document.getElementById('formAction').value='add';document.getElementById('formId').value='';['f_name','f_qty','f_price','f_desc'].forEach(id=>document.getElementById(id).value='');document.getElementById('f_low').value='10';document.getElementById('f_category').value='';document.getElementById('f_unit').value='kg';}
function editProduct(p){
  document.getElementById('modalTitle').textContent='Edit Product';
  document.getElementById('formAction').value='edit';
  document.getElementById('formId').value=p.id;
  document.getElementById('f_name').value=p.name;
  document.getElementById('f_category').value=p.category||'';
  document.getElementById('f_qty').value=p.quantity;
  document.getElementById('f_unit').value=p.unit||'kg';
  document.getElementById('f_price').value=p.price;
  document.getElementById('f_low').value=p.low_stock||10;
  document.getElementById('f_desc').value=p.description||'';
  openModal();
}
document.getElementById('modalBg').addEventListener('click',function(e){if(e.target===this)closeModal();});
</script>
</body>
</html>