<?php
include 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'All fields are required';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            $success = 'Registration successful. You can now <a href="login.php">login</a>.';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Username already exists';
            } else {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Register</h1>
    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>
    <form action="register.php" method="post">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select name="role" id="role">
                <option value="doctor">Doctor</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</div>
</body>
</html>
