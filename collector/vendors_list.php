<?php
session_start();
require_once __DIR__ . '/../config/database.php';

/* ===================== AUTH ===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

/* ===================== FETCH VENDORS ===================== */
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY vendor_name ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ===================== HELPER FUNCTIONS ===================== */
function getRemainingBalance($vendor_id, $monthly_rent, $pdo) {
    // Sum all payments made
    $stmt = $pdo->prepare("SELECT SUM(amount_paid - discount + penalty) AS total_paid FROM payments WHERE vendor_id=?");
    $stmt->execute([$vendor_id]);
    $paid = (float)($stmt->fetchColumn() ?? 0);
    return max($monthly_rent - $paid, 0);
}

function getLastPaymentDate($vendor_id, $pdo) {
    $stmt = $pdo->prepare("SELECT payment_date FROM payments WHERE vendor_id=? ORDER BY payment_date DESC LIMIT 1");
    $stmt->execute([$vendor_id]);
    return $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Collector Vendor List | RPMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<style>
body{font-family:Poppins;background:#f4f6f9}
.card{border-radius:14px}
.table th,.table td{vertical-align:middle;font-size:14px}
.table .table-danger{background:#f8d7da !important;}
.badge-gold{background:#ffc107;color:#000}
@media(max-width:768px){h2{font-size:1.2rem}.btn{width:100%}}
@media(max-width:576px){td .btn {display:block;width:100%;margin-bottom:4px;}}
</style>
</head>
<body>
<?php include __DIR__ . '/collector_navbar.php'; ?>

<div class="container mt-5">
    <h4>📋 Vendor List</h4>
    <div id="vendorMsg"></div>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle" id="vendorTable">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Stall #</th>
                    <th>Monthly</th>
                    <th>Weekly</th>
                    <th>Daily</th>
                    <th>Status</th>
                    <th>Remaining</th>
                    <th>Payments</th>
                    <th>Quick Pay</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vendors as $i=>$v):
                    $monthly = (float)$v['monthly_rent'];
                    $weekly = round($monthly / 4, 2);
                    $daily = round($monthly / 30, 2);
                    $remaining = getRemainingBalance($v['id'], $monthly, $pdo);

                    $lastPayment = getLastPaymentDate($v['id'], $pdo);
                    $isOverdue = false;
                    $statusText = ucfirst($v['status']);

                    if($v['status']=='active') {
                        if($lastPayment) {
                            $nextDue = (new DateTime($lastPayment))->modify('+1 month');
                            $today = new DateTime();
                            if($today > $nextDue) {
                                $isOverdue = true;
                                $statusText = 'OVERDUE';
                            } else {
                                $statusText = 'ON-TIME';
                            }
                        } else {
                            $isOverdue = true;
                            $statusText = 'OVERDUE';
                        }
                    } elseif($v['status']=='inactive') {
                        $statusText = 'INACTIVE';
                    }
                ?>
                <tr id="vendorRow<?= $v['id'] ?>" class="<?= $isOverdue?'table-danger':'' ?>">
                    <td><?= $i+1 ?></td>
                    <td class="vendor_name"><?= htmlspecialchars($v['vendor_name']) ?></td>
                    <td class="stall_number"><?= htmlspecialchars($v['stall_number']) ?></td>
                    <td>₱<?= number_format($monthly,2) ?></td>
                    <td>₱<?= number_format($weekly,2) ?></td>
                    <td>₱<?= number_format($daily,2) ?></td>
                    <td class="status"><span class="badge <?= $isOverdue?'bg-danger':'bg-success' ?>"><?= $statusText ?></span></td>
                    <td>₱<?= number_format($remaining,2) ?></td>
                    <td>
                        <button class="btn btn-sm btn-info history-btn" data-id="<?= $v['id'] ?>">View</button>
                    </td>
                    <td>
                        <a href="collector_payments.php?stall=<?= $v['stall_number'] ?>" class="btn btn-sm btn-success">Pay</a>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $v['id'] ?>">Edit</button>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $v['id'] ?>">Delete</button>
                    </td>
                </tr>

                <!-- Edit & Delete Modals here (same as your current code) -->

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Partial Payment History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Partial Payments History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Loading...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    // ---------- Partial Payment History ----------
    $('.history-btn').click(function(){
        let vendorId = $(this).data('id');
        $.get('vendor_payment_history.php?vendor_id=' + vendorId, function(res){
            $('#historyModal .modal-body').html(res);
            $('#historyModal').modal('show');
        });
    });

    // ---------- Edit / Delete AJAX handlers ----------
    $('.editVendorForm').submit(function(e){
        e.preventDefault();
        let form = $(this);
        let id = form.data('id');
        $.post('edit_vendor_ajax.php?id='+id, form.serialize(), function(res){
            if(res.success){
                let row = $('#vendorRow'+id);
                row.find('.vendor_name').text(res.vendor_name);
                row.find('.stall_number').text(res.stall_number);
                row.find('.status span').text(res.status.charAt(0).toUpperCase()+res.status.slice(1))
                    .removeClass('bg-success bg-secondary bg-danger')
                    .addClass(res.status=='active'?'bg-success':(res.status=='inactive'?'bg-secondary':'bg-danger'));
                $('#editModal'+id).modal('hide');
                $('#vendorMsg').html('<div class="alert alert-success">Vendor updated successfully!</div>');
            } else { alert(res.message); }
        }, 'json');
    });

    $('.deleteVendorForm').submit(function(e){
        e.preventDefault();
        let form = $(this);
        let id = form.data('id');
        $.post('delete_vendor_ajax.php', {id:id}, function(res){
            if(res.success){
                $('#vendorRow'+id).remove();
                $('#deleteModal'+id).modal('hide');
                $('#vendorMsg').html('<div class="alert alert-success">Vendor deleted successfully!</div>');
            } else { alert(res.message); }
        }, 'json');
    });
});
</script>
</body>
</html>
