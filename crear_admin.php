<?php
/**
 * crear_admin.php
 * Crea (o actualiza) un usuario admin global con:
 * username: admin
 * password: 1234
 * rol:      Admin General
 *
 * USO: abre en el navegador /crear_admin.php y comprueba el mensaje.
 * IMPORTANTE: Borra este archivo después de usarlo.
 */

declare(strict_types=1);

// Cargar conexión PDO ($pdo) desde db.php (usa config.php)
require_once __DIR__ . '/db.php';

try {
    // 1) Asegurar que las tablas globales existan (por si falta)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `global_users` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `username` VARCHAR(100) NOT NULL UNIQUE,
          `password_hash` VARCHAR(255) NOT NULL,
          `email` VARCHAR(150) UNIQUE,
          `is_active` BOOLEAN NOT NULL DEFAULT true,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `global_roles` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `role_name` VARCHAR(100) NOT NULL UNIQUE,
          `description` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `global_user_roles` (
          `user_id` INT NOT NULL,
          `role_id` INT NOT NULL,
          PRIMARY KEY (`user_id`, `role_id`),
          FOREIGN KEY (`user_id`) REFERENCES `global_users`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`role_id`) REFERENCES `global_roles`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // Asegurar que el rol 'Admin General' existe
    $pdo->exec("INSERT IGNORE INTO global_roles (role_name, description) VALUES ('Admin General', 'Acceso total a todos los módulos')");


    // 2) Preparar datos del admin
    $username = 'admin';
    $email    = 'admin@local'; // Opcional, puedes poner uno real
    $password = '1234';
    $hash     = password_hash($password, PASSWORD_DEFAULT);

    // 3) Insertar o actualizar usuario si ya existe ese username
    //    (ON DUPLICATE KEY UPDATE requiere la UNIQUE en username)
    $stmt = $pdo->prepare("
        INSERT INTO global_users (username, email, password_hash, is_active)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            email = VALUES(email),
            password_hash = VALUES(password_hash),
            is_active = 1
    ");
    $stmt->execute([$username, $email, $hash]);

    // 4) Obtener el ID del usuario (recién insertado o actualizado)
    $stmt_id = $pdo->prepare("SELECT id FROM global_users WHERE username = ?");
    $stmt_id->execute([$username]);
    $user_id = $stmt_id->fetchColumn();

    if ($user_id) {
        // 5) Obtener el ID del rol 'Admin General'
        $stmt_role = $pdo->prepare("SELECT id FROM global_roles WHERE role_name = 'Admin General'");
        $stmt_role->execute();
        $role_id = $stmt_role->fetchColumn();

        if ($role_id) {
            // 6) Asignar el rol al usuario (ignorando si ya lo tiene)
            $stmt_assign = $pdo->prepare("INSERT IGNORE INTO global_user_roles (user_id, role_id) VALUES (?, ?)");
            $stmt_assign->execute([$user_id, $role_id]);
        } else {
             echo "<h3 style='color:orange;'>Advertencia: No se encontró el rol 'Admin General'. El usuario fue creado pero sin rol.</h3>";
        }

        // 7) Confirmar
        echo "<h3>Usuario admin global creado/actualizado correctamente.</h3>";
        echo "<p>Usuario: <code>{$username}</code><br>Contraseña: <code>{$password}</code></p>";
        echo "<p>Rol asignado: <strong>Admin General</strong></p>";
        echo '<p>Ahora puedes ir a <a href="login.php">login.php</a>.</p>';
        echo '<p style="color:red; font-weight:bold;">¡RECUERDA BORRAR ESTE ARCHIVO (crear_admin.php) AHORA!</p>';

    } else {
         echo "<h3 style='color:red;'>Error: No se pudo crear o encontrar el usuario admin.</h3>";
    }


} catch (PDOException $e) {
    http_response_code(500);
    echo "<h2>Error de Base de Datos</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Error General</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}