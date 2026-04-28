<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$host="localhost"; $db="anitrack"; $user="root"; $pass="";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) { die("Connection failed: ".$conn->connect_error); }

$uid = $_SESSION['user_id'];
$errors=[]; $success='';
// ── USER (needed early for PDF) ──
$stmt=$conn->prepare("SELECT first_name,last_name,username,email,phone,user_type,avatar FROM users WHERE id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$userData=$stmt->get_result()->fetch_assoc(); $stmt->close();
$displayName=htmlspecialchars($userData['first_name'].' '.$userData['last_name']);
$userType=htmlspecialchars($userData['user_type']);

// ── DOWNLOAD PDF ──
if (isset($_GET['download']) && $_GET['download']==='pdf') {
    $search        = trim($_GET['q']       ?? '');
    $dateFrom      = trim($_GET['from']    ?? '');
    $dateTo        = trim($_GET['to']      ?? '');
    $paymentFilter = trim($_GET['payment'] ?? 'all');
    $sql2="SELECT s.*, c.full_name AS customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.user_id=?";
    $params2=[$uid]; $types2="i";
    if ($search)   { $sql2.=" AND s.product_name LIKE ?"; $params2[]="%$search%"; $types2.="s"; }
    if ($dateFrom) { $sql2.=" AND s.sale_date >= ?";      $params2[]=$dateFrom;   $types2.="s"; }
    if ($dateTo)   { $sql2.=" AND s.sale_date <= ?";      $params2[]=$dateTo;     $types2.="s"; }
    if ($paymentFilter!=='all') { $sql2.=" AND s.payment_status = ?"; $params2[]=$paymentFilter; $types2.="s"; }
    $sql2.=" ORDER BY s.created_at DESC";
    $pstmt=$conn->prepare($sql2);
    $pstmt->bind_param($types2,...$params2); $pstmt->execute();
    $pdfRows=$pstmt->get_result()->fetch_all(MYSQLI_ASSOC); $pstmt->close();

    $totalRevenue   = array_sum(array_column($pdfRows,'total_amount'));
    $totalCollected = array_sum(array_column($pdfRows,'amount_paid'));
    $totalBalance   = array_sum(array_column($pdfRows,'balance'));

    $filterLabel = '';
    if ($dateFrom && $dateTo) $filterLabel = date('M d, Y',strtotime($dateFrom)).' - '.date('M d, Y',strtotime($dateTo));
    elseif ($dateFrom)        $filterLabel = 'From '.date('M d, Y',strtotime($dateFrom));
    elseif ($dateTo)          $filterLabel = 'Until '.date('M d, Y',strtotime($dateTo));
    else                      $filterLabel = 'All Time';
    if ($paymentFilter!=='all') $filterLabel .= ' | '.ucfirst($paymentFilter).' Only';
    if ($search)                $filterLabel .= ' | Product: "'.htmlspecialchars($search).'"';

    header('Content-Type: text/html; charset=utf-8');
    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<title>ANI-TRACK Sales Report</title>
<style>
@page{size:A4 landscape;margin:18mm 14mm;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,sans-serif;font-size:11px;color:#1a2e1b;background:#fff;}
.no-print{background:#1b3a1f;padding:12px 20px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:99;}
.no-print span{color:#a5d6a7;font-size:13px;font-weight:700;}
.no-print button{border:none;padding:8px 20px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;}
.btn-print{background:#43a047;color:#fff;margin-left:auto;}
.btn-close{background:rgba(255,255,255,0.1);color:#fff;}
.wrap{padding:8mm 2mm;}
.header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:3px solid #43a047;}
.logo{font-size:26px;font-weight:900;color:#2e7d32;letter-spacing:1px;}
.logo span{color:#a5d6a7;}
.logo small{display:block;font-size:9px;font-weight:400;color:#888;letter-spacing:2px;margin-top:2px;}
.report-title{text-align:right;}
.report-title h2{font-size:16px;font-weight:700;color:#1b3a1f;}
.report-title p{font-size:10px;color:#888;margin-top:3px;}
.meta{display:flex;gap:10px;margin-bottom:14px;}
.meta-box{flex:1;background:#f1f8f1;border-radius:8px;padding:10px 14px;border-left:4px solid #43a047;}
.meta-box.red{border-left-color:#e53935;background:#fff5f5;}
.meta-box.blue{border-left-color:#1e88e5;background:#f0f6ff;}
.meta-box label{font-size:9px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:3px;}
.meta-box strong{font-size:15px;font-weight:700;color:#1b3a1f;}
.meta-box.red strong{color:#c62828;}
.meta-box.blue strong{color:#1565c0;}
.filter-bar{background:#e8f5e9;border-radius:6px;padding:7px 12px;font-size:10px;color:#2e7d32;margin-bottom:14px;font-weight:600;}
.filter-bar span{color:#555;font-weight:400;margin-left:6px;}
table{width:100%;border-collapse:collapse;font-size:10px;}
thead tr{background:#2e7d32;}
thead th{color:#fff;padding:8px;text-align:left;font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
tbody tr:nth-child(even){background:#f8fdf8;}
tbody tr:nth-child(odd){background:#fff;}
tbody td{padding:7px 8px;border-bottom:1px solid #e8f0e8;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
.amount{font-weight:700;color:#2e7d32;}
.balance{font-weight:700;color:#e65100;}
.badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:9px;font-weight:700;}
.badge.paid{background:#e8f5e9;color:#2e7d32;}
.badge.partial{background:#fff3e0;color:#e65100;}
.badge.unpaid{background:#ffebee;color:#c62828;}
.method-tag{display:inline-block;padding:2px 7px;border-radius:5px;background:#f3f4f6;color:#555;font-size:9px;font-weight:600;}
tfoot tr{background:#1b3a1f;}
tfoot td{padding:9px 8px;color:#fff;font-weight:700;font-size:10px;}
.footer-bar{margin-top:16px;padding-top:10px;border-top:1px solid #d0e8d0;display:flex;justify-content:space-between;font-size:9px;color:#aaa;}
@media print{.no-print{display:none!important;}body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style>
</head>
<body>
<div class="no-print">
  <span>&#128196; ANI-TRACK Sales Report</span>
  <button class="btn-print" onclick="window.print()">&#11015; Save as PDF</button>
  <button class="btn-close" onclick="window.close()">&#10005; Close</button>
</div>
<div class="wrap">
  <div class="header">
    <div>
      <div class="logo">ANI<span>TRACK</span><small>FARM SALES TRACKER</small></div>
      <div style="font-size:10px;color:#666;margin-top:6px;">Prepared for: <strong><?php echo htmlspecialchars($userData['first_name'].' '.$userData['last_name']); ?></strong> (<?php echo htmlspecialchars($userData['user_type']); ?>)</div>
    </div>
    <div class="report-title">
      <h2>Sales Report</h2>
      <p>Generated: <?php echo date('F d, Y \a\t h:i A'); ?></p>
      <p style="margin-top:4px;">Total Records: <strong><?php echo count($pdfRows); ?></strong></p>
    </div>
  </div>
  <div class="meta">
    <div class="meta-box"><label>Total Revenue</label><strong>&#8369;<?php echo number_format($totalRevenue,2); ?></strong></div>
    <div class="meta-box blue"><label>Total Collected</label><strong>&#8369;<?php echo number_format($totalCollected,2); ?></strong></div>
    <div class="meta-box red"><label>Total Balance</label><strong>&#8369;<?php echo number_format($totalBalance,2); ?></strong></div>
    <div class="meta-box"><label>Transactions</label><strong><?php echo count($pdfRows); ?></strong></div>
  </div>
  <div class="filter-bar">Filter: <span><?php echo $filterLabel; ?></span></div>
  <table>
    <thead><tr><th>#</th><th>Date</th><th>Product</th><th>Customer</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Method</th><th>Status</th><th>Paid</th><th>Balance</th><th>Notes</th></tr></thead>
    <tbody>
      <?php if(empty($pdfRows)): ?>
      <tr><td colspan="12" style="text-align:center;padding:20px;color:#888;">No sales records found.</td></tr>
      <?php else: foreach($pdfRows as $i=>$s): ?>
      <tr>
        <td><?php echo $i+1; ?></td>
        <td style="white-space:nowrap;"><?php echo date('M d, Y',strtotime($s['sale_date'])); ?></td>
        <td><strong><?php echo htmlspecialchars($s['product_name']); ?></strong></td>
        <td><?php echo $s['customer_name']?htmlspecialchars($s['customer_name']):'<span style="color:#bbb">Walk-in</span>'; ?></td>
        <td><?php echo number_format($s['quantity'],2); ?></td>
        <td>&#8369;<?php echo number_format($s['unit_price'],2); ?></td>
        <td class="amount">&#8369;<?php echo number_format($s['total_amount'],2); ?></td>
        <td><span class="method-tag"><?php echo ucfirst($s['payment_method']??'cash'); ?></span></td>
        <td><span class="badge <?php echo $s['payment_status']??'paid'; ?>"><?php echo ucfirst($s['payment_status']??'paid'); ?></span></td>
        <td class="amount">&#8369;<?php echo number_format($s['amount_paid'],2); ?></td>
        <td><?php if(($s['balance']??0)>0): ?><span class="balance">&#8369;<?php echo number_format($s['balance'],2); ?></span><?php else: ?>&#8212;<?php endif; ?></td>
        <td style="color:#888;"><?php echo htmlspecialchars($s['notes']??''); ?></td>
      </tr>
      <?php endforeach;endif; ?>
    </tbody>
    <?php if(!empty($pdfRows)): ?>
    <tfoot><tr>
      <td colspan="6" style="text-align:right;font-size:9px;letter-spacing:1px;">TOTALS</td>
      <td>&#8369;<?php echo number_format($totalRevenue,2); ?></td>
      <td colspan="2"></td>
      <td>&#8369;<?php echo number_format($totalCollected,2); ?></td>
      <td style="color:#ffcc80;">&#8369;<?php echo number_format($totalBalance,2); ?></td>
      <td></td>
    </tr></tfoot>
    <?php endif; ?>
  </table>
  <div class="footer-bar">
    <span>ANI-TRACK | Farm Sales Tracker</span>
    <span>This report is auto-generated and for internal use only.</span>
    <span>&copy; <?php echo date('Y'); ?> ANI-TRACK</span>
  </div>
</div>
</body>
</html>
<?php ob_end_flush(); exit; }



// ── DELETE ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    $id=(int)$_POST['id'];
    // restore stock
    $s=$conn->prepare("SELECT inventory_id,quantity FROM sales WHERE id=? AND user_id=?");
    $s->bind_param("ii",$id,$uid); $s->execute();
    $row=$s->get_result()->fetch_assoc(); $s->close();
    if ($row && $row['inventory_id']) {
        $u=$conn->prepare("UPDATE inventory SET quantity=quantity+? WHERE id=? AND user_id=?");
        $u->bind_param("dii",$row['quantity'],$row['inventory_id'],$uid); $u->execute(); $u->close();
    }
    $stmt=$conn->prepare("DELETE FROM sales WHERE id=? AND user_id=?");
    $stmt->bind_param("ii",$id,$uid); $stmt->execute(); $stmt->close();
    $success="Sale deleted and stock restored.";
}

// ── ADD ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add') {
    $inv_id      = (int)floatval($_POST['inventory_id'] ?? 0);
    $cust_id     = (int)floatval($_POST['customer_id']  ?? 0);
    $product_name= trim($_POST['product_name'] ?? '');
    $quantity    = floatval($_POST['quantity']  ?? 0);
    $unit_price  = floatval($_POST['unit_price'] ?? 0);
    $notes       = trim($_POST['notes']        ?? '');
    $sale_date   = $_POST['sale_date']         ?? date('Y-m-d');
    if (empty($product_name)) { $errors[]="Product name is required."; }
    if ($quantity <= 0)       { $errors[]="Quantity must be greater than 0."; }
    if ($unit_price <= 0)     { $errors[]="Unit price must be greater than 0."; }
    // Check stock
    if ($inv_id && empty($errors)) {
        $chk=$conn->prepare("SELECT quantity,name FROM inventory WHERE id=? AND user_id=?");
        $chk->bind_param("ii",$inv_id,$uid); $chk->execute();
        $inv=$chk->get_result()->fetch_assoc(); $chk->close();
        if ($inv && $inv['quantity'] < $quantity) {
            $errors[]="Insufficient stock. Available: ".number_format($inv['quantity'],2);
        }
    }
    if (empty($errors)) {
        $inv_id_val  = $inv_id  ?: null;
        $cust_id_val = $cust_id ?: null;
        $stmt=$conn->prepare("INSERT INTO sales (user_id,customer_id,inventory_id,product_name,quantity,unit_price,notes,sale_date) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iiisddss",$uid,$cust_id_val,$inv_id_val,$product_name,$quantity,$unit_price,$notes,$sale_date);
        $stmt->execute(); $stmt->close();
        // Deduct stock
        if ($inv_id) {
            $u=$conn->prepare("UPDATE inventory SET quantity=quantity-? WHERE id=? AND user_id=?");
            $u->bind_param("dii",$quantity,$inv_id,$uid); $u->execute(); $u->close();
        }
        $success="Sale recorded successfully.";
    }
}

// ── FETCH SALES ──
$search=trim($_GET['q']??'');
$dateFrom=trim($_GET['from']??'');
$dateTo  =trim($_GET['to']  ??'');
$sql="SELECT s.*, c.full_name AS customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.user_id=?";
$params=[$uid]; $types="i";
if ($search) { $sql.=" AND s.product_name LIKE ?"; $params[]="%$search%"; $types.="s"; }
if ($dateFrom) { $sql.=" AND s.sale_date >= ?"; $params[]=$dateFrom; $types.="s"; }
if ($dateTo)   { $sql.=" AND s.sale_date <= ?"; $params[]=$dateTo;   $types.="s"; }
$sql.=" ORDER BY s.created_at DESC";
$stmt=$conn->prepare($sql);
$stmt->bind_param($types,...$params); $stmt->execute();
$sales=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// ── TOTALS ──
$totalStmt=$conn->prepare("SELECT COUNT(*) as cnt, SUM(total_amount) as total, SUM(CASE WHEN MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE()) THEN total_amount ELSE 0 END) as monthly FROM sales WHERE user_id=?");
$totalStmt->bind_param("i",$uid); $totalStmt->execute();
$totals=$totalStmt->get_result()->fetch_assoc(); $totalStmt->close();

// ── DROPDOWNS ──
$invStmt=$conn->prepare("SELECT id,name,quantity,unit,price FROM inventory WHERE user_id=? AND quantity>0 ORDER BY name");
$invStmt->bind_param("i",$uid); $invStmt->execute();
$inventoryList=$invStmt->get_result()->fetch_all(MYSQLI_ASSOC); $invStmt->close();

$custStmt=$conn->prepare("SELECT id,full_name FROM customers WHERE user_id=? ORDER BY full_name");
$custStmt->bind_param("i",$uid); $custStmt->execute();
$customerList=$custStmt->get_result()->fetch_all(MYSQLI_ASSOC); $custStmt->close();

// ── USER ──
$stmt=$conn->prepare("SELECT first_name,last_name,username,email,phone,user_type FROM users WHERE id=?");
$stmt->bind_param("i",$uid); $stmt->execute();
$userData=$stmt->get_result()->fetch_assoc(); $stmt->close();
$displayName=htmlspecialchars($userData['first_name'].' '.$userData['last_name']);
$userType=htmlspecialchars($userData['user_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ANI-TRACK | Sales</title>
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
.stat-mini-icon.blue{background:#e3f2fd;color:#1e88e5;}
.stat-mini-icon.orange{background:#fff3e0;color:#fb8c00;}
.stat-mini-icon.teal{background:#e0f2f1;color:#00897b;}
.stat-mini-label{font-size:11px;color:var(--text-light);font-weight:500;}
.stat-mini-val{font-size:18px;font-weight:700;color:var(--text-dark);line-height:1.2;}
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.search-wrap{position:relative;flex:1;max-width:260px;}
.search-wrap svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-light);}
.search-wrap input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid #d8eed8;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;}
.date-input{padding:9px 12px;border:1.5px solid #d8eed8;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;color:var(--text-dark);}
.table-wrap{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--green-surface);padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
tbody td{padding:13px 16px;font-size:13px;color:var(--text-dark);border-bottom:1px solid #f0f7f0;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:#fafffe;}
.amount{font-weight:700;color:var(--green-mid);}
.cust-tag{display:inline-block;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;background:#e3f2fd;color:#1565c0;}
.action-btns{display:flex;gap:6px;}
.btn-icon{width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;}
.btn-icon svg{width:14px;height:14px;}
.btn-icon.del{background:#ffebee;color:#e53935;}
.btn-icon.del:hover{background:#ffcdd2;}
.empty-state{text-align:center;padding:50px 20px;color:var(--text-light);}
.empty-state svg{width:48px;height:48px;opacity:.25;margin-bottom:12px;}
.empty-state p{font-size:13px;}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:200;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal{background:#fff;border-radius:16px;padding:28px 30px;width:520px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);position:relative;max-height:90vh;overflow-y:auto;}
.modal h2{font-size:16px;font-weight:700;color:var(--text-dark);margin-bottom:18px;}
.modal .close-btn{position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:var(--text-light);}
.modal .close-btn svg{width:18px;height:18px;}
.form-field{margin-bottom:14px;}
.form-field label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px;}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:9px 12px;border:1.5px solid #cde8ce;border-radius:7px;font-size:13px;font-family:'Poppins',sans-serif;background:#f7fbf7;outline:none;transition:border-color .2s;appearance:none;}
.form-field input:focus,.form-field select:focus,.form-field textarea:focus{border-color:var(--green-main);background:#fff;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.total-preview{background:var(--green-surface);border-radius:9px;padding:12px 16px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
.total-preview span{font-size:12px;color:var(--text-mid);font-weight:500;}
.total-preview strong{font-size:18px;font-weight:700;color:var(--green-mid);}
.modal-actions{display:flex;gap:10px;}
.btn-cancel{flex:1;padding:10px;background:#f5f5f5;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;color:#777;}
.btn-cancel:hover{background:#ebebeb;}
.btn-save{flex:2;padding:10px;background:linear-gradient(135deg,var(--green-light),var(--green-mid));color:#fff;border:none;border-radius:8px;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;}
.alert{padding:11px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.alert.success{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;}
.alert.error{background:#ffebee;border:1px solid #ef9a9a;color:#c62828;}
.stock-hint{font-size:11px;color:var(--text-light);margin-top:4px;}
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
      <a href="sales.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Sales</a>
      <a href="inventory.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Inventory</a>
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
    <div class="topbar-left"><h1>Sales</h1><p id="topbarDate"></p></div>
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
        <div class="stat-mini-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div><div class="stat-mini-label">Total Revenue</div><div class="stat-mini-val">₱<?php echo number_format($totals['total']??0,2); ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <div><div class="stat-mini-label">Total Transactions</div><div class="stat-mini-val"><?php echo (int)($totals['cnt']??0); ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="stat-mini-label">This Month</div><div class="stat-mini-val">₱<?php echo number_format($totals['monthly']??0,2); ?></div></div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg></div>
        <div><div class="stat-mini-label">Avg per Sale</div><div class="stat-mini-val">₱<?php $avg=($totals['cnt']??0)>0?($totals['total']/$totals['cnt']):0; echo number_format($avg,2); ?></div></div>
      </div>
    </div>

    <div class="page-header">
      <div><h1>Sales Records</h1><p><?php echo count($sales); ?> record<?php echo count($sales)!=1?'s':''; ?></p></div>
      <button class="btn-primary" onclick="openModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Record Sale
      </button>
        <a id="downloadPdfBtn" href="sales.php?download=pdf" target="_blank" class="btn-primary" style="background:linear-gradient(135deg,#ef6c00,#e65100);box-shadow:0 4px 12px rgba(230,81,0,0.3);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download PDF
        </a>
    </div>

    <form method="GET">
      <div class="toolbar">
        <div class="search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" placeholder="Search product…" value="<?php echo htmlspecialchars($search); ?>"/>
        </div>
        <input type="date" name="from" class="date-input" value="<?php echo htmlspecialchars($dateFrom); ?>" title="From date"/>
        <input type="date" name="to"   class="date-input" value="<?php echo htmlspecialchars($dateTo);   ?>" title="To date"/>
        <button type="submit" class="btn-primary" style="padding:9px 16px;">Filter</button>
        <?php if ($search||$dateFrom||$dateTo): ?><a href="sales.php" class="btn-primary" style="background:#f0f0f0;color:#555;box-shadow:none;">Clear</a><?php endif; ?>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Date</th><th>Product</th><th>Customer</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Notes</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if (empty($sales)): ?>
          <tr><td colspan="9"><div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <p>No sales recorded yet. Record your first sale!</p>
          </div></td></tr>
          <?php else: ?>
          <?php foreach($sales as $i=>$s): ?>
          <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo date('M d, Y',strtotime($s['sale_date'])); ?></td>
            <td><strong><?php echo htmlspecialchars($s['product_name']); ?></strong></td>
            <td><?php if($s['customer_name']): ?><span class="cust-tag"><?php echo htmlspecialchars($s['customer_name']); ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?php echo number_format($s['quantity'],2); ?></td>
            <td>₱<?php echo number_format($s['unit_price'],2); ?></td>
            <td class="amount">₱<?php echo number_format($s['total_amount'],2); ?></td>
            <td><?php echo htmlspecialchars($s['notes']?:'—'); ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this sale? Stock will be restored.');" style="display:inline;">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?php echo $s['id']; ?>"/>
                <button type="submit" class="btn-icon del" title="Delete">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3,6 5,6 21,6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </form>
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
    <h2>Record New Sale</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add"/>
      <!-- Product from inventory OR manual -->
      <div class="form-field">
        <label>Select from Inventory (optional)</label>
        <select name="inventory_id" id="f_inv" onchange="fillFromInventory(this)">
          <option value="">— Type manually below —</option>
          <?php foreach($inventoryList as $inv): ?>
          <option value="<?php echo $inv['id']; ?>"
            data-name="<?php echo htmlspecialchars($inv['name']); ?>"
            data-price="<?php echo $inv['price']; ?>"
            data-qty="<?php echo $inv['quantity']; ?>"
            data-unit="<?php echo htmlspecialchars($inv['unit']); ?>">
            <?php echo htmlspecialchars($inv['name']); ?> (<?php echo number_format($inv['quantity'],2).' '.$inv['unit']; ?> available)
          </option>
          <?php endforeach; ?>
        </select>
        <div class="stock-hint" id="stockHint"></div>
      </div>
      <div class="form-field">
        <label>Product Name *</label>
        <input type="text" name="product_name" id="f_pname" placeholder="e.g. Tomatoes" required/>
      </div>
      <div class="form-row">
        <div class="form-field">
          <label>Customer (optional)</label>
          <select name="customer_id" id="f_cust">
            <option value="">— Walk-in / Anonymous —</option>
            <?php foreach($customerList as $cust): ?>
            <option value="<?php echo $cust['id']; ?>"><?php echo htmlspecialchars($cust['full_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Sale Date *</label>
          <input type="date" name="sale_date" id="f_date" value="<?php echo date('Y-m-d'); ?>" required/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-field">
          <label>Quantity *</label>
          <input type="number" name="quantity" id="f_qty" placeholder="0" step="0.01" min="0.01" required onchange="updateTotal()" oninput="updateTotal()"/>
        </div>
        <div class="form-field">
          <label>Unit Price (₱) *</label>
          <input type="number" name="unit_price" id="f_uprice" placeholder="0.00" step="0.01" min="0.01" required onchange="updateTotal()" oninput="updateTotal()"/>
        </div>
      </div>
      <div class="total-preview">
        <span>Total Amount</span>
        <strong id="totalPreview">₱0.00</strong>
      </div>
      <div class="form-field">
        <label>Notes</label>
        <textarea name="notes" id="f_notes" rows="2" placeholder="Optional notes…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-save">Save Sale</button>
      </div>
    </form>
  </div>
</div>

<script>
const d=new Date(); document.getElementById('topbarDate').textContent=d.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
function openModal(){document.getElementById('modalBg').classList.add('open');}
function closeModal(){document.getElementById('modalBg').classList.remove('open');}
document.getElementById('modalBg').addEventListener('click',function(e){if(e.target===this)closeModal();});
function fillFromInventory(sel){
  const opt=sel.options[sel.selectedIndex];
  if(opt.value){
    document.getElementById('f_pname').value=opt.dataset.name;
    document.getElementById('f_uprice').value=opt.dataset.price;
    document.getElementById('stockHint').textContent='Available: '+parseFloat(opt.dataset.qty).toFixed(2)+' '+opt.dataset.unit;
    document.getElementById('f_qty').max=opt.dataset.qty;
  } else {
    document.getElementById('stockHint').textContent='';
    document.getElementById('f_qty').removeAttribute('max');
  }
  updateTotal();
}
function updateTotal(){
  const q=parseFloat(document.getElementById('f_qty').value)||0;
  const p=parseFloat(document.getElementById('f_uprice').value)||0;
  document.getElementById('totalPreview').textContent='₱'+(q*p).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
}
</script>
</body>
</html>