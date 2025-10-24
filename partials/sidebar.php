<?php
// partials/sidebar.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- AÑADIDO ---
// Cargar el sistema de autenticación para comprobar roles
require_once __DIR__ . '/../auth.php';
// --- FIN AÑADIDO ---

// Ruta actual (solo nombre de archivo)
$current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Helpers
if (!function_exists('is_active_file')) {
  function is_active_file(string $file): bool {
    global $current;
    return $current === $file;
  }
}
if (!function_exists('group_is_active')) {
  function group_is_active(array $files): bool {
    global $current;
    return in_array($current, $files, true);
  }
}
if (!function_exists('link_active_class')) {
  function link_active_class(string $file): string {
    return is_active_file($file) ? 'active' : '';
  }
}

// Grupos
$material = [
  'productos.php','categorias.php','almacenes.php','movimientos.php','productos_stock.php'
];
$compras = [
  'pedidos.php','albaranes.php'
];
$produccion = [
  'work_orders.php'
];
$hubs = [
  'empresas.php','departamentos.php','empleados.php','clientes.php','proyectos.php','proveedores.php'
];

// --- AÑADIDO: Grupo de RRHH ---
$rrhh = [
  'rrhh_dashboard.php',
  'rrhh_empleados.php',
  'rrhh_empleado_form.php',
  'rrhh_empleado_ver.php',
  'rrhh_vacaciones.php',
  'rrhh_gestionar_solicitudes.php',
  'rrhh_solicitar_vacaciones.php',
  'rrhh_nominas.php',
  'rrhh_gestionar_festivos.php'
];
// --- FIN AÑADIDO ---


// Estados de apertura (Bootstrap collapse)
$openMaterial   = group_is_active($material)   ? 'show' : '';
$openCompras    = group_is_active($compras)    ? 'show' : '';
$openProduccion = group_is_active($produccion) ? 'show' : '';
$openHubs       = group_is_active($hubs)       ? 'show' : '';

// --- AÑADIDO: Estado de RRHH ---
$openRRHH       = group_is_active($rrhh)       ? 'show' : '';
// --- FIN AÑADIDO ---

