<?php
include('inc/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['funcion'])) {
    if ($_POST['funcion'] == "Tabla") {
        $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
        $porPagina = 7;
        $inicio = ($pagina - 1) * $porPagina;
    
        $busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';
    
        $sql = "SELECT p.id_producto, p.nombre, p.precio, m.nombre as nombre_material 
                FROM producto p 
                JOIN materiales m ON p.id_material = m.id_material";
    
        $sqlCount = "SELECT COUNT(*) as total FROM producto p 
                    JOIN materiales m ON p.id_material = m.id_material";
    
        if (!empty($busqueda)) {
            $sql .= " WHERE p.nombre LIKE :busqueda OR p.precio LIKE :busqueda OR m.nombre LIKE :busqueda";
            $sqlCount .= " WHERE p.nombre LIKE :busqueda OR p.precio LIKE :busqueda OR m.nombre LIKE :busqueda";
        }
        $sql .= " ORDER BY p.id_producto ASC LIMIT :inicio, :porPagina";
    
        $stmt = $consulta->prepare($sql);
        if (!empty($busqueda)) {
            $stmt->bindValue(':busqueda', "%$busqueda%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
        $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $tabla = "";
        foreach ($productos as $producto) {
            $tabla .= "<tr>
                <td>" . htmlspecialchars($producto['nombre']) . "</td>
                <td>" . htmlspecialchars($producto['precio']) . "</td>
                <td>" . htmlspecialchars($producto['nombre_material']) . "</td>
                <td><button class='btn btn-warning editar' idregistros='" . htmlspecialchars($producto['id_producto']) . "'>Editar</button> 
                <button class='btn btn-danger eliminar' idregistros='" . htmlspecialchars($producto['id_producto']) . "'>Eliminar</button></td>
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
        $stmt = $consulta->prepare("INSERT INTO producto (id_material, nombre, precio) VALUES (:id_material, :nombre, :precio)");
        $stmt->execute([
            ':id_material' => $_POST['id_material'],
            ':nombre' => $_POST['nombre'],
            ':precio' => $_POST['precio']
        ]);
        exit();
    }

    if ($_POST['funcion'] == 'Editar') {
        $stmt = $consulta->prepare("UPDATE producto SET id_material=:id_material, nombre=:nombre, precio=:precio WHERE id_producto=:id_producto");
        $stmt->execute([
            ':id_material' => $_POST['id_material'],
            ':nombre' => $_POST['nombre'],
            ':precio' => $_POST['precio'],
            ':id_producto' => $_POST['idregistros']
        ]);
        exit();
    }

    if ($_POST['funcion'] == 'Eliminar') {
        $stmt = $consulta->prepare("DELETE FROM producto WHERE id_producto=:id_producto");
        $stmt->execute([':id_producto' => $_POST['idregistros']]);
        exit();
    }

    if ($_POST['funcion'] == "Modal") {
        $materiales = $consulta->query("SELECT * FROM materiales")->fetchAll(PDO::FETCH_ASSOC);

        $row = [];
        if ($_POST['tipo'] == "Editar") {
            $stmt = $consulta->prepare("SELECT p.*, m.nombre as nombre_material 
                                     FROM producto p 
                                     JOIN materiales m ON p.id_material = m.id_material 
                                     WHERE p.id_producto=:id_producto");
            $stmt->execute([':id_producto' => $_POST['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $options = "";
        foreach ($materiales as $material) {
            $selected = (isset($row['id_material']) && $row['id_material'] == $material['id_material']) ? 'selected' : '';
            $options .= "<option value='" . $material['id_material'] . "' $selected>" . htmlspecialchars($material['nombre']) . "</option>";
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
                    <b for='id_material'>Material</b>
                    <select class='form-control' id='id_material' name='id_material'>
                        <option value=''>Seleccione material</option>
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
    <title>Productos</title>
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
                    <h5 class="modal-title" id="titulo_modal">Alta de Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary invisible" id="Guardar_Edita">Editar Producto</button>
                    <button type="button" class="btn btn-primary" id="Guardar_Nuevo">Agregar Producto</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-10 text-center">
                <h1>Productos</h1>
            </div>
            <div class="col-2 text-center">
                <button class="btn btn-success" id="nuevo" data-bs-toggle="modal" data-bs-target="#modal"> Nuevo </button>
            </div>
            <div class="col-12 text-center">
                <div class="col-5 text-center">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="buscador" placeholder="Buscar producto...">
                        <button class="btn btn-outline-secondary" type="button" id="btnBuscar">Buscar</button>
                    </div>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Precio</th>
                            <th scope="col">Material</th>
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
        if ($("#id_material").val() == "") {
            alertify.warning('El campo Material es obligatorio');
            $("#id_material").focus();
            return false;
        }
        
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                funcion: 'Guardar',
                nombre: $("#nombre").val(),
                precio: $("#precio").val(),
                id_material: $("#id_material").val()
            },
            success: function(response) {
                cargarTabla();
                $('#modal').modal('hide');
                alertify.success('Producto registrado');
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
        if ($("#id_material").val() == "") {
            alertify.warning('El campo Material es obligatorio');
            $("#id_material").focus();
            return false;
        }
        
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                funcion: 'Editar',
                nombre: $("#nombre").val(),
                precio: $("#precio").val(),
                id_material: $("#id_material").val(),
                idregistros: idregistros
            },
            success: function(response) {
                cargarTabla();
                $('#modal').modal('hide');
                alertify.success('Producto editado');
            }
        });
    });

    $(document).on("click", "#nuevo", function() {
        Modal("Nuevo", 0);
        $("#Guardar_Nuevo").removeClass('invisible').show();
        $("#Guardar_Edita").hide();
        $("#titulo_modal").text("Alta de Producto");
    });

    $(document).on("click", ".editar", function() {
        var idregistros = $(this).attr('idregistros');
        Modal("Editar", idregistros);
        $("#Guardar_Nuevo").hide();
        $("#Guardar_Edita").show().removeClass('invisible').attr('idregistros', idregistros);
        $("#titulo_modal").text("Editar Producto");
        $('#modal').modal('show');
    });

    $(document).on("click", ".eliminar", function() {
        var idregistros = $(this).attr('idregistros');
        alertify.confirm("Eliminar Producto", 
            "¿Está seguro que desea eliminar este producto?", 
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
                        alertify.success('Producto eliminado');
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