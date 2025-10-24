<?php
// enterprise_helpers.php (Adaptado para inv_ y hr_)
require_once __DIR__ . '/db.php';

/** Devuelve el hr_empleados.id vinculado al usuario logueado (o null). */
function current_employee_id(PDO $pdo): ?int {
  if (empty($_SESSION['user_id'])) return null; // Usa user_id de la sesión global
  // --- MODIFICADO ---
  $st = $pdo->prepare("SELECT id FROM hr_empleados WHERE user_id=? LIMIT 1"); // Busca en hr_empleados por user_id
  // --- FIN MODIFICADO ---
  $st->execute([$_SESSION['user_id']]);
  $id = $st->fetchColumn();
  return $id ? (int)$id : null;
}

/** ¿El usuario actual (a través de su empleado) es supervisor del empleado $employeeId? */
// NOTA: La tabla 'employee_supervisors' no está definida en nuestro esquema actual.
// Esta función necesitaría una tabla hr_supervisores o similar para funcionar.
function is_supervisor_of(PDO $pdo, int $employeeId): bool {
    // Si eres Admin General o Admin RRHH, siempre puedes
    if (function_exists('has_role') && has_role(['Admin General', 'Admin RRHH'])) {
        return true;
    }

    $me = current_employee_id($pdo);
    if (!$me) return false;

    // --- MODIFICADO (Asumiendo una tabla hr_supervisores) ---
    // Esta parte es especulativa, necesitas crear la tabla hr_supervisores
    // $st = $pdo->prepare("SELECT 1 FROM hr_supervisores WHERE empleado_id=? AND supervisor_empleado_id=?");
    // $st->execute([$employeeId, $me]);
    // return (bool)$st->fetchColumn();
    // --- FIN MODIFICADO ---

    // De momento, devolvemos false si no es admin
    return false;
}

/** Listados auxiliares filtrados por empresa */
function companies_all(PDO $pdo): array {
  // --- MODIFICADO ---
  return $pdo->query("SELECT id,name FROM inv_companies ORDER BY name")->fetchAll(); // Quitado is_active si no existe
  // --- FIN MODIFICADO ---
}

function employees_by_company(PDO $pdo, int $company_id): array {
  // --- MODIFICADO: Lee de hr_empleados ---
  $st = $pdo->prepare("SELECT id, CONCAT(nombre, ' ', apellidos) as name FROM hr_empleados WHERE company_id=? ORDER BY apellidos, nombre");
  // --- FIN MODIFICADO ---
  $st->execute([$company_id]); return $st->fetchAll();
}

function clients_by_company(PDO $pdo, int $company_id): array {
  // --- MODIFICADO ---
  // Asumiendo que inv_clients tiene company_id (necesitaría añadirse al esquema)
  // $st = $pdo->prepare("SELECT id, name, internal_ref FROM inv_clients WHERE company_id=? ORDER BY name");
  // Si no, devolver todos los clientes:
  $st = $pdo->prepare("SELECT id, name FROM inv_clients ORDER BY name");
  // --- FIN MODIFICADO ---
  $st->execute(); return $st->fetchAll();
}

// ... (Otras funciones si las hubiera)
?>