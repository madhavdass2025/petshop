<?php
include 'db.php';

// Redirect if no patient is selected
if (!isset($_GET['patient_id'])) {
    header('Location: patients.php');
    exit;
}

$patient_id = (int)$_GET['patient_id'];

// Check if this is a new consultation session for this patient.
// If the patient ID in the session is different from the current one,
// it means we switched patients, so we clear the old consultation data.
if (isset($_SESSION['consultation_patient_id']) && $_SESSION['consultation_patient_id'] != $patient_id) {
    unset($_SESSION['prescribed_medicines']);
    unset($_SESSION['ordered_tests']);
}

// Fetch patient details
$patient_sql = "SELECT * FROM patients WHERE id = $patient_id";
$patient_result = $conn->query($patient_sql);
if ($patient_result->num_rows == 0) {
    echo "Patient not found.";
    exit;
}
$patient = $patient_result->fetch_assoc();

// Store patient_id in session to link all consultation data
$_SESSION['consultation_patient_id'] = $patient_id;

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
    <h1>Consultation for <?php echo htmlspecialchars($patient['name']); ?></h1>

    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        if ($status == 'success') {
            echo '<div style="color: green; border: 1px solid green; padding: 10px; margin-bottom: 15px; border-radius: 5px;">Consultation saved successfully!</div>';
        } elseif ($status == 'error') {
            $msg = htmlspecialchars($_GET['msg'] ?? 'An unknown error occurred.');
            echo '<div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px; border-radius: 5px;">Error: ' . $msg . '</div>';
        } elseif ($status == 'attachment_uploaded'){
            echo '<div style="color: green; border: 1px solid green; padding: 10px; margin-bottom: 15px; border-radius: 5px;">Attachment uploaded successfully!</div>';
        }
    }
    ?>

    <div class="tab-nav">
        <button class="tab-link active" onclick="openTab(event, 'info')">Patient Info</button>
        <button class="tab-link" onclick="openTab(event, 'history')">History</button>
        <button class="tab-link" onclick="openTab(event, 'medicine')">Medicine</button>
        <button class="tab-link" onclick="openTab(event, 'test')">Test</button>
        <button class="tab-link" onclick="openTab(event, 'attachments')">Attachments</button>
        <button class="tab-link" onclick="openTab(event, 'preview')">Preview & Finish</button>
    </div>

    <div id="info" class="tab-content active">
        <h3>Patient Information</h3>
        <p><strong>Species:</strong> <?php echo htmlspecialchars($patient['species']); ?></p>
        <p><strong>Breed:</strong> <?php echo htmlspecialchars($patient['breed']); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
        <p><strong>Owner:</strong> <?php echo htmlspecialchars($patient['owner_name']); ?> (<?php echo htmlspecialchars($patient['owner_contact']); ?>)</p>
        <hr>
        <h3>Current Consultation</h3>
        <form id="consultation-form">
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
                <label for="chief_complaint">Chief Complaint / Initial Notes</label>
                <textarea id="chief_complaint" name="chief_complaint" rows="4"></textarea>
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
                echo "<div class='history-item' style='border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px;'>";
                echo "<h4>Consultation on " . date('d-m-Y h:i A', strtotime($consult['consultation_date'])) . "</h4>";
                echo "<p><strong>Weight:</strong> " . htmlspecialchars($consult['weight']) . " kg</p>";
                echo "<p><strong>Temperature:</strong> " . htmlspecialchars($consult['temperature']) . " °C</p>";
                echo "<p><strong>Notes:</strong> " . nl2br(htmlspecialchars($consult['chief_complaint'])) . "</p>";

                $consultation_id = $consult['id'];

                // Fetch and display prescribed medicines for this consultation
                $med_sql = "SELECT pm.*, mm.name as medicine_name FROM prescribed_medicines pm JOIN medicines_master mm ON pm.medicine_id = mm.id WHERE pm.consultation_id = $consultation_id";
                $med_result = $conn->query($med_sql);
                if ($med_result->num_rows > 0) {
                    echo "<h5>Prescribed Medicines:</h5><ul>";
                    while ($med = $med_result->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($med['medicine_name']) . " - " . htmlspecialchars($med['notes']) . "</li>";
                    }
                    echo "</ul>";
                }

                // Fetch and display ordered tests for this consultation
                $test_sql = "SELECT ot.*, tm.name as test_name FROM ordered_tests ot JOIN tests_master tm ON ot.test_id = tm.id WHERE ot.consultation_id = $consultation_id";
                $test_result = $conn->query($test_sql);
                if ($test_result->num_rows > 0) {
                    echo "<h5>Ordered Tests:</h5><ul>";
                    while ($test = $test_result->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($test['test_name']) . " - Result: " . htmlspecialchars($test['result']) . "</li>";
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
                    <select name="medicine_id" id="medicine_id" onchange="toggleOtherMedicine(this.value)">
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
                        <option value="QID">Four times a day (QID)</option>
                        <option value="PRN">As needed (PRN)</option>
                        <option value="QOD">Every other day (QOD)</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Monthly">Monthly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dosage_form">Dosage Form</label>
                    <select name="dosage_form" id="dosage_form">
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Liquid">Liquid</option>
                        <option value="Ointment">Ointment</option>
                        <option value="Drop">Drop</option>
                        <option value="Injection">Injection</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="unit_quantity">Unit Quantity</label>
                    <input type="text" name="unit_quantity" id="unit_quantity" placeholder="e.g., 1, 5, 10">
                </div>
                <div class="form-group">
                    <label for="unit_type">Unit Type</label>
                    <select name="unit_type" id="unit_type">
                        <option value="tablet">tablet(s)</option>
                        <option value="ml">ml</option>
                        <option value="mg">mg</option>
                        <option value="drop">drop(s)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="food_relation">Food Relation</label>
                    <select name="food_relation" id="food_relation">
                        <option value="After food">After food</option>
                        <option value="Before food">Before food</option>
                        <option value="With food">With food</option>
                        <option value="Irrespective of food">Irrespective of food</option>
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
        <div id="medicines-preview-table">
            <!-- Medicines will be loaded here by AJAX -->
        </div>
    </div>

    <div id="test" class="tab-content">
        <h3>Order Tests</h3>
        <form id="test-form" onsubmit="return false;">
            <div class="grid-container" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="test_id">Test Name</label>
                    <select name="test_id" id="test_id" onchange="toggleOtherTest(this.value)">
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
        <div id="tests-preview-table">
            <!-- Tests will be loaded here by AJAX -->
        </div>
    </div>

    <div id="attachments" class="tab-content">
        <h3>Manage Attachments</h3>
        <form action="upload_attachment.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="consultation_id" value="<?php echo isset($_GET['consultation_id']) ? $_GET['consultation_id'] : ''; ?>">
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" required>
            </div>
            <div class="form-group">
                <label for="attachment_file">File</label>
                <input type="file" name="attachment_file" id="attachment_file" required>
            </div>
            <button type="submit" name="upload_attachment">Upload Attachment</button>
        </form>
        <hr>
        <h3>Uploaded Attachments</h3>
        <div id="attachments-list">
            <?php
            <?php
            // The attachments tab is only fully functional when viewing a past consultation.
            // For a new consultation, we guide the user to save it first.
            $current_consultation_id = $_GET['consultation_id'] ?? null;

            if ($current_consultation_id) {
                $c_id = (int)$current_consultation_id;
                $att_sql = "SELECT * FROM attachments WHERE consultation_id = $c_id ORDER BY id DESC";
                $att_result = $conn->query($att_sql);
                if ($att_result->num_rows > 0) {
                    echo "<ul>";
                    while ($att = $att_result->fetch_assoc()) {
                        echo "<li><a href='" . htmlspecialchars($att['file_path']) . "' target='_blank'>" . htmlspecialchars($att['description']) . "</a></li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>No attachments found for this consultation.</p>";
                }
            } else {
                echo "<p>Please save the consultation first by using the 'Preview & Finish' tab. After saving, you can manage attachments from the 'History' tab.</p>";
                // Disable the form if it's a new consultation
                echo "<script>document.querySelector('#attachments form').style.display = 'none';</script>";
            }
            ?>
        </div>
    </div>

    <div id="preview" class="tab-content">
        <h3>Preview Consultation</h3>
        <form action="finish_consultation.php" method="POST" id="finish-form">
            <!-- Hidden fields to carry over the main consultation notes -->
            <input type="hidden" name="weight" id="preview_weight">
            <input type="hidden" name="temperature" id="preview_temperature">
            <input type="hidden" name="chief_complaint" id="preview_chief_complaint">

            <div id="preview-content">
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

    if (tabName === 'medicine') {
        loadSessionMedicines();
    }
    if (tabName === 'test') {
        loadSessionTests();
    }
    if (tabName === 'preview') {
        generatePreview();
    }
}

function toggleOtherMedicine(value) {
    document.getElementById('medicine_name_other_div').style.display = value === 'other' ? 'block' : 'none';
}

function addMedicine() {
    const form = document.getElementById('medicine-form');
    const formData = new FormData(form);
    formData.append('action', 'add_medicine');

    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadSessionMedicines();
            form.reset();
            toggleOtherMedicine(''); // Hide 'other' field after adding
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeMedicine(id) {
    if (!confirm('Are you sure you want to remove this medicine?')) return;

    const formData = new FormData();
    formData.append('action', 'remove_medicine');
    formData.append('id', id);

    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadSessionMedicines();
        } else {
            alert('Error removing medicine.');
        }
    });
}

