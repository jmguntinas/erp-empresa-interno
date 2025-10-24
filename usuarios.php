<?php
require_once __DIR__ . '/auth.php';
require_login();
require_role(['Admin General']); // Solo Admin General puede gestionar usuarios
require_once __DIR__ . '/db.php';
check_csrf(); // Asumiendo que check_csrf está disponible

$msg='';
$editUser = null; $editUserId = (int)($_GET['edit'] ?? 0);
$userRoles = [];

// --- Acción POST ---
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id = (int)($_POST['id'] ?? 0);
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? ''; // Dejar vacío para no cambiar
  $isActive = isset($_POST['is_active']) ? 1 : 0;
  $roles = $_POST['roles'] ?? []; // Array de IDs de rol

  if ($username && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        $pdo->beginTransaction();
        if ($id > 0) { // Editar
            $sql = "UPDATE global_users SET username=?, email=?, is_active=?";
            $params = [$username, $email, $isActive];
            if ($password) {
                $sql .= ", password_hash=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);

            // Actualizar roles: borrar antiguos y añadir nuevos
            $pdo->prepare("DELETE FROM global_user_roles WHERE user_id=?")->execute([$id]);
            if (!empty($roles)) {
                $stmt_role = $pdo->prepare("INSERT INTO global_user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($roles as $roleId) {
                    $stmt_role->execute([$id, (int)$roleId]);
                }
            }
            set_flash('success', 'Usuario #' . $id . ' actualizado.');

        } else { // Crear
            if (!$password) { throw new Exception("La contraseña es obligatoria para nuevos usuarios."); }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO global_users (username, email, password_hash, is_active) VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$username, $email, $hash, $isActive]);
            $newId = (int)$pdo->lastInsertId();

            // Asignar roles
            if (!empty($roles)) {
                $stmt_role = $pdo->prepare("INSERT INTO global_user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($roles as $roleId) {
                    $stmt_role->execute([$newId, (int)$roleId]);
                }
            }
            set_flash('success', 'Usuario #' . $newId . ' creado.');
        }
        $pdo->commit();
        header('Location: usuarios.php'); exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-danger">Error: ' . h($e->getMessage()) . '</div>';
    }
  } else {
      $msg = '<div class="alert alert-danger">Nombre de usuario y email válido son obligatorios.</div>';
  }
}

// --- Acción GET (Eliminar o Editar) ---
if (isset($_GET['del'])) {
    $delId = (int)$_GET['del'];
    if ($delId > 0 && $delId !== $_SESSION['user_id']) { // No permitir auto-eliminarse
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM global_user_roles WHERE user_id=?")->execute([$delId]);
            $pdo->prepare("DELETE FROM global_users WHERE id=?")->execute([$delId]);
            $pdo->commit();
            set_flash('info', 'Usuario #' . $delId . ' eliminado.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            set_flash('danger', 'Error al eliminar: ' . h($e->getMessage()));
        }
        header('Location: usuarios.php'); exit;
    } elseif ($delId === $_SESSION['user_id']) {
         set_flash('warning', 'No puedes eliminar tu propia cuenta.');
         header('Location: usuarios.php'); exit;
    }
} elseif ($editUserId > 0) {
    // Cargar datos del usuario para editar
    $stmt_edit = $pdo->prepare("SELECT * FROM global_users WHERE id=?");
    $stmt_edit->execute([$editUserId]);
    $editUser = $stmt_edit->fetch();
    if ($editUser) {
        // Cargar roles actuales del usuario
        $stmt_roles = $pdo->prepare("SELECT role_id FROM global_user_roles WHERE user_id=?");
        $stmt_roles->execute([$editUserId]);
        $userRoles = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
    } else {
         set_flash('danger', 'Usuario no encontrado.');
         header('Location: usuarios.php'); exit;
    }
}

