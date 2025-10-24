<?php
require dirname(__DIR__, 2) . '/config.php';
require dirname(__DIR__, 2) . '/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autenticado.'
    ]);
    exit;
}

$current_user_id = (int) $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? '';
$normalized_role = strtolower($current_user_role);
$allowed_roles = ['admin', 'digitador', 'digitadora'];

if (!in_array($normalized_role, $allowed_roles, true)) {
    echo json_encode([
        'success' => true,
        'alerts' => [],
        'timestamp' => time()
    ]);
    exit;
}

$since_timestamp = isset($_GET['since']) ? max(0, (int) $_GET['since']) : (time() - 60);
$since_datetime = date('Y-m-d H:i:s', $since_timestamp);

$user_filter = '';
if ($normalized_role !== 'admin') {
    $escaped_role = $conn->real_escape_string($current_user_role);
    $user_filter = " AND (t.assigned_to_user_id = {$current_user_id} OR (t.assigned_to_group = '{$escaped_role}' AND (t.assigned_to_user_id IS NULL OR t.assigned_to_user_id = 0)))";
}

$sql = "
    SELECT
        a.id AS alert_id,
        a.title AS alert_title,
        a.description AS alert_description,
        a.priority AS alert_priority,
        a.created_at AS alert_created_at,
        t.id AS task_id,
        t.title AS task_title,
        t.instruction AS task_instruction,
        t.priority AS task_priority,
        t.created_at AS task_created_at,
        t.assigned_to_group,
        t.assigned_to_user_id,
        ci.invoice_number
    FROM tasks t
    INNER JOIN alerts a ON t.alert_id = a.id
    INNER JOIN check_ins ci ON a.check_in_id = ci.id
    WHERE
        t.status = 'Pendiente'
        AND ci.status = 'Discrepancia'
        AND (t.priority IN ('Critica', 'Alta') OR a.priority IN ('Critica', 'Alta'))
        AND COALESCE(a.created_at, t.created_at) > ?
        {$user_filter}
    ORDER BY COALESCE(a.created_at, t.created_at) DESC
    LIMIT 10
";

$new_alerts = [];
$priority_map = ['baja' => 1, 'media' => 2, 'alta' => 3, 'critica' => 4];

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('s', $since_datetime);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $task_priority = $row['task_priority'] ?? 'Media';
            $alert_priority = $row['alert_priority'] ?? $task_priority;

            $task_priority_val = $priority_map[strtolower($task_priority)] ?? 0;
            $alert_priority_val = $priority_map[strtolower($alert_priority)] ?? 0;
            $final_priority = ($alert_priority_val >= $task_priority_val) ? $alert_priority : $task_priority;

            if ($final_priority === 'Critica' || $final_priority === 'Alta') {
                $new_alerts[] = [
                    'id' => $row['alert_id'] ?: $row['task_id'],
                    'title' => $row['alert_title'] ?: $row['task_title'],
                    'description' => $row['alert_description'] ?: $row['task_instruction'],
                    'priority' => $final_priority,
                    'created_at' => $row['alert_created_at'] ?: $row['task_created_at'],
                    'type' => 'discrepancy_alert',
                    'invoice_number' => $row['invoice_number'] ?? null
                ];
            }
        }
    }
    $stmt->close();
} else {
    error_log('Error preparando consulta en realtime_alerts_api: ' . $conn->error);
}

$conn->close();

echo json_encode([
    'success' => true,
    'alerts' => $new_alerts,
    'timestamp' => time()
]);
