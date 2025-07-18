<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();

    try {
        // 1. Get data from POST and SESSION
        $patient_id = $_SESSION['consultation_patient_id'] ?? 0;
        if ($patient_id == 0) {
            throw new Exception("Patient session not found.");
        }

        $weight = $_POST['weight'] ? $conn->real_escape_string($_POST['weight']) : 'NULL';
        $temperature = $_POST['temperature'] ? $conn->real_escape_string($_POST['temperature']) : 'NULL';
        $chief_complaint = $conn->real_escape_string($_POST['chief_complaint']);

        // 2. Create the main consultation record
        $consult_sql = "INSERT INTO consultations (patient_id, weight, temperature, chief_complaint) VALUES ($patient_id, $weight, $temperature, '$chief_complaint')";
        if (!$conn->query($consult_sql)) {
            throw new Exception("Error creating consultation record: " . $conn->error);
        }
        $consultation_id = $conn->insert_id;

        // 3. Process and insert prescribed medicines
        if (isset($_SESSION['prescribed_medicines']) && is_array($_SESSION['prescribed_medicines'])) {
            foreach ($_SESSION['prescribed_medicines'] as $med) {
                $medicine_id = $med['medicine_id'];

                // If it's a new medicine, add it to the master table first
                if ($medicine_id == 'other' && !empty($med['medicine_name_other'])) {
                    $new_med_name = $conn->real_escape_string($med['medicine_name_other']);
                    $conn->query("INSERT INTO medicines_master (name) VALUES ('$new_med_name')");
                    $medicine_id = $conn->insert_id;
                }

                $frequency = $conn->real_escape_string($med['frequency']);
                $dosage_form = $conn->real_escape_string($med['dosage_form']);
                $unit_quantity = $conn->real_escape_string($med['unit_quantity']);
                $unit_type = $conn->real_escape_string($med['unit_type']);
                $food_relation = $conn->real_escape_string($med['food_relation']);
                $notes = $conn->real_escape_string($med['notes']);

                $presc_sql = "INSERT INTO prescribed_medicines (consultation_id, medicine_id, frequency, dosage_form, unit_quantity, unit_type, food_relation, notes) VALUES ($consultation_id, $medicine_id, '$frequency', '$dosage_form', '$unit_quantity', '$unit_type', '$food_relation', '$notes')";
                if (!$conn->query($presc_sql)) {
                    throw new Exception("Error saving prescribed medicine: " . $conn->error);
                }
            }
        }

        // 4. Process and insert ordered tests
        if (isset($_SESSION['ordered_tests']) && is_array($_SESSION['ordered_tests'])) {
            foreach ($_SESSION['ordered_tests'] as $test) {
                $test_id = $test['test_id'];

                // If it's a new test, add it to the master table first
                if ($test_id == 'other' && !empty($test['test_name_other'])) {
                    $new_test_name = $conn->real_escape_string($test['test_name_other']);
                    $conn->query("INSERT INTO tests_master (name) VALUES ('$new_test_name')");
                    $test_id = $conn->insert_id;
                }

                $order_test_sql = "INSERT INTO ordered_tests (consultation_id, test_id) VALUES ($consultation_id, $test_id)";
                if (!$conn->query($order_test_sql)) {
                    throw new Exception("Error saving ordered test: " . $conn->error);
                }
            }
        }

        // 5. Commit transaction
        $conn->commit();

        // 6. Clear session data and redirect
        unset($_SESSION['consultation_patient_id']);
        unset($_SESSION['prescribed_medicines']);
        unset($_SESSION['ordered_tests']);

        header("Location: consultation.php?patient_id=$patient_id&status=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        // You can log the error message ($e->getMessage()) and show a user-friendly error page.
        header("Location: consultation.php?patient_id=" . ($_SESSION['consultation_patient_id'] ?? 0) . "&status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Redirect if accessed directly
    header('Location: patients.php');
    exit();
}
?>
