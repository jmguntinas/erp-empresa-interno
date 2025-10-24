<?php
// partials/export_buttons.php
// Muestra tres botones de exportación, conservando el query-string actual (filtros).
$qs = $_GET; unset($qs['page'], $qs['edit']); // ignorar paginación/edición en export
$base = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>
<div class="d-flex gap-2">
  <a class="btn btn-sm btn-outline-success" href="<?= $base.'?'.http_build_query(array_merge($qs, ['export'=>'xlsx'])) ?>">
    <i class="bi bi-file-earmark-excel"></i> Excel
  </a>
  <a class="btn btn-sm btn-outline-danger" href="<?= $base.'?'.http_build_query(array_merge($qs, ['export'=>'pdf'])) ?>">
    <i class="bi bi-file-earmark-pdf"></i> PDF
  </a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= $base.'?'.http_build_query(array_merge($qs, ['export'=>'csv'])) ?>">
    <i class="bi bi-download"></i> CSV
  </a>
</div>
