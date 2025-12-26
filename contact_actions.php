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

$action     = $_POST['action']     ?? '';
$contact_id = $_POST['contact_id'] ?? '';

if ($contact_id === '' || !is_numeric($contact_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid contact id']);
    exit();
}

$contact_id = (int) $contact_id;

if ($action === 'assign') {
    // Assign contact to current user
    $sql = "UPDATE contacts SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $contact_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB error assigning contact']);
        exit();
    }

    // Get updated info
    $info_sql = "
        SELECT c.updated_at, u.firstname, u.lastname
        FROM contacts c
        JOIN users u ON c.assigned_to = u.id
        WHERE c.id = ?
    ";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->bind_param("i", $contact_id);
    $info_stmt->execute();
    $info = $info_stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success'      => true,
        'updated_at'   => $info['updated_at'],
        'assigned_to'  => $info['firstname'] . ' ' . $info['lastname'],
    ]);
    exit();

} elseif ($action === 'toggle_type') {

    $new_type = $_POST['new_type'] ?? '';
    if (!in_array($new_type, ['Sales Lead', 'Support'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid type']);
        exit();
    }

    $sql = "UPDATE contacts SET type = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_type, $contact_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'DB error updating type']);
        exit();
    }

    // Get updated info
    $info_sql = "SELECT type, updated_at FROM contacts WHERE id = ?";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->bind_param("i", $contact_id);
    $info_stmt->execute();
    $info = $info_stmt->get_result()->fetch_assoc();

    // Work out new label/next type for the button
    if ($info['type'] === 'Sales Lead') {
        $next_label   = 'Switch to Support';
        $next_newType = 'Support';
    } else {
        $next_label   = 'Switch to Sales Lead';
        $next_newType = 'Sales Lead';
    }

    echo json_encode([
        'success'      => true,
        'type'         => $info['type'],
        'updated_at'   => $info['updated_at'],
        'next_label'   => $next_label,
        'next_newType' => $next_newType
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
