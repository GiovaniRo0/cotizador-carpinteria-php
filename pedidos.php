<?php
session_start();
require('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

try {
    $stmt = $consulta->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Usuario no encontrado.");
    }

    $rol = $user['id_rol'];

    $id_cliente = null;
    if ($rol == 3) {
        $stmt = $consulta->prepare("
            SELECT c.id_cliente
            FROM usuarios u
            JOIN persona p ON u.id_persona = p.id_persona
            JOIN clientes c ON p.id_persona = c.id_persona
            WHERE u.id_usuario = ?
        ");
        $stmt->execute([$_SESSION['id_usuario']]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_cliente = $cliente['id_cliente'] ?? null;
    }

    $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $por_pagina = 3;
    $offset = ($pagina - 1) * $por_pagina;

    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

    $query = "
        SELECT 
            p.id_pedido, 
            p.estado,
            p.fecha_creacion,
            pr.total,
            pagos_totales.pagado,
            CONCAT(per.nombre, ' ', per.appat) AS cliente,
            pr.id_presupuesto
        FROM pedido p
        JOIN presupuestos pr ON p.id_presupuesto = pr.id_presupuesto
        JOIN clientes c ON pr.id_cliente = c.id_cliente
        JOIN persona per ON c.id_persona = per.id_persona
        LEFT JOIN (
            SELECT id_pedido, COALESCE(SUM(monto), 0) AS pagado
            FROM pagos
            GROUP BY id_pedido
        ) pagos_totales ON pagos_totales.id_pedido = p.id_pedido
        WHERE 1=1
    ";

    $params = [];

    if ($rol == 3) {
        if ($id_cliente) {
            $query .= " AND c.id_cliente = :id_cliente";
            $params[':id_cliente'] = $id_cliente;
        } else {
            $query .= " AND 1 = 0"; 
        }
    }

    // Agregar condiciones de búsqueda si hay término
    if (!empty($busqueda)) {
        $query .= " AND (p.id_pedido LIKE :busqueda 
                    OR pr.id_presupuesto LIKE :busqueda 
                    OR CONCAT(per.nombre, ' ', per.appat) LIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }

    $query_count = "SELECT COUNT(*) as total FROM ($query) AS subquery";
    $stmt_count = $consulta->prepare($query_count);

    foreach ($params as $key => $value) {
        $stmt_count->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt_count->execute();
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $por_pagina);

    $query .= " ORDER BY p.fecha_creacion DESC LIMIT :offset, :limit";
    $stmt = $consulta->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $query_completados = str_replace("ORDER BY p.fecha_creacion DESC LIMIT :offset, :limit", 
                                       "AND p.estado = 'Completado'", $query);
        $stmt_completados = $consulta->prepare("SELECT COUNT(*) as total FROM ($query_completados) AS subquery");
        foreach ($params as $key => $value) {
            $stmt_completados->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt_completados->execute();
        $completados = $stmt_completados->fetchColumn();
        
        $query_proceso = str_replace("ORDER BY p.fecha_creacion DESC LIMIT :offset, :limit", 
                                    "AND p.estado = 'En Proceso'", $query);
        $stmt_proceso = $consulta->prepare("SELECT COUNT(*) as total FROM ($query_proceso) AS subquery");
        foreach ($params as $key => $value) {
            $stmt_proceso->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt_proceso->execute();
        $en_proceso = $stmt_proceso->fetchColumn();
        
        $query_pendientes = str_replace("ORDER BY p.fecha_creacion DESC LIMIT :offset, :limit", 
                                       "AND p.estado = 'Pendiente'", $query);
        $stmt_pendientes = $consulta->prepare("SELECT COUNT(*) as total FROM ($query_pendientes) AS subquery");
        foreach ($params as $key => $value) {
            $stmt_pendientes->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt_pendientes->execute();
        $pendientes = $stmt_pendientes->fetchColumn();
        
    } catch (Exception $e) {
        $completados = 0;
        $en_proceso = 0;
        $pendientes = 0;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $rol == 3 ? 'Mis Pedidos' : 'Listado de Pedidos' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .progress { height: 20px; }
        .badge-estado { font-size: 0.85rem; }
        .pagination .page-item.active .page-link { 
            background-color: #0d6efd; 
            border-color: #0d6efd; 
        }
        .table-actions { white-space: nowrap; }
        
        /* Nuevos estilos para las barras de resumen */
        .resumen-bar {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
            background-color: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .resumen-icon {
            font-size: 1.25rem;
            margin-right: 0.75rem;
        }
        .resumen-content {
            flex-grow: 1;
        }
        .resumen-title {
            font-size: 0.85rem;
            margin-bottom: 0.1rem;
            color: #6c757d;
        }
        .resumen-value {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .resumen-primary { border-left: 4px solid #0d6efd; }
        .resumen-warning { border-left: 4px solid #ffc107; }
        .resumen-success { border-left: 4px solid #198754; }
        .resumen-secondary { border-left: 4px solid #6c757d; }
    </style>
</head>
<body>
    <?php include('menu.php'); ?>

    <div class="container mt-4">
        <h1 class="mb-4">
            <i class="bi bi-list-check"></i> 
            <?= $rol == 3 ? 'Mis Pedidos' : 'Listado de Pedidos' ?>
        </h1>

        <!-- Barra de búsqueda -->
        <div class="card shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="pedidos.php" class="row g-2">
                    <div class="col-md-8">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control form-control-sm" name="busqueda" 
                                   placeholder="Buscar por número de pedido, presupuesto o cliente" 
                                   value="<?= htmlspecialchars($busqueda) ?>">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if (!empty($busqueda)): ?>
                            <a href="pedidos.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-3 g-2">
            <div class="col-md-3">
                <div class="resumen-bar resumen-primary">
                    <div class="resumen-icon text-primary">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div class="resumen-content">
                        <div class="resumen-title">Total</div>
                        <div class="resumen-value"><?= $total_registros ?></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="resumen-bar resumen-warning">
                    <div class="resumen-icon text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="resumen-content">
                        <div class="resumen-title">En Proceso</div>
                        <div class="resumen-value"><?= $en_proceso ?></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="resumen-bar resumen-success">
                    <div class="resumen-icon text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="resumen-content">
                        <div class="resumen-title">Completados</div>
                        <div class="resumen-value"><?= $completados ?></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="resumen-bar resumen-secondary">
                    <div class="resumen-icon text-secondary">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="resumen-content">
                        <div class="resumen-title">Pendientes</div>
                        <div class="resumen-value"><?= $pendientes ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (!empty($busqueda)): ?>
                    <div class="alert alert-info mb-4">
                        Mostrando resultados para: <strong>"<?= htmlspecialchars($busqueda) ?>"</strong>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>No.</th>
                                <th>Fecha</th>
                                <?php if ($rol != 3): ?>
                                    <th>Cliente</th>
                                <?php endif; ?>
                                <th>Presupuesto</th>
                                <th>Total</th>
                                <th>Pagado</th>
                                <th>Estado</th>
                                <th class="table-actions text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pedidos)): ?>
                                <tr>
                                    <td colspan="<?= $rol == 3 ? 7 : 8 ?>" class="text-center py-4">
                                        No se encontraron pedidos <?= !empty($busqueda) ? 'que coincidan con la búsqueda' : '' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php
                                    $total = (float)$pedido['total'];
                                    $pagado = (float)$pedido['pagado'];
                                    $porcentaje_pagado = $total > 0 ? ($pagado / $total) * 100 : 0;
                                    $color_estado = [
                                        'Pendiente' => 'secondary',
                                        'En Proceso' => 'warning',
                                        'Completado' => 'success'
                                    ][$pedido['estado']];
                                    ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($pedido['id_pedido']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($pedido['fecha_creacion'])) ?></td>
                                        <?php if ($rol != 3): ?>
                                            <td><?= htmlspecialchars($pedido['cliente']) ?></td>
                                        <?php endif; ?>
                                        <td>#<?= htmlspecialchars($pedido['id_presupuesto']) ?></td>
                                        <td>$<?= number_format($total, 2) ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?= $porcentaje_pagado ?>%">
                                                    <?= number_format($porcentaje_pagado, 0) ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                $<?= number_format($pagado, 2) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $color_estado ?> badge-estado">
                                                <?= htmlspecialchars($pedido['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="ver_pedido.php?id=<?= $pedido['id_pedido'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Ver Detalles">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($rol != 3): ?>
                                                <a href="editar_pedido.php?id=<?= $pedido['id_pedido'] ?>" 
                                                   class="btn btn-sm btn-outline-warning"
                                                   title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($pagina > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">
                                        &laquo;&laquo;
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                                        &laquo;
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php 
                            $pagina_inicio = max(1, $pagina - 2);
                            $pagina_fin = min($total_paginas, $pagina + 2);
                            
                            for ($i = $pagina_inicio; $i <= $pagina_fin; $i++): ?>
                                <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($pagina < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                                        &raquo;
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>">
                                        &raquo;&raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <div class="text-center text-muted mt-2">
                        Página <?= $pagina ?> de <?= $total_paginas ?> - 
                        Mostrando <?= count($pedidos) ?> de <?= $total_registros ?> registros
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>