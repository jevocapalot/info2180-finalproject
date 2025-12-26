<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid contact.");
}

$contact_id = (int) $_GET['id'];

// Handle actions: assign to me, toggle type, add note
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $sql = "UPDATE contacts SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $contact_id);
        $stmt->execute();
    } elseif ($action === 'toggle_type') {
        $new_type = $_POST['new_type'] ?? '';
        if (in_array($new_type, ['Sales Lead', 'Support'])) {
            $sql = "UPDATE contacts SET type = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_type, $contact_id);
            $stmt->execute();
        }
    } elseif ($action === 'add_note') {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment !== '') {
            // Insert note
            $sql = "INSERT INTO notes (contact_id, comment, created_by, created_at)
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $contact_id, $comment, $user_id);
            $stmt->execute();

            // Update contact updated_at
            $sql2 = "UPDATE contacts SET updated_at = NOW() WHERE id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("i", $contact_id);
            $stmt2->execute();
        }
    }

    // Avoid resubmission on refresh
    header("Location: contact.php?id=" . $contact_id);
    exit();
}

// Get contact details 
$sql = "
    SELECT c.*,
           creator.firstname AS creator_first,
           creator.lastname  AS creator_last,
           assignee.firstname AS assignee_first,
           assignee.lastname  AS assignee_last
    FROM contacts c
    LEFT JOIN users creator ON c.created_by = creator.id
    LEFT JOIN users assignee ON c.assigned_to = assignee.id
    WHERE c.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contact_id);
$stmt->execute();
$contact_result = $stmt->get_result();

if ($contact_result->num_rows !== 1) {
    die("Contact not found.");
}

$contact = $contact_result->fetch_assoc();

// Get notes for this contact
$note_sql = "
    SELECT n.comment, n.created_at,
           u.firstname, u.lastname
    FROM notes n
    JOIN users u ON n.created_by = u.id
    WHERE n.contact_id = ?
    ORDER BY n.created_at DESC
";
$note_stmt = $conn->prepare($note_sql);
$note_stmt->bind_param("i", $contact_id);
$note_stmt->execute();
$notes = $note_stmt->get_result();

// Determine toggle label & target type
$current_type = $contact['type'];
if ($current_type === 'Sales Lead') {
    $toggle_label = "Switch to Support";
    $new_type = "Support";
} else {
    $toggle_label = "Switch to Sales Lead";
    $new_type = "Sales Lead";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dolphin CRM - Contact</title>
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
            <a href="dashboard.php" class="active-nav"><i class="fa-solid fa-house"></i> Home</a>
            <a href="new_contact.php"><i class="fa-solid fa-user-plus"></i> New Contact</a>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <?php endif; ?>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main">
        <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
        
        <!-- Header Section -->
        <div class="contact-header">
            <div class="contact-info">
                <div class="contact-avatar">
                    <i class="fa-solid fa-user-circle"></i>
                </div>
                <div class="contact-text">
                    <h1>
                        <?php echo htmlspecialchars(trim($contact['title'].' '.$contact['firstname'].' '.$contact['lastname'])); ?>
                    </h1>
                    <div class="contact-meta">
                        Created on <?php echo date("F j, Y", strtotime($contact['created_at'])); ?>
                        by <?php echo htmlspecialchars(trim($contact['creator_first'].' '.$contact['creator_last'])); ?>
                        <br>
                        Updated on <span id="contact-updated-at"><?php echo date("F j, Y", strtotime($contact['updated_at'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="contact-actions">
                <button type="button" id="assign-btn" class="btn-assign">
                    <i class="fa-solid fa-hand"></i> Assign to me
                </button>
                <button type="button" id="toggle-type-btn" class="btn-switch"
                        data-new-type="<?php echo htmlspecialchars($new_type); ?>">
                    <i class="fa-solid fa-right-left"></i> <?php echo htmlspecialchars($toggle_label); ?>
                </button>
            </div>
        </div>

        <!-- Details Card -->
        <section class="card details-box">
            <div class="details-grid">
                <div>
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($contact['email']); ?></p>
                </div>
                <div>
                    <label>Telephone</label>
                    <p><?php echo htmlspecialchars($contact['telephone']); ?></p>
                </div>
                <div>
                    <label>Company</label>
                    <p><?php echo htmlspecialchars($contact['company']); ?></p>
                </div>
                <div>
                    <label>Type</label>
                    <p id="contact-type"><?php echo htmlspecialchars($contact['type']); ?></p>
                </div>
                <div>
                    <label>Assigned To</label>
                    <p id="contact-assigned-to">
                        <?php
                        if ($contact['assignee_first']) {
                            echo htmlspecialchars($contact['assignee_first'].' '.$contact['assignee_last']);
                        } else {
                            echo "Unassigned";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </section>

        <!-- Notes Section -->
        <section class="card notes-section">
            <div class="notes-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Notes</h3>
            </div>

            <div id="notes-list" class="notes-content">
                <?php if ($notes->num_rows > 0): ?>
                    <?php while ($note = $notes->fetch_assoc()): ?>
                        <div class="note-entry">
                            <div class="note-author"><?php echo htmlspecialchars($note['firstname'].' '.$note['lastname']); ?></div>
                            <div class="note-text"><?php echo nl2br(htmlspecialchars($note['comment'])); ?></div>
                            <div class="note-date"><?php echo date("F j, Y \a\\t g:ia", strtotime($note['created_at'])); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No notes yet.</p>
                <?php endif; ?>
            </div>

            <div class="add-note">
                <h4 class="add-note-title">Add a note about <?php echo htmlspecialchars($contact['firstname']); ?></h4>
                <form id="note-form">
                    <textarea name="comment" placeholder="Enter details here" required></textarea>
                    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                    <div style="text-align: right;">
                        <button type="submit" class="btn-add-note">Add Note</button>
                    </div>
                </form>
                <p id="note-error" class="error"></p>
            </div>
        </section>
    </main>
</div>
<script src="./app.js"></script> 
</body>
</html>
