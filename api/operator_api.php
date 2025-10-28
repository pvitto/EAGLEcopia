<?php
require '../config.php';
require '../db_connection.php';
require_once '../send_email.php';
header('Content-Type: application/json');

// Session and permission check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Operador'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

// GET request handler
if ($method === 'GET') {
    $planilla = $_GET['planilla'] ?? null;
    if (!$planilla) {
        http_response_code(400);
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

// POST request handler
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['check_in_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos inválidos o faltantes.']);
        exit;
    }

    // --- Start DB Transaction ---
    $conn->begin_transaction();
    $details_for_email = []; // Array to hold info for email sending

    try {
        // Step 1: Insert the financial count
        $stmt_insert = $conn->prepare(
            "INSERT INTO operator_counts (check_in_id, operator_id, bills_100k, bills_50k, bills_20k, bills_10k, bills_5k, bills_2k, coins, total_counted, discrepancy, observations) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_insert->bind_param("iiiiiiidddis", 
            $data['check_in_id'], $user_id, $data['bills_100k'] ?? 0, $data['bills_50k'] ?? 0, $data['bills_20k'] ?? 0,
            $data['bills_10k'] ?? 0, $data['bills_5k'] ?? 0, $data['bills_2k'] ?? 0, $data['coins'] ?? 0, $data['total_counted'] ?? 0,
            $data['discrepancy'] ?? 0, $data['observations'] ?? ''
        );
        $stmt_insert->execute();
        $stmt_insert->close();

        // Step 2: Update the planilla status
        $discrepancy = $data['discrepancy'] ?? 0;
        $check_in_id = $data['check_in_id'];
        $new_status = ($discrepancy == 0) ? 'Procesado' : 'Discrepancia';

        $stmt_update = $conn->prepare("UPDATE check_ins SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $check_in_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Step 3: Get details needed for alerts and emails
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

        // Store details for later use
        $details_for_email = [
            'invoice_number' => $details_result['invoice_number'] ?? 'N/A',
            'client_name' => $details_result['client_name'] ?? 'N/A',
            'operator_name' => $details_result['operator_name'] ?? 'N/A',
            'discrepancy' => $discrepancy
        ];

        // Step 4: Create an alert if there is a discrepancy
        if ($discrepancy != 0) {
            $discrepancy_formatted = number_format($discrepancy, 0, ',', '.');
            $alert_title = "Discrepancia en Planilla: " . $details_for_email['invoice_number'];
            $alert_desc = "Diferencia de $" . $discrepancy_formatted . ". Requiere revisión.";
            
            $stmt_alert = $conn->prepare("INSERT INTO alerts (title, description, priority, status, suggested_role, check_in_id) VALUES (?, ?, 'Critica', 'Pendiente', 'Digitador', ?)");
            $stmt_alert->bind_param("ssi", $alert_title, $alert_desc, $check_in_id);
            $stmt_alert->execute();
            $stmt_alert->close();
        }

        // Step 5: Commit transaction. All critical data is now saved.
        $conn->commit();

        // --- Email Notification (Non-critical, happens after commit) ---
        // Any errors here will be logged, but will not block the success response.
        try {
            $stmt_users = $conn->prepare("SELECT name, email FROM users WHERE role IN ('Digitador', 'Admin') AND email IS NOT NULL AND email != ''");
            $stmt_users->execute();
            $users_to_notify = $stmt_users->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_users->close();

            if (!empty($users_to_notify)) {
                $discrepancy = $details_for_email['discrepancy'];
                $invoice_number = $details_for_email['invoice_number'];
                $subject = "";
                $body_template = "";

                if ($discrepancy != 0) {
                    // --- CORREO DE DISCREPANCIA DESACTIVADO POR SOLICITUD ---
                    // Para reactivar, eliminar el `if(false)` y dejar el contenido.
                    if (false) {
                        $discrepancy_formatted = number_format($discrepancy, 0, ',', '.');
                        $subject = "[ALERTA CRÍTICA] Discrepancia Detectada en Planilla " . $invoice_number;
                        $body_template = "
                            <h1>Alerta de Discrepancia</h1><p>Hola %s,</p><p>Se ha detectado una discrepancia monetaria que requiere atención inmediata.</p><hr>
                            <ul><li><strong>Planilla Nro:</strong> %s</li><li><strong>Cliente:</strong> %s</li><li><strong>Operador:</strong> %s</li><li><strong>Monto de la Discrepancia:</strong> $%s</li></ul>
                            <p>Por favor, ingresa al sistema EAGLE 3.0 para revisar los detalles.</p>";

                        foreach ($users_to_notify as $user) {
                             try {
                                $user_name = htmlspecialchars($user['name']);
                                $inv_num_safe = htmlspecialchars($invoice_number);
                                $client_name_safe = htmlspecialchars($details_for_email['client_name']);
                                $operator_name_safe = htmlspecialchars($details_for_email['operator_name']);
                                $final_body = sprintf($body_template, $user_name, $inv_num_safe, $client_name_safe, $operator_name_safe, htmlspecialchars($discrepancy_formatted));
                                send_task_email($user['email'], $user_name, $subject, "<div style='font-family: sans-serif;'>".$final_body."<br><p><em>Este es un correo automático.</em></p></div>");
                            } catch (Exception $email_ex) {
                                error_log("Failed to send DISCREPANCY email to " . $user['email'] . ". Error: " . $email_ex->getMessage());
                            }
                        }
                    }
                    // --- FIN DE LA DESACTIVACIÓN ---
                } else {
                    $subject = "[INFO] Planilla " . $invoice_number . " Procesada Correctamente";
                    $body_template = "
                        <h1>Notificación de Procesamiento</h1><p>Hola %s,</p><p>Te informamos que la planilla <strong>%s</strong> ha sido procesada exitosamente sin diferencias.</p><hr>
                        <ul><li><strong>Cliente:</strong> %s</li><li><strong>Operador:</strong> %s</li><li><strong>Resultado:</strong> Procesado sin discrepancias.</li></ul>
                        <p>No se requiere ninguna acción.</p>";

                    foreach ($users_to_notify as $user) {
                        try {
                            $user_name = htmlspecialchars($user['name']);
                            $inv_num_safe = htmlspecialchars($invoice_number);
                            $client_name_safe = htmlspecialchars($details_for_email['client_name']);
                            $operator_name_safe = htmlspecialchars($details_for_email['operator_name']);
                            $final_body = sprintf($body_template, $user_name, $inv_num_safe, $client_name_safe, $operator_name_safe);
                            send_task_email($user['email'], $user_name, $subject, "<div style='font-family: sans-serif;'>".$final_body."<br><p><em>Este es un correo automático.</em></p></div>");
                        } catch (Exception $email_ex) {
                            error_log("Failed to send NORMAL email to " . $user['email'] . ". Error: " . $email_ex->getMessage());
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Log error if fetching users or preparing email fails, but don't send error to client.
            error_log("Operator API - Email sending process failed AFTER DB commit. Error: " . $e->getMessage());
        }

        // --- Final Success Response ---
        // This is the VERY LAST thing to be sent to the client.
        echo json_encode(['success' => true, 'message' => 'Conteo guardado exitosamente.']);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Operator API DB Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al guardar los datos en la base de datos: ' . $e->getMessage()]);
    } finally {
        // Always close the connection
        if (isset($conn) && $conn->ping()) {
            $conn->close();
        }
    }
    exit; // Ensure no other output is sent
}

// Fallback for non-POST requests or other issues
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>