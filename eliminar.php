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

$id_presupuesto = $_GET['id'];

try {
    $stmt = $consulta->prepare("UPDATE presupuestos SET activo = 0 WHERE id_presupuesto = :id");
    $stmt->bindParam(':id', $id_presupuesto, PDO::PARAM_INT);
    $stmt->execute();
    
    $descripcion = "Presupuesto marcado como eliminado";
    $stmt = $consulta->prepare("
        INSERT INTO historial 
        (id_presupuesto, descripcion, fecha)
        VALUES (:id_presupuesto, :descripcion, NOW())
    ");
    $stmt->bindParam(':id_presupuesto', $id_presupuesto, PDO::PARAM_INT);
    $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->execute();
    
    $_SESSION['mensaje'] = "Presupuesto #$id_presupuesto marcado como eliminado correctamente";
    
} catch (PDOException $e) {
}

header("Location: historial.php");
exit();
?>