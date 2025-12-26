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
    <link rel="stylesheet" href="css/style.css">
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
            <a href="dashboard.php"><span class="icon">🏠</span> Home</a>
            <a href="new_contact.php" class="active-nav"><span class="icon">➕</span> New Contact</a>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <a href="users.php"><span class="icon">👥</span> Users</a>
            <?php endif; ?>
            <a href="logout.php"><span class="icon">⤴</span> Logout</a>
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
                <div class="form-grid-2">
                    <div>
                        <label>Title
                            <input type="text" name="title" placeholder="Mr, Ms, Dr, etc.">
                        </label>
                    </div>
                    <div></div>

                    <div>
                        <label>First Name
                            <input type="text" name="firstname" required>
                        </label>
                    </div>
                    <div>
                        <label>Last Name
                            <input type="text" name="lastname" required>
                        </label>
                    </div>

                    <div>
                        <label>Email
                            <input type="email" name="email" required>
                        </label>
                    </div>
                    <div>
                        <label>Telephone
                            <input type="text" name="telephone">
                        </label>
                    </div>

                    <div>
                        <label>Company
                            <input type="text" name="company">
                        </label>
                    </div>
                    <div>
                        <label>Type
                            <select name="type" required>
                                <option value="">-- Select Type --</option>
                                <option value="Sales Lead">Sales Lead</option>
                                <option value="Support">Support</option>
                            </select>
                        </label>
                    </div>

                    <div>
                        <label>Assigned To
                            <select name="assigned_to" required>
                                <option value="">-- Select User --</option>
                                <?php if ($userResult && $userResult->num_rows > 0): ?>
                                    <?php while ($u = $userResult->fetch_assoc()): ?>
                                        <option value="<?php echo $u['id']; ?>">
                                            <?php echo htmlspecialchars($u['firstname'].' '.$u['lastname']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </label>
                    </div>
                </div>

                <button type="submit" style="margin-top:16px;">Save Contact</button>
            </form>
        </section>
    </main>
</div>
<script src="app.js"></script>
</body>
</html>

