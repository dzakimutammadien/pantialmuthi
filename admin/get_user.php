<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$id = (int)$_GET['id'];

$sql = "SELECT u.*, r.nama_role 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = $id";
        
$result = mysqli_query($conn, $sql);

if ($user = mysqli_fetch_assoc($result)) {
    unset($user['password']);
    
    // ======================================================
    // PERBAIKAN: Jika foto_profil NULL atau kosong, set default
    // ======================================================
    if (empty($user['foto_profil'])) {
        $user['foto_profil'] = 'default-user.png';
    }
    
    echo json_encode(['success' => true, 'user' => $user]);
} else {
    echo json_encode(['success' => false]);
}
?>