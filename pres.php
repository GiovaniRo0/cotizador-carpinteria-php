<?php
session_start();
include('inc/conexion.php');

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$clientes = [];
try {
    $stmt = $consulta->prepare("
        SELECT c.id_cliente, CONCAT(p.nombre, ' ', p.appat, ' ', p.apmat) as nombre 
        FROM usuarios u
        JOIN persona p ON u.id_persona = p.id_persona
        JOIN clientes c ON p.id_persona = c.id_persona
        WHERE u.id_usuario = :id_usuario
    ");
    $stmt->bindParam(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener cliente: " . $e->getMessage());
}

$productos = [];
try {
    $stmt = $consulta->query("
        SELECT p.id_producto, p.nombre, m.nombre as material, p.precio
        FROM producto p
        JOIN materiales m ON p.id_material = m.id_material
        ORDER BY p.nombre
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener productos: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cliente'])) {
    if (empty($_POST['productos']) || !is_array($_POST['productos'])) {
        die("Debe seleccionar al menos un producto");
    }

    try {
        $consulta->beginTransaction();

        $stmt = $consulta->prepare("
        INSERT INTO presupuestos (id_cliente, total, fecha)
        VALUES (:id_cliente, 0, NOW())
    ");
        $stmt->bindParam(':id_cliente', $_POST['id_cliente'], PDO::PARAM_INT);
        $stmt->execute();
        $id_presupuesto = $consulta->lastInsertId();

        $productos_procesados = [];

        foreach ($_POST['productos'] as $index => $id_producto) {
            if (empty($id_producto)) continue;

            if (in_array($id_producto, $productos_procesados)) {
                die("Error: El producto ID $id_producto está duplicado");
            }
            $productos_procesados[] = $id_producto;

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

        $stmt = $consulta->prepare("SELECT total FROM presupuestos WHERE id_presupuesto = ?");
        $stmt->execute([$id_presupuesto]);
        $total = $stmt->fetchColumn();

        $descripcion = "Presupuesto #$id_presupuesto - Total: $" . number_format($total, 2);
        $stmt = $consulta->prepare("
            INSERT INTO historial 
            (id_cliente, descripcion, id_presupuesto, fecha)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$_POST['id_cliente'], $descripcion, $id_presupuesto]);

        $consulta->commit();
        header("Location: exito.php?id=$id_presupuesto");
        exit();
    } catch (PDOException $e) {
        $consulta->rollBack();
        die("Error al guardar: " . $e->getMessage());
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
    <title>Crear Cotización</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        .producto-duplicado {
            border: 2px solid #dc3545 !important;
        }
        .card-header {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="bi bi-file-earmark-text"></i> Crear Nueva Cotización</h1>

        <?php if (isset($_SESSION['nombreusu'])): ?>
            <div class="alert alert-info">
                <i class="bi bi-person"></i> Usuario: <strong><?= htmlspecialchars($_SESSION['nombreusu']) ?></strong>
            </div>
        <?php endif; ?>

        <form method="post" id="form-cotizacion">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-person"></i> Datos del Cliente
                </div>
                <div class="card-body">
                    <?php if (!empty($clientes)): ?>
                        <input type="hidden" name="id_cliente" value="<?= $clientes[0]['id_cliente'] ?>">
                        <p class="lead"><?= htmlspecialchars($clientes[0]['nombre']) ?></p>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No tienes un cliente asociado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

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
                            <tr>
                                <td>
                                    <select name="productos[]" class="form-select producto" required onchange="validarProducto(this)">
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
                                    <input type="number" name="cantidades[]" class="form-control" min="1" value="1" required>
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
                    <h4 class="mb-3">Total: <span id="total-cotizacion" class="text-primary">$0.00</span></h4>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Guardar Cotización
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productosSeleccionados = new Set();

        function agregarProducto() {
            const tbody = document.getElementById('detalle-productos');
            const filaOriginal = tbody.querySelector('tr');
            const nuevaFila = filaOriginal.cloneNode(true);

            const select = nuevaFila.querySelector('select');
            select.selectedIndex = 0;
            select.classList.remove('producto-duplicado');

            nuevaFila.querySelector('input').value = 1;
            nuevaFila.querySelector('.precio').textContent = '$0.00';
            nuevaFila.querySelector('.subtotal').textContent = '$0.00';

            select.onchange = function() {
                validarProducto(this);
            };

            tbody.appendChild(nuevaFila);
        }

        function eliminarFila(btn) {
            const fila = btn.closest('tr');
            const select = fila.querySelector('select');

            if (select.value) {
                productosSeleccionados.delete(select.value);
            }

            if (document.querySelectorAll('#detalle-productos tr').length > 1) {
                fila.remove();
                calcularTotal();
            }
        }

        function validarProducto(select) {
            const productoId = select.value;
            const productoNombre = select.selectedOptions[0]?.dataset.nombre || '';

            select.classList.remove('producto-duplicado');

            if (!productoId) {
                if (select.value) {
                    productosSeleccionados.delete(select.value);
                }
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
                select.dispatchEvent(new Event('change'));
                return;
            }

            if (select.value) {
                productosSeleccionados.add(select.value);
            }

            const precio = select.selectedOptions[0]?.dataset.precio || 0;
            const fila = select.closest('tr');
            fila.querySelector('.precio').textContent = '$' + parseFloat(precio).toFixed(2);
            calcularSubtotal(fila);
        }

        function calcularSubtotal(fila) {
            const precio = parseFloat(fila.querySelector('.precio').textContent.replace('$', '')) || 0;
            const cantidad = parseInt(fila.querySelector('input').value) || 0;
            const subtotal = precio * cantidad;
            fila.querySelector('.subtotal').textContent = '$' + subtotal.toFixed(2);
            calcularTotal();
        }

        function calcularTotal() {
            let total = 0;
            document.querySelectorAll('.subtotal').forEach(el => {
                total += parseFloat(el.textContent.replace('$', '')) || 0;
            });
            document.getElementById('total-cotizacion').textContent = '$' + total.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('detalle-productos').addEventListener('input', function(e) {
                if (e.target.name === 'cantidades[]') {
                    calcularSubtotal(e.target.closest('tr'));
                }
            });
        });
    </script>
</body>
</html>