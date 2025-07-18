<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();

    try {
        $patient_id = $_SESSION['consultation_patient_id'] ?? 0;
        if ($patient_id == 0) throw new Exception("Patient session expired.");

        // Handle weight conversion
        $weight_value = (float)$_POST['weight'];
        $weight_unit = $_POST['weight_unit'];
        $weight_in_kg = ($weight_unit == 'g') ? $weight_value / 1000 : $weight_value;

        $weight_db = $weight_in_kg > 0 ? "'" . $conn->real_escape_string($weight_in_kg) . "'" : 'NULL';
        $temperature_db = !empty($_POST['temperature']) ? "'" . $conn->real_escape_string($_POST['temperature']) . "'" : 'NULL';
        $chief_complaint_db = $conn->real_escape_string($_POST['chief_complaint']);

        // Insert main consultation record
        $consult_sql = "INSERT INTO consultations (patient_id, weight, temperature, chief_complaint) VALUES ($patient_id, $weight_db, $temperature_db, '$chief_complaint_db')";
        if (!$conn->query($consult_sql)) throw new Exception("Error creating consultation: " . $conn->error);
        $consultation_id = $conn->insert_id;

        // Process medicines
        if (isset($_SESSION['prescribed_medicines'])) {
            foreach ($_SESSION['prescribed_medicines'] as $med) {
                $medicine_id = $med['medicine_id'];
                if ($medicine_id == 'other') {
                    $new_med_name = $conn->real_escape_string($med['medicine_name_other']);
                    $conn->query("INSERT INTO medicines_master (name) VALUES ('$new_med_name')");
                    $medicine_id = $conn->insert_id;
                }
                $med_stmt = $conn->prepare("INSERT INTO prescribed_medicines (consultation_id, medicine_id, frequency, dosage_form, unit_quantity, unit_type, food_relation, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $med_stmt->bind_param("iissssss", $consultation_id, $medicine_id, $med['frequency'], $med['dosage_form'], $med['unit_quantity'], $med['unit_type'], $med['food_relation'], $med['notes']);
                if (!$med_stmt->execute()) throw new Exception("Error saving medicine.");
            }
        }

        // Process tests
        if (isset($_SESSION['ordered_tests'])) {
            foreach ($_SESSION['ordered_tests'] as $test) {
                $test_id = $test['test_id'];
                if ($test_id == 'other') {
                    $new_test_name = $conn->real_escape_string($test['test_name_other']);
                    $conn->query("INSERT INTO tests_master (name) VALUES ('$new_test_name')");
                    $test_id = $conn->insert_id;
                }
                $test_stmt = $conn->prepare("INSERT INTO ordered_tests (consultation_id, test_id) VALUES (?, ?)");
                $test_stmt->bind_param("ii", $consultation_id, $test_id);
                if (!$test_stmt->execute()) throw new Exception("Error saving test.");
            }
        }

        $conn->commit();
        unset($_SESSION['prescribed_medicines'], $_SESSION['ordered_tests']);
        header("Location: consultation.php?patient_id=$patient_id&status=success");

    } catch (Exception $e) {
        $conn->rollback();
        $patient_id_on_error = $_SESSION['consultation_patient_id'] ?? 0;
        header("Location: consultation.php?patient_id=$patient_id_on_error&status=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}
?>
