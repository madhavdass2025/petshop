<?php
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['patient_id'])) {
    header('Location: patients.php');
    exit;
}
$patient_id = (int)$_GET['patient_id'];

// Fetch patient details
$patient_sql = "SELECT * FROM patients WHERE id = $patient_id";
$patient_result = $conn->query($patient_sql);
if ($patient_result->num_rows == 0) {
    die("Patient not found.");
}
$patient = $patient_result->fetch_assoc();

// Fetch consultation history
$filter = $_GET['filter'] ?? 'all';
$history_sql = "SELECT * FROM consultations WHERE patient_id = $patient_id";
if ($filter == 'today') {
    $history_sql .= " AND DATE(consultation_date) = CURDATE()";
}
$history_sql .= " ORDER BY consultation_date DESC";
$history_result = $conn->query($history_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Report for <?php echo htmlspecialchars($patient['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="no-print">
        <a href="consultation.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-info" style="margin-bottom: 20px;">Back to Consultation</a>

        <form method="get" action="report.php" style="display: inline-block; margin-bottom: 20px;">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            <label><input type="radio" name="filter" value="all" <?php echo (!isset($_GET['filter']) || $_GET['filter'] == 'all') ? 'checked' : ''; ?>> All</label>
            <label><input type="radio" name="filter" value="today" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'today') ? 'checked' : ''; ?>> Today's</label>
            <button type="submit" class="btn">Go</button>
        </form>

        <button onclick="window.print()" class="btn">Print Report</button>
    </div>

    <div class="patient-header">
        <h1>Consultation Report for <?php echo htmlspecialchars($patient['name']); ?></h1>
        <div class="patient-info-grid">
            <p><strong>Species:</strong> <?php echo htmlspecialchars($patient['species']); ?></p>
            <p><strong>Breed:</strong> <?php echo htmlspecialchars($patient['breed']); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
            <p><strong>Owner:</strong> <?php echo htmlspecialchars($patient['owner_name']); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($patient['owner_contact']); ?></p>
        </div>
    </div>

    <div id="consultation-history">
        <h3>Consultation History</h3>
        <?php
        if ($history_result->num_rows > 0) {
            while ($consult = $history_result->fetch_assoc()) {
                $consult_id = $consult['id'];
                echo "<div class='history-item' style='border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px;'>";
                echo "<h4>Consultation on " . date('d-m-Y h:i A', strtotime($consult['consultation_date'])) . "</h4>";
                if ($consult['weight']) echo "<p><strong>Weight:</strong> " . htmlspecialchars($consult['weight']) . " kg</p>";
                if ($consult['temperature']) echo "<p><strong>Temperature:</strong> " . htmlspecialchars($consult['temperature']) . " °C</p>";
                if ($consult['chief_complaint']) echo "<p><strong>Notes:</strong> " . nl2br(htmlspecialchars($consult['chief_complaint'])) . "</p>";

                // Medicines for this history item
                $med_history_sql = "SELECT m.name, pm.notes FROM prescribed_medicines pm JOIN medicines_master m ON pm.medicine_id=m.id WHERE pm.consultation_id=$consult_id";
                $med_history_res = $conn->query($med_history_sql);
                if($med_history_res->num_rows > 0) {
                    echo "<h5>Medicines Prescribed:</h5><ul>";
                    while($med_row = $med_history_res->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($med_row['name']) . " - " . htmlspecialchars($med_row['notes']) . "</li>";
                    }
                    echo "</ul>";
                }

                // Tests for this history item
                $test_history_sql = "SELECT t.name, ot.result FROM ordered_tests ot JOIN tests_master t ON ot.test_id=t.id WHERE ot.consultation_id=$consult_id";
                $test_history_res = $conn->query($test_history_sql);
                if($test_history_res->num_rows > 0) {
                    echo "<h5>Tests Ordered:</h5><ul>";
                    while($test_row = $test_history_res->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($test_row['name']) . ($test_row['result'] ? " (Result: ".htmlspecialchars($test_row['result']).")" : "") . "</li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
            }
        } else {
            echo "<p>No past medical history found.</p>";
        }
        ?>
    </div>
</div>
</body>
</html>
