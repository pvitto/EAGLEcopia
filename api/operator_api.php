<?php
<?php
require '../config.php';
require '../db_connection.php';
require_once '../send_email.php'; // RE-HABILITADO: Necesario para notificar discrepancias
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Operador'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

if ($method === 'GET') {
    $planilla = $_GET['planilla'] ?? null;
    if (!$planilla) {
        echo json_encode(['success' => false, 'error' => 'No se proporcionó número de planilla.']);
        exit;
    }
    $stmt = $conn->prepare("
        SELECT ci.id, ci.invoice_number, ci.seal_number, ci.declared_value, c.name as client_name
        FROM check_ins ci
        JOIN clients c ON ci.client_id = c.id
        WHERE ci.invoice_number = ? AND ci.status = 'Pendiente'
    ");
    $stmt->bind_param("s", $planilla);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Planilla no encontrada o ya fue procesada.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $conn->begin_transaction();
    try {
        $stmt_insert = $conn->prepare(
            "INSERT INTO operator_counts (check_in_id, operator_id, bills_100k, bills_50k, bills_20k, bills_10k, bills_5k, bills_2k, coins, total_counted, discrepancy, observations) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_insert->bind_param("iiiiiiidddis", 
            $data['check_in_id'], $user_id, $data['bills_100k'], $data['bills_50k'], $data['bills_20k'],
            $data['bills_10k'], $data['bills_5k'], $data['bills_2k'], $data['coins'], $data['total_counted'],
            $data['discrepancy'], $data['observations']
        );
        $stmt_insert->execute();
        $stmt_insert->close();

        // Lógica estándar: marca como Procesado o Discrepancia. No auto-aprueba.
        $new_status = ($data['discrepancy'] == 0) ? 'Procesado' : 'Discrepancia';
        $stmt_update = $conn->prepare("UPDATE check_ins SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $data['check_in_id']);
        $stmt_update->execute();
        $stmt_update->close();

        // Generar alerta solo si hay discrepancia
        if ($data['discrepancy'] != 0) {
            $check_in_id = $data['check_in_id'];
            // --- OBTENER MÁS DATOS PARA EL CORREO ---
            $stmt_details = $conn->prepare("
                SELECT ci.invoice_number, c.name as client_name, u.name as operator_name
                FROM check_ins ci
                JOIN clients c ON ci.client_id = c.id
                JOIN users u ON u.id = ?
                WHERE ci.id = ?
            ");
            $stmt_details->bind_param("ii", $user_id, $check_in_id);
            $stmt_details->execute();
            $details_result = $stmt_details->get_result()->fetch_assoc();
            $stmt_details->close();

            $invoice_number = $details_result['invoice_number'] ?? 'N/A';
            $client_name = $details_result['client_name'] ?? 'N/A';
            $operator_name = $details_result['operator_name'] ?? 'N/A';
            $discrepancy_formatted = number_format($data['discrepancy'], 0, ',', '.');
            
            $alert_title = "Discrepancia en Planilla: " . $invoice_number;
            $alert_desc = "Diferencia de $" . $discrepancy_formatted . ". Requiere revisión y seguimiento.";
            
            $stmt_alert = $conn->prepare("INSERT INTO alerts (title, description, priority, status, suggested_role, check_in_id) VALUES (?, ?, 'Critica', 'Pendiente', 'Digitador', ?)");
            $stmt_alert->bind_param("ssi", $alert_title, $alert_desc, $check_in_id);
            $stmt_alert->execute();
            $alert_id = $stmt_alert->insert_id;
            $stmt_alert->close();

            if ($alert_id) {
                $instruction = "Realizar seguimiento a la discrepancia (" . $invoice_number . "), contactar a los responsables y documentar la resolución.";
                $stmt_task = $conn->prepare("INSERT INTO tasks (alert_id, assigned_to_group, instruction, type, status, priority, created_by_user_id) VALUES (?, 'Digitador', ?, 'Asignacion', 'Pendiente', 'Critica', ?)");
                $stmt_task->bind_param("isi", $alert_id, $instruction, $user_id);
                $stmt_task->execute();
                $stmt_task->close();
                $conn->query("UPDATE alerts SET status = 'Asignada' WHERE id = $alert_id");

                // --- NUEVO: LÓGICA DE NOTIFICACIÓN POR CORREO ---
                $stmt_users = $conn->prepare("SELECT name, email FROM users WHERE role IN ('Digitador', 'Admin') AND email IS NOT NULL AND email != ''");
                $stmt_users->execute();
                $users_to_notify = $stmt_users->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_users->close();

                $subject = "[ALERTA CRÍTICA] Discrepancia Detectada en Planilla " . $invoice_number;

                foreach ($users_to_notify as $user) {
                    $body = "
                        <h1>Alerta de Discrepancia</h1>
                        <p>Hola " . htmlspecialchars($user['name']) . ",</p>
                        <p>Se ha detectado una discrepancia en el conteo de una planilla que requiere tu atención inmediata.</p>
                        <hr>
                        <ul>
                            <li><strong>Planilla Nro:</strong> " . htmlspecialchars($invoice_number) . "</li>
                            <li><strong>Cliente:</strong> " . htmlspecialchars($client_name) . "</li>
                            <li><strong>Operador:</strong> " . htmlspecialchars($operator_name) . "</li>
                            <li><strong>Monto de la Discrepancia:</strong> $" . htmlspecialchars($discrepancy_formatted) . "</li>
                        </ul>
                        <p>Por favor, ingresa al sistema EAGLE 3.0 para revisar los detalles y tomar las acciones correspondientes.</p>
                        <br>
                        <p><em>Este es un correo automático, por favor no respondas a este mensaje.</em></p>
                    ";
                    // Se asume que la función send_email_notification ya maneja los errores internamente (error_log)
                    send_email_notification($user['email'], $user['name'], $subject, $body);
                }
                // --- FIN DE LA LÓGICA DE CORREO ---
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Conteo guardado exitosamente.']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
}

$conn->close();
?>