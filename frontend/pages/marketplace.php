<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Vendor') {
    header("Location: ../../auth/login.php"); exit;
}
$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed."); }
$uid = $_SESSION['user_id'];

$success=''; $error='';

// ── PLACE ORDER ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='order') {
    $inv_id   = (int)($_POST['inventory_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    if ($inv_id && $quantity > 0) {
        // Get inventory item + farmer info
        $s = $conn->prepare("SELECT i.*, u.id AS farmer_id FROM inventory i JOIN users u ON i.user_id=u.id WHERE i.id=? AND i.quantity>=? AND u.status='approved'");
        $s->bind_param("id",$inv_id,$quantity); $s->execute();
        $inv = $s->get_result()->fetch_assoc(); $s->close();

        if ($inv) {
            $stmt = $conn->prepare("INSERT INTO orders (vendor_id,farmer_id,inventory_id,product_name,quantity,unit,unit_price,vendor_notes,status) VALUES (?,?,?,?,?,?,?,'pending',?)");
            // Note: status is positional 8th param but it's a literal — rewrite:
            $stmt = $conn->prepare("INSERT INTO orders (vendor_id,farmer_id,inventory_id,product_name,quantity,unit,unit_price,vendor_notes) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iiisddss",$uid,$inv['farmer_id'],$inv_id,$inv['name'],$quantity,$inv['unit'],$inv['price'],$notes);
            if ($stmt->execute()) {
                // Notify farmer
                $vendorName = $_SESSION['fullname'];
                $msg = "📦 New order from $vendorName for {$quantity} {$inv['unit']} of {$inv['name']}.";
                $ns = $conn->prepare("INSERT INTO notifications (user_id,type,message,link) VALUES (?,?,?,?)");
                $link = 'orders.php'; $type = 'order';
                $ns->bind_param("isss",$inv['farmer_id'],$type,$msg,$link); $ns->execute(); $ns->close();
                $success = "Order placed successfully! The farmer will review your request.";
            } else { $error = "Failed to place order. Please try again."; }
            $stmt->close();
        } else { $error = "Item not available or insufficient stock."; }
    } else { $error = "Please select an item and enter a valid quantity."; }
}

// ── FETCH FARMERS + THEIR INVENTORY ──
$search   = trim($_GET['q']        ?? '');
$category = trim($_GET['category'] ?? '');
$farmer_f = (int)($_GET['farmer']  ?? 0);

$sql = "SELECT i.*, u.first_name, u.last_name, u.id AS farmer_id 
        FROM inventory i 
        JOIN users u ON i.user_id=u.id 
        WHERE u.user_type='Farmer' AND u.status='approved' AND i.quantity > 0";
