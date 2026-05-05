<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit; }
$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed."); }
$uid      = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
// Only Farmers and Vendors can access this
if (!in_array($userType,['Farmer','Vendor'])) { header("Location: ../../auth/login.php"); exit; }

$success=''; $error='';

// ── FARMER: Accept / Reject order ──
if ($userType==='Farmer' && $_SERVER['REQUEST_METHOD']==='POST') {
    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id && in_array($action,['accept','reject','complete'])) {
        // Verify this order belongs to this farmer
        $chk=$conn->prepare("SELECT o.*,i.quantity AS stock FROM orders o JOIN inventory i ON o.inventory_id=i.id WHERE o.id=? AND o.farmer_id=?");
        $chk->bind_param("ii",$order_id,$uid); $chk->execute();
        $ord=$chk->get_result()->fetch_assoc(); $chk->close();
        if ($ord) {
            $newStatus = $action==='accept'?'accepted':($action==='reject'?'rejected':'completed');
            $farmerNotes = trim($_POST['farmer_notes']??'');
            $s=$conn->prepare("UPDATE orders SET status=?,farmer_notes=? WHERE id=?");
            $s->bind_param("ssi",$newStatus,$farmerNotes,$order_id); $s->execute(); $s->close();

            // Notify vendor
            $vendorId = $ord['vendor_id'];
            $prodName = $ord['product_name'];
            $farmerName = $_SESSION['fullname'];
            if ($newStatus==='accepted') {
                $msg="✅ Your order for $prodName was accepted by $farmerName.";
            } elseif ($newStatus==='rejected') {
                $msg="❌ Your order for $prodName was rejected by $farmerName.";
            } else {
                $msg="🎉 Your order for $prodName has been marked as completed by $farmerName.";
            }
            $ns=$conn->prepare("INSERT INTO notifications (user_id,type,message,link) VALUES (?,'order',?,?)");
            $link='vendor_orders.php';
            $ns->bind_param("iss",$vendorId,$msg,$link); $ns->execute(); $ns->close();

            // Mark all vendor notifications as read
            $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");

            $success = "Order ".ucfirst($newStatus)." successfully.";
        }
    }
}

// ── VENDOR: Cancel order ──
if ($userType==='Vendor' && $_SERVER['REQUEST_METHOD']==='POST') {
    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($action==='cancel' && $order_id) {
        $s=$conn->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND vendor_id=? AND status='pending'");
        $s->bind_param("ii",$order_id,$uid); $s->execute(); $s->close();
        $success="Order cancelled.";
    }
    // Mark notifications read
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid AND is_read=0");
}

// ── FETCH ORDERS ──
$statusFilter = $_GET['status'] ?? 'all';
$validStatus  = ['all','pending','accepted','rejected','completed','cancelled'];
if (!in_array($statusFilter,$validStatus)) $statusFilter='all';

if ($userType==='Farmer') {
    $sql="SELECT o.*, u.first_name AS vendor_fname, u.last_name AS vendor_lname, u.phone AS vendor_phone
          FROM orders o JOIN users u ON o.vendor_id=u.id
          WHERE o.farmer_id=?".($statusFilter!=='all'?" AND o.status='$statusFilter'":"")."
          ORDER BY o.ordered_at DESC";
} else {
    $sql="SELECT o.*, u.first_name AS farmer_fname, u.last_name AS farmer_lname, u.phone AS farmer_phone
          FROM orders o JOIN users u ON o.farmer_id=u.id
          WHERE o.vendor_id=?".($statusFilter!=='all'?" AND o.status='$statusFilter'":"")."
          ORDER BY o.ordered_at DESC";
}
$s=$conn->prepare($sql); $s->bind_param("i",$uid); $s->execute();
$orders=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

// ── ORDER COUNTS ──
$counts=[];
foreach(['all','pending','accepted','rejected','completed','cancelled'] as $st) {
    $w = $userType==='Farmer' ? "farmer_id=$uid" : "vendor_id=$uid";
    $ww = $st==='all' ? $w : "$w AND status='$st'";
    $r=$conn->query("SELECT COUNT(*) FROM orders WHERE $ww"); $counts[$st]=$r->fetch_row()[0];
}

// ── USER DATA ──
$userData = $conn->query("SELECT first_name,last_name,user_type,avatar FROM users WHERE id=$uid")->fetch_assoc();
$displayName = htmlspecialchars($userData['first_name'].' '.$userData['last_name']);