function loadSessionMedicines() {
    fetch('ajax_handler.php?action=get_session_medicines')
    .then(response => response.json())
    .then(data => {
        const previewDiv = document.getElementById('medicines-preview-table');
        let tableHtml = '<table><thead><tr><th>Name</th><th>Dosage</th><th>Frequency</th><th>Food Relation</th><th>Notes</th><th>Action</th></tr></thead><tbody>';
        if (data.length > 0) {
            data.forEach(med => {
                tableHtml += `
                    <tr>
                        <td>${escapeHTML(med.medicine_name)}</td>
                        <td>${escapeHTML(med.unit_quantity)} ${escapeHTML(med.unit_type)} (${escapeHTML(med.dosage_form)})</td>
                        <td>${escapeHTML(med.frequency)}</td>
                        <td>${escapeHTML(med.food_relation)}</td>
                        <td>${escapeHTML(med.notes)}</td>
                        <td><button type="button" class="btn-danger" onclick="removeMedicine('${med.id}')">Remove</button></td>
                    </tr>
                `;
            });
        } else {
            tableHtml += '<tr><td colspan="6">No medicines added yet.</td></tr>';
        }
        tableHtml += '</tbody></table>';
        previewDiv.innerHTML = tableHtml;
    });
}

function escapeHTML(str) {
    if (str === null || str === undefined) {
        return '';
    }
    return str.toString().replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}