$msg .= get_flash_msg(); // Recoger mensajes flash
$allRoles = $pdo->query("SELECT id, role_name FROM global_roles ORDER BY role_name")->fetchAll();
$rows = $pdo->query("
    SELECT u.id, u.username, u.email, u.is_active, GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') as roles
    FROM global_users u
    LEFT JOIN global_user_roles ur ON u.id = ur.user_id
    LEFT JOIN global_roles r ON ur.role_id = r.id
    GROUP BY u.id, u.username, u.email, u.is_active
    ORDER BY u.username
")->fetchAll();

$pageTitle = 'Gestionar Usuarios';
include __DIR__ . '/partials/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4><i class="bi bi-people-fill me-2"></i> <?= h($pageTitle) ?></h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario"><i class="bi bi-plus-lg"></i> Nuevo Usuario</button>
</div>
<?= $msg ?>

<div class="table-responsive">
<table class="table table-sm table-striped table-hover align-middle">
<thead>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Roles</th>
        <th>Activo</th>
        <th class="text-end">Acciones</th>
    </tr>
</thead>
<tbody>
<?php foreach($rows as $r): ?>
    <tr>
        <td><?= h($r['id']) ?></td>
        <td><?= h($r['username']) ?></td>
        <td><?= h($r['email']) ?></td>
        <td><small><?= h($r['roles'] ?? 'Ninguno') ?></small></td>
        <td><span class="badge bg-<?= $r['is_active'] ? 'success' : 'secondary' ?>"><?= $r['is_active'] ? 'Sí' : 'No' ?></span></td>
        <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>#modalUsuario" title="Editar"><i class="bi bi-pencil"></i></a>
            <?php if ($r['id'] !== $_SESSION['user_id']): // No permitir borrar al usuario actual ?>
            <a class="btn btn-sm btn-outline-danger" href="?del=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar usuario <?= h($r['username']) ?>?');" title="Eliminar"><i class="bi bi-trash"></i></a>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; if(empty($rows)): ?>
    <tr><td colspan="6" class="text-center text-muted">No hay usuarios.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1" <?php if($editUser || strpos($msg, 'Error') !== false) echo 'data-force-show="1"'; ?> >
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= $editUserId ?>">
      <div class="modal-header">
        <h5 class="modal-title"><?= $editUserId ? 'Editar Usuario #' . $editUserId : 'Nuevo Usuario' ?></h5>
        <a href="usuarios.php" class="btn-close"></a>
      </div>
      <div class="modal-body">
          <div class="mb-2">
              <label class="form-label">Username *</label>
              <input class="form-control" name="username" value="<?= h($editUser['username'] ?? '') ?>" required>
          </div>
          <div class="mb-2">
              <label class="form-label">Email *</label>
              <input class="form-control" type="email" name="email" value="<?= h($editUser['email'] ?? '') ?>" required>
          </div>
          <div class="mb-2">
              <label class="form-label">Contraseña <?= $editUserId ? '(dejar en blanco para no cambiar)' : '*' ?></label>
              <input class="form-control" type="password" name="password" <?= $editUserId ? '' : 'required' ?>>
          </div>
          <div class="mb-2">
              <label class="form-label">Roles</label>
              <select class="form-select" name="roles[]" multiple size="5">
                  <?php foreach($allRoles as $role): ?>
                      <option value="<?= $role['id'] ?>" <?= in_array($role['id'], $userRoles) ? 'selected' : '' ?>><?= h($role['role_name']) ?></option>
                  <?php endforeach; ?>
              </select>
               <div class="form-text">Mantén pulsado Ctrl/Cmd para seleccionar varios.</div>
          </div>
          <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active_chk" value="1" <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="is_active_chk">Activo</label>
          </div>
      </div>
      <div class="modal-footer">
        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script>
    // Script para mostrar modal si hay error o se está editando
    document.addEventListener('DOMContentLoaded', function(){
        const modal = document.getElementById('modalUsuario');
        if (modal && modal.dataset.forceShow === '1') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
        // Cerrar modal al hacer clic fuera (si no estamos editando)
        modal.addEventListener('click', function(e) {
            if (modal.dataset.forceShow !== '1' && e.target === modal) {
                 window.location.href = 'usuarios.php';
            }
        });
    });
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>