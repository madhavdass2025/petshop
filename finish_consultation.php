<?php
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->beginTransaction();

    try {
        $patient_id = $_SESSION['consultation_patient_id'] ?? 0;
        if ($patient_id == 0) throw new Exception("Patient session expired.");

        // Handle weight conversion
        $weight_value = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $weight_unit = $_POST['weight_unit'] ?? 'kg';
        $weight_in_kg = ($weight_unit == 'g' && $weight_value !== null) ? $weight_value / 1000 : $weight_value;

        $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
        $chief_complaint = $_POST['chief_complaint'];
        $submitted_by = $_SESSION['user_id'];

        // Insert main consultation record
        $stmt = $conn->prepare("INSERT INTO consultations (patient_id, weight, temperature, chief_complaint, submitted_by) VALUES (:patient_id, :weight, :temperature, :chief_complaint, :submitted_by)");
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':weight', $weight_in_kg);
        $stmt->bindParam(':temperature', $temperature);
        $stmt->bindParam(':chief_complaint', $chief_complaint);
        $stmt->bindParam(':submitted_by', $submitted_by);
        if (!$stmt->execute()) throw new Exception("Error creating consultation.");
        $consultation_id = $conn->lastInsertId();

        // Process medicines
        if (isset($_SESSION['prescribed_medicines'])) {
            foreach ($_SESSION['prescribed_medicines'] as $med) {
                $medicine_id = $med['medicine_id'];
                if ($medicine_id == 'other') {
                    $new_med_name = $med['medicine_name_other'];
                    $stmt = $conn->prepare("INSERT INTO medicines_master (name) VALUES (:name)");
                    $stmt->bindParam(':name', $new_med_name);
                    $stmt->execute();
                    $medicine_id = $conn->lastInsertId();
                }
                $stmt = $conn->prepare("INSERT INTO prescribed_medicines (consultation_id, medicine_id, frequency, dosage_form, unit_quantity, unit_type, food_relation, notes, submitted_by) VALUES (:consultation_id, :medicine_id, :frequency, :dosage_form, :unit_quantity, :unit_type, :food_relation, :notes, :submitted_by)");
                $stmt->bindParam(':consultation_id', $consultation_id);
                $stmt->bindParam(':medicine_id', $medicine_id);
                $stmt->bindParam(':frequency', $med['frequency']);
                $stmt->bindParam(':dosage_form', $med['dosage_form']);
                $stmt->bindParam(':unit_quantity', $med['unit_quantity']);
                $stmt->bindParam(':unit_type', $med['unit_type']);
                $stmt->bindParam(':food_relation', $med['food_relation']);
                $stmt->bindParam(':notes', $med['notes']);
                $stmt->bindParam(':submitted_by', $submitted_by);
                if (!$stmt->execute()) throw new Exception("Error saving medicine.");
            }
        }

        // Process tests
        if (isset($_SESSION['ordered_tests'])) {
            foreach ($_SESSION['ordered_tests'] as $test) {
                $test_id = $test['test_id'];
                if ($test_id == 'other') {
                    $new_test_name = $test['test_name_other'];
                    $stmt = $conn->prepare("INSERT INTO tests_master (name) VALUES (:name)");
                    $stmt->bindParam(':name', $new_test_name);
                    $stmt->execute();
                    $test_id = $conn->lastInsertId();
                }
                $stmt = $conn->prepare("INSERT INTO ordered_tests (consultation_id, test_id, submitted_by) VALUES (:consultation_id, :test_id, :submitted_by)");
                $stmt->bindParam(':consultation_id', $consultation_id);
                $stmt->bindParam(':test_id', $test_id);
                $stmt->bindParam(':submitted_by', $submitted_by);
                if (!$stmt->execute()) throw new Exception("Error saving test.");
            }
        }

        $conn->commit();
        unset($_SESSION['prescribed_medicines'], $_SESSION['ordered_tests']);
        header("Location: consultation.php?patient_id=$patient_id&status=success");

    } catch (Exception $e) {
        $conn->rollBack();
        $patient_id_on_error = $_SESSION['consultation_patient_id'] ?? 0;
        header("Location: consultation.php?patient_id=$patient_id_on_error&status=error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}
?>
