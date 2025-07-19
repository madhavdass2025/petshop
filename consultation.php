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

// Clear session data if we are starting a new consultation for a different patient
if (isset($_SESSION['consultation_patient_id']) && $_SESSION['consultation_patient_id'] != $patient_id) {
    unset($_SESSION['prescribed_medicines'], $_SESSION['ordered_tests']);
}
$_SESSION['consultation_patient_id'] = $patient_id;

// Fetch patient details
$patient_sql = "SELECT * FROM patients WHERE id = $patient_id";
$patient_result = $conn->query($patient_sql);
if ($patient_result->num_rows == 0) {
    die("Patient not found.");
}
$patient = $patient_result->fetch_assoc();

// Fetch the last recorded weight
$last_weight_sql = "SELECT weight FROM consultations WHERE patient_id = $patient_id AND weight IS NOT NULL ORDER BY consultation_date DESC LIMIT 1";
$last_weight_result = $conn->query($last_weight_sql);
$last_weight = ($last_weight_result->num_rows > 0) ? $last_weight_result->fetch_assoc()['weight'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation for <?php echo htmlspecialchars($patient['name']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <a href="patients.php" class="btn btn-info" style="margin-bottom: 20px;">Back to Patient List</a>
    <a href="report.php?patient_id=<?php echo $patient_id; ?>" class="btn" style="margin-bottom: 20px;">Print Report</a>

    <!-- Persistent Patient Header -->
    <div class="patient-header">
        <h1><?php echo htmlspecialchars($patient['name']); ?></h1>
        <div class="patient-info-grid">
            <p><strong>Species:</strong> <?php echo htmlspecialchars($patient['species']); ?></p>
            <p><strong>Breed:</strong> <?php echo htmlspecialchars($patient['breed']); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
            <p><strong>Owner:</strong> <?php echo htmlspecialchars($patient['owner_name']); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($patient['owner_contact']); ?></p>
        </div>
    </div>

    <!-- Status Messages -->
    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        $msg_class = 'success';
        $msg_text = '';

        if ($status == 'success') {
            $msg_text = 'Consultation saved successfully!';
        } elseif ($status == 'attachment_success') {
            $msg_text = 'Attachment uploaded successfully!';
        } elseif ($status == 'error') {
            $msg_class = 'error';
            $msg_text = 'Error: ' . htmlspecialchars($_GET['msg'] ?? 'An unknown error occurred.');
        }

        if ($msg_text) {
            echo "<div class='status-message {$msg_class}' style='padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid; color: " . ($msg_class == 'success' ? 'green' : 'red') . "; background-color: " . ($msg_class == 'success' ? '#d4edda' : '#f8d7da') . ";'>{$msg_text}</div>";
        }
    }
    ?>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-link active" onclick="openTab(event, 'history')">History</button>
        <button class="tab-link" onclick="openTab(event, 'notes')">Notes</button>
        <button class="tab-link" onclick="openTab(event, 'medicine')">Medicine</button>
        <button class="tab-link" onclick="openTab(event, 'test')">Test</button>
        <button class="tab-link" onclick="openTab(event, 'attachments')">Attachments</button>
        <button class="tab-link" onclick="openTab(event, 'preview')">Preview & Finish</button>
    </div>

    <!-- Tab Content -->
    <div id="history" class="tab-content active">
        <h3>Medical History</h3>
        <?php
        $history_sql = "SELECT c.*, u.username FROM consultations c JOIN users u ON c.submitted_by = u.id WHERE c.patient_id = :patient_id AND c.cancel = 0 ORDER BY c.consultation_date DESC";
        $stmt = $conn->prepare($history_sql);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        $history_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($history_result) > 0) {
            foreach ($history_result as $consult) {
                $consult_id = $consult['id'];
                echo "<div class='history-item' style='border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px;'>";
                echo "<h4>Consultation on " . date('d-m-Y h:i A', strtotime($consult['consultation_date'])) . " (by " . htmlspecialchars($consult['username']) . ")</h4>";
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

                // Link to manage attachments for this specific past consultation
                echo "<a href='consultation.php?patient_id={$patient_id}&consultation_id={$consult_id}' class='btn' style='background-color:#6c757d; margin-top:10px;'>Manage Attachments</a>";

                if ($_SESSION['role'] === 'admin') {
                    echo " <a href='delete.php?type=consultation&id=" . $consult_id . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this consultation?\")'>Delete</a>";
                }

                echo "</div>";
            }
        } else {
            echo "<p>No past medical history found.</p>";
        }
        ?>
    </div>

    <div id="notes" class="tab-content">
        <h3>Current Consultation Notes</h3>
        <form id="consultation-notes-form">
            <div class="grid-container">
                <div class="form-group">
                    <label for="weight">Weight</label>
                    <div class="weight-group">
                        <input type="number" step="any" id="weight" name="weight">
                        <select name="weight_unit" id="weight_unit">
                            <option value="kg">kg</option>
                            <option value="g">g</option>
                        </select>
                    </div>
                    <?php if ($last_weight): ?>
                        <span class="previous-weight">Previous: <?php echo htmlspecialchars($last_weight); ?> kg</span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="temperature">Temperature (°C)</label>
                    <input type="number" step="0.1" id="temperature" name="temperature">
                </div>
            </div>
            <div class="form-group">
                <label for="chief_complaint">Chief Complaint / Notes</label>
                <textarea id="chief_complaint" name="chief_complaint" rows="6"></textarea>
            </div>
        </form>
    </div>

    <div id="medicine" class="tab-content">
        <h3>Prescribe Medicine</h3>
        <form id="medicine-form" onsubmit="return false;">
            <div class="grid-container">
                <div class="form-group">
                    <label for="medicine_id">Medicine Name</label>
                    <select name="medicine_id" id="medicine_id" onchange="toggleOtherField(this, '#medicine_name_other_div')">
                        <option value="">Select Medicine</option>
                        <?php
                        $medicines_sql = "SELECT id, name FROM medicines_master ORDER BY name";
                        $medicines_result = $conn->query($medicines_sql);
                        while($med = $medicines_result->fetch_assoc()) {
                            echo "<option value='{$med['id']}'>" . htmlspecialchars($med['name']) . "</option>";
                        }
                        ?>
                        <option value="other">Other...</option>
                    </select>
                </div>
                <div class="form-group" id="medicine_name_other_div" style="display:none;">
                    <label for="medicine_name_other">New Medicine Name</label>
                    <input type="text" name="medicine_name_other" id="medicine_name_other">
                </div>
                 <div class="form-group">
                    <label for="frequency">Frequency</label>
                    <select name="frequency" id="frequency">
                        <option value="OD">Once a day (OD)</option>
                        <option value="BD">Twice a day (BD)</option>
                        <option value="TID">Three times a day (TID)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dosage_form">Dosage Form</label>
                    <select name="dosage_form" id="dosage_form">
                        <option value="Tablet">Tablet</option>
                        <option value="Liquid">Liquid</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="unit_quantity">Unit Quantity</label>
                    <input type="text" name="unit_quantity" id="unit_quantity">
                </div>
                <div class="form-group">
                    <label for="unit_type">Unit Type</label>
                    <select name="unit_type" id="unit_type">
                        <option value="tablet">tablet(s)</option>
                        <option value="ml">ml</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="food_relation">Food Relation</label>
                    <select name="food_relation" id="food_relation">
                        <option value="After food">After food</option>
                        <option value="Before food">Before food</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="medicine_notes">Medicine Notes</label>
                <textarea name="notes" id="medicine_notes" rows="2"></textarea>
            </div>
            <button type="button" onclick="addMedicine()">Add Medicine</button>
        </form>
        <hr>
        <h3>Added Medicines</h3>
        <div id="medicines-preview-table"></div>
    </div>

    <div id="test" class="tab-content">
        <h3>Order Tests</h3>
        <form id="test-form" onsubmit="return false;">
             <div class="grid-container" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="test_id">Test Name</label>
                    <select name="test_id" id="test_id" onchange="toggleOtherField(this, '#test_name_other_div')">
                        <option value="">Select Test</option>
                        <?php
                        $tests_sql = "SELECT id, name FROM tests_master ORDER BY name";
                        $tests_result = $conn->query($tests_sql);
                        while($test = $tests_result->fetch_assoc()) {
                            echo "<option value='{$test['id']}'>" . htmlspecialchars($test['name']) . "</option>";
                        }
                        ?>
                        <option value="other">Other...</option>
                    </select>
                </div>
                <div class="form-group" id="test_name_other_div" style="display:none;">
                    <label for="test_name_other">New Test Name</label>
                    <input type="text" name="test_name_other" id="test_name_other">
                </div>
            </div>
            <button type="button" onclick="addTest()">Add Test</button>
        </form>
        <hr>
        <h3>Ordered Tests</h3>
        <div id="tests-preview-table"></div>
    </div>
    <div id="attachments" class="tab-content">
        <h3>Manage Attachments</h3>
        <?php
        $consultation_id_for_attachments = $_GET['consultation_id'] ?? null;
        if ($consultation_id_for_attachments):
        ?>
            <form action="upload_attachment.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation_id_for_attachments; ?>">
                <div class="form-group">
                    <label for="description">File Description</label>
                    <input type="text" name="description" id="description" required>
                </div>
                <div class="form-group">
                    <label for="attachment_file">Select File</label>
                    <input type="file" name="attachment_file" id="attachment_file" required>
                </div>
                <button type="submit" name="upload_attachment">Upload Attachment</button>
            </form>
            <hr>
            <h4>Uploaded Files:</h4>
            <?php
            $att_sql = "SELECT * FROM attachments WHERE consultation_id = " . (int)$consultation_id_for_attachments;
            $att_res = $conn->query($att_sql);
            if($att_res->num_rows > 0) {
                echo "<ul>";
                while($att_row = $att_res->fetch_assoc()) {
                    echo "<li><a href='" . htmlspecialchars($att_row['file_path']) . "' target='_blank'>" . htmlspecialchars($att_row['description']) . "</a></li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No attachments for this consultation yet.</p>";
            }
            ?>
        <?php else: ?>
            <p>You must save a consultation before you can add attachments. To add attachments to a past visit, find it in the 'History' tab and click 'Manage Attachments'.</p>
        <?php endif; ?>
    </div>

    <div id="preview" class="tab-content">
        <h3>Preview Consultation</h3>
        <form action="finish_consultation.php" method="POST" id="finish-form">
            <input type="hidden" name="weight" id="preview_weight">
            <input type="hidden" name="weight_unit" id="preview_weight_unit">
            <input type="hidden" name="temperature" id="preview_temperature">
            <input type="hidden" name="chief_complaint" id="preview_chief_complaint">
            <div id="preview-content" style="margin-bottom: 20px;"></div>
            <button type="submit" class="btn">Finish and Save Consultation</button>
        </form>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";

    if (tabName === 'medicine') loadMedicines();
    if (tabName === 'test') loadTests();
    if (tabName === 'preview') generatePreview();
}

