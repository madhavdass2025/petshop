<?php
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle adding a new patient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_patient'])) {
    $name = $_POST['name'];
    $species = $_POST['species'];
    $breed = $_POST['breed'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $owner_name = $_POST['owner_name'];
    $owner_contact = $_POST['owner_contact'];
    $submitted_by = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO patients (name, species, breed, age, gender, owner_name, owner_contact, submitted_by) VALUES (:name, :species, :breed, :age, :gender, :owner_name, :owner_contact, :submitted_by)");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':species', $species);
    $stmt->bindParam(':breed', $breed);
    $stmt->bindParam(':age', $age);
    $stmt->bindParam(':gender', $gender);
    $stmt->bindParam(':owner_name', $owner_name);
    $stmt->bindParam(':owner_contact', $owner_contact);
    $stmt->bindParam(':submitted_by', $submitted_by);
    if (!$stmt->execute()) {
        echo "Error: " . $stmt->errorInfo()[2];
    }
}

// Handle search
$search_query = "";
if (isset($_GET['search'])) {
    $search_term = $_GET['search'];
    $search_query = " AND (name LIKE :search_term OR owner_name LIKE :search_term)";
}

$patients_sql = "SELECT * FROM patients WHERE cancel = 0" . $search_query . " ORDER BY id DESC";
$stmt = $conn->prepare($patients_sql);
if (isset($_GET['search'])) {
    $search_param = "%" . $_GET['search'] . "%";
    $stmt->bindParam(':search_term', $search_param);
}
$stmt->execute();
$patients_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Patient Management</h1>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

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

    <hr style="margin: 30px 0;">

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
                if (count($patients_result) > 0) {
                    foreach ($patients_result as $patient) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($patient['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($patient['species']) . "</td>";
                        echo "<td>" . htmlspecialchars($patient['breed']) . "</td>";
                        echo "<td>" . htmlspecialchars($patient['owner_name']) . "</td>";
                        echo "<td><a href='consultation.php?patient_id=" . $patient['id'] . "' class='btn'>Start Consultation</a>";
                        if ($_SESSION['role'] === 'admin') {
                            echo " <a href='delete.php?type=patient&id=" . $patient['id'] . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this patient?\")'>Delete</a>";
                        }
                        echo "</td>";
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
