<?php
// partials/table_actions.php — acciones reutilizables (Editar + Eliminar)
// Uso: echo render_actions('productos.php', $id, $_GET);

if (!function_exists('render_actions')) {
  function render_actions(string $page, int $id, array $get): string {
    // Enlace de edición preservando filtros/paginación
    $qs = http_build_query(array_merge($get, ['edit' => $id]));
    $editUrl = htmlspecialchars($page . '?' . $qs, ENT_QUOTES, 'UTF-8');
    $pageEsc = htmlspecialchars($page, ENT_QUOTES, 'UTF-8');
    $idEsc   = (int)$id;

    // CSRF global unificado
    $csrf = $_SESSION['csrf_token'] ?? '';
    $csrfInput = $csrf ? '<input type="hidden" name="csrf_token" value="'.
                         htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">' : '';

    return <<<HTML
<a class="btn btn-sm btn-outline-primary me-1"
   href="{$editUrl}"
   title="Editar">
  <i class="bi bi-pencil"></i>
</a>

<form method="post" action="{$pageEsc}" class="d-inline-block"
      onsubmit="return confirm('¿Eliminar #{$idEsc}? Esta acción no se puede deshacer.')">
  {$csrfInput}
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="{$idEsc}">
  <button type="submit"
          class="btn btn-sm btn-outline-danger"
          title="Eliminar">
    <i class="bi bi-trash"></i>
  </button>
</form>
HTML;
  }
}
