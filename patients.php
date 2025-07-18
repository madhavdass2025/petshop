<?php
include 'db.php';

// Handle adding a new patient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_patient'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $species = $conn->real_escape_string($_POST['species']);
    $breed = $conn->real_escape_string($_POST['breed']);
    $age = $conn->real_escape_string($_POST['age']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $owner_name = $conn->real_escape_string($_POST['owner_name']);
    $owner_contact = $conn->real_escape_string($_POST['owner_contact']);

    $insert_sql = "INSERT INTO patients (name, species, breed, age, gender, owner_name, owner_contact) VALUES ('$name', '$species', '$breed', '$age', '$gender', '$owner_name', '$owner_contact')";
    if (!$conn->query($insert_sql)) {
        echo "Error: " . $conn->error;
    }
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_term = $conn->real_escape_string($_GET['search']);
    $search_query = " WHERE name LIKE '%$search_term%' OR owner_name LIKE '%$search_term%'";
}

$patients_sql = "SELECT * FROM patients" . $search_query . " ORDER BY id DESC";
$patients_result = $conn->query($patients_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Patient Management</h1>

    <div class="add-patient-form">
        <h2>Add New Patient</h2>
        <form action="patients.php" method="post">
            <div class="grid-container">
                <div class="form-group">
                    <label for="name">Pet Name</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label for="species">Species</label>
                    <input type="text" name="species" id="species">
                </div>
                <div class="form-group">
                    <label for="breed">Breed</label>
                    <input type="text" name="breed" id="breed">
                </div>
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" name="age" id="age">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select name="gender" id="gender">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="owner_name">Owner Name</label>
                    <input type="text" name="owner_name" id="owner_name" required>
                </div>
                <div class="form-group">
                    <label for="owner_contact">Owner Contact</label>
                    <input type="text" name="owner_contact" id="owner_contact" required>
                </div>
            </div>
            <button type="submit" name="add_patient">Add Patient</button>
        </form>
    </div>

    <hr>

    <div class="patient-list">
        <h2>Patient List</h2>
        <form action="patients.php" method="get" class="search-form">
            <input type="search" name="search" placeholder="Search for patients..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit">Search</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Pet Name</th>
                    <th>Species</th>
                    <th>Breed</th>
                    <th>Owner Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($patients_result->num_rows > 0) {
                    while ($patient = $patients_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($patient['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($patient['species']) . "</td>";
                        echo "<td>" . htmlspecialchars($patient['breed']) . "</td>";
                        echo "<td>" . htmlspecialchars($patient['owner_name']) . "</td>";
                        echo "<td><a href='consultation.php?patient_id=" . $patient['id'] . "' class='btn'>Start Consultation</a></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No patients found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
