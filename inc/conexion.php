<?php
date_default_timezone_set('America/Mexico_City');
$usuario = "root";
$contrasena = "1234";

;
try {
    $options = [
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, 
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];
    
    $consulta= new PDO('mysql:host=localhost;dbname=cotizador', $usuario, $contrasena, $options);
    $consulta->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $consulta->prepare("ALTER TABLE bitacora_presupuestos ALTER COLUMN id_usuario SET DEFAULT 0");
    $stmt->execute();
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
