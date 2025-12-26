<?php
session_start();
require 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get list of users for "Assigned To" dropdown
$userQuery = "SELECT id, firstname, lastname FROM users ORDER BY firstname, lastname";
$userResult = $conn->query($userQuery);

// Handle form submit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title      = trim($_POST['title'] ?? '');
    $firstname  = trim($_POST['firstname'] ?? '');
    $lastname   = trim($_POST['lastname'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $telephone  = trim($_POST['telephone'] ?? '');
    $company    = trim($_POST['company'] ?? '');
    $type       = trim($_POST['type'] ?? '');
    $assigned_to = $_POST['assigned_to'] ?? null;

    // validation
    if ($firstname === "" || $lastname === "" || $email === "" || $type === "" || $assigned_to === "") {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($type, ['Sales Lead', 'Support'])) {
        $error = "Invalid contact type.";
    } else {
        // Insert into database
        $sql = "INSERT INTO contacts 
                    (title, firstname, lastname, email, telephone, company, type, assigned_to, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssii",
            $title,
            $firstname,
            $lastname,
            $email,
            $telephone,
            $company,
            $type,
            $assigned_to,
            $user_id
        );

        if ($stmt->execute()) {
            
            header("Location: dashboard.php");
            exit();
            
        } else {
            $error = "Error creating contact. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Contact - Dolphin CRM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        nav a { margin-right: 10px; text-decoration: none; }
        form { max-width: 600px; }
        label { display: block; margin-top: 10px; }
        input, select, textarea { width: 100%; padding: 6px; margin-top: 4px; }
        button { margin-top: 15px; padding: 8px 16px; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
        .required { color: red; }
    </style>
</head>
<body>

<header>
    <h1>New Contact</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>

<form method="POST" action="new_contact.php">
    <label>Title
        <input type="text" name="title" placeholder="Mr, Ms, Dr, etc.">
    </label>

    <label>First Name <span class="required">*</span>
        <input type="text" name="firstname" required>
    </label>

    <label>Last Name <span class="required">*</span>
        <input type="text" name="lastname" required>
    </label>

    <label>Email <span class="required">*</span>
        <input type="email" name="email" required>
    </label>

    <label>Telephone
        <input type="text" name="telephone">
    </label>

    <label>Company
        <input type="text" name="company">
    </label>

    <label>Type <span class="required">*</span>
        <select name="type" required>
            <option value="">-- Select Type --</option>
            <option value="Sales Lead">Sales Lead</option>
            <option value="Support">Support</option>
        </select>
    </label>

    <label>Assigned To <span class="required">*</span>
        <select name="assigned_to" required>
            <option value="">-- Select User --</option>
            <?php if ($userResult && $userResult->num_rows > 0): ?>
                <?php while ($u = $userResult->fetch_assoc()): ?>
                    <option value="<?php echo $u['id']; ?>">
                        <?php echo htmlspecialchars($u['firstname'] . " " . $u['lastname']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </label>

    <button type="submit">Save Contact</button>
</form>

</body>
</html>
