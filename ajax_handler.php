<?php
include 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Simple router for AJAX actions
switch ($action) {
    case 'add_medicine':
        add_medicine_to_session();
        break;
    case 'remove_medicine':
        remove_medicine_from_session();
        break;
    case 'get_session_medicines':
        get_session_medicines();
        break;
    case 'add_test':
        add_test_to_session();
        break;
    case 'remove_test':
        remove_test_from_session();
        break;
    case 'get_session_tests':
        get_session_tests();
        break;
}

function add_medicine_to_session() {
    if (!isset($_SESSION['prescribed_medicines'])) {
        $_SESSION['prescribed_medicines'] = [];
    }

    if (empty($_POST['medicine_id']) || ($_POST['medicine_id'] == 'other' && empty($_POST['medicine_name_other']))) {
        echo json_encode(['status' => 'error', 'message' => 'Medicine name is required.']);
        return;
    }

    $medicine_id = $_POST['medicine_id'];
    $medicine_name = '';

    if ($medicine_id == 'other') {
        $medicine_name = $_POST['medicine_name_other'];
    } else {
        global $conn;
        $id = (int)$medicine_id;
        $result = $conn->query("SELECT name FROM medicines_master WHERE id = $id");
        if($row = $result->fetch_assoc()) {
            $medicine_name = $row['name'];
        }
    }

    $medicine_entry = [
        'id' => uniqid(),
        'medicine_id' => $medicine_id,
        'medicine_name' => $medicine_name,
        'medicine_name_other' => $_POST['medicine_name_other'] ?? '',
        'frequency' => $_POST['frequency'],
        'dosage_form' => $_POST['dosage_form'],
        'unit_quantity' => $_POST['unit_quantity'],
        'unit_type' => $_POST['unit_type'],
        'food_relation' => $_POST['food_relation'],
        'notes' => $_POST['notes']
    ];

    $_SESSION['prescribed_medicines'][] = $medicine_entry;
    echo json_encode(['status' => 'success']);
}

function remove_medicine_from_session() {
    $remove_id = $_POST['id'];
    if (isset($_SESSION['prescribed_medicines'])) {
        $_SESSION['prescribed_medicines'] = array_values(array_filter($_SESSION['prescribed_medicines'], fn($med) => $med['id'] != $remove_id));
    }
    echo json_encode(['status' => 'success']);
}

function get_session_medicines() {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['prescribed_medicines'] ?? []);
}

function add_test_to_session() {
    if (!isset($_SESSION['ordered_tests'])) {
        $_SESSION['ordered_tests'] = [];
    }

    if (empty($_POST['test_id']) || ($_POST['test_id'] == 'other' && empty($_POST['test_name_other']))) {
        echo json_encode(['status' => 'error', 'message' => 'Test name is required.']);
        return;
    }

    $test_id = $_POST['test_id'];
    $test_name = '';

    if ($test_id == 'other') {
        $test_name = $_POST['test_name_other'];
    } else {
        global $conn;
        $id = (int)$test_id;
        $result = $conn->query("SELECT name FROM tests_master WHERE id = $id");
        if($row = $result->fetch_assoc()) {
            $test_name = $row['name'];
        }
    }

    $test_entry = [
        'id' => uniqid(),
        'test_id' => $test_id,
        'test_name' => $test_name,
        'test_name_other' => $_POST['test_name_other'] ?? '',
    ];

    $_SESSION['ordered_tests'][] = $test_entry;
    echo json_encode(['status' => 'success']);
}

function remove_test_from_session() {
    $remove_id = $_POST['id'];
    if (isset($_SESSION['ordered_tests'])) {
        $_SESSION['ordered_tests'] = array_values(array_filter($_SESSION['ordered_tests'], fn($test) => $test['id'] != $remove_id));
    }
    echo json_encode(['status' => 'success']);
}

function get_session_tests() {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['ordered_tests'] ?? []);
}
?>
