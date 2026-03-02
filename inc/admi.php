<?php
include('inc/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['funcion'])) {
    if ($_POST['funcion'] == "Tabla") {
        $stmt = $consulta->prepare("
            SELECT u.id_usuario, u.id_persona, u.id_rol, u.nombreusu, u.correo, p.nombre, p.appat, p.apmat, p.telefono, p.direccion 
            FROM usuarios u 
            JOIN persona p ON u.id_persona = p.id_persona 
            LIMIT 5
        ");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tabla = "";
        foreach ($usuarios as $usuario) {
            $tabla .= "<tr>
                <td>" . htmlspecialchars($usuario['id_usuario']) . "</td>
                <td>" . htmlspecialchars($usuario['id_persona']) . "</td>
                <td>" . htmlspecialchars($usuario['id_rol']) . "</td>
                <td>" . htmlspecialchars($usuario['nombreusu']) . "</td>
                <td>" . htmlspecialchars($usuario['correo']) . "</td>
                <td>" . htmlspecialchars($usuario['nombre']) . " " . htmlspecialchars($usuario['appat']) . " " . htmlspecialchars($usuario['apmat']) . "</td>
                <td>" . htmlspecialchars($usuario['telefono']) . "</td>
                <td>" . htmlspecialchars($usuario['direccion']) . "</td>
                <td><button class='btn btn-warning editar' idregistros='" . htmlspecialchars($usuario['id_usuario']) . "'>Editar</button> <button class='btn btn-danger eliminar' idregistros='" . htmlspecialchars($usuario['id_usuario']) . "'>Eliminar</button></td>
            </tr>";
        }
        echo $tabla;
        exit();
    }

    if ($_POST['funcion'] == 'Guardar') {
        $contraseña_hash = password_hash($_POST['contraseña'], PASSWORD_DEFAULT);
        
        $stmt = $consulta->prepare("INSERT INTO persona (nombre, appat, apmat, telefono, direccion) VALUES (:nombre, :appat, :apmat, :telefono, :direccion)");
        $stmt->execute([
            ':nombre' => $_POST['nombre'],
            ':appat' => $_POST['appat'],
            ':apmat' => $_POST['apmat'],
            ':telefono' => $_POST['telefono'],
            ':direccion' => $_POST['direccion']
        ]);
        
        $id_persona = $consulta->lastInsertId();
        
        $stmt = $consulta->prepare("INSERT INTO usuarios (id_persona, id_rol, nombreusu, contraseña, correo) VALUES (:id_persona, :id_rol, :nombreusu, :contraseña, :correo)");
        $stmt->execute([
            ':id_persona' => $id_persona,
            ':id_rol' => $_POST['id_rol'],
            ':nombreusu' => $_POST['nombreusu'],
            ':contraseña' => $contraseña_hash,
            ':correo' => $_POST['correo']
        ]);
        exit();
    }
    
    if ($_POST['funcion'] == 'Editar') {
        $contraseña_hash = password_hash($_POST['contraseña'], PASSWORD_DEFAULT);
        
        $stmt = $consulta->prepare("UPDATE persona SET nombre=:nombre, appat=:appat, apmat=:apmat, telefono=:telefono, direccion=:direccion WHERE id_persona=:id_persona");
        $stmt->execute([
            ':nombre' => $_POST['nombre'],
            ':appat' => $_POST['appat'],
            ':apmat' => $_POST['apmat'],
            ':telefono' => $_POST['telefono'],
            ':direccion' => $_POST['direccion'],
            ':id_persona' => $_POST['id_persona']
        ]);
        
        // Actualizar la tabla `usuarios`
        $stmt = $consulta->prepare("UPDATE usuarios SET id_rol=:id_rol, nombreusu=:nombreusu, contraseña=:contraseña, correo=:correo WHERE id_usuario=:id_usuario");
        $stmt->execute([
            ':id_rol' => $_POST['id_rol'],
            ':nombreusu' => $_POST['nombreusu'],
            ':contraseña' => $contraseña_hash,
            ':correo' => $_POST['correo'],
            ':id_usuario' => $_POST['idregistros']
        ]);
        exit();
    }
    
    if ($_POST['funcion'] == 'Eliminar') {
        // Eliminar de la tabla `usuarios`
        $stmt = $consulta->prepare("DELETE FROM usuarios WHERE id_usuario=:id_usuario");
        $stmt->execute([':id_usuario' => $_POST['idregistros']]);
        
        // Eliminar de la tabla `persona`
        $stmt = $consulta->prepare("DELETE FROM persona WHERE id_persona=:id_persona");
        $stmt->execute([':id_persona' => $_POST['id_persona']]);
        exit();
    }

    if ($_POST['funcion'] == "Modal") {
        $row = [];
        if ($_POST['tipo'] == "Editar") {
            // Obtener datos de ambas tablas
            $stmt = $consulta->prepare("
                SELECT u.*, p.nombre, p.appat, p.apmat, p.telefono, p.direccion 
                FROM usuarios u 
                JOIN persona p ON u.id_persona = p.id_persona 
                WHERE u.id_usuario=:id_usuario
            ");
            $stmt->execute([':id_usuario' => $_POST['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $modal = "
        <div class='row'>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='nombre'>Nombre</b>
                    <input type='text' class='form-control' id='nombre' value='" . htmlspecialchars($row['nombre']) . "' name='nombre'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='appat'>Apellido Paterno</b>
                    <input type='text' class='form-control' id='appat' value='" . htmlspecialchars($row['appat']) . "' name='appat'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='apmat'>Apellido Materno</b>
                    <input type='text' class='form-control' id='apmat' value='" . htmlspecialchars($row['apmat']) . "' name='apmat'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='telefono'>Teléfono</b>
                    <input type='text' class='form-control' id='telefono' value='" . htmlspecialchars($row['telefono']) . "' name='telefono'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='direccion'>Dirección</b>
                    <input type='text' class='form-control' id='direccion' value='" . htmlspecialchars($row['direccion']) . "' name='direccion'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='id_rol'>ID Rol</b>
                    <input type='text' class='form-control' id='id_rol' value='" . htmlspecialchars($row['id_rol']) . "' name='id_rol'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='nombreusu'>Nombre de Usuario</b>
                    <input type='text' class='form-control' id='nombreusu' value='" . htmlspecialchars($row['nombreusu']) . "' name='nombreusu'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='contraseña'>Contraseña</b>
                    <input type='password' class='form-control' id='contraseña' value='' name='contraseña'>
                </div>
            </div>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='correo'>Correo</b>
                    <input type='email' class='form-control' id='correo' value='" . htmlspecialchars($row['correo']) . "' name='correo'>
                </div>
            </div>
        </div>";
        echo $modal;
        exit();
    }
}
?>