// Initial load for the medicine tab if it's the active one on page load (it's not, but good practice)
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('medicine').classList.contains('active')) {
        loadSessionMedicines();
    }
    if (document.getElementById('test').classList.contains('active')) {
        loadSessionTests();
    }
});

function toggleOtherTest(value) {
    document.getElementById('test_name_other_div').style.display = value === 'other' ? 'block' : 'none';
}

function addTest() {
    const form = document.getElementById('test-form');
    const formData = new FormData(form);
    formData.append('action', 'add_test');

    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadSessionTests();
            form.reset();
            toggleOtherTest('');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeTest(id) {
    if (!confirm('Are you sure you want to remove this test?')) return;

    const formData = new FormData();
    formData.append('action', 'remove_test');
    formData.append('id', id);

    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            loadSessionTests();
        } else {
            alert('Error removing test.');
        }
    });
}

function loadSessionTests() {
    fetch('ajax_handler.php?action=get_session_tests')
    .then(response => response.json())
    .then(data => {
        const previewDiv = document.getElementById('tests-preview-table');
        let tableHtml = '<table><thead><tr><th>Name</th><th>Action</th></tr></thead><tbody>';
        if (data.length > 0) {
            data.forEach(test => {
                tableHtml += `
                    <tr>
                        <td>${escapeHTML(test.test_name)}</td>
                        <td><button type="button" class="btn-danger" onclick="removeTest('${test.id}')">Remove</button></td>
                    </tr>
                `;
            });
        } else {
            tableHtml += '<tr><td colspan="2">No tests ordered yet.</td></tr>';
        }
        tableHtml += '</tbody></table>';
        previewDiv.innerHTML = tableHtml;
    });
}

function generatePreview() {
    // 1. Populate hidden form fields with main consultation data
    document.getElementById('preview_weight').value = document.getElementById('weight').value;
    document.getElementById('preview_temperature').value = document.getElementById('temperature').value;
    document.getElementById('preview_chief_complaint').value = document.getElementById('chief_complaint').value;

    const previewContent = document.getElementById('preview-content');
    let html = '<h4>Consultation Notes</h4>';
    html += `<p><strong>Weight:</strong> ${escapeHTML(document.getElementById('weight').value) || 'N/A'} kg</p>`;
    html += `<p><strong>Temperature:</strong> ${escapeHTML(document.getElementById('temperature').value) || 'N/A'} °C</p>`;
    html += `<p><strong>Notes:</strong><br>${nl2br(escapeHTML(document.getElementById('chief_complaint').value)) || 'No notes.'}</p><hr>`;

    // 2. Fetch and display session medicines
    html += '<h4>Prescribed Medicines</h4>';
    const medsPromise = fetch('ajax_handler.php?action=get_session_medicines').then(res => res.json());

    // 3. Fetch and display session tests
    html += '<h4>Ordered Tests</h4>';
    const testsPromise = fetch('ajax_handler.php?action=get_session_tests').then(res => res.json());

    Promise.all([medsPromise, testsPromise]).then(([meds, tests]) => {
        // Render medicines
        if (meds.length > 0) {
            html += '<ul>';
            meds.forEach(med => {
                html += `<li>${escapeHTML(med.medicine_name)} - ${escapeHTML(med.unit_quantity)} ${escapeHTML(med.unit_type)}, ${escapeHTML(med.frequency)}, ${escapeHTML(med.food_relation)}</li>`;
            });
            html += '</ul>';
        } else {
            html += '<p>No medicines prescribed.</p>';
        }
        html += '<hr>';

        // Render tests
        html += '<h4>Ordered Tests</h4>';
        if (tests.length > 0) {
            html += '<ul>';
            tests.forEach(test => {
                html += `<li>${escapeHTML(test.test_name)}</li>`;
            });
            html += '</ul>';
        } else {
            html += '<p>No tests ordered.</p>';
        }

        previewContent.innerHTML = html;
    });
}

function nl2br(str) {
    return str.replace(/(\r\n|\n\r|\r|\n)/g, "<br>");
}
</script>

</body>
</html>
