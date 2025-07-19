<?php
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Simple router for AJAX actions
switch ($action) {
    case 'add_medicine':
        add_medicine();
        break;
    case 'get_session_medicines':
        get_medicines();
        break;
    case 'add_test':
        add_test();
        break;
    case 'get_session_tests':
        get_tests();
        break;
}

function add_medicine() {
    global $conn;
    $consultation_id = $_SESSION['consultation_patient_id'] ?? 0;
    if ($consultation_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Patient session expired.']);
        return;
    }

    if (empty($_POST['medicine_id']) || ($_POST['medicine_id'] == 'other' && empty($_POST['medicine_name_other']))) {
        echo json_encode(['status' => 'error', 'message' => 'Medicine name is required.']);
        return;
    }

    $medicine_id = $_POST['medicine_id'];
    if ($medicine_id == 'other') {
        $new_med_name = $_POST['medicine_name_other'];
        $stmt = $conn->prepare("INSERT INTO medicines_master (name) VALUES (:name)");
        $stmt->bindParam(':name', $new_med_name);
        $stmt->execute();
        $medicine_id = $conn->lastInsertId();
    }

    $submitted_by = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO prescribed_medicines (consultation_id, medicine_id, frequency, dosage_form, unit_quantity, unit_type, food_relation, notes, submitted_by) VALUES (:consultation_id, :medicine_id, :frequency, :dosage_form, :unit_quantity, :unit_type, :food_relation, :notes, :submitted_by)");
    $stmt->bindParam(':consultation_id', $consultation_id);
    $stmt->bindParam(':medicine_id', $medicine_id);
    $stmt->bindParam(':frequency', $_POST['frequency']);
    $stmt->bindParam(':dosage_form', $_POST['dosage_form']);
    $stmt->bindParam(':unit_quantity', $_POST['unit_quantity']);
    $stmt->bindParam(':unit_type', $_POST['unit_type']);
    $stmt->bindParam(':food_relation', $_POST['food_relation']);
    $stmt->bindParam(':notes', $_POST['notes']);
    $stmt->bindParam(':submitted_by', $submitted_by);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add medicine.']);
    }
}

function get_medicines() {
    global $conn;
    $consultation_id = $_SESSION['consultation_patient_id'] ?? 0;
    $stmt = $conn->prepare("SELECT pm.*, mm.name as medicine_name FROM prescribed_medicines pm JOIN medicines_master mm ON pm.medicine_id = mm.id WHERE pm.consultation_id = :consultation_id AND pm.cancel = 0");
    $stmt->bindParam(':consultation_id', $consultation_id);
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($medicines);
}

function add_test() {
    global $conn;
    $consultation_id = $_SESSION['consultation_patient_id'] ?? 0;
    if ($consultation_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Patient session expired.']);
        return;
    }

    if (empty($_POST['test_id']) || ($_POST['test_id'] == 'other' && empty($_POST['test_name_other']))) {
        echo json_encode(['status' => 'error', 'message' => 'Test name is required.']);
        return;
    }

    $test_id = $_POST['test_id'];
    if ($test_id == 'other') {
        $new_test_name = $_POST['test_name_other'];
        $stmt = $conn->prepare("INSERT INTO tests_master (name) VALUES (:name)");
        $stmt->bindParam(':name', $new_test_name);
        $stmt->execute();
        $test_id = $conn->lastInsertId();
    }

    $submitted_by = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO ordered_tests (consultation_id, test_id, submitted_by) VALUES (:consultation_id, :test_id, :submitted_by)");
    $stmt->bindParam(':consultation_id', $consultation_id);
    $stmt->bindParam(':test_id', $test_id);
    $stmt->bindParam(':submitted_by', $submitted_by);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add test.']);
    }
}

function get_tests() {
    global $conn;
    $consultation_id = $_SESSION['consultation_patient_id'] ?? 0;
    $stmt = $conn->prepare("SELECT ot.*, tm.name as test_name FROM ordered_tests ot JOIN tests_master tm ON ot.test_id = tm.id WHERE ot.consultation_id = :consultation_id AND ot.cancel = 0");
    $stmt->bindParam(':consultation_id', $consultation_id);
    $stmt->execute();
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($tests);
}
?>
