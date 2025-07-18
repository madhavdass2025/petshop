<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_attachment'])) {
    $consultation_id = (int)$_POST['consultation_id'];
    $description = $conn->real_escape_string($_POST['description']);

    if ($consultation_id > 0 && isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $target_file = $target_dir . basename($_FILES["attachment_file"]["name"]);
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Basic validation (e.g., file type, size) can be added here

        if (move_uploaded_file($_FILES["attachment_file"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO attachments (consultation_id, file_path, description) VALUES ($consultation_id, '$target_file', '$description')";
            if (!$conn->query($sql)) {
                echo "Error: " . $conn->error;
            }
        } else {
            echo "Error uploading file.";
        }
    }

    // Redirect back to the consultation page, preserving the patient_id
    $patient_id_sql = "SELECT patient_id FROM consultations WHERE id = $consultation_id";
    $result = $conn->query($patient_id_sql);
    if($row = $result->fetch_assoc()) {
        $patient_id = $row['patient_id'];
        header("Location: consultation.php?patient_id=$patient_id&consultation_id=$consultation_id&status=attachment_uploaded");
    } else {
        header("Location: patients.php");
    }
    exit();
} else {
    header('Location: patients.php');
    exit();
}
?>
