<?php
session_start();
require('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['mensaje'] = "ID de presupuesto no válido";
    header("Location: historial.php");
    exit();
}

$id_presupuesto_original = $_GET['id'];

try {
    $consulta->beginTransaction();

    $stmt = $consulta->prepare("
        SELECT id_cliente, total 
        FROM presupuestos 
        WHERE id_presupuesto = :id_presupuesto
    ");
    $stmt->bindParam(':id_presupuesto', $id_presupuesto_original, PDO::PARAM_INT);
    $stmt->execute();
    $presupuesto_original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto_original) {
        throw new Exception("Presupuesto no encontrado");
    }

    $stmt = $consulta->prepare("
        INSERT INTO presupuestos (id_cliente, total, fecha)
        VALUES (:id_cliente, :total, NOW())
    ");
    $stmt->bindParam(':id_cliente', $presupuesto_original['id_cliente'], PDO::PARAM_INT);
    $stmt->bindParam(':total', $presupuesto_original['total'], PDO::PARAM_STR);
    $stmt->execute();
    $id_presupuesto_nuevo = $consulta->lastInsertId();

    $stmt = $consulta->prepare("
        INSERT INTO detalle_pres 
        (id_presupuesto, id_producto, cantidad, precio_unitario, subtotal)
        SELECT :id_presupuesto_nuevo, id_producto, cantidad, precio_unitario, subtotal
        FROM detalle_pres
        WHERE id_presupuesto = :id_presupuesto_original
    ");
    $stmt->bindParam(':id_presupuesto_nuevo', $id_presupuesto_nuevo, PDO::PARAM_INT);
    $stmt->bindParam(':id_presupuesto_original', $id_presupuesto_original, PDO::PARAM_INT);
    $stmt->execute();

    $descripcion = "Presupuesto clonado del #" . $id_presupuesto_original;
    $stmt = $consulta->prepare("
        INSERT INTO historial 
        (id_cliente, descripcion, id_presupuesto, fecha)
        VALUES (:id_cliente, :descripcion, :id_presupuesto, NOW())
    ");
    $stmt->bindParam(':id_cliente', $presupuesto_original['id_cliente'], PDO::PARAM_INT);
    $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindParam(':id_presupuesto', $id_presupuesto_nuevo, PDO::PARAM_INT);
    $stmt->execute();

    $consulta->commit();

    $_SESSION['mensaje'] = "Presupuesto clonado correctamente (Nuevo ID: $id_presupuesto_nuevo)";
    header("Location: editar.php?id=$id_presupuesto_nuevo");
    exit();

} catch (Exception $e) {
    $consulta->rollBack();
    $_SESSION['mensaje'] = "Error al clonar presupuesto: " . $e->getMessage();
    header("Location: historial.php");
    exit();
}
?>