?>
<aside class="layout-sidebar bg-light border-end p-3">
  <nav class="nav nav-pills flex-column">
    <li class="nav-item">
      <a href="index.php" class="nav-link <?= link_active_class('index.php') ?>">
        <i class="bi bi-house-door me-2"></i> Inicio
      </a>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link" data-bs-toggle="collapse" href="#collapseMaterial" role="button">
        <i class="bi bi-box me-2"></i> Materiales
      </a>
      <div class="collapse <?= $openMaterial ?>" id="collapseMaterial">
        <ul class="nav flex-column ms-3 my-1">
          <li><a href="productos.php"       class="nav-link <?= link_active_class('productos.php') ?>"><i class="bi bi-box-seam me-2"></i> Productos</a></li>
          <li><a href="categorias.php"      class="nav-link <?= link_active_class('categorias.php') ?>"><i class="bi bi-tags me-2"></i> Categorías</a></li>
          <li><a href="almacenes.php"       class="nav-link <?= link_active_class('almacenes.php') ?>"><i class="bi bi-buildings me-2"></i> Almacenes</a></li>
          <li><a href="movimientos.php"     class="nav-link <?= link_active_class('movimientos.php') ?>"><i class="bi bi-arrows-expand-vertical me-2"></i> Movimientos</a></li>
          <li><a href="productos_stock.php" class="nav-link <?= link_active_class('productos_stock.php') ?>"><i class="bi bi-clipboard-data me-2"></i> Stock Actual</a></li>
        </ul>
      </div>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link" data-bs-toggle="collapse" href="#collapseCompras" role="button">
        <i class="bi bi-cart3 me-2"></i> Compras
      </a>
      <div class="collapse <?= $openCompras ?>" id="collapseCompras">
        <ul class="nav flex-column ms-3 my-1">
          <li><a href="pedidos.php"   class="nav-link <?= link_active_class('pedidos.php') ?>"><i class="bi bi-clipboard-check me-2"></i> Pedidos (PO)</a></li>
          <li><a href="albaranes.php" class="nav-link <?= link_active_class('albaranes.php') ?>"><i class="bi bi-truck-front me-2"></i> Albaranes (DN)</a></li>
        </ul>
      </div>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link" data-bs-toggle="collapse" href="#collapseProduccion" role="button">
        <i class="bi bi-motherboard me-2"></i> Producción
      </a>
      <div class="collapse <?= $openProduccion ?>" id="collapseProduccion">
        <ul class="nav flex-column ms-3 my-1">
          <li><a href="work_orders.php" class="nav-link <?= link_active_class('work_orders.php') ?>"><i class="bi bi-list-check me-2"></i> Órdenes Trabajo</a></li>
        </ul>
      </div>
    </li>

    <?php 
    // Comprobar si el usuario tiene rol para ver este módulo
    // has_role() proviene del 'auth.php' que generamos
    if (function_exists('has_role') && has_role(['Admin General', 'Admin RRHH', 'Empleado RRHH', 'Empleado'])): 
    ?>
    <li class="nav-item mt-2">
      <a class="nav-link" data-bs-toggle="collapse" href="#collapseRRHH" role="button">
        <i class="bi bi-people-fill me-2"></i> Recursos Humanos
      </a>
      <div class="collapse <?= $openRRHH ?>" id="collapseRRHH">
        <ul class="nav flex-column ms-3 my-1">
          
          <?php if (has_role(['Admin General', 'Admin RRHH', 'Empleado RRHH'])): ?>
            <li><a href="rrhh_dashboard.php" class="nav-link <?= link_active_class('rrhh_dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i> Dashboard RRHH</a></li>
          <?php endif; ?>
          
          <?php if (has_role(['Admin General', 'Admin RRHH'])): ?>
            <li><a href="rrhh_empleados.php" class="nav-link <?= link_active_class('rrhh_empleados.php') ?>"><i class="bi bi-person-vcard me-2"></i> Empleados</a></li>
          <?php endif; ?>

          <li><a href="rrhh_vacaciones.php" class="nav-link <?= link_active_class('rrhh_vacaciones.php') ?>"><i class="bi bi-calendar-event me-2"></i> Calendario Vacaciones</a></li>
          
          <?php if (has_role(['Admin General', 'Admin RRHH'])): ?>
            <li><a href="rrhh_gestionar_solicitudes.php" class="nav-link <?= link_active_class('rrhh_gestionar_solicitudes.php') ?>"><i class="bi bi-check2-circle me-2"></i> Gestionar Solicitudes</a></li>
            <li><a href="rrhh_nominas.php" class="nav-link <?= link_active_class('rrhh_nominas.php') ?>"><i class="bi bi-calculator me-2"></i> Cálculo Nóminas</a></li>
            <li><a href="rrhh_gestionar_festivos.php" class="nav-link <?= link_active_class('rrhh_gestionar_festivos.php') ?>"><i class="bi bi-calendar-x me-2"></i> Gestionar Festivos</a></li>
          <?php endif; ?>

        </ul>
      </div>
    </li>
    <?php endif; // Fin del bloque if has_role() ?>
    <li class="nav-item mt-2">
      <a class="nav-link" data-bs-toggle="collapse" href="#collapseHubs" role="button">
        <i class="bi bi-bounding-box-circles me-2"></i> Hubs / Otros
      </a>
      <div class="collapse <?= $openHubs ?>" id="collapseHubs">
        <ul class="nav flex-column ms-3 my-1">
          <li><a href="empresas.php"      class="nav-link <?= link_active_class('empresas.php') ?>"><i class="bi bi-building me-2"></i> Empresas</a></li>
          <li><a href="departamentos.php" class="nav-link <?= link_active_class('departamentos.php') ?>"><i class="bi bi-diagram-2 me-2"></i> Departamentos</a></li>
          <li><a href="empleados.php"     class="nav-link <?= link_active_class('empleados.php') ?>"><i class="bi bi-people me-2"></i> Empleados</a></li>
          <li><a href="clientes.php"      class="nav-link <?= link_active_class('clientes.php') ?>"><i class="bi bi-person-badge me-2"></i> Clientes</a></li>
          <li><a href="proyectos.php"     class="nav-link <?= link_active_class('proyectos.php') ?>"><i class="bi bi-kanban me-2"></i> Proyectos</a></li>
          <li><a href="proveedores.php"   class="nav-link <?= link_active_class('proveedores.php') ?>"><i class="bi bi-truck me-2"></i> Proveedores</a></li>
        </ul>
      </div>
    </li>

    </nav>
</aside>