<?php
session_start();
header('Content-Type: application/json');

require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$contact_id = $_POST['contact_id'] ?? '';
$comment    = trim($_POST['comment'] ?? '');

if ($contact_id === '' || !is_numeric($contact_id) || $comment === '') {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid data']);
    exit();
}

$contact_id = (int) $contact_id;

// Insert note
$sql = "INSERT INTO notes (contact_id, comment, created_by, created_at)
        VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $contact_id, $comment, $user_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'DB error inserting note']);
    exit();
}

// Get info to send back (comment, date, user name)
$note_id = $stmt->insert_id;

$info_sql = "
    SELECT n.comment, n.created_at, u.firstname, u.lastname
    FROM notes n
    JOIN users u ON n.created_by = u.id
    WHERE n.id = ?
";
$info_stmt = $conn->prepare($info_sql);
$info_stmt->bind_param("i", $note_id);
$info_stmt->execute();
$info = $info_stmt->get_result()->fetch_assoc();

// Also update contact updated_at
$up_sql = "UPDATE contacts SET updated_at = NOW() WHERE id = ?";
$up_stmt = $conn->prepare($up_sql);
$up_stmt->bind_param("i", $contact_id);
$up_stmt->execute();

echo json_encode([
    'success'    => true,
    'comment'    => $info['comment'],
    'created_at' => $info['created_at'],
    'user_name'  => $info['firstname'] . ' ' . $info['lastname']
]);
