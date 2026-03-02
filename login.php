<?php
session_start();
include('inc/conexion.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $consulta->prepare("SELECT id_usuario, nombreusu, contraseña FROM usuarios WHERE nombreusu = ?");
    $stmt->execute([$usuario]);
    $datos_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($datos_usuario && password_verify($password, $datos_usuario['contraseña'])) {
        $_SESSION['id_usuario'] = $datos_usuario['id_usuario'];
        $_SESSION['nombreusu'] = $datos_usuario['nombreusu'];
        header("Location: paginainicio.php");
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
    
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="loginestilos.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.7.1.min.js"></script>
</head>

<body>
<div class="login-container">
    <div class="login-form">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">
            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
            <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>
        </svg>
        <h2>Inicio de sesión</h2>

        <form method="POST" action="">
            <div class="input-group">
                <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Usuario" required>
            </div>
            <div class="input-group">
                <input type="password" id="password" name="password" class="form-control" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Iniciar sesión</button>
        </form>

        <?php if (isset($error)): ?>
            <p style="color: red;"><?= $error; ?></p>
        <?php endif; ?>

        <div class="create-account">
            <a href="usuarios.php">Crear usuario</a>
        </div>
    </div>
</div>
</body>
</html>
