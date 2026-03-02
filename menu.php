<?php
session_start();
require('inc/conexion.php');

if (isset($_SESSION['id_usuario'])) {
    $stmt = $consulta->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $rol = $user['id_rol'] ?? 0;
} else {
    $rol = 0; 
}
?>

<!-- Agrega esto en el head de tu HTML para usar Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="paginainicio.php">
            <img src="img/logo.png" alt="Logo">
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav left-items">
            <li class="nav-item">
                <a class="nav-link active" href="paginainicio.php"><i class="fas fa-home me-2"></i>Home</a>
            </li>
            
            <?php if ($rol > 0):  ?>
                <?php if ($rol == 1 || $rol == 2): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="materiales.php"><i class="fas fa-boxes me-2"></i>Materiales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php"><i class="fas fa-cubes me-2"></i>Productos</a>
                    </li>
                <?php endif; ?>
                
                <?php if ($rol == 1): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="adminus.php"><i class="fas fa-users-cog me-2"></i>Usuarios</a>
                    </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link" href="historial.php">
                        <i class="fas fa-history me-2"></i><?= ($rol == 3) ? 'Mi Historial' : 'Historial' ?>
                    </a>
                </li>
                
                <?php if ($rol == 1 || $rol == 2): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pres.php"><i class="fas fa-file-invoice-dollar me-2"></i>Presupuestos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pedidos.php"><i class="fas fa-truck me-2"></i>Pedidos</a>
                    </li>
                <?php endif; ?>
                
                <?php if ($rol == 3): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pres.php"><i class="fas fa-file-invoice-dollar me-2"></i>Mis Presupuestos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pedidos.php"><i class="fas fa-truck me-2"></i>Mis Pedidos</a>
                    </li>
                <?php endif; ?>
                
            <?php endif; ?>
        </ul>
            
            <ul class="navbar-nav right-items">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- El resto del estilo permanece igual -->
<style>
nav.navbar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 1030 !important;
    min-height: 60px !important;
    height: 60px !important;
    padding: 0 !important;
    margin: 0 !important;
    background-color: #343a40 !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.navbar > .container-fluid {
    padding: 0 15px !important;
    margin: 0 !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
}

.navbar-brand {
    height: 60px !important;
    padding: 0 15px 0 0 !important;
    margin: 0 !important;
    display: flex !important;
    align-items: center !important;
}

.navbar-brand img {
    height: 40px !important;
    width: auto !important;
    max-height: 40px !important;
    object-fit: contain;
}

.navbar-collapse {
    height: 60px !important;
    flex-grow: 1 !important;
    display: flex !important;
    justify-content: space-between !important;
}

.navbar-nav.left-items {
    height: 60px !important;
    display: flex !important;
    align-items: center !important;
    margin: 0 !important;
    padding: 0 !important;
    list-style: none !important;
}

.navbar-nav.right-items {
    height: 60px !important;
    display: flex !important;
    align-items: center !important;
    margin: 0 !important;
    padding: 0 15px 0 0 !important;
    list-style: none !important;
    margin-left: auto !important;
}

.nav-item {
    height: 60px !important;
    display: flex !important;
    align-items: center !important;
    margin: 0 !important;
}

.nav-link {
    padding: 0 15px !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    color: white !important;
    text-decoration: none !important;
    transition: background 0.3s !important;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

.navbar-toggler {
    margin: 0 !important;
    padding: 0.25rem 0.5rem !important;
    border: none !important;
    order: 2; 
}

.navbar-toggler-icon {
    filter: brightness(0) invert(1);
}

@media (max-width: 991.98px) {
    .navbar-collapse {
        flex-direction: column;
        height: auto !important;
        padding: 10px 0;
    }
    
    .navbar-nav.left-items, 
    .navbar-nav.right-items {
        width: 100%;
        height: auto !important;
        flex-direction: column;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .nav-item {
        width: 100%;
    }
    
    .nav-link {
        padding: 10px 15px !important;
    }
    
    .navbar-toggler {
        order: 2;
    }
}

body {
    padding-top: 60px !important;
}
.navbar-brand img {
    height: 40px !important;
    width: auto !important;
    max-height: 40px !important;
    object-fit: contain;
    border-radius: 10px !important; 
}
</style>