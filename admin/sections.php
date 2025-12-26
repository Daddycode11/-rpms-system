<?php
session_start();
require_once '../config/database.php';

// --- ROLE CHECK: Only Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- IMAGE UPLOAD HELPER ---
function uploadSectionImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) return 'SIZE_ERROR';

    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) return null;

    $newName = uniqid('section_', true) . '.' . $ext;
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/uploads/sections/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) return $newName;
    return null;
}

// --- HANDLE ADD SECTION ---
if (isset($_POST['add_section'])) {
    $section_name = $_POST['section_name'];
    $description = $_POST['description'] ?? '';
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadSectionImage($_FILES['image']);
        if ($upload !== 'SIZE_ERROR') $image = $upload;
    }
    $stmt = $pdo->prepare("INSERT INTO sections (section_name, description, image, deleted_at) VALUES (?, ?, ?, NULL)");
    $stmt->execute([$section_name, $description, $image]);
    exit(json_encode(['success' => true]));
}

// --- HANDLE EDIT SECTION ---
if (isset($_POST['edit_section'])) {
    $id = $_POST['section_id'];
    $section_name = $_POST['section_name'];
    $description = $_POST['description'] ?? '';

    $imageSql = '';
    $params = [$section_name, $description];

    if (!empty($_FILES['image']['name'])) {
        $upload = uploadSectionImage($_FILES['image']);
        if ($upload && $upload !== 'SIZE_ERROR') {
            $imageSql = ', image=?';
            $params[] = $upload;
        }
    }
    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE sections SET section_name=?, description=? $imageSql WHERE id=?");
    $stmt->execute($params);
    exit(json_encode(['success' => true]));
}

// --- SOFT DELETE (ARCHIVE) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("UPDATE sections SET deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    exit(json_encode(['success' => true]));
}

// --- RESTORE SECTION ---
if (isset($_GET['restore'])) {
    $id = $_GET['restore'];
    $stmt = $pdo->prepare("UPDATE sections SET deleted_at=NULL WHERE id=?");
    $stmt->execute([$id]);
    exit(json_encode(['success' => true]));
}

// --- FETCH SECTIONS ---
$sections = $pdo->query("SELECT * FROM sections WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$archivedSections = $pdo->query("SELECT * FROM sections WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sections | Admin - RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; }
.card-section { border-radius:16px; transition:.3s; cursor:pointer; }
.card-section:hover { transform:translateY(-5px); box-shadow:0 10px 25px rgba(0,0,0,.1); }
.card-img-top { height:180px; object-fit:cover; }
.drag-over { border: 2px dashed #0d6efd !important; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Sections Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">Add Section</button>
    </div>

    <div class="d-flex mb-4 gap-2">
        <input type="text" id="searchInput" class="form-control" placeholder="Search sections...">
        <select id="sortSelect" class="form-select" style="width:200px;">
            <option value="id_desc">Sort by Date (Newest)</option>
            <option value="id_asc">Sort by Date (Oldest)</option>
            <option value="name_asc">Sort by Name (A-Z)</option>
            <option value="name_desc">Sort by Name (Z-A)</option>
        </select>
    </div>

    <!-- Active Sections -->
    <div class="row g-4" id="sectionsContainer">
        <?php foreach ($sections as $s): ?>
        <div class="col-md-4 col-lg-3 section-card" id="sectionCard<?= $s['id']; ?>" data-name="<?= htmlspecialchars(strtolower($s['section_name'])); ?>" data-date="<?= strtotime($s['id']); ?>">
            <div class="card card-section h-100">
                <?php if ($s['image']): ?>
                    <img src="/rpms-system/uploads/sections/<?= $s['image']; ?>" class="card-img-top">
                <?php else: ?>
                    <div class="bg-secondary d-flex align-items-center justify-content-center text-white" style="height:180px;">No Image</div>
                <?php endif; ?>
                <div class="card-body">
                    <h5><?= htmlspecialchars($s['section_name']); ?></h5>
                    <p class="text-muted small"><?= htmlspecialchars($s['description']); ?></p>
                </div>
                <div class="card-footer bg-white border-0">
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSectionModal<?= $s['id']; ?>">Edit</button>
                    <button class="btn btn-sm btn-danger archiveBtn" data-id="<?= $s['id']; ?>">Archive</button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editSectionModal<?= $s['id']; ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form class="editForm" data-id="<?= $s['id']; ?>" enctype="multipart/form-data">
                <div class="modal-header">
                  <h5>Edit Section</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="section_id" value="<?= $s['id']; ?>">
                  <img id="preview<?= $s['id']; ?>" src="<?= $s['image'] ? '/rpms-system/uploads/sections/'.$s['image'] : '' ?>" class="img-fluid rounded mb-2" style="max-height:150px; <?= $s['image'] ? '' : 'display:none;' ?>">
                  <input type="file" name="image" class="form-control mb-3" accept="image/*" onchange="previewImage(this,'preview<?= $s['id']; ?>')">
                  <input type="text" name="section_name" class="form-control mb-3" value="<?= htmlspecialchars($s['section_name']); ?>" required>
                  <textarea name="description" class="form-control"><?= htmlspecialchars($s['description']); ?></textarea>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-warning">Save</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Archived Sections -->
    <?php if($archivedSections): ?>
    <h3 class="mt-5">Archived Sections</h3>
    <div class="row g-4" id="archivedContainer">
        <?php foreach ($archivedSections as $s): ?>
        <div class="col-md-4 col-lg-3 section-card" id="archivedCard<?= $s['id']; ?>" data-name="<?= htmlspecialchars(strtolower($s['section_name'])); ?>" data-date="<?= strtotime($s['id']); ?>">
            <div class="card card-section h-100 border-secondary">
                <?php if ($s['image']): ?>
                    <img src="/rpms-system/uploads/sections/<?= $s['image']; ?>" class="card-img-top">
                <?php else: ?>
                    <div class="bg-secondary d-flex align-items-center justify-content-center text-white" style="height:180px;">No Image</div>
                <?php endif; ?>
                <div class="card-body">
                    <h5><?= htmlspecialchars($s['section_name']); ?></h5>
                    <p class="text-muted small"><?= htmlspecialchars($s['description']); ?></p>
                </div>
                <div class="card-footer bg-white border-0">
                    <button class="btn btn-sm btn-success restoreBtn" data-id="<?= $s['id']; ?>">Restore</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="addSectionForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5>Add Section</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="dropArea" class="border rounded p-3 text-center mb-3" style="cursor:pointer;">Drag & drop image here or click to select</div>
          <img id="addPreview" class="img-fluid rounded mb-2" style="display:none;max-height:150px;">
          <input type="file" name="image" class="form-control mb-3" accept="image/*" style="display:none;" id="addImageInput">
          <input type="text" name="section_name" class="form-control mb-3" placeholder="Section Name" required>
          <textarea name="description" class="form-control" placeholder="Description"></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// IMAGE PREVIEW
function previewImage(input, id) {
    const img = document.getElementById(id);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; img.style.display='block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

// DRAG & DROP
const dropArea = document.getElementById('dropArea');
const fileInput = document.getElementById('addImageInput');
dropArea.addEventListener('click', () => fileInput.click());
dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('drag-over'); });
dropArea.addEventListener('dragleave', e => { e.preventDefault(); dropArea.classList.remove('drag-over'); });
dropArea.addEventListener('drop', e => {
    e.preventDefault();
    dropArea.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    fileInput.files = e.dataTransfer.files;
    previewImage({files: [file]}, 'addPreview');
});