$params=[]; $types="";
if ($search)   { $sql.=" AND (i.name LIKE ? OR i.category LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; $types.="ss"; }
if ($category) { $sql.=" AND i.category=?"; $params[]=$category; $types.="s"; }
if ($farmer_f) { $sql.=" AND u.id=?"; $params[]=$farmer_f; $types.="i"; }
$sql.=" ORDER BY u.first_name, i.name";
if ($params) { $s=$conn->prepare($sql); $s->bind_param($types,...$params); $s->execute(); $items=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close(); }
else { $items=$conn->query($sql)->fetch_all(MYSQLI_ASSOC); }

// ── FARMERS LIST for filter ──
$farmers = $conn->query("SELECT id,first_name,last_name FROM users WHERE user_type='Farmer' AND status='approved' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$categories = ['Vegetables','Fruits','Grains','Livestock','Poultry','Dairy','Herbs','Other'];

// ── MY ORDERS ──
$myOrders = $conn->query("
    SELECT o.*, u.first_name AS farmer_fname, u.last_name AS farmer_lname
    FROM orders o JOIN users u ON o.farmer_id=u.id
    WHERE o.vendor_id=$uid ORDER BY o.ordered_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ── USER ──
$vendorData = $conn->query("SELECT first_name,last_name,user_type,avatar FROM users WHERE id=$uid")->fetch_assoc();
$displayName = htmlspecialchars($vendorData['first_name'].' '.$vendorData['last_name']);

// Notif count
$notifCount = 0;
$nr=$conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$nr->bind_param("i",$uid); $nr->execute(); $nr->bind_result($notifCount); $nr->fetch(); $nr->close();

// Selected item for order modal
$selectedItem = null;
if (isset($_GET['order_item'])) {
    $oi=(int)$_GET['order_item'];
    $os=$conn->prepare("SELECT i.*,u.first_name,u.last_name,u.id AS farmer_id FROM inventory i JOIN users u ON i.user_id=u.id WHERE i.id=? AND u.status='approved'");
    $os->bind_param("i",$oi); $os->execute();
    $selectedItem=$os->get_result()->fetch_assoc(); $os->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Marketplace</title>
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
.sidebar-user-info .role{font-size:10px;color:var(--green-pale);font-weight:500;}
.sidebar-nav{flex:1;padding:16px 12px;}
.nav-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;padding:0 12px;margin-bottom:6px;margin-top:14px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.7);transition:background .2s,color .2s;margin-bottom:2px;text-decoration:none;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{background:rgba(255,255,255,0.14);color:#fff;font-weight:600;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;}
.nav-item .notif{margin-left:auto;background:#e53935;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.6);text-decoration:none;transition:background .2s,color .2s;}
.logout-btn:hover{background:rgba(229,57,53,0.15);color:#ef9a9a;}
.logout-btn svg{width:17px;height:17px;}
.main{margin-left:var(--sidebar-w);flex:1;}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0e8;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(27,58,31,0.06);}
.topbar h1{font-size:18px;font-weight:700;}
.topbar p{font-size:12px;color:#8aaa8b;margin-top:1px;}
.content{padding:28px 32px;}
.alert{padding:11px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.alert.success{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}
.alert.error{background:#ffebee;border:1px solid #ef9a9a;color:#c62828;}
/* Filter bar */
.filter-bar{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);margin-bottom:24px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.filter-bar label{display:block;font-size:11px;font-weight:600;color:#555;margin-bottom:4px;}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1.5px solid #d8eed8;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;color:#333;}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--green-main);}
.btn-filter{padding:9px 18px;background:var(--green-main);color:#fff;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;}
.btn-clear{padding:9px 14px;background:#f0f0f0;color:#555;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;}
/* Product grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;margin-bottom:32px;}
.product-card{background:#fff;border-radius:14px;padding:20px;box-shadow:var(--shadow);border:1px solid transparent;transition:transform .2s,border-color .2s,box-shadow .2s;display:flex;flex-direction:column;}
.product-card:hover{transform:translateY(-3px);border-color:var(--green-pale);box-shadow:0 8px 32px rgba(27,58,31,0.14);}
.pc-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;}
.pc-icon{width:44px;height:44px;border-radius:12px;background:#e8f5e9;display:flex;align-items:center;justify-content:center;font-size:22px;}
.pc-stock{font-size:10px;font-weight:600;padding:3px 9px;border-radius:6px;}
.pc-stock.in{background:#e8f5e9;color:#2e7d32;}
.pc-stock.low{background:#fff3e0;color:#e65100;}
.pc-name{font-size:15px;font-weight:700;color:#1a2e1b;margin-bottom:4px;}
.pc-category{font-size:11px;color:#8aaa8b;margin-bottom:8px;}
.pc-farmer{font-size:12px;color:#4a5e4b;font-weight:600;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.pc-farmer svg{width:13px;height:13px;color:var(--green-main);}
.pc-price{font-size:20px;font-weight:800;color:var(--green-mid);margin-bottom:4px;}
.pc-price span{font-size:11px;font-weight:400;color:#8aaa8b;}
.pc-qty{font-size:11px;color:#8aaa8b;margin-bottom:14px;}
.pc-qty strong{color:#4a5e4b;}
.btn-order{width:100%;padding:9px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .2s;margin-top:auto;}
.btn-order:hover{opacity:.9;}
.empty-state{text-align:center;padding:60px 20px;color:#8aaa8b;grid-column:1/-1;}
.empty-state .icon{font-size:48px;margin-bottom:12px;}
.empty-state p{font-size:13px;}
/* My recent orders */
.orders-preview{background:#fff;border-radius:14px;padding:22px;box-shadow:var(--shadow);}
.orders-preview h3{font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;}
.orders-preview h3 a{font-size:12px;color:var(--green-main);font-weight:600;text-decoration:none;}
.order-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0f7f0;}
.order-row:last-child{border-bottom:none;}
.order-info .pname{font-size:13px;font-weight:600;color:#1a2e1b;}
.order-info .sub{font-size:11px;color:#8aaa8b;margin-top:2px;}
.order-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.order-status.pending{background:#fff8e1;color:#e65100;}
.order-status.accepted{background:#e8f5e9;color:#2e7d32;}
.order-status.rejected{background:#ffebee;color:#c62828;}
.order-status.completed{background:#e3f2fd;color:#1565c0;}
.order-status.cancelled{background:#f5f5f5;color:#666;}
/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal{background:#fff;border-radius:16px;padding:28px 30px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;}
.modal h2{font-size:16px;font-weight:700;color:#1a2e1b;margin-bottom:18px;}
.modal .close-btn{position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#8aaa8b;}
.modal .close-btn svg{width:18px;height:18px;}
.product-summary{background:#f0f7f0;border-radius:10px;padding:14px 16px;margin-bottom:16px;}
.product-summary .pname{font-size:15px;font-weight:700;color:#1a2e1b;}
.product-summary .by{font-size:12px;color:#8aaa8b;margin-top:2px;}
.product-summary .price-row{display:flex;justify-content:space-between;align-items:center;margin-top:10px;}
.product-summary .price{font-size:18px;font-weight:800;color:var(--green-mid);}
.product-summary .avail{font-size:11px;color:#8aaa8b;}
.form-field{margin-bottom:14px;}
.form-field label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px;}
.form-field input,.form-field textarea{width:100%;padding:9px 12px;border:1.5px solid #cde8ce;border-radius:7px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s;}
.form-field input:focus,.form-field textarea:focus{border-color:var(--green-main);background:#fff;}
.total-preview{background:#e8f5e9;border-radius:9px;padding:12px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;}
.total-preview span{font-size:13px;color:#4a5e4b;font-weight:600;}
.total-preview strong{font-size:20px;font-weight:800;color:var(--green-mid);}
.modal-actions{display:flex;gap:10px;}
.btn-cancel{flex:1;padding:10px;background:#f5f5f5;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;color:#777;}
.btn-save{flex:2;padding:10px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">ANI<span>TRACK</span><small>FARM SALES TRACKER</small></div>
  <div class="sidebar-user">
    <div class="avatar"><?php echo strtoupper(substr($vendorData['first_name'],0,1).substr($vendorData['last_name'],0,1)); ?></div>
    <div class="sidebar-user-info">
      <div class="name"><?php echo $displayName; ?></div>
      <div class="role">Vendor</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Marketplace</div>
    <a href="marketplace.php" class="nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      Marketplace
    </a>
    <a href="vendor_orders.php" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      My Orders
      <?php if ($notifCount>0): ?><span class="notif"><?php echo $notifCount; ?></span><?php endif; ?>
    </a>
    <div class="nav-label">Browse</div>
    <?php foreach($farmers as $f): ?>
    <a href="?farmer=<?php echo $f['id']; ?>" class="nav-item <?php echo $farmer_f==$f['id']?'active':''; ?>" style="font-size:12px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <?php echo htmlspecialchars($f['first_name'].' '.$f['last_name']); ?>
    </a>
    <?php endforeach; ?>
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
    <div><h1>🛒 Farmer Marketplace</h1><p>Browse and order directly from approved farmers</p></div>
  </header>
  <div class="content">
    <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="GET">
      <div class="filter-bar">
        <div>
          <label>Search</label>
          <input type="text" name="q" placeholder="Product or category…" value="<?php echo htmlspecialchars($search); ?>"/>
        </div>
        <div>
          <label>Category</label>
          <select name="category">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat; ?>" <?php echo $category===$cat?'selected':''; ?>><?php echo $cat; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Farmer</label>
          <select name="farmer">
            <option value="">All Farmers</option>
            <?php foreach($farmers as $f): ?>
            <option value="<?php echo $f['id']; ?>" <?php echo $farmer_f==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['first_name'].' '.$f['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-filter">Search</button>
        <?php if ($search||$category||$farmer_f): ?>
        <a href="marketplace.php" class="btn-clear">Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="grid">
      <?php if (empty($items)): ?>
      <div class="empty-state">
        <div class="icon">🌿</div>
        <p>No products available right now. Check back later!</p>
      </div>
      <?php else: foreach($items as $item):
        $stockLevel = $item['quantity'] <= ($item['low_stock']??10) ? 'low' : 'in';
        $catIcons = ['Vegetables'=>'🥬','Fruits'=>'🍎','Grains'=>'🌾','Livestock'=>'🐄','Poultry'=>'🐓','Dairy'=>'🥛','Herbs'=>'🌿','Other'=>'📦'];
        $icon = $catIcons[$item['category']] ?? '📦';
      ?>
      <div class="product-card">
        <div class="pc-top">
          <div class="pc-icon"><?php echo $icon; ?></div>
          <span class="pc-stock <?php echo $stockLevel; ?>"><?php echo $stockLevel==='in'?'In Stock':'Low Stock'; ?></span>
        </div>
        <div class="pc-name"><?php echo htmlspecialchars($item['name']); ?></div>
        <div class="pc-category"><?php echo htmlspecialchars($item['category']??'Uncategorized'); ?></div>
        <div class="pc-farmer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?php echo htmlspecialchars($item['first_name'].' '.$item['last_name']); ?>
        </div>
        <div class="pc-price">₱<?php echo number_format($item['price'],2); ?> <span>per <?php echo htmlspecialchars($item['unit']); ?></span></div>
        <div class="pc-qty">Available: <strong><?php echo number_format($item['quantity'],2).' '.htmlspecialchars($item['unit']); ?></strong></div>
        <button class="btn-order" onclick="openOrder(<?php echo $item['id']; ?>,'<?php echo addslashes($item['name']); ?>','<?php echo addslashes($item['first_name'].' '.$item['last_name']); ?>',<?php echo $item['price']; ?>,<?php echo $item['quantity']; ?>,'<?php echo $item['unit']; ?>')">
          + Place Order
        </button>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- My Recent Orders -->
    <div class="orders-preview">
      <h3>My Recent Orders <a href="vendor_orders.php">View All →</a></h3>
      <?php if (empty($myOrders)): ?>
      <p style="font-size:13px;color:#8aaa8b;text-align:center;padding:20px 0;">No orders yet. Browse products above to get started!</p>
      <?php else: foreach($myOrders as $o): ?>
      <div class="order-row">
        <div class="order-info">
          <div class="pname"><?php echo htmlspecialchars($o['product_name']); ?></div>
          <div class="sub">From: <?php echo htmlspecialchars($o['farmer_fname'].' '.$o['farmer_lname']); ?> · <?php echo number_format($o['quantity'],2).' '.$o['unit']; ?> · <?php echo date('M d',strtotime($o['ordered_at'])); ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
          <strong style="color:var(--green-mid);">₱<?php echo number_format($o['total_amount'],2); ?></strong>
          <span class="order-status <?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</div>

<!-- Order Modal -->
<div class="modal-bg" id="orderModal">
  <div class="modal">
    <button class="close-btn" onclick="closeOrder()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <h2>Place Order</h2>
    <form method="POST">
      <input type="hidden" name="action" value="order"/>
      <input type="hidden" name="inventory_id" id="m_inv_id"/>
      <div class="product-summary">
        <div class="pname" id="m_name"></div>
        <div class="by" id="m_by"></div>
        <div class="price-row">
          <div class="price" id="m_price"></div>
          <div class="avail" id="m_avail"></div>
        </div>
      </div>
      <div class="form-field">
        <label>Quantity (<span id="m_unit"></span>) *</label>
        <input type="number" name="quantity" id="m_qty" placeholder="Enter quantity" step="0.01" min="0.01" required oninput="calcTotal()"/>
      </div>
      <div class="total-preview">
        <span>Estimated Total</span>
        <strong id="m_total">₱0.00</strong>
      </div>
      <div class="form-field">
        <label>Notes for Farmer (optional)</label>
        <textarea name="notes" rows="2" placeholder="Delivery instructions, preferred date, etc."></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeOrder()">Cancel</button>
        <button type="submit" class="btn-save">📦 Place Order</button>
      </div>
    </form>
  </div>
</div>

<script>
var _price=0, _max=0;
function openOrder(id,name,farmer,price,maxQty,unit){
  _price=price; _max=maxQty;
  document.getElementById('m_inv_id').value=id;
  document.getElementById('m_name').textContent=name;
  document.getElementById('m_by').textContent='From: '+farmer;
  document.getElementById('m_price').textContent='₱'+price.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})+' / '+unit;
  document.getElementById('m_avail').textContent='Available: '+maxQty.toFixed(2)+' '+unit;
  document.getElementById('m_unit').textContent=unit;
  document.getElementById('m_qty').max=maxQty;
  document.getElementById('m_qty').value='';
  document.getElementById('m_total').textContent='₱0.00';
  document.getElementById('orderModal').classList.add('open');
}
function closeOrder(){ document.getElementById('orderModal').classList.remove('open'); }
function calcTotal(){
  var q=parseFloat(document.getElementById('m_qty').value)||0;
  document.getElementById('m_total').textContent='₱'+(q*_price).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
}
document.getElementById('orderModal').addEventListener('click',function(e){if(e.target===this)closeOrder();});
</script>
</body>
</html>