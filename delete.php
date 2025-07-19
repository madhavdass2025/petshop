<?php
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];

    $table_map = [
        'patient' => 'patients',
        'consultation' => 'consultations',
        'medicine' => 'prescribed_medicines',
        'test' => 'ordered_tests',
        'attachment' => 'attachments'
    ];

    if (array_key_exists($type, $table_map)) {
        $table = $table_map[$type];
        $stmt = $conn->prepare("UPDATE $table SET cancel = 1 WHERE id = :id");
        $stmt->bindParam(':id', $id);
        if ($stmt->execute()) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        } else {
            die("Error deleting record.");
        }
    } else {
        die("Invalid type.");
    }
} else {
    die("Invalid request.");
}
?>
