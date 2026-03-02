<?php
include('inc/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['funcion'])) {


    if ($_POST['funcion'] == "Tabla") {
        $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
        $porPagina = 4;
        $inicio = ($pagina - 1) * $porPagina;

        $busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';

        $sql = "SELECT p.id_persona, p.nombre, p.appat, p.apmat, p.telefono, p.direccion, u.nombreusu, u.correo, r.nombre as nombre_rol 
                FROM persona p
                JOIN usuarios u ON p.id_persona = u.id_persona
                JOIN roles r ON u.id_rol = r.id_rol";
        if (!empty($busqueda)) {
            $sql .= " WHERE p.nombre LIKE :busqueda OR p.appat LIKE :busqueda OR p.apmat LIKE :busqueda OR u.nombreusu LIKE :busqueda";
        }
        $sql .= " LIMIT :inicio, :porPagina";

        $stmt = $consulta->prepare($sql);
        if (!empty($busqueda)) {
            $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
        $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        $tabla = "";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tabla .= "<tr>
                <td>" . htmlspecialchars($row['nombre']) . "</td>
                <td>" . htmlspecialchars($row['appat']) . "</td>
                <td>" . htmlspecialchars($row['apmat']) . "</td>
                <td>" . htmlspecialchars($row['telefono']) . "</td>
                <td>" . htmlspecialchars($row['direccion']) . "</td>
                <td>" . htmlspecialchars($row['nombreusu']) . "</td>
                <td>" . htmlspecialchars($row['correo']) . "</td>
                <td>" . htmlspecialchars($row['nombre_rol']) . "</td>
                <td><button class='btn btn-warning editar' idregistros='" . htmlspecialchars($row['id_persona']) . "'>Editar</button> <button class='btn btn-danger eliminar' idregistros='" . htmlspecialchars($row['id_persona']) . "'>Eliminar</button></td>
            </tr>";
        }

        $paginacion = generarPaginacion($busqueda, $pagina, $porPagina);

        echo json_encode(['tabla' => $tabla, 'paginacion' => $paginacion]);
        exit();
    }

    if ($_POST['funcion'] == 'ObtenerDatos') {
        $stmt = $consulta->prepare("SELECT p.*, u.nombreusu, u.correo, u.id_rol, r.nombre as nombre_rol 
                                  FROM persona p 
                                  JOIN usuarios u ON p.id_persona = u.id_persona
                                  JOIN roles r ON u.id_rol = r.id_rol
                                  WHERE p.id_persona=:id_persona");
        $stmt->execute([':id_persona' => $_POST['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row);
        exit();
    }


    if ($_POST['funcion'] == 'Guardar') {
        $nombre = $_POST['nombre'];
        $appat = $_POST['appat'];
        $apmat = $_POST['apmat'];
        $telefono = $_POST['telefono'];
        $direccion = $_POST['direccion'];
        $nombreusu = $_POST['nombreusu'];
        $password = $_POST['password'];
        $correo = $_POST['correo'];
        $id_rol = $_POST['id_rol'];

        if (empty($_POST['nombre']) || empty($_POST['appat']) || empty($_POST['apmat']) || empty($_POST['telefono']) || empty($_POST['direccion']) || empty($_POST['nombreusu']) || empty($_POST['correo']) || empty($_POST['password']) || empty($_POST['id_rol'])) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
            exit();
        }

        if (!filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido.']);
            exit();
        }
        if (!preg_match('/^\d+$/', $telefono)) {
            echo json_encode(['success' => false, 'message' => 'El teléfono debe contener solo números.']);
            exit();
        }



        $consulta->beginTransaction();


        $stmt = $consulta->prepare("INSERT INTO persona (nombre, appat, apmat, telefono, direccion) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $appat, $apmat, $telefono, $direccion]);
        $id_persona = $consulta->lastInsertId();

        $stmt = $consulta->prepare("INSERT INTO usuarios (id_persona, nombreusu, contraseña, correo, id_rol) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_persona, $nombreusu, password_hash($password, PASSWORD_BCRYPT), $correo, $id_rol]);

        $stmt = $consulta->prepare("INSERT INTO clientes (id_persona) VALUES (?)");
        $stmt->execute([$id_persona]);

        $consulta->commit();
        echo json_encode(['success' => true, 'message' => 'Registro guardado correctamente.']);
        exit();
    }

    if ($_POST['funcion'] == 'Editar') {
        if (empty($_POST['nombre']) || empty($_POST['appat']) || empty($_POST['apmat']) || empty($_POST['telefono']) || empty($_POST['direccion']) || empty($_POST['nombreusu']) || empty($_POST['correo']) || empty($_POST['id_rol'])) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
            exit();
        }


        if (!filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido.']);
            exit();
        }


        try {
            $consulta->beginTransaction();

            $stmt = $consulta->prepare("UPDATE persona SET nombre=:nombre, appat=:appat, apmat=:apmat, telefono=:telefono, direccion=:direccion WHERE id_persona=:id_persona");
            $stmt->execute([
                ':nombre' => $_POST['nombre'],
                ':appat' => $_POST['appat'],
                ':apmat' => $_POST['apmat'],
                ':telefono' => $_POST['telefono'],
                ':direccion' => $_POST['direccion'],
                ':id_persona' => $_POST['idregistros']
            ]);

            $stmt = $consulta->prepare("UPDATE usuarios SET nombreusu=:nombreusu, correo=:correo, id_rol=:id_rol WHERE id_persona=:id_persona");
            $stmt->execute([
                ':nombreusu' => $_POST['nombreusu'],
                ':correo' => $_POST['correo'],
                ':id_rol' => $_POST['id_rol'],
                ':id_persona' => $_POST['idregistros']
            ]);

            $consulta->commit();
            echo json_encode(['success' => true, 'message' => 'Registro actualizado correctamente.']);
            exit();
        } catch (Exception $e) {
            $consulta->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
            exit();
        }
    }

    if ($_POST['funcion'] == 'Eliminar') {
        try {
            $consulta->beginTransaction();

            $stmt = $consulta->prepare("DELETE FROM usuarios WHERE id_persona=:id_persona");
            $stmt->execute([':id_persona' => $_POST['idregistros']]);

            $stmt = $consulta->prepare("DELETE FROM persona WHERE id_persona=:id_persona");
            $stmt->execute([':id_persona' => $_POST['idregistros']]);

            $consulta->commit();
            echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente.']);
            exit();
        } catch (Exception $e) {
            $consulta->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
            exit();
        }
    }
}

function generarPaginacion($busqueda, $paginaActual, $porPagina)
{
    global $consulta;
    $sql = "SELECT COUNT(*) as total FROM persona p JOIN usuarios u ON p.id_persona = u.id_persona";
    if (!empty($busqueda)) {
        $sql .= " WHERE p.nombre LIKE :busqueda OR p.appat LIKE :busqueda OR p.apmat LIKE :busqueda OR u.nombreusu LIKE :busqueda";
    }
    $stmt = $consulta->prepare($sql);
    if (!empty($busqueda)) {
        $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
    }
    $stmt->execute();
    $totalRegistros = $stmt->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $porPagina);

    $paginacion = '';
    $rango = 1;

    if ($totalPaginas > 1) {
        if ($paginaActual > 1 + $rango) {
            $paginacion .= "<li class='page-item'><a class='page-link' href='#' data-pagina='1'>Inicio</a></li>";
            $paginacion .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }

        for ($i = max(1, $paginaActual - $rango); $i < $paginaActual; $i++) {
            $paginacion .= "<li class='page-item'><a class='page-link' href='#' data-pagina='$i'>$i</a></li>";
        }

        $paginacion .= "<li class='page-item active'><span class='page-link'>$paginaActual</span></li>";

        for ($i = $paginaActual + 1; $i <= min($totalPaginas, $paginaActual + $rango); $i++) {
            $paginacion .= "<li class='page-item'><a class='page-link' href='#' data-pagina='$i'>$i</a></li>";
        }

        if ($paginaActual < $totalPaginas - $rango) {
            $paginacion .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
            $paginacion .= "<li class='page-item'><a class='page-link' href='#' data-pagina='$totalPaginas'>Final</a></li>";
        }
    }

    return $paginacion;
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personas y Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs/build/alertify.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs/build/css/alertify.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs/build/css/themes/default.min.css" />
    <link rel="stylesheet" href="producto.css">
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-10 text-center">
                <h1>Personas y Usuarios</h1>
            </div>
            <div class="col-2 text-center">
                <button class="btn btn-success" id="nuevo" data-bs-toggle="modal" data-bs-target="#modal"> Nuevo </button>
            </div>
            <div class="col-12 text-center">
                <div class="col-5 text-center">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="buscador" placeholder="Buscar...">
                        <button class="btn btn-outline-secondary" type="button" id="btnBuscar">Buscar</button>
                    </div>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Apellido Paterno</th>
                            <th scope="col">Apellido Materno</th>
                            <th scope="col">Teléfono</th>
                            <th scope="col">Dirección</th>
                            <th scope="col">Nombre de Usuario</th>
                            <th scope="col">Correo</th>
                            <th scope="col">Rol</th>
                            <th scope="col">Opciones</th>
                        </tr>
                    </thead>
                    <tbody id="resultados_productos"></tbody>
                </table>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center" id="paginacion"></ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" id="modalGuardar">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-body-guardar">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <b for="nombre">Nombre</b>
                                <input type="text" class="form-control" id="nombre" name="nombre">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="appat">Apellido Paterno</b>
                                <input type="text" class="form-control" id="appat" name="appat">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="apmat">Apellido Materno</b>
                                <input type="text" class="form-control" id="apmat" name="apmat">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="telefono">Teléfono</b>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="direccion">Dirección</b>
                                <input type="text" class="form-control" id="direccion" name="direccion">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="nombreusu">Nombre de Usuario</b>
                                <input type="text" class="form-control" id="nombreusu" name="nombreusu">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="correo">Correo</b>
                                <input type="text" class="form-control" id="correo" name="correo">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="password">Contraseña</b>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="id_rol">Rol</b>
                                <select class="form-control" id="id_rol" name="id_rol">
                                    <?php
                                    $stmt = $consulta->query("SELECT id_rol, nombre FROM roles  WHERE id_rol=1 OR id_rol=2 OR id_rol=3");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $row['id_rol'] . "'>" . htmlspecialchars($row['nombre']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="Guardar_Nuevo">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" id="modalEditar">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-body-editar">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <b for="nombre_editar">Nombre</b>
                                <input type="text" class="form-control" id="nombre_editar" name="nombre_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="appat_editar">Apellido Paterno</b>
                                <input type="text" class="form-control" id="appat_editar" name="appat_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="apmat_editar">Apellido Materno</b>
                                <input type="text" class="form-control" id="apmat_editar" name="apmat_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="telefono_editar">Teléfono</b>
                                <input type="text" class="form-control" id="telefono_editar" name="telefono_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="direccion_editar">Dirección</b>
                                <input type="text" class="form-control" id="direccion_editar" name="direccion_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="nombreusu_editar">Nombre de Usuario</b>
                                <input type="text" class="form-control" id="nombreusu_editar" name="nombreusu_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="correo_editar">Correo</b>
                                <input type="text" class="form-control" id="correo_editar" name="correo_editar">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <b for="id_rol_editar">Rol</b>
                                <select class="form-control" id="id_rol_editar" name="id_rol_editar">
                                    <?php
                                    $stmt = $consulta->query("SELECT id_rol, nombre FROM roles  WHERE id_rol=1 OR id_rol=2 OR id_rol=3");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $row['id_rol'] . "'>" . htmlspecialchars($row['nombre']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="Guardar_Edita">Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            let paginaActual = 1;
            let busqueda = '';

            function cargarTabla() {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        funcion: 'Tabla',
                        pagina: paginaActual,
                        busqueda: busqueda
                    },
                    success: function(response) {
                        let data = JSON.parse(response);
                        $('#resultados_productos').html(data.tabla);
                        $('#paginacion').html(data.paginacion);
                    }
                });
            }

            cargarTabla();

            $('#btnBuscar').click(function() {
                busqueda = $('#buscador').val();
                paginaActual = 1;
                cargarTabla();
            });

            $(document).on('click', '.page-link', function(e) {
                e.preventDefault();
                paginaActual = $(this).data('pagina');
                cargarTabla();
            });

            $('#nuevo').click(function() {
                $('#modalGuardar').modal('show');
            });

            $(document).on('click', '.editar', function() {
                var idregistros = $(this).attr('idregistros');
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        funcion: 'ObtenerDatos',
                        id: idregistros
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        $('#nombre_editar').val(data.nombre);
                        $('#appat_editar').val(data.appat);
                        $('#apmat_editar').val(data.apmat);
                        $('#telefono_editar').val(data.telefono);
                        $('#direccion_editar').val(data.direccion);
                        $('#nombreusu_editar').val(data.nombreusu);
                        $('#correo_editar').val(data.correo);
                        $('#id_rol_editar').val(data.id_rol);
                        $('#Guardar_Edita').attr('idregistros', idregistros);
                        $('#modalEditar').modal('show');
                    }
                });
            });

            $('#Guardar_Nuevo').click(function() {
                if ($('#nombre').val() == "" || $('#appat').val() == "" || $('#apmat').val() == "" || $('#telefono').val() == "" || $('#direccion').val() == "" || $('#nombreusu').val() == "" || $('#correo').val() == "" || $('#password').val() == "" || $('#id_rol').val() == "") {
                    alertify.warning('Todos los campos son obligatorios.');
                    return false;
                }

                if (!/^\d+$/.test($('#telefono').val())) {
                    alertify.warning('El teléfono debe contener solo números.');
                    return false;
                }

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        funcion: 'Guardar',
                        nombre: $('#nombre').val(),
                        appat: $('#appat').val(),
                        apmat: $('#apmat').val(),
                        telefono: $('#telefono').val(),
                        direccion: $('#direccion').val(),
                        nombreusu: $('#nombreusu').val(),
                        correo: $('#correo').val(),
                        password: $('#password').val(),
                        id_rol: $('#id_rol').val()
                    },
                    success: function(response) {
                        let data = JSON.parse(response);
                        if (data.success) {
                            alertify.success(data.message);
                            cargarTabla();
                            $('#modalGuardar').modal('hide');
                            $('#formGuardar')[0].reset();
                        } else {
                            alertify.error(data.message);
                        }
                    }
                });
            });


            $('#Guardar_Edita').click(function() {
                var idregistros = $(this).attr('idregistros');
                if ($('#nombre_editar').val() == "" || $('#appat_editar').val() == "" || $('#apmat_editar').val() == "" || $('#telefono_editar').val() == "" || $('#direccion_editar').val() == "" || $('#nombreusu_editar').val() == "" || $('#correo_editar').val() == "" || $('#id_rol_editar').val() == "") {
                    alertify.warning('Todos los campos son obligatorios.');
                    return false;
                }
                if (!/^\d+$/.test($('#telefono_editar').val())) {
                    alertify.warning('El teléfono debe contener solo números.');
                    return false;
                }

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        funcion: 'Editar',
                        nombre: $('#nombre_editar').val(),
                        appat: $('#appat_editar').val(),
                        apmat: $('#apmat_editar').val(),
                        telefono: $('#telefono_editar').val(),
                        direccion: $('#direccion_editar').val(),
                        nombreusu: $('#nombreusu_editar').val(),
                        correo: $('#correo_editar').val(),
                        id_rol: $('#id_rol_editar').val(),
                        idregistros: idregistros
                    },
                    success: function(response) {
                        let data = JSON.parse(response);
                        if (data.success) {
                            alertify.success(data.message);
                            cargarTabla();
                            $('#modalEditar').modal('hide');
                        } else {
                            alertify.error(data.message);
                        }
                    }
                });
            });

            $(document).on('click', '.eliminar', function() {
                var idregistros = $(this).attr('idregistros');
                if (confirm('¿Desea eliminar este registro?')) {
                    $.ajax({
                        url: '',
                        type: 'POST',
                        data: {
                            funcion: 'Eliminar',
                            idregistros: idregistros
                        },
                        success: function(response) {
                            let data = JSON.parse(response);
                            if (data.success) {
                                alertify.success(data.message);
                                cargarTabla();
                            } else {
                                alertify.error(data.message);
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>