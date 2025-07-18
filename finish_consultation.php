<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();

    try {
        $patient_id = $_SESSION['consultation_patient_id'] ?? 0;
        if ($patient_id == 0) throw new Exception("Patient session expired or not found.");

        // Data from the 'Notes' tab
        $weight = $_POST['weight'] ? "'" . $conn->real_escape_string($_POST['weight']) . "'" : 'NULL';
        $temperature = $_POST['temperature'] ? "'" . $conn->real_escape_string($_POST['temperature']) . "'" : 'NULL';
        $chief_complaint = $conn->real_escape_string($_POST['chief_complaint']);

        // Create the main consultation record
        $consult_sql = "INSERT INTO consultations (patient_id, weight, temperature, chief_complaint) VALUES ($patient_id, $weight, $temperature, '$chief_complaint')";
        if (!$conn->query($consult_sql)) throw new Exception("Error creating consultation record: " . $conn->error);
        $consultation_id = $conn->insert_id;

        // Process prescribed medicines from session
        if (isset($_SESSION['prescribed_medicines'])) {
            foreach ($_SESSION['prescribed_medicines'] as $med) {
                $medicine_id = $med['medicine_id'];
                if ($medicine_id == 'other' && !empty($med['medicine_name_other'])) {
                    $new_med_name = $conn->real_escape_string($med['medicine_name_other']);
                    $conn->query("INSERT INTO medicines_master (name) VALUES ('$new_med_name')");
                    $medicine_id = $conn->insert_id;
                }

                $presc_sql = "INSERT INTO prescribed_medicines (consultation_id, medicine_id, frequency, dosage_form, unit_quantity, unit_type, food_relation, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($presc_sql);
                $stmt->bind_param("iissssss", $consultation_id, $medicine_id, $med['frequency'], $med['dosage_form'], $med['unit_quantity'], $med['unit_type'], $med['food_relation'], $med['notes']);
                if (!$stmt->execute()) throw new Exception("Error saving medicine: " . $stmt->error);
            }
        }

        // Process ordered tests from session
        if (isset($_SESSION['ordered_tests'])) {
            foreach ($_SESSION['ordered_tests'] as $test) {
                $test_id = $test['test_id'];
                if ($test_id == 'other' && !empty($test['test_name_other'])) {
                    $new_test_name = $conn->real_escape_string($test['test_name_other']);
                    $conn->query("INSERT INTO tests_master (name) VALUES ('$new_test_name')");
                    $test_id = $conn->insert_id;
                }

                $order_test_sql = "INSERT INTO ordered_tests (consultation_id, test_id) VALUES (?, ?)";
                $stmt = $conn->prepare($order_test_sql);
                $stmt->bind_param("ii", $consultation_id, $test_id);
                if (!$stmt->execute()) throw new Exception("Error saving test: " . $stmt->error);
            }
        }

        $conn->commit();

        // Cleanup session
        unset($_SESSION['prescribed_medicines']);
        unset($_SESSION['ordered_tests']);

        header("Location: consultation.php?patient_id=$patient_id&status=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $patient_id_on_error = $_SESSION['consultation_patient_id'] ?? 0;
        header("Location: consultation.php?patient_id=$patient_id_on_error&status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: patients.php');
    exit();
}
?>
