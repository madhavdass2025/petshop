<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_attachment'])) {
    $consultation_id = (int)$_POST['consultation_id'];
    $description = $conn->real_escape_string($_POST['description']);

    // You can only upload to an existing, saved consultation
    if ($consultation_id > 0 && isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Sanitize filename
        $original_name = basename($_FILES["attachment_file"]["name"]);
        $safe_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_name);
        $target_file = $target_dir . $safe_filename;

        if (move_uploaded_file($_FILES["attachment_file"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO attachments (consultation_id, file_path, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $consultation_id, $target_file, $description);
            if (!$stmt->execute()) {
                // Handle error
            }
        }
    }

    // Redirect back to the consultation page, focusing on the history tab
    $patient_id_sql = "SELECT patient_id FROM consultations WHERE id = ?";
    $stmt = $conn->prepare($patient_id_sql);
    $stmt->bind_param("i", $consultation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()) {
        $patient_id = $row['patient_id'];
        // Redirecting back to the patient's main consultation page.
        // The user can then click the history tab to see the updated record with the attachment.
        header("Location: consultation.php?patient_id=$patient_id&upload_status=success");
    } else {
        header("Location: patients.php");
    }
    exit();
} else {
    header('Location: patients.php');
    exit();
}
?>
