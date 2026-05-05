<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php"); exit;
}
$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed."); }
$uid = $_SESSION['user_id'];

$success = ''; $error = '';

// ── APPROVE / REJECT ──
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    if (in_array($action,['approve','reject','delete']) && $user_id) {
        if ($action === 'approve') {
            $s=$conn->prepare("UPDATE users SET status='approved' WHERE id=?");
            $s->bind_param("i",$user_id); $s->execute(); $s->close();
            $success = "User approved successfully.";
        } elseif ($action === 'reject') {
            $s=$conn->prepare("UPDATE users SET status='rejected' WHERE id=?");
            $s->bind_param("i",$user_id); $s->execute(); $s->close();
            $success = "User rejected.";
        } elseif ($action === 'delete') {
            $s=$conn->prepare("DELETE FROM users WHERE id=? AND user_type != 'admin'");
            $s->bind_param("i",$user_id); $s->execute(); $s->close();
            $success = "User deleted.";
        }
    }
}

// ── STATS ──
$stats = [];
foreach (['pending'=>"status='pending'", 'approved'=>"status='approved'", 'rejected'=>"status='rejected'", 'farmers'=>"user_type='Farmer'", 'vendors'=>"user_type='Vendor'"] as $k=>$w) {
    $r=$conn->query("SELECT COUNT(*) FROM users WHERE user_type!='admin' AND $w");
    $stats[$k] = $r->fetch_row()[0];
}

