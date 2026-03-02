<?php
session_start();
require('inc/conexion.php');

if (!isset($_SESSION['id_usuario']) || !isset($_GET['id'])) {
    header("Location: historial.php");
    exit();
}

$id_pedido = $_GET['id'];

try {
    $stmt = $consulta->prepare("CALL sp_get_pedido_info(?)");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(); 
    $stmt->closeCursor(); 

    if (!$pedido) throw new Exception("Pedido no encontrado");

    $stmt = $consulta->prepare("CALL sp_get_pedido_productos(?)");
    $stmt->execute([$pedido['id_presupuesto']]);  
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($productos === false) {
        $productos = [];
    }

    $stmt = $consulta->prepare("CALL sp_get_pedido_pagos(?)");
    $stmt->execute([$id_pedido]);  
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($pagos === false) {
        $pagos = [];
    }

    $total_pagado = array_sum(array_column($pagos, 'monto'));
    $saldo_pendiente = max(0, $pedido['total'] - $total_pagado);

} catch (PDOException $e) {
    die("Error al cargar pedido: " . $e->getMessage());
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido #<?= $id_pedido ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .progress-bar { transition: width 0.6s ease; }
        .badge-estado { font-size: 1rem; padding: 0.5em; }
    </style>
</head>
<body>
    <?php include('menu.php'); ?>

    <div class="container mt-4">
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['mensaje'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-receipt"></i> Pedido #<?= $id_pedido ?></h2>
            <span class="badge bg-<?= 
                $pedido['estado'] == 'Completado' ? 'success' : 
                ($pedido['estado'] == 'En Proceso' ? 'warning' : 'secondary') 
            ?> badge-estado">
                <?= $pedido['estado'] ?>
            </span>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-person"></i> Cliente
                    </div>
                    <div class="card-body">
                        <h5><?= htmlspecialchars($pedido['cliente']) ?></h5>
                        <p class="mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($pedido['direccion']) ?></p>
                        <p class="mb-0"><i class="bi bi-telephone"></i> <?= htmlspecialchars($pedido['telefono']) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-cash-stack"></i> Finanzas
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Total: <span class="float-end">$<?= number_format($pedido['total'], 2) ?></span></h6>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?= min(100, ($total_pagado/$pedido['total'])*100) ?>%">
                                    <?= number_format(($total_pagado/$pedido['total'])*100, 2) ?>%
                                </div>
                            </div>
                        </div>
                        <p>Pagado: <strong class="float-end">$<?= number_format($total_pagado, 2) ?></strong></p>
                        <p>Saldo: <strong class="float-end">$<?= number_format($saldo_pendiente, 2) ?></strong></p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <i class="bi bi-link-45deg"></i> Relacionados
                    </div>
                    <div class="card-body">
                        <p>Presupuesto: <a href="generar.php?id=<?= $pedido['id_presupuesto'] ?>" 
                                          class="float-end">#<?= $pedido['id_presupuesto'] ?></a></p>
                        <p>Creado: <span class="float-end"><?= date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) ?></span></p>
                        <?php if ($pedido['fecha_actualizacion']): ?>
                        <p>Actualizado: <span class="float-end"><?= date('d/m/Y H:i', strtotime($pedido['fecha_actualizacion'])) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="pedidoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="productos-tab" data-bs-toggle="tab" 
                        data-bs-target="#productos" type="button">
                    <i class="bi bi-box-seam"></i> Productos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pagos-tab" data-bs-toggle="tab" 
                        data-bs-target="#pagos" type="button">
                    <i class="bi bi-credit-card"></i> Pagos
                </button>
            </li>
        </ul>

        <div class="tab-content" id="pedidoTabsContent">
            <div class="tab-pane fade show active" id="productos">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">P. Unitario</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                <td class="text-center"><?= $prod['cantidad'] ?></td>
                                <td class="text-end">$<?= number_format($prod['precio_real'], 2) ?></td>
                                <td class="text-end">$<?= number_format($prod['subtotal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-primary fw-bold">
                                <td colspan="3" class="text-end">TOTAL</td>
                                <td class="text-end">$<?= number_format($pedido['total'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="pagos">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-plus-circle"></i> Registrar Pago
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="id_pedido" value="<?= $id_pedido ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Monto*</label>
                                        <input type="number" class="form-control" name="monto"
                                               min="0.01" max="<?= $saldo_pendiente ?>" step="0.01" 
                                               value="<?= min(1000, $saldo_pendiente) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Método de Pago*</label>
                                        <select class="form-select" name="metodo_pago" required>
                                            <option value="Efectivo">Efectivo</option>
                                            <option value="Transferencia">Transferencia</option>
                                            <option value="Tarjeta">Tarjeta</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Referencia (Opcional)</label>
                                        <input type="text" class="form-control" name="referencia">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-save"></i> Registrar Pago
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-list-check"></i> Historial de Pagos
                            </div>
                            <div class="card-body">
                                <?php if (empty($pagos)): ?>
                                    <div class="alert alert-info">No hay pagos registrados</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Método</th>
                                                    <th class="text-end">Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pagos as $pago): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></td>
                                                    <td><?= $pago['metodo_pago'] ?></td>
                                                    <td class="text-end">$<?= number_format($pago['monto'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <tr class="table-success fw-bold">
                                                    <td colspan="2">TOTAL PAGADO</td>
                                                    <td class="text-end">$<?= number_format($total_pagado, 2) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tabElms = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabElms.forEach(tabEl => {
            tabEl.addEventListener('click', function (event) {
                event.preventDefault();
                const tab = new bootstrap.Tab(this);
                tab.show();
            })
        });
    </script>
</body>
</html>