// Mark notifications read on page load
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid AND is_read=0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Orders</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--green-dark:#1b3a1f;--green-mid:#2e7d32;--green-main:#43a047;--green-light:#66bb6a;--green-pale:#a5d6a7;--shadow:0 4px 24px rgba(27,58,31,0.10);--radius:14px;--sidebar-w:240px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f0f7f0;min-height:100vh;display:flex;color:#1a2e1b;}
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:linear-gradient(175deg,var(--green-dark) 0%,var(--green-mid) 55%,var(--green-main) 100%);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;}
.sidebar-logo{padding:28px 24px 20px;font-size:20px;font-weight:800;color:#fff;letter-spacing:1px;border-bottom:1px solid rgba(255,255,255,0.08);}
.sidebar-logo span{color:var(--green-pale);}
.sidebar-logo small{display:block;font-size:9px;font-weight:400;color:rgba(255,255,255,0.35);letter-spacing:2px;margin-top:2px;}
.sidebar-user{padding:18px 24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.08);}
.avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green-pale),var(--green-light));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--green-dark);flex-shrink:0;}
.sidebar-user-info .name{font-size:12px;font-weight:600;color:#fff;}
.sidebar-user-info .role{font-size:10px;color:var(--green-pale);}
.sidebar-nav{flex:1;padding:16px 12px;}
.nav-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;padding:0 12px;margin-bottom:6px;margin-top:14px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);transition:background .2s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,0.14);color:#fff;font-weight:600;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.6);text-decoration:none;transition:background .2s;}
.logout-btn:hover{background:rgba(229,57,53,0.15);color:#ef9a9a;}
.logout-btn svg{width:17px;height:17px;}
.main{margin-left:var(--sidebar-w);flex:1;}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0e8;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(27,58,31,0.06);}
.topbar h1{font-size:18px;font-weight:700;}
.topbar p{font-size:12px;color:#8aaa8b;margin-top:1px;}
.content{padding:28px 32px;}
.alert{padding:11px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.alert.success{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}
.tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;}
.tab-btn{padding:7px 14px;border-radius:7px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;color:#8aaa8b;background:#fff;border:1.5px solid #e0e0e0;transition:all .2s;}
.tab-btn.active{background:var(--green-main);color:#fff;border-color:var(--green-main);}
.tab-btn .cnt{background:rgba(0,0,0,0.1);padding:1px 6px;border-radius:8px;font-size:10px;margin-left:4px;}
.tab-btn.active .cnt{background:rgba(255,255,255,0.25);}
/* Order cards */
.orders-list{display:flex;flex-direction:column;gap:14px;}
.order-card{background:#fff;border-radius:14px;padding:20px 24px;box-shadow:var(--shadow);border-left:4px solid #e0e0e0;}
.order-card.pending  {border-left-color:#fb8c00;}
.order-card.accepted {border-left-color:#43a047;}
.order-card.rejected {border-left-color:#e53935;}
.order-card.completed{border-left-color:#1e88e5;}
.order-card.cancelled{border-left-color:#bdbdbd;}
.order-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;}
.order-title{font-size:15px;font-weight:700;color:#1a2e1b;}
.order-sub{font-size:12px;color:#8aaa8b;margin-top:3px;}
.order-status{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;}
.order-status.pending  {background:#fff8e1;color:#e65100;}
.order-status.accepted {background:#e8f5e9;color:#2e7d32;}
.order-status.rejected {background:#ffebee;color:#c62828;}
.order-status.completed{background:#e3f2fd;color:#1565c0;}
.order-status.cancelled{background:#f5f5f5;color:#666;}
.order-details{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px;}
.od-item label{font-size:10px;font-weight:600;color:#8aaa8b;text-transform:uppercase;display:block;margin-bottom:2px;}
.od-item span{font-size:13px;font-weight:600;color:#1a2e1b;}
.od-item .amount{color:var(--green-mid);font-size:16px;font-weight:800;}
.order-notes{background:#f9f9f9;border-radius:8px;padding:10px 14px;font-size:12px;color:#666;margin-bottom:14px;}
.order-notes strong{color:#333;}
.order-actions{display:flex;gap:8px;flex-wrap:wrap;}
.btn-accept{padding:8px 18px;background:#e8f5e9;color:#2e7d32;border:none;border-radius:7px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:background .2s;}
.btn-accept:hover{background:#c8e6c9;}
.btn-reject-o{padding:8px 18px;background:#fff8e1;color:#e65100;border:none;border-radius:7px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;cursor:pointer;}
.btn-reject-o:hover{background:#ffecb3;}
.btn-complete{padding:8px 18px;background:#e3f2fd;color:#1565c0;border:none;border-radius:7px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;cursor:pointer;}
.btn-cancel-o{padding:8px 16px;background:#ffebee;color:#e53935;border:none;border-radius:7px;font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;cursor:pointer;}
.notes-input{width:100%;padding:7px 10px;border:1.5px solid #cde8ce;border-radius:6px;font-size:12px;font-family:'Poppins',sans-serif;margin-bottom:6px;outline:none;}
.empty{text-align:center;padding:60px 20px;color:#8aaa8b;}
.empty .icon{font-size:48px;margin-bottom:12px;}
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
    <?php if ($userType==='Farmer'): ?>
    <div class="nav-label">Main</div>
    <a href="dashboard.php"  class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
    <a href="sales.php"      class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Sales</a>
    <a href="inventory.php"  class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Inventory</a>
    <a href="customers.php"  class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Customers</a>
    <a href="orders.php"     class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>Incoming Orders</a>
    <?php else: ?>
    <div class="nav-label">Marketplace</div>
    <a href="marketplace.php"  class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Marketplace</a>
    <a href="orders.php"       class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>My Orders</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="../../auth/logout.php" class="logout-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div>
      <h1><?php echo $userType==='Farmer' ? '📥 Incoming Orders' : '📦 My Orders'; ?></h1>
      <p><?php echo $userType==='Farmer' ? 'Review and respond to vendor orders' : 'Track your orders from farmers'; ?></p>
    </div>
  </header>
  <div class="content">
    <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="tabs">
      <?php foreach(['all'=>'All','pending'=>'⏳ Pending','accepted'=>'✅ Accepted','rejected'=>'❌ Rejected','completed'=>'🎉 Completed','cancelled'=>'🚫 Cancelled'] as $st=>$label): ?>
      <a href="?status=<?php echo $st; ?>" class="tab-btn <?php echo $statusFilter===$st?'active':''; ?>">
        <?php echo $label; ?><span class="cnt"><?php echo $counts[$st]; ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="orders-list">
      <?php if (empty($orders)): ?>
      <div class="empty">
        <div class="icon"><?php echo $userType==='Farmer'?'📭':'🛒'; ?></div>
        <p><?php echo $userType==='Farmer'?'No incoming orders yet.':'No orders placed yet. <a href="marketplace.php" style="color:var(--green-main);">Browse the marketplace</a>!'; ?></p>
      </div>
      <?php else: foreach($orders as $o): ?>
      <div class="order-card <?php echo $o['status']; ?>">
        <div class="order-header">
          <div>
            <div class="order-title"><?php echo htmlspecialchars($o['product_name']); ?></div>
            <div class="order-sub">
              <?php if ($userType==='Farmer'): ?>
                Ordered by: <strong><?php echo htmlspecialchars($o['vendor_fname'].' '.$o['vendor_lname']); ?></strong>
                · <?php echo htmlspecialchars($o['vendor_phone']??''); ?>
              <?php else: ?>
                Farmer: <strong><?php echo htmlspecialchars($o['farmer_fname'].' '.$o['farmer_lname']); ?></strong>
                · <?php echo htmlspecialchars($o['farmer_phone']??''); ?>
              <?php endif; ?>
              · <?php echo date('M d, Y h:i A',strtotime($o['ordered_at'])); ?>
            </div>
          </div>
          <span class="order-status <?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span>
        </div>

        <div class="order-details">
          <div class="od-item"><label>Quantity</label><span><?php echo number_format($o['quantity'],2).' '.$o['unit']; ?></span></div>
          <div class="od-item"><label>Unit Price</label><span>₱<?php echo number_format($o['unit_price'],2); ?></span></div>
          <div class="od-item"><label>Total</label><span class="amount">₱<?php echo number_format($o['total_amount'],2); ?></span></div>
          <div class="od-item"><label>Order Date</label><span><?php echo date('M d, Y',strtotime($o['ordered_at'])); ?></span></div>
        </div>

        <?php if ($o['vendor_notes']): ?>
        <div class="order-notes"><strong>Vendor Notes:</strong> <?php echo htmlspecialchars($o['vendor_notes']); ?></div>
        <?php endif; ?>
        <?php if ($o['farmer_notes']): ?>
        <div class="order-notes" style="background:#e8f5e9;"><strong>Farmer Notes:</strong> <?php echo htmlspecialchars($o['farmer_notes']); ?></div>
        <?php endif; ?>

        <!-- Farmer actions -->
        <?php if ($userType==='Farmer' && $o['status']==='pending'): ?>
        <div class="order-actions">
          <form method="POST" style="flex:1;">
            <input type="hidden" name="action" value="accept"/>
            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"/>
            <input type="text" name="farmer_notes" class="notes-input" placeholder="Optional note to vendor…"/>
            <button type="submit" class="btn-accept">✓ Accept Order</button>
          </form>
          <form method="POST">
            <input type="hidden" name="action" value="reject"/>
            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"/>
            <input type="text" name="farmer_notes" class="notes-input" placeholder="Reason for rejection…"/>
            <button type="submit" class="btn-reject-o">✕ Reject</button>
          </form>
        </div>
        <?php elseif ($userType==='Farmer' && $o['status']==='accepted'): ?>
        <div class="order-actions">
          <form method="POST">
            <input type="hidden" name="action" value="complete"/>
            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"/>
            <button type="submit" class="btn-complete">🎉 Mark as Completed</button>
          </form>
        </div>
        <?php elseif ($userType==='Vendor' && $o['status']==='pending'): ?>
        <div class="order-actions">
          <form method="POST" onsubmit="return confirm('Cancel this order?');">
            <input type="hidden" name="action" value="cancel"/>
            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"/>
            <button type="submit" class="btn-cancel-o">🚫 Cancel Order</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
</body>
</html>