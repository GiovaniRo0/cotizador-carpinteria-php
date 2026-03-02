<?php
session_start();
require('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['id_usuario'])) {
    $stmt = $consulta->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $rol = $user['id_rol'] ?? 0;
} else {
    $rol = 0;
}

// Parámetros de búsqueda
$busqueda = $_GET['busqueda'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 4;

$query = "
    SELECT 
        p.id_presupuesto,
        p.fecha,
        p.total,
        h.descripcion,
        CONCAT(per.nombre, ' ', per.appat, ' ', COALESCE(per.apmat, '')) AS cliente_nombre_completo,
        per.nombre AS cliente_nombre,
        per.appat AS cliente_appat,
        per.apmat AS cliente_apmat,
        p.activo
    FROM presupuestos p
    JOIN clientes c ON p.id_cliente = c.id_cliente
    JOIN persona per ON c.id_persona = per.id_persona
    LEFT JOIN (
        SELECT id_presupuesto, MAX(fecha) as ultima_fecha 
        FROM historial 
        GROUP BY id_presupuesto
    ) ult ON p.id_presupuesto = ult.id_presupuesto
    LEFT JOIN historial h ON p.id_presupuesto = h.id_presupuesto AND h.fecha = ult.ultima_fecha
    WHERE p.activo = 1 
";

$params = [];

// Filtro por rol (clientes solo ven sus presupuestos)
if ($rol == 3) {
    try {
        $stmt = $consulta->prepare("
            SELECT c.id_cliente
            FROM usuarios u
            JOIN persona p ON u.id_persona = p.id_persona
            JOIN clientes c ON p.id_persona = c.id_persona
            WHERE u.id_usuario = :id_usuario
        ");
        $stmt->bindParam(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            $query .= " AND p.id_cliente = :id_cliente";
            $params[':id_cliente'] = $cliente['id_cliente'];
        } else {
            $query .= " AND 1 = 0";
        }
    } catch (PDOException $e) {
        die("Error al obtener cliente: " . $e->getMessage());
    }
}

// Filtro de búsqueda (nombre, apellido paterno o materno)
if (!empty($busqueda)) {
    $query .= " AND (
        per.nombre LIKE :busqueda_nombre OR 
        per.appat LIKE :busqueda_appat OR 
        per.apmat LIKE :busqueda_apmat OR
        CONCAT(per.nombre, ' ', per.appat, ' ', COALESCE(per.apmat, '')) LIKE :busqueda_completo
    )";
    $params[':busqueda_nombre'] = "%$busqueda%";
    $params[':busqueda_appat'] = "%$busqueda%";
    $params[':busqueda_apmat'] = "%$busqueda%";
    $params[':busqueda_completo'] = "%$busqueda%";
}

// Filtro por rango de fechas
if (!empty($fecha_desde) && !empty($fecha_hasta)) {
    $query .= " AND DATE(p.fecha) BETWEEN :fecha_desde AND :fecha_hasta";
    $params[':fecha_desde'] = $fecha_desde;
    $params[':fecha_hasta'] = $fecha_hasta;
} elseif (!empty($fecha_desde)) {
    $query .= " AND DATE(p.fecha) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
} elseif (!empty($fecha_hasta)) {
    $query .= " AND DATE(p.fecha) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

// Contar total de registros
$query_count = "SELECT COUNT(*) as total " . substr($query, strpos($query, "FROM"));
$stmt_count = $consulta->prepare($query_count);

foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}

$stmt_count->execute();
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

if ($pagina > $total_paginas && $total_paginas > 0) {
    $pagina = $total_paginas;
}

// Consulta principal con paginación
$query .= " ORDER BY p.fecha DESC LIMIT :offset, :limit";
$params[':offset'] = ($pagina - 1) * $por_pagina;
$params[':limit'] = $por_pagina;

$stmt = $consulta->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Presupuestos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .table-actions {
            white-space: nowrap;
            width: 1%;
        }

        .action-btn {
            margin: 0 3px;
        }

        .search-card {
            background-color: #f8f9fa;
        }

        .pagination {
            margin-top: 20px;
        }

        .disabled-link {
            pointer-events: none;
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .date-range-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-range-separator {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php include('menu.php'); ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="bi bi-clock-history"></i> Historial de Presupuestos</h1>

        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <div class="card search-card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" name="busqueda" class="form-control"
                            placeholder="Buscar por nombre o apellidos..."
                            value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    
                    <div class="col-md-5">
                        <div class="date-range-container">
                            <input type="date" name="fecha_desde" class="form-control" 
                                placeholder="Desde" value="<?= htmlspecialchars($fecha_desde) ?>">
                            <span class="date-range-separator">a</span>
                            <input type="date" name="fecha_hasta" class="form-control" 
                                placeholder="Hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                    
                    <div class="col-md-12 mt-2">
                        <div class="d-flex justify-content-between">
                            <a href="historial.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Limpiar filtros
                            </a>
                            <div class="text-muted">
                                <?= $total_registros ?> resultados encontrados
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>No.</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Última acción</th>
                        <th class="table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <?= empty($busqueda) && empty($fecha_desde) && empty($fecha_hasta) 
                                    ? 'No hay presupuestos en el historial' 
                                    : 'No se encontraron resultados con los filtros aplicados' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historial as $item): ?>
                            <?php
                            $stmt_pedido = $consulta->prepare("
                                SELECT id_pedido FROM pedido 
                                WHERE id_presupuesto = ?
                                LIMIT 1
                            ");
                            $stmt_pedido->execute([$item['id_presupuesto']]);
                            $pedido_existente = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
                            $tiene_pedido = !empty($pedido_existente);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($item['id_presupuesto']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['fecha'])) ?></td>
                                <td><?= htmlspecialchars(trim($item['cliente_nombre'] . ' ' . $item['cliente_appat'] . ' ' . ($item['cliente_apmat'] ?? ''))) ?></td>
                                <td>$<?= number_format($item['total'], 2) ?></td>
                                <td><?= htmlspecialchars($item['descripcion']) ?></td>
                                <td class="table-actions">
                                    <div class="d-flex">
                                        <a href="generar.php?id=<?= $item['id_presupuesto'] ?>"
                                            class="btn btn-sm btn-secondary action-btn"
                                            title="Generar PDF">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>

                                        <a href="editar.php?id=<?= $item['id_presupuesto'] ?>"
                                            class="btn btn-sm btn-warning action-btn <?= $tiene_pedido ? 'disabled-link' : '' ?>"
                                            title="<?= $tiene_pedido ? 'No se puede editar (tiene pedido asociado)' : 'Editar' ?>"
                                            <?= $tiene_pedido ? 'data-bs-toggle="tooltip" data-bs-placement="top"' : '' ?>>
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="clonar.php?id=<?= $item['id_presupuesto'] ?>"
                                            class="btn btn-sm btn-info action-btn"
                                            title="Clonar presupuesto"
                                            onclick="return confirm('¿Estás seguro que deseas clonar este presupuesto?')">
                                            <i class="bi bi-files"></i>
                                        </a>

                                        <a href="<?= $pedido_existente ? 'ver_pedido.php?id=' . $pedido_existente['id_pedido'] : 'crear_pedido.php?id_presupuesto=' . $item['id_presupuesto'] ?>"
                                            class="btn btn-sm btn-<?= $pedido_existente ? 'success' : 'primary' ?> action-btn"
                                            title="<?= $pedido_existente ? 'Ver Pedido #' . $pedido_existente['id_pedido'] : 'Crear Pedido' ?>"
                                            onclick="<?= $pedido_existente ? '' : 'return confirm(\'¿Convertir este presupuesto en pedido?\')' ?>">
                                            <i class="bi bi-cart-<?= $pedido_existente ? 'check' : 'plus' ?>"></i>
                                        </a>

                                        <a href="eliminar.php?id=<?= $item['id_presupuesto'] ?>"
                                            class="btn btn-sm btn-danger action-btn"
                                            title="Eliminar"
                                            onclick="return confirm('¿Marcar este presupuesto como eliminado?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
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
                Mostrando <?= count($historial) ?> de <?= $total_registros ?> registros
            </div>
        <?php endif; ?>

        <div class="text-end mt-3">
            <a href="pres.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Nuevo Presupuesto
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Configurar datepickers
            flatpickr('input[type="date"]', {
                dateFormat: 'Y-m-d',
                locale: 'es',
                allowInput: true
            });
        });
    </script>
</body>

</html>