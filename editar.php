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
    $stmt = $consulta->prepare("
        SELECT p.id_presupuesto, p.id_cliente, p.fecha, p.total, 
               CONCAT(per.nombre, ' ', per.appat, ' ', per.apmat) AS cliente_nombre
        FROM presupuestos p
        JOIN clientes c ON p.id_cliente = c.id_cliente
        JOIN persona per ON c.id_persona = per.id_persona
        WHERE p.id_presupuesto = :id_presupuesto
    ");
    $stmt->bindParam(':id_presupuesto', $id_presupuesto, PDO::PARAM_INT);
    $stmt->execute();
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        throw new Exception("Presupuesto no encontrado");
    }

    $stmt = $consulta->prepare("
        SELECT dp.id_producto, pr.nombre, m.nombre as material, 
               dp.cantidad, dp.precio_unitario, dp.subtotal
        FROM detalle_pres dp
        JOIN producto pr ON dp.id_producto = pr.id_producto
        JOIN materiales m ON pr.id_material = m.id_material
        WHERE dp.id_presupuesto = :id_presupuesto
    ");
    $stmt->bindParam(':id_presupuesto', $id_presupuesto, PDO::PARAM_INT);
    $stmt->execute();
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $consulta->query("
        SELECT p.id_producto, p.nombre, m.nombre as material, p.precio
        FROM producto p
        JOIN materiales m ON p.id_material = m.id_material
        ORDER BY p.nombre
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener datos del presupuesto: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $consulta->beginTransaction();

        $stmt = $consulta->prepare("
            DELETE FROM detalle_pres WHERE id_presupuesto = :id_presupuesto
        ");
        $stmt->bindParam(':id_presupuesto', $id_presupuesto, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($_POST['productos'] as $index => $id_producto) {
            if (empty($id_producto)) continue;

            $cantidad = max(1, intval($_POST['cantidades'][$index] ?? 1));

            $stmt = $consulta->prepare("SELECT precio FROM producto WHERE id_producto = ?");
            $stmt->execute([$id_producto]);
            $precio = $stmt->fetchColumn();

            if ($precio === false) {
                throw new Exception("Producto no válido: $id_producto");
            }

            $subtotal = $precio * $cantidad;

            $stmt = $consulta->prepare("
                INSERT INTO detalle_pres 
                (id_presupuesto, id_producto, cantidad, precio_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_presupuesto, $id_producto, $cantidad, $precio, $subtotal]);
        }


        $descripcion = "Presupuesto modificado";
        $stmt = $consulta->prepare("
            INSERT INTO historial 
            (id_cliente, descripcion, id_presupuesto, fecha)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$presupuesto['id_cliente'], $descripcion, $id_presupuesto]);

        $consulta->commit();

        $_SESSION['mensaje'] = "Presupuesto actualizado correctamente";
        header("Location: historial.php");
        exit();
    } catch (PDOException $e) {
        $consulta->rollBack();
        die("Error al actualizar presupuesto: " . $e->getMessage());
    } catch (Exception $e) {
        $consulta->rollBack();
        die($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Presupuesto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        .producto-duplicado {
            border: 2px solid #dc3545 !important;
        }

        .card-header {
            font-weight: bold;
        }

        .presupuesto-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="bi bi-pencil-square"></i> Editar Presupuesto #<?= $id_presupuesto ?></h1>

        <div class="presupuesto-info mb-4">
            <div class="row">
                <div class="col-md-6">
                    <strong>Cliente:</strong> <?= htmlspecialchars($presupuesto['cliente_nombre']) ?>
                </div>
                <div class="col-md-6">
                    <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($presupuesto['fecha'])) ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <strong>Total actual:</strong> $<?= number_format($presupuesto['total'], 2) ?>
                </div>
            </div>
        </div>

        <form method="post" id="form-cotizacion">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-box-seam"></i> Productos
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Producto</th>
                                <th width="120">Cantidad</th>
                                <th width="150">Precio Unitario</th>
                                <th width="150">Subtotal</th>
                                <th width="100">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="detalle-productos">
                            <?php foreach ($detalles as $detalle): ?>
                                <tr>
                                    <td>
                                        <select name="productos[]" class="form-select producto" required onchange="validarProducto(this)">
                                            <option value="">Seleccione...</option>
                                            <?php foreach ($productos as $p): ?>
                                                <option value="<?= $p['id_producto'] ?>"
                                                    data-precio="<?= $p['precio'] ?>"
                                                    data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                                    <?= ($p['id_producto'] == $detalle['id_producto']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                    (<?= htmlspecialchars($p['material']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="cantidades[]" class="form-control"
                                            min="1" value="<?= $detalle['cantidad'] ?>" required>
                                    </td>
                                    <td class="precio">$<?= number_format($detalle['precio_unitario'], 2) ?></td>
                                    <td class="subtotal">$<?= number_format($detalle['subtotal'], 2) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFila(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td>
                                    <select name="productos[]" class="form-select producto" onchange="validarProducto(this)">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($productos as $p): ?>
                                            <option value="<?= $p['id_producto'] ?>"
                                                data-precio="<?= $p['precio'] ?>"
                                                data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                                                <?= htmlspecialchars($p['nombre']) ?>
                                                (<?= htmlspecialchars($p['material']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="cantidades[]" class="form-control" min="1" value="1">
                                </td>
                                <td class="precio">$0.00</td>
                                <td class="subtotal">$0.00</td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFila(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <button type="button" class="btn btn-success" onclick="agregarProducto()">
                        <i class="bi bi-plus-circle"></i> Agregar Producto
                    </button>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body text-end bg-light">
                    <h4 class="mb-3">Total: <span id="total-cotizacion" class="text-primary">$<?= number_format($presupuesto['total'], 2) ?></span></h4>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                    <a href="historial.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productosSeleccionados = new Set();

        document.querySelectorAll('.producto').forEach(select => {
            if (select.value) {
                productosSeleccionados.add(select.value);
            }
        });

        function agregarProducto() {
            const tbody = document.getElementById('detalle-productos');
            const filaOriginal = tbody.querySelector('tr:last-child');
            const nuevaFila = filaOriginal.cloneNode(true);

            const select = nuevaFila.querySelector('select');
            select.selectedIndex = 0;
            select.classList.remove('producto-duplicado');
            select.value = '';

            nuevaFila.querySelector('input').value = 1;
            nuevaFila.querySelector('.precio').textContent = '$0.00';
            nuevaFila.querySelector('.subtotal').textContent = '$0.00';

            tbody.appendChild(nuevaFila);

            configurarEventosFila(nuevaFila);
        }

        function eliminarFila(btn) {
            const fila = btn.closest('tr');
            const select = fila.querySelector('select');
            const tbody = document.getElementById('detalle-productos');
            const filas = tbody.querySelectorAll('tr');

            if (filas.length <= 1) return;
            if (select.value) {
                productosSeleccionados.delete(select.value);
            }

            fila.remove();
            calcularTotal();
        }

        function validarProducto(select) {
            const productoId = select.value;
            const productoNombre = select.selectedOptions[0]?.dataset.nombre || '';
            const fila = select.closest('tr');
            const precioElement = fila.querySelector('.precio');
            const inputCantidad = fila.querySelector('input[name="cantidades[]"]');
            const subtotalElement = fila.querySelector('.subtotal');

            select.classList.remove('producto-duplicado');

            if (!productoId) {
                if (select.value) {
                    productosSeleccionados.delete(select.value);
                }
                precioElement.textContent = '$0.00';
                subtotalElement.textContent = '$0.00';
                calcularTotal();
                return;
            }

            let esDuplicado = false;
            document.querySelectorAll('.producto').forEach(s => {
                if (s !== select && s.value === productoId) {
                    esDuplicado = true;
                    s.classList.add('producto-duplicado');
                }
            });

            if (esDuplicado) {
                select.classList.add('producto-duplicado');
                alert(`"${productoNombre}" ya está seleccionado`);
                select.value = '';
                precioElement.textContent = '$0.00';
                subtotalElement.textContent = '$0.00';
                calcularTotal();
                return;
            }

            if (select.value) {
                productosSeleccionados.add(select.value);
            }

            const precio = select.selectedOptions[0]?.dataset.precio || 0;
            precioElement.textContent = '$' + parseFloat(precio).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            const cantidad = parseInt(inputCantidad.value) || 0;
            const subtotal = parseFloat(precio) * cantidad;
            subtotalElement.textContent = '$' + subtotal.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            calcularTotal();
        }

        function calcularTotal() {
            let total = 0;
            const filas = document.querySelectorAll('#detalle-productos tr');

            filas.forEach(fila => {
                const subtotalElement = fila.querySelector('.subtotal');
                if (subtotalElement) {
                    const subtotalText = subtotalElement.textContent
                        .replace('$', '')
                        .replace(/,/g, '');
                    total += parseFloat(subtotalText) || 0;
                }
            });

            document.getElementById('total-cotizacion').textContent =
                '$' + total.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
        }

        function configurarEventosFila(fila) {
            const select = fila.querySelector('select');
            const inputCantidad = fila.querySelector('input[name="cantidades[]"]');

            select.addEventListener('change', function() {
                validarProducto(this);
            });

            inputCantidad.addEventListener('input', function() {
                const fila = this.closest('tr');
                const select = fila.querySelector('select');
                if (select.value) {
                    validarProducto(select);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#detalle-productos tr').forEach(fila => {
                configurarEventosFila(fila);
            });

            calcularTotal();
        });
    </script>


</body>

</html>