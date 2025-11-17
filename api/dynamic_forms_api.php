<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'get_forms':
            handle_get_forms($conn);
            break;
        case 'get_form':
            handle_get_form($conn, $_GET['id']);
            break;
        case 'create_form':
            handle_create_form($conn, $input);
            break;
        case 'update_form':
            handle_update_form($conn, $input);
            break;
        case 'delete_form':
            handle_delete_form($conn, $_GET['id']);
            break;
        case 'add_field':
            handle_add_field($conn, $input);
            break;
        case 'update_field':
            handle_update_field($conn, $input);
            break;
        case 'delete_field':
            handle_delete_field($conn, $_GET['id']);
            break;
        case 'update_field_order':
            handle_update_field_order($conn, $input);
            break;
        default:
            echo json_encode(["error" => "Invalid action"]);
            http_response_code(400);
            break;
    }
} else {
    echo json_encode(["error" => "Action not specified"]);
    http_response_code(400);
}

function handle_get_forms($conn) {
    $result = $conn->query("SELECT id, title, description, created_at FROM dynamic_forms ORDER BY created_at DESC");
    if ($result) {
        $forms = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($forms);
    } else {
        echo json_encode(["error" => "Failed to fetch forms: " . $conn->error]);
        http_response_code(500);
    }
}

function handle_update_field_order($conn, $data) {
    if (!isset($data['ordered_ids']) || !is_array($data['ordered_ids'])) {
        echo json_encode(["error" => "An array of ordered field IDs is required"]);
        http_response_code(400);
        return;
    }

    $ordered_ids = $data['ordered_ids'];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE form_fields SET field_order = ? WHERE id = ?");
        foreach ($ordered_ids as $index => $field_id) {
            $order = $index + 1;
            $stmt->bind_param("ii", $order, $field_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update order for field ID $field_id: " . $stmt->error);
            }
        }
        $stmt->close();
        $conn->commit();
        echo json_encode(["message" => "Field order updated successfully"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    }
}

function handle_get_form($conn, $id) {
    if (!$id) {
        echo json_encode(["error" => "Form ID is required"]);
        http_response_code(400);
        return;
    }
    $stmt = $conn->prepare("SELECT id, title, description, created_at FROM dynamic_forms WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $form = $result->fetch_assoc();
    if ($form) {
        $stmt_fields = $conn->prepare("SELECT id, label, field_type, options, is_required, field_order FROM form_fields WHERE form_id = ? ORDER BY field_order ASC");
        $stmt_fields->bind_param("i", $id);
        $stmt_fields->execute();
        $result_fields = $stmt_fields->get_result();
        $fields = $result_fields->fetch_all(MYSQLI_ASSOC);
        foreach ($fields as &$field) {
            if (in_array($field['field_type'], ['select', 'radio', 'checkbox'])) {
                $field['options'] = json_decode($field['options']);
            }
        }
        $form['fields'] = $fields;
        echo json_encode($form);
    } else {
        echo json_encode(["error" => "Form not found"]);
        http_response_code(404);
    }
}

function handle_create_form($conn, $data) {
    if (!isset($data['title'])) {
        echo json_encode(["error" => "Form title is required"]);
        http_response_code(400);
        return;
    }
    $title = $data['title'];
    $description = isset($data['description']) ? $data['description'] : '';
    $stmt = $conn->prepare("INSERT INTO dynamic_forms (title, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $title, $description);
    if ($stmt->execute()) {
        $new_form_id = $conn->insert_id;
        echo json_encode(["message" => "Form created successfully", "form_id" => $new_form_id]);
    } else {
        echo json_encode(["error" => "Failed to create form: " . $stmt->error]);
        http_response_code(500);
    }
}

function handle_update_form($conn, $data) {
    if (!isset($data['id']) || !isset($data['title'])) {
        echo json_encode(["error" => "Form ID and title are required"]);
        http_response_code(400);
        return;
    }
    $id = $data['id'];
    $title = $data['title'];
    $description = isset($data['description']) ? $data['description'] : '';
    $stmt = $conn->prepare("UPDATE dynamic_forms SET title = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $description, $id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "Form updated successfully"]);
        } else {
            echo json_encode(["message" => "No changes made to the form"]);
        }
    } else {
        echo json_encode(["error" => "Failed to update form: " . $stmt->error]);
        http_response_code(500);
    }
}

function handle_delete_form($conn, $id) {
    if (!$id) {
        echo json_encode(["error" => "Form ID is required"]);
        http_response_code(400);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM dynamic_forms WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "Form deleted successfully"]);
        } else {
            echo json_encode(["error" => "Form not found"]);
            http_response_code(404);
        }
    } else {
        echo json_encode(["error" => "Failed to delete form: " . $stmt->error]);
        http_response_code(500);
    }
}

function handle_add_field($conn, $data) {
    if (!isset($data['form_id']) || !isset($data['label']) || !isset($data['field_type'])) {
        echo json_encode(["error" => "form_id, label, and field_type are required"]);
        http_response_code(400);
        return;
    }
    $form_id = $data['form_id'];
    $label = $data['label'];
    $field_type = $data['field_type'];
    $options = isset($data['options']) ? json_encode($data['options']) : null;
    $is_required = isset($data['is_required']) ? (int)$data['is_required'] : 0;

    // Get the highest current field_order and add 1
    $order_result = $conn->query("SELECT MAX(field_order) as max_order FROM form_fields WHERE form_id = $form_id");
    $max_order = $order_result->fetch_assoc()['max_order'];
    $field_order = $max_order !== null ? $max_order + 1 : 0;

    $stmt = $conn->prepare("INSERT INTO form_fields (form_id, label, field_type, options, is_required, field_order) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssii", $form_id, $label, $field_type, $options, $is_required, $field_order);

    if ($stmt->execute()) {
        $new_field_id = $conn->insert_id;
        echo json_encode(["message" => "Field added successfully", "field_id" => $new_field_id]);
    } else {
        echo json_encode(["error" => "Failed to add field: " . $stmt->error]);
        http_response_code(500);
    }
}

function handle_update_field($conn, $data) {
    if (!isset($data['id'])) {
        echo json_encode(["error" => "Field ID is required"]);
        http_response_code(400);
        return;
    }
    $id = $data['id'];
    // For now, only supporting label, options, and is_required updates
    $label = isset($data['label']) ? $data['label'] : null;
    $options = isset($data['options']) ? json_encode($data['options']) : null;
    $is_required = isset($data['is_required']) ? (int)$data['is_required'] : null;

    // This is a simplified update. A more robust version would dynamically build the query
    // based on which fields are present in the input.
    $stmt = $conn->prepare("UPDATE form_fields SET label = ?, options = ?, is_required = ? WHERE id = ?");
    $stmt->bind_param("ssii", $label, $options, $is_required, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "Field updated successfully"]);
        } else {
            echo json_encode(["message" => "No changes made to the field"]);
        }
    } else {
        echo json_encode(["error" => "Failed to update field: " . $stmt->error]);
        http_response_code(500);
    }
}

function handle_delete_field($conn, $id) {
    if (!$id) {
        echo json_encode(["error" => "Field ID is required"]);
        http_response_code(400);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM form_fields WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "Field deleted successfully"]);
        } else {
            echo json_encode(["error" => "Field not found"]);
            http_response_code(404);
        }
    } else {
        echo json_encode(["error" => "Failed to delete field: " . $stmt->error]);
        http_response_code(500);
    }
}

$conn->close();
?>