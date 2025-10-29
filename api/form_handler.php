<?php
header('Content-Type: application/json');

// Get the raw POST data
$json_data = file_get_contents('php://input');

// Decode the JSON data
$data = json_decode($json_data, true);

// Check if decoding was successful
if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// For now, just acknowledge receipt of the data
// Later, this is where we'll send the data to Google Sheets

echo json_encode(['success' => true, 'message' => 'Data received successfully']);
?>
