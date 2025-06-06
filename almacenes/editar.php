<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evitar secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;

// Verificar que el usuario sea administrador
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tiene permisos para editar almacenes.";
    header("Location: listar.php");
    exit();
}

// Validar el ID del almacén
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de almacén no válido.";
    header("Location: listar.php");
    exit();
}

$almacen_id = $_GET['id'];

// Obtener información del almacén
$sql = "SELECT * FROM almacenes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$almacen = $result->fetch_assoc();
$stmt->close();

if (!$almacen) {
    $_SESSION['error'] = "Almacén no encontrado.";
    header("Location: listar.php");
    exit();
}

$mensaje = "";
$error = "";
$nombre = $almacen['nombre'];
$ubicacion = $almacen['ubicacion'];

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST["nombre"]) && !empty($_POST["ubicacion"])) {
        $nuevo_nombre = trim($_POST["nombre"]);
        $nueva_ubicacion = trim($_POST["ubicacion"]);

        // Verificar si el nuevo nombre ya existe (excepto el actual)
        $sql_check = "SELECT id FROM almacenes WHERE nombre = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $nuevo_nombre, $almacen_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "⚠️ Ya existe un almacén con ese nombre.";
        } else {
            // Actualizar el almacén
            $sql_update = "UPDATE almacenes SET nombre = ?, ubicacion = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update) {
                $stmt_update->bind_param("ssi", $nuevo_nombre, $nueva_ubicacion, $almacen_id);
                if ($stmt_update->execute()) {
                    // Registrar la acción en logs (opcional)
                    $usuario_id = $_SESSION["user_id"];
                    $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                                VALUES (?, 'EDITAR_ALMACEN', ?, NOW())";
                    $stmt_log = $conn->prepare($sql_log);
                    $detalle = "Editó el almacén ID {$almacen_id}: '{$nombre}' -> '{$nuevo_nombre}'";
                    $stmt_log->bind_param("is", $usuario_id, $detalle);
                    $stmt_log->execute();
                    $stmt_log->close();
                    
                    $_SESSION['success'] = "✅ Almacén actualizado con éxito.";
                    header("Location: ver-almacen.php?id=" . $almacen_id);
                    exit();
                } else {
                    $error = "❌ Error al actualizar el almacén: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
    } else {
        $error = "⚠️ Todos los campos son obligatorios.";
    }
}
?>