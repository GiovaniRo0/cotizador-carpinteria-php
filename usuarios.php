<?php
include('inc/conexion.php');



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $appat = trim($_POST['appat']);
    $apmat = trim($_POST['apmat']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
   

    if ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico inválido.";
    }else {
        $password_hashed = password_hash($password, PASSWORD_BCRYPT);

        try {
            $consulta->beginTransaction();
            
            $stmt = $consulta->prepare("CALL sp_insertar_persona(?, ?, ?, ?, ?, @id_persona)");
            $stmt->execute([$nombre, $appat, $apmat, $telefono, $direccion]);
            
            $stmt = $consulta->query("SELECT @id_persona AS id");
            $result = $stmt->fetch();
            $id_persona = $result['id'];
            
            $stmt = $consulta->prepare("CALL sp_insertar_usuario(?, ?, ?, ?)");
            $stmt->execute([$id_persona, $username, $password_hashed, $email]);
            
            $stmt = $consulta->prepare("CALL sp_insertar_cliente(?)");
            $stmt->execute([$id_persona]);
            
            $consulta->commit();
        } catch (Exception $e) {
            $consulta->rollBack();
            throw $e;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="usuarios.css">
    <script src="https://kit.fontawesome.com/aef5e5a3c7.js" crossorigin="anonymous"></script>
</head>

<body>
    <div class="container">
        <div class="container-glass">
            <h2 class="text-center mb-4">Registro de Usuario</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success"><?= $mensaje ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-section">
                    <h4 class="text-white"><i class="fas fa-user-circle me-2"></i>Datos Personales</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Nombre</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ingrese su nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Apellido Paterno</label>
                            <input type="text" name="appat" class="form-control" placeholder="Ingrese su apellido paterno" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Apellido Materno</label>
                            <input type="text" name="apmat" class="form-control" placeholder="Ingrese su apellido materno" required> 
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" placeholder="Ingrese su teléfono" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white-50">Dirección</label>
                        <input type="text" name="direccion" class="form-control" placeholder="Ingrese su dirección" required>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="text-white"><i class="fas fa-user-shield me-2"></i>Datos de Usuario</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Nombre de Usuario</label>
                            <input type="text" name="username" class="form-control" placeholder="Cree un nombre de usuario" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" placeholder="Ingrese su correo" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Contraseña</label>
                            <input type="password" name="password" class="form-control" placeholder="Cree una contraseña" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-white-50">Confirmar Contraseña</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repita la contraseña" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-croma btn-block">
                    <i class="fas fa-user-plus me-2"></i>Registrarse
                </button>
                
                <div class="text-center mt-3">
                    <p class="text-white-50">¿Ya tienes una cuenta? <a href="login.php" class="text-white">Inicia sesión</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

</html>
