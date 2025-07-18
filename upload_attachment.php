<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_attachment'])) {
    $consultation_id = (int)$_POST['consultation_id'];
    $description = $conn->real_escape_string($_POST['description']);
    $patient_id = (int)$_POST['patient_id'];

    if ($consultation_id > 0 && $patient_id > 0 && isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $original_name = basename($_FILES["attachment_file"]["name"]);
        $safe_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_name);
        $target_file = $target_dir . $safe_filename;

        if (move_uploaded_file($_FILES["attachment_file"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO attachments (consultation_id, file_path, description) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $consultation_id, $target_file, $description);
            $stmt->execute();
        }
    }

    header("Location: consultation.php?patient_id=$patient_id&status=attachment_success");
    exit();
}
?>
