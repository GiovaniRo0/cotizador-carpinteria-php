<?php
include('inc/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['funcion'])) {
    if ($_POST['funcion'] == "Tabla") {
        $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
        $porPagina = 7;
        $inicio = ($pagina - 1) * $porPagina;
    
        $busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';
    
        $sql = "SELECT m.id_material, m.nombre, m.precio, c.nombre as nombre_categoria 
                FROM materiales m 
                JOIN cat_material c ON m.id_categoria = c.id_categoria";
    
        $sqlCount = "SELECT COUNT(*) as total FROM materiales m 
                    JOIN cat_material c ON m.id_categoria = c.id_categoria";
    
        if (!empty($busqueda)) {
            $sql .= " WHERE m.nombre LIKE :busqueda OR m.precio LIKE :busqueda OR c.nombre LIKE :busqueda";
            $sqlCount .= " WHERE m.nombre LIKE :busqueda OR m.precio LIKE :busqueda OR c.nombre LIKE :busqueda";
        }
        $sql .= " ORDER BY m.id_material ASC LIMIT :inicio, :porPagina";
    
        $stmt = $consulta->prepare($sql);
        if (!empty($busqueda)) {
            $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
        $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
        $stmt->execute();
        $materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $tabla = "";
        foreach ($materiales as $material) {
            $tabla .= "<tr>
                <td>" . htmlspecialchars($material['nombre']) . "</td>
                <td>" . htmlspecialchars($material['precio']) . "</td>
                <td>" . htmlspecialchars($material['nombre_categoria']) . "</td>
                <td><button class='btn btn-warning editar' idregistros='" . htmlspecialchars($material['id_material']) . "'>Editar</button> 
                <button class='btn btn-danger eliminar' idregistros='" . htmlspecialchars($material['id_material']) . "'>Eliminar</button></td>
            </tr>";
        }
    
        $stmtCount = $consulta->prepare($sqlCount);
        if (!empty($busqueda)) {
            $stmtCount->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalRegistros = $stmtCount->fetchColumn();
        $totalPaginas = ceil($totalRegistros / $porPagina);
    
        $paginacion = '';
        $rango = 1; 
        
        if ($totalPaginas > 1) {
            if ($pagina > 1 + $rango)  {
                $paginacion .= "<li class='page-item'><a class='page-link' href='#' data-pagina='1'>Inicio</a></li>";
                
            }
            
            for ($i = max(1, $pagina - $rango); $i <= min($totalPaginas, $pagina + $rango); $i++) {
                $active = ($i == $pagina) ? 'active' : '';
                $paginacion .= "<li class='page-item $active'><a class='page-link' href='#' data-pagina='$i'>$i</a></li>";
            }
            
            if ($pagina < $totalPaginas - $rango) {
                $paginacion .= "<li class='page-item'><a class='page-link' href='#' data-pagina='$totalPaginas'>Final</a></li>";
            }
        }
    
        echo json_encode(['tabla' => $tabla, 'paginacion' => $paginacion]);
        exit();
    }

    if ($_POST['funcion'] == 'Guardar') {
        $stmt = $consulta->prepare("INSERT INTO materiales (id_categoria, nombre, precio) VALUES (:id_categoria, :nombre, :precio)");
        $stmt->execute([
            ':id_categoria' => $_POST['id_categoria'],
            ':nombre' => $_POST['nombre'],
            ':precio' => $_POST['precio']
        ]);
        exit();
    }

    if ($_POST['funcion'] == 'Editar') {
        $stmt = $consulta->prepare("UPDATE materiales SET id_categoria=:id_categoria, nombre=:nombre, precio=:precio WHERE id_material=:id_material");
        $stmt->execute([
            ':id_categoria' => $_POST['id_categoria'],
            ':nombre' => $_POST['nombre'],
            ':precio' => $_POST['precio'],
            ':id_material' => $_POST['idregistros']
        ]);
        exit();
    }

    if ($_POST['funcion'] == 'Eliminar') {
        $stmt = $consulta->prepare("DELETE FROM materiales WHERE id_material=:id_material");
        $stmt->execute([':id_material' => $_POST['idregistros']]);
        exit();
    }

    if ($_POST['funcion'] == "Modal") {
        $categorias = $consulta->query("SELECT * FROM cat_material")->fetchAll(PDO::FETCH_ASSOC);

        $row = [];
        if ($_POST['tipo'] == "Editar") {
            $stmt = $consulta->prepare("SELECT m.*, c.nombre as nombre_categoria 
                                     FROM materiales m 
                                     JOIN cat_material c ON m.id_categoria = c.id_categoria 
                                     WHERE m.id_material=:id_material");
            $stmt->execute([':id_material' => $_POST['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $options = "";
        foreach ($categorias as $categoria) {
            $selected = (isset($row['id_categoria']) && $row['id_categoria'] == $categoria['id_categoria']) ? 'selected' : '';
            $options .= "<option value='" . $categoria['id_categoria'] . "' $selected>" . htmlspecialchars($categoria['nombre']) . "</option>";
        }

        $modal = "
        <div class='row'>
            <div class='col-6'>
                <div class='form-group'>
                    <b for='nombre'>Nombre</b>
                    <input type='text' class='form-control' id='nombre' value='" . htmlspecialchars($row['nombre'] ?? '') . "' name='nombre'>
                </div>
            </div>
            <div class='col-3'>
                <div class='form-group'>
                    <b for='precio'>Precio</b>
                    <input type='text' class='form-control' id='precio' value='" . htmlspecialchars($row['precio'] ?? '') . "' name='precio'>
                </div>
            </div>
            <div class='col-5'>
                <div class='form-group'>
                    <b for='id_categoria'>Categoría</b>
                    <select class='form-control' id='id_categoria' name='id_categoria'>
                        <option value=''>Seleccione categoría</option>
                        $options
                    </select>
                </div>
            </div>
        </div>";
        echo $modal;
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs/build/alertify.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs/build/css/alertify.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs/build/css/themes/default.min.css" />
    <link rel="stylesheet" href="producto.css">
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="modal" tabindex="-1" id="modal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo_modal">Alta de Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary invisible" id="Guardar_Edita">Editar Material</button>
                    <button type="button" class="btn btn-primary" id="Guardar_Nuevo">Agregar Material</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-10 text-center">
                <h1>Materiales</h1>
            </div>
            <div class="col-2 text-center">
                <button class="btn btn-success" id="nuevo" data-bs-toggle="modal" data-bs-target="#modal"> Nuevo </button>
            </div>
            <div class="col-12 text-center">
                <div class="col-5 text-center">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="buscador" placeholder="Buscar material...">
                        <button class="btn btn-outline-secondary" type="button" id="btnBuscar">Buscar</button>
                    </div>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Precio</th>
                            <th scope="col">Categoría</th>
                            <th scope="col">Opciones</th>
                        </tr>
                    </thead>
                    <tbody id="resultados_materiales"></tbody>
                </table>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center" id="paginacion"></ul>
                </nav>
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
                $('#resultados_materiales').html(data.tabla);
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

    $(document).on("click", "#Guardar_Nuevo", function() {
        if ($("#nombre").val() == "") {
            alertify.warning('El campo nombre es obligatorio');
            $("#nombre").focus();
            return false;
        }
        if ($("#precio").val() == "") {
            alertify.warning('El campo Precio es obligatorio');
            $("#precio").focus();
            return false;
        }
        if ($("#id_categoria").val() == "") {
            alertify.warning('El campo Categoría es obligatorio');
            $("#id_categoria").focus();
            return false;
        }
        
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                funcion: 'Guardar',
                nombre: $("#nombre").val(),
                precio: $("#precio").val(),
                id_categoria: $("#id_categoria").val()
            },
            success: function(response) {
                cargarTabla();
                $('#modal').modal('hide');
                alertify.success('Material registrado');
            }
        });
    });

    $(document).on("click", "#Guardar_Edita", function() {
        var idregistros = $(this).attr('idregistros');
        if ($("#nombre").val() == "") {
            alertify.warning('Nombre obligatorio');
            $("#nombre").focus();
            return false;
        }
        if ($("#precio").val() == "") {
            alertify.warning('El campo Precio es obligatorio');
            $("#precio").focus();
            return false;
        }
        if ($("#id_categoria").val() == "") {
            alertify.warning('El campo Categoría es obligatorio');
            $("#id_categoria").focus();
            return false;
        }
        
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                funcion: 'Editar',
                nombre: $("#nombre").val(),
                precio: $("#precio").val(),
                id_categoria: $("#id_categoria").val(),
                idregistros: idregistros
            },
            success: function(response) {
                cargarTabla();
                $('#modal').modal('hide');
                alertify.success('Material editado');
            }
        });
    });

    $(document).on("click", "#nuevo", function() {
        Modal("Nuevo", 0);
        $("#Guardar_Nuevo").removeClass('invisible').show();
        $("#Guardar_Edita").hide();
        $("#titulo_modal").text("Alta de Material");
    });

    $(document).on("click", ".editar", function() {
        var idregistros = $(this).attr('idregistros');
        Modal("Editar", idregistros);
        $("#Guardar_Nuevo").hide();
        $("#Guardar_Edita").show().removeClass('invisible').attr('idregistros', idregistros);
        $("#titulo_modal").text("Editar Material");
        $('#modal').modal('show');
    });

    $(document).on("click", ".eliminar", function() {
        var idregistros = $(this).attr('idregistros');
        alertify.confirm("Eliminar Material", 
            "¿Está seguro que desea eliminar este material?", 
            function() {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        funcion: 'Eliminar',
                        idregistros: idregistros
                    },
                    success: function(response) {
                        cargarTabla();
                        alertify.success('Material eliminado');
                    }
                });
            }, 
            function() {
                alertify.error('Cancelado');
            }
        );
    });

    function Modal(tipo, id) {
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                funcion: 'Modal',
                tipo: tipo,
                id: id
            },
            success: function(response) {
                $('#modal-body').html(response);
            }
        });
    }
});
    </script>
</body>

</html>