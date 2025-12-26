<?php
session_start();
require_once '../config/database.php';

// --- ROLE CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

// --- IMAGE UPLOAD HELPER ---
function uploadVendorImage($file) {
    if(!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxSize = 2*1024*1024; // 2MB
    if($file['size'] > $maxSize) return 'SIZE_ERROR';

    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext,$allowed)) return null;

    $newName = uniqid('vendor_',true).'.'.$ext;
    $uploadDir = $_SERVER['DOCUMENT_ROOT'].'/rpms-system/uploads/vendors/';
    if(!is_dir($uploadDir)) mkdir($uploadDir,0777,true);

    if(move_uploaded_file($file['tmp_name'], $uploadDir.$newName)) return $newName;
    return null;
}

// --- ADD VENDOR ---
if(isset($_POST['action']) && $_POST['action']=='add') {
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];
    $stall = $_POST['stall_number'];
    $section_id = $_POST['section_id'];
    $rent = $_POST['monthly_rent'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $image = null;

    if(!empty($_FILES['image']['name'])) {
        $upload = uploadVendorImage($_FILES['image']);
        if($upload && $upload !== 'SIZE_ERROR') $image = $upload;
    }

    try {
        $pdo->beginTransaction();

        // Insert into users
        $stmt = $pdo->prepare("INSERT INTO users (role, first_name, last_name, email, password, image, status) VALUES ('vendor', ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$first,$last,$email,$password,$image]);
        $user_id = $pdo->lastInsertId();

        // Insert into vendors
        $stmt = $pdo->prepare("INSERT INTO vendors (user_id, stall_number, section_id, monthly_rent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $stall, $section_id, $rent]);
        $vendor_id = $pdo->lastInsertId();

        $pdo->commit();

        // Build table row HTML for instant DOM append
        $stmt = $pdo->prepare("SELECT v.*, s.section_name, u.first_name, u.last_name, u.email, u.id AS user_id, u.image 
                               FROM vendors v 
                               LEFT JOIN users u ON v.user_id=u.id 
                               LEFT JOIN sections s ON v.section_id=s.id
                               WHERE v.id=?");
        $stmt->execute([$vendor_id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);

        $row = '<tr id="vendorRow'.$v['id'].'">';
        $row .= '<td>'.($v['image']?'<img src="/rpms-system/uploads/vendors/'.$v['image'].'" class="vendor-img">':'').htmlspecialchars($v['first_name'].' '.$v['last_name']).'</td>';
        $row .= '<td>'.htmlspecialchars($v['email']).'</td>';
        $row .= '<td>'.htmlspecialchars($v['stall_number']).'</td>';
        $row .= '<td>'.htmlspecialchars($v['section_name']).'</td>';
        $row .= '<td>₱'.number_format($v['monthly_rent'],2).'</td>';
        $row .= '<td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editVendorModal'.$v['id'].'">Edit</button>
                    <button class="btn btn-sm btn-danger deleteVendorBtn" data-id="'.$v['id'].'">Delete</button>
                 </td>';
        $row .= '</tr>';

        echo json_encode(['success'=>true, 'message'=>'Vendor added successfully!', 'row'=>$row]);
        exit;

    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
        exit;
    }
}

// --- EDIT VENDOR ---
if(isset($_POST['action']) && $_POST['action']=='edit') {
    $vendor_id = $_POST['vendor_id'];
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];
    $stall = $_POST['stall_number'];
    $section_id = $_POST['section_id'];
    $rent = $_POST['monthly_rent'];

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id=?");
        $stmt->execute([$vendor_id]);
        $user_id = $stmt->fetchColumn();

        // Handle image
        if(!empty($_FILES['image']['name'])) {
            $upload = uploadVendorImage($_FILES['image']);
            if($upload && $upload !== 'SIZE_ERROR') {
                $stmt = $pdo->prepare("UPDATE users SET image=? WHERE id=?");
                $stmt->execute([$upload, $user_id]);
            }
        }

        // Handle password
        if(!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, password=? WHERE id=?");
            $stmt->execute([$first,$last,$email,$password,$user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
            $stmt->execute([$first,$last,$email,$user_id]);
        }

        // Update vendors
        $stmt = $pdo->prepare("UPDATE vendors SET stall_number=?, section_id=?, monthly_rent=? WHERE id=?");
        $stmt->execute([$stall,$section_id,$rent,$vendor_id]);

        echo json_encode(['success'=>true,'message'=>'Vendor updated successfully!']);
        exit;

    } catch(PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
        exit;
    }
}

// --- DELETE VENDOR ---
if(isset($_GET['delete'])) {
    $vendor_id = $_GET['delete'];

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM vendors WHERE id=?");
        $stmt->execute([$vendor_id]);
        $user_id = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM vendors WHERE id=?");
        $stmt->execute([$vendor_id]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$user_id]);

        echo json_encode(['success'=>true,'message'=>'Vendor deleted successfully!']);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
        exit;
    }
}
