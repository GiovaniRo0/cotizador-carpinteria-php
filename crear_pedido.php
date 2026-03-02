<?php
session_start();
require('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id_presupuesto']) || !is_numeric($_GET['id_presupuesto'])) {
    $_SESSION['error'] = "ID de presupuesto no válido";
    header("Location: historial.php");
    exit();
}

$id_presupuesto = $_GET['id_presupuesto'];

try {
    $stmt = $consulta->prepare("
        SELECT p.id_presupuesto, p.total, p.id_cliente
        FROM presupuestos p
        JOIN clientes c ON p.id_cliente = c.id_cliente
        JOIN usuarios u ON c.id_persona = u.id_persona
        WHERE p.id_presupuesto = ? AND u.id_usuario = ?
    ");
    $stmt->execute([$id_presupuesto, $_SESSION['id_usuario']]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        throw new Exception("No tienes permisos sobre este presupuesto");
    }

    $stmt = $consulta->prepare("
        INSERT INTO pedido (id_presupuesto, estado)
        VALUES (?, 'Pendiente')
    ");
    $stmt->execute([$id_presupuesto]);
    $id_pedido = $consulta->lastInsertId();

    $_SESSION['exito'] = "Pedido #$id_pedido creado correctamente";
    header("Location: ver_pedido.php?id=$id_pedido");
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    header("Location: historial.php");
    exit();
}