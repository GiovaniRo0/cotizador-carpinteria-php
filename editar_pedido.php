<?php
session_start();
require('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_pedido = $_GET['id'] ?? null;
if (!$id_pedido) {
    header("Location: pedidos.php");
    exit();
}

$stmt = $consulta->prepare("
    SELECT 
        p.*,
        pr.total,
        (SELECT SUM(monto) FROM pagos WHERE id_pedido = p.id_pedido) AS pagado,
        CONCAT(per.nombre, ' ', per.appat) AS cliente
    FROM pedido p
    JOIN presupuestos pr ON p.id_presupuesto = pr.id_presupuesto
    JOIN clientes c ON pr.id_cliente = c.id_cliente
    JOIN persona per ON c.id_persona = per.id_persona
    WHERE p.id_pedido = ?
");
$stmt->execute([$id_pedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header("Location: pedidos.php");
    exit();
}

$stmt = $consulta->prepare("SELECT * FROM pagos WHERE id_pedido = ? ORDER BY fecha_pago DESC");
$stmt->execute([$id_pedido]);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado'])) {
    $nuevo_estado = $_POST['estado'];
    
    $stmt = $consulta->prepare("UPDATE pedido SET estado = ? WHERE id_pedido = ?");
    $stmt->execute([$nuevo_estado, $id_pedido]);
    
    header("Location: editar_pedido.php?id=$id_pedido");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_pago'])) {
    $monto = $_POST['monto'];
    $metodo_pago = $_POST['metodo_pago'];
    $referencia = $_POST['referencia'] ?? '';
    
    $stmt = $consulta->prepare("
        INSERT INTO pagos (id_pedido, monto, metodo_pago, referencia)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$id_pedido, $monto, $metodo_pago, $referencia]);
    
    header("Location: editar_pedido.php?id=$id_pedido");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Pedido #<?= $pedido['id_pedido'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .progress { height: 20px; }
        .badge-estado { font-size: 0.85rem; }
        .card-header { font-weight: 500; }
    </style>
</head>
<body>
    <?php include('menu.php'); ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                <i class="bi bi-pencil-square"></i> 
                Editar Pedido #<?= $pedido['id_pedido'] ?>
            </h1>
            <a href="pedidos.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <i class="bi bi-info-circle"></i> Información del Pedido
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente']) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Fecha creación:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Total:</strong> $<?= number_format($pedido['total'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <i class="bi bi-arrow-repeat"></i> Actualizar Estado
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <select name="estado" class="form-select">
                            <option value="Pendiente" <?= $pedido['estado'] == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="En Proceso" <?= $pedido['estado'] == 'En Proceso' ? 'selected' : '' ?>>En Proceso</option>
                            <option value="Completado" <?= $pedido['estado'] == 'Completado' ? 'selected' : '' ?>>Completado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" name="actualizar_estado" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> Actualizar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cash-stack"></i> Gestión de Pagos</span>
                <span class="badge bg-primary">
                    Total Pagado: $<?= number_format($pedido['pagado'] ?? 0, 2) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Progreso de pago</span>
                        <span><?= number_format(($pedido['pagado'] / $pedido['total']) * 100, 0) ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" 
                             style="width: <?= ($pedido['pagado'] / $pedido['total']) * 100 ?>%">
                        </div>
                    </div>
                </div>

                <form method="post" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Monto</label>
                        <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Método de Pago</label>
                        <select name="metodo_pago" class="form-select" required>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Referencia (opcional)</label>
                        <input type="text" name="referencia" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="agregar_pago" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Agregar
                        </button>
                    </div>
                </form>

                <h5 class="mb-3">Historial de Pagos</h5>
                <?php if (empty($pagos)): ?>
                    <div class="alert alert-info">No hay pagos registrados para este pedido</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></td>
                                        <td>$<?= number_format($pago['monto'], 2) ?></td>
                                        <td><?= $pago['metodo_pago'] ?></td>
                                        <td><?= $pago['referencia'] ? htmlspecialchars($pago['referencia']) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>