<?php
require('fpdf/fpdf.php');
include('inc/conexion.php');

if (!isset($_GET['id'])) die("ID de presupuesto no especificado");

$id_presupuesto = $_GET['id'];

try {
    $stmt = $consulta->prepare("
        SELECT p.*, CONCAT(per.nombre, ' ', per.appat, ' ', per.apmat) as cliente,
               per.telefono, per.direccion
        FROM presupuestos p
        JOIN clientes c ON p.id_cliente = c.id_cliente
        JOIN persona per ON c.id_persona = per.id_persona
        WHERE p.id_presupuesto = ? AND p.activo = 1
    ");
    $stmt->execute([$id_presupuesto]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        die("Presupuesto no encontrado o ha sido eliminado");
    }

    $stmt = $consulta->prepare("
        SELECT dp.*, pr.nombre as producto, pr.precio as precio_real,
               m.nombre as material
        FROM detalle_pres dp
        JOIN producto pr ON dp.id_producto = pr.id_producto
        JOIN materiales m ON pr.id_material = m.id_material
        WHERE dp.id_presupuesto = ?
        ORDER BY dp.id_detalle
    ");
    $stmt->execute([$id_presupuesto]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    $pdf->Cell(0, 10, utf8_decode('Presupuesto #') . $id_presupuesto, 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 7, utf8_decode('Rústicos Romo'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, utf8_decode('Dirección: Rosa amarilla 14'), 0, 1);
    $pdf->Cell(0, 5, utf8_decode('Teléfono: 55-1234-5678'), 0, 1);
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 7, utf8_decode('Datos del cliente:'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, utf8_decode('Nombre: ') . utf8_decode($presupuesto['cliente']), 0, 1);
    if (!empty($presupuesto['direccion'])) {
        $pdf->Cell(0, 5, utf8_decode('Dirección: ') . utf8_decode($presupuesto['direccion']), 0, 1);
    }
    if (!empty($presupuesto['telefono'])) {
        $pdf->Cell(0, 5, utf8_decode('Teléfono: ') . utf8_decode($presupuesto['telefono']), 0, 1);
    }
    $pdf->Cell(0, 5, utf8_decode('Fecha: ') . date('d/m/Y', strtotime($presupuesto['fecha'])), 0, 1);
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 7, 'Producto', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(30, 7, 'P. Unitario', 1, 0, 'C');
    $pdf->Cell(35, 7, 'Subtotal', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 9);
    foreach ($detalles as $d) {
        $pdf->Cell(100, 7, utf8_decode($d['producto'] . ' (' . $d['material'] . ')'), 1);
        $pdf->Cell(25, 7, $d['cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 7, '$' . number_format($d['precio_real'], 2), 1, 0, 'R');
        $pdf->Cell(35, 7, '$' . number_format($d['subtotal'], 2), 1, 1, 'R');
    }

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(155, 10, 'TOTAL:', 1, 0, 'R');
    $pdf->Cell(35, 10, '$' . number_format($presupuesto['total'], 2), 1, 1, 'R');

    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, utf8_decode('Este presupuesto es válido por 30 días a partir de la fecha de emisión'), 0, 1, 'C');

    $pdf->Output('D', "presupuesto_$id_presupuesto.pdf");

} catch (PDOException $e) {
    die("Error al generar PDF: " . $e->getMessage());
}