// AJAX ADD SECTION
$('#addSectionForm').submit(function(e){
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('add_section', true);
    $.ajax({
        url: 'sections.php',
        type: 'POST',
        data: formData,
        contentType:false,
        processData:false,
        success: function(){ location.reload(); }
    });
});

// AJAX EDIT SECTION
$('.editForm').submit(function(e){
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('edit_section', true);
    $.ajax({
        url: 'sections.php',
        type: 'POST',
        data: formData,
        contentType:false,
        processData:false,
        success: function(){ location.reload(); }
    });
});

// AJAX ARCHIVE / RESTORE WITH DOM MOVE
$(document).on('click', '.archiveBtn', function(){
    let id = $(this).data('id');
    let card = $('#sectionCard'+id);
    $.get('sections.php', {delete:id}, function(){
        card.find('.archiveBtn').replaceWith('<button class="btn btn-sm btn-success restoreBtn" data-id="'+id+'">Restore</button>');
        card.appendTo('#archivedContainer');
        card.addClass('border-secondary').attr('id','archivedCard'+id);
    });
});

$(document).on('click', '.restoreBtn', function(){
    let id = $(this).data('id');
    let card = $('#archivedCard'+id);
    $.get('sections.php', {restore:id}, function(){
        card.find('.restoreBtn').replaceWith('<button class="btn btn-sm btn-danger archiveBtn" data-id="'+id+'">Archive</button>');
        card.appendTo('#sectionsContainer');
        card.removeClass('border-secondary').attr('id','sectionCard'+id);
    });
});

// SEARCH FILTER
$('#searchInput').on('keyup', function(){
    let val = $(this).val().toLowerCase();
    $('.section-card').each(function(){
        let name = $(this).data('name');
        $(this).toggle(name.includes(val));
    });
});

// SORT
$('#sortSelect').on('change', function(){
    let val = $(this).val();
    ['#sectionsContainer', '#archivedContainer'].forEach(function(container){
        let cards = $(container).children('.section-card').get();
        cards.sort(function(a,b){
            if(val=='id_desc') return $(b).data('date') - $(a).data('date');
            if(val=='id_asc') return $(a).data('date') - $(b).data('date');
            if(val=='name_asc') return $(a).dataset.name.localeCompare($(b).dataset.name);
            if(val=='name_desc') return $(b).dataset.name.localeCompare($(a).dataset.name);
        });
        $.each(cards, function(idx, card){ $(container).append(card); });
    });
});
</script>
</body>
</html>
