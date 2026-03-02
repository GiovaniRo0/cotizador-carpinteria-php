<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cotizaciones - Inicio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs/build/alertify.min.js"></script>
    <style>
        .hero-section {
            background-color: #4299E1;
            color: white;
            padding: 50px 20px;
            text-align: center;
        }

        .feature-card {
            text-align: center;
            padding: 20px;
        }

        .logout-btn {
            margin-top: 10px;
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="hero-section">
        <h1>Bienvenido, <?php echo $_SESSION['nombreusu']; ?>!</h1>
        <p>Gestione sus cotizaciones de manera rápida, segura y eficiente.</p>
    </div>

    <div class="container mt-5">
        <h2 class="text-center mb-4">¿Qué puedes hacer en este sistema?</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="card feature-card">
                    <h5>📋 Crear Cotizaciones</h5>
                    <p>Genere presupuestos precisos en segundos con cálculos automáticos.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card">
                    <h5>📂 Exportar en PDF</h5>
                    <p>Descargue y comparta cotizaciones en formato profesional.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card">
                    <h5>📊 Historial</h5>
                    <p>Revise cotizaciones pasadas y edite sus cotizaciones.</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-5 py-3 bg-light">
        <p>© 2025 Sistema de Cotizaciones | Desarrollado por Giovani Romo</p>
    </footer>

</body>

</html>