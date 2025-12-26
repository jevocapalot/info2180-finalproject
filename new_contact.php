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
    <title>Dolphin CRM - New Contact</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-logo">
        <span>🐬</span>
        Dolphin CRM
    </div>
</header>

<div class="app">
    <aside class="sidebar">
        <div class="sidebar-user">
            Logged in as <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>
            (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)
        </div>
        <nav>
            <a href="dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="new_contact.php" class="active-nav"><i class="fa-solid fa-user-plus"></i> New Contact</a>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <?php endif; ?>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main">
        <div class="main-header">
            <h2>New Contact</h2>
        </div>

        <section class="card">
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <form method="POST" action="new_contact.php">
                
                <div style="margin-bottom: 1.5rem; width: 200px;">
                    <label>Title</label>
                    <select name="title">
                        <option value="Mr">Mr</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Ms">Ms</option>
                        <option value="Dr">Dr</option>
                        <option value="Prof">Prof</option>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label>First Name</label>
                        <input type="text" name="firstname" placeholder="Jane" required>
                    </div>
                    <div>
                        <label>Last Name</label>
                        <input type="text" name="lastname" placeholder="Doe" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" placeholder="something@example.com" required>
                    </div>
                    <div>
                        <label>Telephone</label>
                        <input type="text" name="telephone">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label>Company</label>
                        <input type="text" name="company">
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="type" required>
                            <option value="Sales Lead">Sales Lead</option>
                            <option value="Support">Support</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label>Assigned To</label>
                    <select name="assigned_to" required>
                        <?php if ($userResult && $userResult->num_rows > 0): ?>
                            <?php while ($u = $userResult->fetch_assoc()): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['firstname'].' '.$u['lastname']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </section>
    </main>
</div>
<script src="app.js"></script>
</body>
</html>

