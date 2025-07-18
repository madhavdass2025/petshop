<?php
include 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add_medicine':
        add_medicine();
        break;
    case 'remove_medicine':
        remove_medicine();
        break;
    case 'get_session_medicines':
        get_session_medicines();
        break;
    case 'add_test':
        add_test();
        break;
    case 'remove_test':
        remove_test();
        break;
    case 'get_session_tests':
        get_session_tests();
        break;
}

function add_medicine() {
    if (!isset($_SESSION['prescribed_medicines'])) {
        $_SESSION['prescribed_medicines'] = [];
    }

    // Server-side validation
    if (empty($_POST['medicine_id']) || ($_POST['medicine_id'] == 'other' && empty($_POST['medicine_name_other']))) {
        echo json_encode(['status' => 'error', 'message' => 'Medicine name is required.']);
        return;
    }

    $medicine_id = $_POST['medicine_id'];
    $medicine_name = '';

    if ($medicine_id == 'other') {
        // The medicine doesn't exist in the master, so we use the temp name.
        // It will be added to the master table upon finishing the consultation.
        $medicine_name = $_POST['medicine_name_other'];
    } else {
        // Fetch the name from the master table for display
        global $conn;
        $id = (int)$medicine_id;
        $result = $conn->query("SELECT name FROM medicines_master WHERE id = $id");
        if($row = $result->fetch_assoc()) {
            $medicine_name = $row['name'];
        }
    }

    $medicine_entry = [
        'id' => time() . rand(), // a unique ID for the session array
        'medicine_id' => $medicine_id, // 'other' or a real ID
        'medicine_name' => $medicine_name, // The display name
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

function remove_medicine() {
    $remove_id = $_POST['id'];
    if (isset($_SESSION['prescribed_medicines'])) {
        $_SESSION['prescribed_medicines'] = array_filter($_SESSION['prescribed_medicines'], function($med) use ($remove_id) {
            return $med['id'] != $remove_id;
        });
        // Re-index array
        $_SESSION['prescribed_medicines'] = array_values($_SESSION['prescribed_medicines']);
    }
    echo json_encode(['status' => 'success']);
}

function get_session_medicines() {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['prescribed_medicines'] ?? []);
}


function add_test() {
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
        'id' => time() . rand(), // unique session ID
        'test_id' => $test_id, // 'other' or a real ID
        'test_name' => $test_name, // display name
        'test_name_other' => $_POST['test_name_other'] ?? '',
    ];

    $_SESSION['ordered_tests'][] = $test_entry;
    echo json_encode(['status' => 'success']);
}

function remove_test() {
    $remove_id = $_POST['id'];
    if (isset($_SESSION['ordered_tests'])) {
        $_SESSION['ordered_tests'] = array_filter($_SESSION['ordered_tests'], function($test) use ($remove_id) {
            return $test['id'] != $remove_id;
        });
        $_SESSION['ordered_tests'] = array_values($_SESSION['ordered_tests']);
    }
    echo json_encode(['status' => 'success']);
}

function get_session_tests() {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['ordered_tests'] ?? []);
}
?>