function toggleOtherField(selectElement, otherDivSelector) {
    document.querySelector(otherDivSelector).style.display = selectElement.value === 'other' ? 'block' : 'none';
}

function addMedicine() {
    const form = document.getElementById('medicine-form');
    const formData = new FormData(form);
    formData.append('action', 'add_medicine');
    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                loadSessionMedicines();
                form.reset();
                toggleOtherField({ value: '' }, '#medicine_name_other_div');
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function loadMedicines() {
    fetch('ajax_handler.php?action=get_medicines')
        .then(res => res.json())
        .then(data => {
            const previewDiv = document.getElementById('medicines-preview-table');
            let tableHtml = '<table><thead><tr><th>Name</th><th>Dosage</th><th>Notes</th><th>Action</th></tr></thead><tbody>';
            if (data && data.length > 0) {
                data.forEach(med => {
                    tableHtml += `<tr>
                        <td>${escapeHTML(med.medicine_name)}</td>
                        <td>${escapeHTML(med.unit_quantity)} ${escapeHTML(med.unit_type)}</td>
                        <td>${escapeHTML(med.notes)}</td>
                        <td>`;
                    if ("<?php echo $_SESSION['role']; ?>" === 'admin') {
                        tableHtml += `<a href='delete.php?type=medicine&id=${med.id}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>`;
                    }
                    tableHtml += `</td>
                    </tr>`;
                });
            } else {
                tableHtml += '<tr><td colspan="4">No medicines added.</td></tr>';
            }
            tableHtml += '</tbody></table>';
            previewDiv.innerHTML = tableHtml;
        });
}

function addTest() {
    const form = document.getElementById('test-form');
    const formData = new FormData(form);
    formData.append('action', 'add_test');
    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                loadSessionTests();
                form.reset();
                toggleOtherField({ value: '' }, '#test_name_other_div');
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function loadTests() {
    fetch('ajax_handler.php?action=get_tests')
        .then(res => res.json())
        .then(data => {
            const previewDiv = document.getElementById('tests-preview-table');
            let tableHtml = '<table><thead><tr><th>Name</th><th>Action</th></tr></thead><tbody>';
            if (data && data.length > 0) {
                data.forEach(test => {
                    tableHtml += `<tr>
                        <td>${escapeHTML(test.test_name)}</td>
                        <td>`;
                    if ("<?php echo $_SESSION['role']; ?>" === 'admin') {
                        tableHtml += `<a href='delete.php?type=test&id=${test.id}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>`;
                    }
                    tableHtml += `</td>
                    </tr>`;
                });
            } else {
                tableHtml += '<tr><td colspan="2">No tests ordered.</td></tr>';
            }
            tableHtml += '</tbody></table>';
            previewDiv.innerHTML = tableHtml;
        });
}

function escapeHTML(str) {
    return str ? str.toString().replace(/[&<>"']/g, match => ({'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#39;'})[match]) : '';
}

function generatePreview() {
    // Populate hidden form fields
    document.getElementById('preview_weight').value = document.getElementById('weight').value;
    document.getElementById('preview_weight_unit').value = document.getElementById('weight_unit').value;
    document.getElementById('preview_temperature').value = document.getElementById('temperature').value;
    document.getElementById('preview_chief_complaint').value = document.getElementById('chief_complaint').value;

    const previewContent = document.getElementById('preview-content');
    let html = '<h4>Consultation Notes</h4>';
    html += `<p><strong>Weight:</strong> ${escapeHTML(document.getElementById('weight').value)} ${escapeHTML(document.getElementById('weight_unit').value)}</p>`;
    html += `<p><strong>Temperature:</strong> ${escapeHTML(document.getElementById('temperature').value) || 'N/A'} °C</p>`;
    html += `<p><strong>Notes:</strong><br>${document.getElementById('chief_complaint').value.replace(/\n/g, '<br>') || 'No notes.'}</p><hr>`;

    const medsPromise = fetch('ajax_handler.php?action=get_medicines').then(res => res.json());
    const testsPromise = fetch('ajax_handler.php?action=get_tests').then(res => res.json());

    Promise.all([medsPromise, testsPromise]).then(([meds, tests]) => {
        html += '<h4>Prescribed Medicines</h4>';
        if (meds && meds.length > 0) {
            html += '<ul>';
            meds.forEach(med => { html += `<li>${escapeHTML(med.medicine_name)}</li>`; });
            html += '</ul>';
        } else {
            html += '<p>No medicines prescribed.</p>';
        }
        html += '<hr>';

        html += '<h4>Ordered Tests</h4>';
        if (tests && tests.length > 0) {
            html += '<ul>';
            tests.forEach(test => { html += `<li>${escapeHTML(test.test_name)}</li>`; });
            html += '</ul>';
        } else {
            html += '<p>No tests ordered.</p>';
        }
        previewContent.innerHTML = html;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.tab-link.active').click();
});
</script>

</body>
</html>