// ── FILTER ──
$tab = $_GET['tab'] ?? 'pending';
$validTabs = ['pending','approved','rejected','all'];
if (!in_array($tab,$validTabs)) $tab='pending';
$whereClause = $tab==='all' ? "user_type!='admin'" : "user_type!='admin' AND status='$tab'";
$users = $conn->query("SELECT * FROM users WHERE $whereClause ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// ── ORDERS OVERVIEW ──
$recentOrders = $conn->query("
    SELECT o.*, 
           v.first_name AS vendor_fname, v.last_name AS vendor_lname,
           f.first_name AS farmer_fname, f.last_name AS farmer_lname
    FROM orders o
    JOIN users v ON o.vendor_id=v.id
    JOIN users f ON o.farmer_id=f.id
    ORDER BY o.ordered_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$adminData = $conn->query("SELECT first_name,last_name,avatar FROM users WHERE id=$uid")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--green-dark:#1b3a1f;--green-mid:#2e7d32;--green-main:#43a047;--green-light:#66bb6a;--green-pale:#a5d6a7;--shadow:0 4px 24px rgba(27,58,31,0.10);--radius:14px;--sidebar-w:240px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f7f0;min-height:100vh;display:flex;color:#1a2e1b;}
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:linear-gradient(175deg,var(--green-dark) 0%,var(--green-mid) 55%,var(--green-main) 100%);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;}
.sidebar-logo{padding:28px 24px 20px;font-size:20px;font-weight:800;color:#fff;letter-spacing:1px;border-bottom:1px solid rgba(255,255,255,0.08);}
.sidebar-logo span{color:var(--green-pale);}
.sidebar-logo small{display:block;font-size:9px;font-weight:500;color:rgba(255,255,255,0.35);letter-spacing:2px;margin-top:2px;}
.sidebar-logo .admin-badge{display:inline-block;background:#e53935;color:#fff;font-size:9px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:1px;margin-top:6px;}
.sidebar-user{padding:18px 24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.08);}
.avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#ef9a9a,#e53935);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0;}
.sidebar-user-info .name{font-size:12px;font-weight:600;color:#fff;}
.sidebar-user-info .role{font-size:10px;color:#ef9a9a;font-weight:500;}
.sidebar-nav{flex:1;padding:16px 12px;}
.nav-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;padding:0 12px;margin-bottom:6px;margin-top:14px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);transition:background .2s,color .2s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,0.14);color:#fff;font-weight:600;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;opacity:.8;}
.nav-item .badge-cnt{margin-left:auto;background:#e53935;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,0.6);text-decoration:none;transition:background .2s,color .2s;}
.logout-btn:hover{background:rgba(229,57,53,0.15);color:#ef9a9a;}
.logout-btn svg{width:17px;height:17px;}
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0e8;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(27,58,31,0.06);}
.topbar h1{font-size:18px;font-weight:700;}
.topbar p{font-size:12px;color:#8aaa8b;margin-top:1px;}
.content{padding:28px 32px;flex:1;}
.alert{padding:11px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.alert.success{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:28px;}
.stat-card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);text-align:center;}
.stat-num{font-size:28px;font-weight:800;color:var(--green-mid);line-height:1;}
.stat-label{font-size:11px;color:#8aaa8b;margin-top:4px;font-weight:500;}
.stat-card.red .stat-num{color:#e53935;}
.stat-card.orange .stat-num{color:#fb8c00;}
.tabs{display:flex;gap:6px;margin-bottom:18px;background:#fff;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;}
.tab-btn{padding:7px 18px;border-radius:7px;border:none;font-family:'Poppins',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:background .2s,color .2s;color:#8aaa8b;background:transparent;text-decoration:none;}
.tab-btn.active{background:var(--green-main);color:#fff;}
.tab-btn:hover:not(.active){background:#f0f7f0;color:var(--green-mid);}
.table-wrap{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:28px;}
.table-wrap h3{padding:16px 20px;font-size:14px;font-weight:700;border-bottom:1px solid #f0f7f0;}
table{width:100%;border-collapse:collapse;}
thead th{background:#e8f5e9;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:#4a5e4b;text-transform:uppercase;letter-spacing:.5px;}
tbody td{padding:12px 14px;font-size:13px;border-bottom:1px solid #f0f7f0;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#fafffe;}
.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.status-badge.pending{background:#fff8e1;color:#e65100;}
.status-badge.approved{background:#e8f5e9;color:#2e7d32;}
.status-badge.rejected{background:#ffebee;color:#c62828;}
.type-badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;}
.type-badge.Farmer{background:#e8f5e9;color:#1b5e20;}
.type-badge.Vendor{background:#e3f2fd;color:#0d47a1;}
.action-group{display:flex;gap:6px;}
.btn-sm{padding:5px 12px;border-radius:6px;border:none;font-family:'Poppins',sans-serif;font-size:11px;font-weight:600;cursor:pointer;transition:opacity .2s;}
.btn-approve{background:#e8f5e9;color:#2e7d32;}
.btn-approve:hover{background:#c8e6c9;}
.btn-reject{background:#fff8e1;color:#e65100;}
.btn-reject:hover{background:#ffecb3;}
.btn-delete{background:#ffebee;color:#e53935;}
.btn-delete:hover{background:#ffcdd2;}
.empty{text-align:center;padding:40px;color:#8aaa8b;font-size:13px;}
.order-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.order-status.pending{background:#fff8e1;color:#e65100;}
.order-status.accepted{background:#e8f5e9;color:#2e7d32;}
.order-status.rejected{background:#ffebee;color:#c62828;}
.order-status.completed{background:#e3f2fd;color:#1565c0;}
.order-status.cancelled{background:#f5f5f5;color:#666;}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">ANI<span>TRACK</span><small>FARM SALES TRACKER</small><br/><span class="admin-badge">ADMIN PANEL</span></div>
  <div class="sidebar-user">
    <div class="avatar"><?php echo strtoupper(substr($adminData['first_name'],0,1).substr($adminData['last_name'],0,1)); ?></div>
    <div class="sidebar-user-info">
      <div class="name"><?php echo htmlspecialchars($adminData['first_name'].' '.$adminData['last_name']); ?></div>
      <div class="role">Administrator</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Admin</div>
    <a href="dashboard.php" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
      <?php if ($stats['pending']>0): ?><span class="badge-cnt"><?php echo $stats['pending']; ?></span><?php endif; ?>
    </a>
    <a href="users.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      All Users
    </a>
    <a href="orders.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
      All Orders
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../../frontend/auth/logout.php" class="logout-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div><h1>Admin Dashboard</h1><p id="topDate"></p></div>
  </header>
  <div class="content">
    <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="stats-row">
      <div class="stat-card red"><div class="stat-num"><?php echo $stats['pending']; ?></div><div class="stat-label">Pending Approval</div></div>
      <div class="stat-card"><div class="stat-num"><?php echo $stats['approved']; ?></div><div class="stat-label">Approved Users</div></div>
      <div class="stat-card orange"><div class="stat-num"><?php echo $stats['rejected']; ?></div><div class="stat-label">Rejected</div></div>
      <div class="stat-card"><div class="stat-num"><?php echo $stats['farmers']; ?></div><div class="stat-label">Farmers</div></div>
      <div class="stat-card"><div class="stat-num"><?php echo $stats['vendors']; ?></div><div class="stat-label">Vendors</div></div>
    </div>

    <!-- User Management -->
    <div class="tabs">
      <?php foreach(['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','all'=>'All Users'] as $t=>$label): ?>
      <a href="?tab=<?php echo $t; ?>" class="tab-btn <?php echo $tab===$t?'active':''; ?>"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <h3><?php echo ucfirst($tab); ?> Users (<?php echo count($users); ?>)</h3>
      <table>
        <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Type</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($users)): ?>
          <tr><td colspan="9"><div class="empty">No users found in this category.</div></td></tr>
          <?php else: foreach($users as $i=>$u): ?>
          <tr>
            <td><?php echo $i+1; ?></td>
            <td><strong><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($u['username']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td><?php echo htmlspecialchars($u['phone']); ?></td>
            <td><span class="type-badge <?php echo $u['user_type']; ?>"><?php echo $u['user_type']; ?></span></td>
            <td><span class="status-badge <?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
            <td style="font-size:11px;color:#8aaa8b;"><?php echo date('M d, Y', strtotime($u['created_at']??'now')); ?></td>
            <td>
              <div class="action-group">
                <?php if ($u['status']==='pending'||$u['status']==='rejected'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="approve"/>
                  <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>"/>
                  <button type="submit" class="btn-sm btn-approve">✓ Approve</button>
                </form>
                <?php endif; ?>
                <?php if ($u['status']==='pending'||$u['status']==='approved'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="reject"/>
                  <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>"/>
                  <button type="submit" class="btn-sm btn-reject">✕ Reject</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user permanently?');">
                  <input type="hidden" name="action" value="delete"/>
                  <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>"/>
                  <button type="submit" class="btn-sm btn-delete">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Orders -->
    <div class="table-wrap">
      <h3>Recent Orders</h3>
      <table>
        <thead><tr><th>#</th><th>Vendor</th><th>Farmer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
          <tr><td colspan="8"><div class="empty">No orders yet.</div></td></tr>
          <?php else: foreach($recentOrders as $i=>$o): ?>
          <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo htmlspecialchars($o['vendor_fname'].' '.$o['vendor_lname']); ?></td>
            <td><?php echo htmlspecialchars($o['farmer_fname'].' '.$o['farmer_lname']); ?></td>
            <td><?php echo htmlspecialchars($o['product_name']); ?></td>
            <td><?php echo number_format($o['quantity'],2).' '.$o['unit']; ?></td>
            <td><strong>₱<?php echo number_format($o['total_amount'],2); ?></strong></td>
            <td><span class="order-status <?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span></td>
            <td style="font-size:11px;color:#8aaa8b;"><?php echo date('M d, Y',strtotime($o['ordered_at'])); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
var d=new Date();
document.getElementById('topDate').textContent=d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
</script>
</body>
</html>