<?php
session_start();
include('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    die("Acceso denegado. Debes iniciar sesión.");
}

if (!isset($_GET['id'])) {
    die("ID de presupuesto no especificado.");
}

$id_presupuesto = $_GET['id'];

try {
    $stmt = $consulta->prepare("
        SELECT p.*, CONCAT(per.nombre, ' ', per.appat, ' ', per.apmat) as cliente_nombre
        FROM presupuestos p
        JOIN clientes c ON p.id_cliente = c.id_cliente
        JOIN persona per ON c.id_persona = per.id_persona
        WHERE p.id_presupuesto = ?
    ");
    $stmt->execute([$id_presupuesto]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        die("Presupuesto no encontrado.");
    }
} catch (PDOException $e) {
    die("Error al cargar presupuesto: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>¡Presupuesto Guardado!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        .btn-pdf { background-color: #e74c3c; color: white; }
        .btn-pdf:hover { background-color: #c0392b; }
    </style>
</head>
<body>
    <?php include('menu.php'); ?>

    <div class="container mt-4">
        <div class="alert alert-success text-center">
            <h2><i class="bi bi-check-circle-fill"></i> ¡Presupuesto guardado con éxito!</h2>
            <p class="lead">Número de presupuesto: <strong>#<?= htmlspecialchars($id_presupuesto) ?></strong></p>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-file-text"></i> Resumen</h5>
                <hr>
                <p><strong><i class="bi bi-person"></i> Cliente:</strong> <?= htmlspecialchars($presupuesto['cliente_nombre']) ?></p>
                <p><strong><i class="bi bi-calendar"></i> Fecha:</strong> <?= $presupuesto['fecha'] ?></p>
                <p><strong><i class="bi bi-currency-dollar"></i> Total:</strong> $<?= number_format($presupuesto['total'], 2) ?></p>
            </div>
        </div>

        <div class="d-flex justify-content-center gap-3">
            <a href="generar.php?id=<?= $id_presupuesto ?>" class="btn btn-pdf">
                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
            </a>
            <a href="pres.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear nuevo
            </a>
            <a href="historial.php" class="btn btn-secondary">
                <i class="bi bi-clock-history"></i> Ver historial
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>