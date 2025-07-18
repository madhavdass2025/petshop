<?php
include 'db.php';

// Redirect if no patient is selected
if (!isset($_GET['patient_id'])) {
    header('Location: patients.php');
    exit;
}

$patient_id = (int)$_GET['patient_id'];

// Clear session data if we are starting a new consultation for a different patient
if (isset($_SESSION['consultation_patient_id']) && $_SESSION['consultation_patient_id'] != $patient_id) {
    unset($_SESSION['prescribed_medicines']);
    unset($_SESSION['ordered_tests']);
}
$_SESSION['consultation_patient_id'] = $patient_id;


// Fetch patient details
$patient_sql = "SELECT * FROM patients WHERE id = $patient_id";
$patient_result = $conn->query($patient_sql);
if ($patient_result->num_rows == 0) {
    echo "Patient not found.";
    exit;
}
$patient = $patient_result->fetch_assoc();

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

    <?php
    // To display status messages from form submissions
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        if ($status == 'success') {
            echo '<div style="color: green; border: 1px solid green; padding: 10px; margin-bottom: 15px; border-radius: 5px;">Consultation saved successfully!</div>';
        } elseif ($status == 'error') {
            $msg = htmlspecialchars($_GET['msg'] ?? 'An unknown error occurred.');
            echo '<div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px; border-radius: 5px;">Error: ' . $msg . '</div>';
        }
    }
    ?>

    <div class="tab-nav">
        <button class="tab-link active" onclick="openTab(event, 'notes')">Notes</button>
        <button class="tab-link" onclick="openTab(event, 'history')">History</button>
        <button class="tab-link" onclick="openTab(event, 'medicine')">Medicine</button>
        <button class="tab-link" onclick="openTab(event, 'test')">Test</button>
        <button class="tab-link" onclick="openTab(event, 'attachments')">Attachments</button>
        <button class="tab-link" onclick="openTab(event, 'preview')">Preview & Finish</button>
    </div>

    <div id="notes" class="tab-content active">
        <h3>Current Consultation Notes</h3>
        <form id="consultation-notes-form">
            <div class="grid-container">
                <div class="form-group">
                    <label for="weight">Weight (kg)</label>
                    <input type="number" step="0.01" id="weight" name="weight">
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

    <div id="history" class="tab-content">
        <h3>Medical History</h3>
        <?php
        $history_sql = "SELECT * FROM consultations WHERE patient_id = $patient_id ORDER BY consultation_date DESC";
        $history_result = $conn->query($history_sql);

        if ($history_result->num_rows > 0) {
            while ($consult = $history_result->fetch_assoc()) {
                $consultation_id = $consult['id'];
                echo "<div class='history-item' style='border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px;'>";
                echo "<h4>Consultation on " . date('d-m-Y h:i A', strtotime($consult['consultation_date'])) . "</h4>";
                echo "<p><strong>Weight:</strong> " . htmlspecialchars($consult['weight']) . " kg</p>";
                echo "<p><strong>Temperature:</strong> " . htmlspecialchars($consult['temperature']) . " °C</p>";
                echo "<p><strong>Notes:</strong> " . nl2br(htmlspecialchars($consult['chief_complaint'])) . "</p>";

                // Fetch and display prescribed medicines for this past consultation
                $med_sql = "SELECT mm.name, pm.notes FROM prescribed_medicines pm JOIN medicines_master mm ON pm.medicine_id = mm.id WHERE pm.consultation_id = $consultation_id";
                $med_result = $conn->query($med_sql);
                if ($med_result->num_rows > 0) {
                    echo "<h5>Prescribed Medicines:</h5><ul>";
                    while ($med = $med_result->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($med['name']) . " - " . htmlspecialchars($med['notes']) . "</li>";
                    }
                    echo "</ul>";
                }

                // Fetch and display ordered tests for this past consultation
                $test_sql = "SELECT tm.name, ot.result FROM ordered_tests ot JOIN tests_master tm ON ot.test_id = tm.id WHERE ot.consultation_id = $consultation_id";
                $test_result = $conn->query($test_sql);
                if ($test_result->num_rows > 0) {
                    echo "<h5>Ordered Tests:</h5><ul>";
                    while ($test = $test_result->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($test['name']) . ($test['result'] ? " - Result: " . htmlspecialchars($test['result']) : "") . "</li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
            }
        } else {
            echo "<p>No past medical history found for this patient.</p>";
        }
        ?>
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
        <p>Attachments can only be added to a saved consultation. Please finish the current consultation first, then find it in the 'History' tab to add files.</p>
        <!-- In a real app, you'd show an upload form here if the consultation is already saved -->
    </div>

    <div id="preview" class="tab-content">
        <h3>Preview Consultation</h3>
        <form action="finish_consultation.php" method="POST" id="finish-form">
            <!-- Hidden fields to carry over the main consultation notes -->
            <input type="hidden" name="weight" id="preview_weight">
            <input type="hidden" name="temperature" id="preview_temperature">
            <input type="hidden" name="chief_complaint" id="preview_chief_complaint">

            <div id="preview-content" style="margin-bottom: 20px;">
                <!-- Content will be loaded here by JS -->
            </div>

            <button type="submit" class="btn" name="finish_consultation">Finish and Save Consultation</button>
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

    // Load data for tabs when they are opened
    if (tabName === 'medicine') loadSessionMedicines();
    if (tabName === 'test') loadSessionTests();
    if (tabName === 'preview') generatePreview();
}

function toggleOtherField(selectElement, otherDivSelector) {
    document.querySelector(otherDivSelector).style.display = selectElement.value === 'other' ? 'block' : 'none';
}

// --- Medicine Functions ---
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
            toggleOtherField({value: ''}, '#medicine_name_other_div');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeMedicine(id) {
    if (!confirm('Are you sure?')) return;
    const formData = new FormData();
    formData.append('action', 'remove_medicine');
    formData.append('id', id);
    fetch('ajax_handler.php', { method: 'POST', body: formData })
    .then(() => loadSessionMedicines());
}

function loadSessionMedicines() {
    fetch('ajax_handler.php?action=get_session_medicines')
    .then(res => res.json())
    .then(data => {
        const previewDiv = document.getElementById('medicines-preview-table');
        let tableHtml = '<table><thead><tr><th>Name</th><th>Dosage</th><th>Frequency</th><th>Notes</th><th>Action</th></tr></thead><tbody>';
        if (data && data.length > 0) {
            data.forEach(med => {
                tableHtml += `
                    <tr>
                        <td>${escapeHTML(med.medicine_name)}</td>
                        <td>${escapeHTML(med.unit_quantity)} ${escapeHTML(med.unit_type)}</td>
                        <td>${escapeHTML(med.frequency)}</td>
                        <td>${escapeHTML(med.notes)}</td>
                        <td><button type="button" class="btn-danger" onclick="removeMedicine('${med.id}')">Remove</button></td>
                    </tr>`;
            });
        } else {
            tableHtml += '<tr><td colspan="5">No medicines added.</td></tr>';
        }
        tableHtml += '</tbody></table>';
        previewDiv.innerHTML = tableHtml;
    });
}

// --- Test Functions ---
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
            toggleOtherField({value: ''}, '#test_name_other_div');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeTest(id) {
    if (!confirm('Are you sure?')) return;
    const formData = new FormData();
    formData.append('action', 'remove_test');
    formData.append('id', id);
    fetch('ajax_handler.php', { method: 'POST', body: formData })
    .then(() => loadSessionTests());
}

function loadSessionTests() {
    fetch('ajax_handler.php?action=get_session_tests')
    .then(res => res.json())
    .then(data => {
        const previewDiv = document.getElementById('tests-preview-table');
        let tableHtml = '<table><thead><tr><th>Name</th><th>Action</th></tr></thead><tbody>';
        if (data && data.length > 0) {
            data.forEach(test => {
                tableHtml += `
                    <tr>
                        <td>${escapeHTML(test.test_name)}</td>
                        <td><button type="button" class="btn-danger" onclick="removeTest('${test.id}')">Remove</button></td>
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
    if (str === null || str === undefined) return '';
    return str.toString().replace(/[&<>"']/g, match => ({'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#39;'})[match]);
}

function generatePreview() {
    // 1. Populate hidden form fields with main consultation data from the 'Notes' tab
    document.getElementById('preview_weight').value = document.getElementById('weight').value;
    document.getElementById('preview_temperature').value = document.getElementById('temperature').value;
    document.getElementById('preview_chief_complaint').value = document.getElementById('chief_complaint').value;

    const previewContent = document.getElementById('preview-content');
    let html = '<h4>Consultation Notes</h4>';
    html += `<p><strong>Weight:</strong> ${escapeHTML(document.getElementById('weight').value) || 'N/A'} kg</p>`;
    html += `<p><strong>Temperature:</strong> ${escapeHTML(document.getElementById('temperature').value) || 'N/A'} °C</p>`;
    html += `<p><strong>Notes:</strong><br>${document.getElementById('chief_complaint').value.replace(/\n/g, '<br>') || 'No notes.'}</p><hr>`;

    // 2. Fetch and display session medicines and tests
    const medsPromise = fetch('ajax_handler.php?action=get_session_medicines').then(res => res.json());
    const testsPromise = fetch('ajax_handler.php?action=get_session_tests').then(res => res.json());

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


// Set the 'Notes' tab to be active by default on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.tab-link').click();
});
</script>

</body>
</html>
