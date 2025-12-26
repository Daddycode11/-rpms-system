<?php
session_start();
require_once '../config/database.php';

// --- ROLE CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- FETCH VENDORS ---
$vendors = $pdo->query("
    SELECT v.*, s.section_name, u.first_name, u.last_name, u.email, u.id AS user_id, u.image
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.id
    LEFT JOIN sections s ON v.section_id = s.id
")->fetchAll(PDO::FETCH_ASSOC);

// --- FETCH SECTIONS ---
$sections = $pdo->query("SELECT * FROM sections")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendors | Admin - RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'].'/rpms-system/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background: #f4f6f9; }
.vendor-img { width: 40px; height:40px; object-fit:cover; border-radius:50%; margin-right:8px; }
.table th, .table td { vertical-align: middle; }
img.preview { max-height:100px; margin-bottom:10px; }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Vendors Management</h2>

    <div id="vendorAlert"></div>

    <!-- Sorting / Filtering UI -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" id="vendorSearch" class="form-control" placeholder="Search by name, email, stall, section">
        </div>
        <div class="col-md-4">
            <select id="vendorSort" class="form-control">
                <option value="">Sort by...</option>
                <option value="name_asc">Name A-Z</option>
                <option value="name_desc">Name Z-A</option>
                <option value="rent_asc">Rent Low → High</option>
                <option value="rent_desc">Rent High → Low</option>
                <option value="section_asc">Section A-Z</option>
                <option value="section_desc">Section Z-A</option>
            </select>
        </div>
    </div>

    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addVendorModal">Add Vendor</button>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle" id="vendorTable">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Stall #</th>
                    <th>Section</th>
                    <th>Monthly Rent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($vendors as $v): ?>
                <tr id="vendorRow<?= $v['id']; ?>">
                    <td>
                        <?php if($v['image']): ?>
                            <img src="/rpms-system/uploads/vendors/<?= $v['image']; ?>" class="vendor-img">
                        <?php endif; ?>
                        <?= htmlspecialchars($v['first_name'].' '.$v['last_name']); ?>
                    </td>
                    <td><?= htmlspecialchars($v['email']); ?></td>
                    <td><?= htmlspecialchars($v['stall_number']); ?></td>
                    <td><?= htmlspecialchars($v['section_name']); ?></td>
                    <td>₱<?= number_format($v['monthly_rent'],2); ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning editVendorBtn" data-id="<?= $v['id']; ?>" data-bs-toggle="modal" data-bs-target="#editVendorModal<?= $v['id']; ?>">Edit</button>
                        <button class="btn btn-sm btn-danger deleteVendorBtn" data-id="<?= $v['id']; ?>">Delete</button>
                    </td>
                </tr>

                <!-- Edit Vendor Modal -->
                <div class="modal fade" id="editVendorModal<?= $v['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form class="editVendorForm" data-id="<?= $v['id']; ?>" enctype="multipart/form-data">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Vendor</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="vendor_id" value="<?= $v['id']; ?>">

                                    <?php if($v['image']): ?>
                                    <img src="/rpms-system/uploads/vendors/<?= $v['image']; ?>" class="preview" id="editPreview<?= $v['id']; ?>">
                                    <?php else: ?>
                                    <img class="preview" id="editPreview<?= $v['id']; ?>" style="display:none;">
                                    <?php endif; ?>

                                    <input type="file" name="image" class="form-control mb-3" accept="image/*" onchange="previewImage(this,'editPreview<?= $v['id']; ?>')">

                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($v['first_name']); ?>" required></div>
                                        <div class="col-md-6 mb-3"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($v['last_name']); ?>" required></div>
                                    </div>
                                    <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($v['email']); ?>" required></div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label>Stall #</label><input type="text" name="stall_number" class="form-control" value="<?= htmlspecialchars($v['stall_number']); ?>" required></div>
                                        <div class="col-md-4 mb-3">
                                            <label>Section</label>
                                            <select name="section_id" class="form-control" required>
                                                <?php foreach($sections as $s): ?>
                                                    <option value="<?= $s['id']; ?>" <?= $s['id']==$v['section_id']?'selected':''; ?>><?= htmlspecialchars($s['section_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3"><label>Monthly Rent</label><input type="number" name="monthly_rent" class="form-control" value="<?= htmlspecialchars($v['monthly_rent']); ?>" required></div>
                                    </div>
                                    <div class="mb-3"><label>Password (leave blank to keep current)</label><input type="password" name="password" class="form-control"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-warning">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addVendorForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <img class="preview" id="addPreview" style="display:none;">
                    <input type="file" name="image" class="form-control mb-3" accept="image/*" onchange="previewImage(this,'addPreview')">

                    <div class="row">
                        <div class="col-md-6 mb-3"><label>First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label>Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Stall #</label><input type="text" name="stall_number" class="form-control" required></div>
                        <div class="col-md-4 mb-3">
                            <label>Section</label>
                            <select name="section_id" class="form-control" required>
                                <?php foreach($sections as $s): ?>
                                    <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3"><label>Monthly Rent</label><input type="number" name="monthly_rent" class="form-control" value="0" required></div>
                    </div>
                    <div class="mb-3"><label>Password</label><input type="password" name="password" class="form-control" value="vendor123" required></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Vendor</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// --- Image Preview ---
function previewImage(input, id){
    const img = document.getElementById(id);
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => { img.src=e.target.result; img.style.display='block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

// --- Add Vendor AJAX ---
$('#addVendorForm').submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('action','add');
    $.ajax({
        url:'vendor_ajax.php',
        type:'POST',
        data:formData,
        contentType:false,
        processData:false,
        success:function(res){
            const data = JSON.parse(res);
            $('#vendorAlert').html('<div class="alert alert-info">'+data.message+'</div>');
            if(data.success){
                $('#vendorTable tbody').append(data.row);
                $('#addVendorModal').modal('hide');
                $('#addVendorForm')[0].reset();
                $('#addPreview').hide();
            }
        }
    });
});

// --- Edit Vendor AJAX ---
$(document).on('submit','.editVendorForm',function(e){
    e.preventDefault();
    var id = $(this).data('id');
    var formData = new FormData(this);
    formData.append('action','edit');
    $.ajax({
        url:'vendor_ajax.php',
        type:'POST',
        data:formData,
        contentType:false,
        processData:false,
        success:function(res){
            const data = JSON.parse(res);
            $('#vendorAlert').html('<div class="alert alert-info">'+data.message+'</div>');
            if(data.success){
                location.reload(); // can be improved to instant row update
            }
        }
    });
});

// --- Delete Vendor AJAX ---
$(document).on('click','.deleteVendorBtn',function(){
    if(!confirm('Delete this vendor?')) return;
    var id = $(this).data('id');
    $.ajax({
        url:'vendor_ajax.php?delete='+id,
        type:'GET',
        success:function(res){
            const data = JSON.parse(res);
            $('#vendorAlert').html('<div class="alert alert-info">'+data.message+'</div>');
            if(data.success){
                $('#vendorRow'+id).remove();
            }
        }
    });
});

// --- Filtering & Sorting ---
function filterAndSortVendors(){
    let search = $('#vendorSearch').val().toLowerCase();
    let sort = $('#vendorSort').val();
    let rows = $('#vendorTable tbody tr');

    // Sort rows
    rows.sort(function(a,b){
        if(!sort) return 0;
        let valA, valB;
        if(sort.includes('name')){
            valA = $(a).find('td:eq(0)').text().toLowerCase();
            valB = $(b).find('td:eq(0)').text().toLowerCase();
        } else if(sort.includes('rent')){
            valA = parseFloat($(a).find('td:eq(4)').text().replace(/[₱,]/g,''));
            valB = parseFloat($(b).find('td:eq(4)').text().replace(/[₱,]/g,''));
        } else if(sort.includes('section')){
            valA = $(a).find('td:eq(3)').text().toLowerCase();
            valB = $(b).find('td:eq(3)').text().toLowerCase();
        }
        return sort.endsWith('_asc') ? (valA>valB?1:-1) : (valA<valB?1:-1);
    }).appendTo('#vendorTable tbody');

    // Filter rows
    rows.each(function(){
        let text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(search) > -1);
    });
}

// Event listeners
$('#vendorSearch').on('keyup', filterAndSortVendors);
$('#vendorSort').on('change', filterAndSortVendors);
</script>
</body>
</html>
