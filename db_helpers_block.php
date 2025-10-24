<?php
// === Seguridad y helpers globales (añadir al final de tu db.php existente) ===
if (!function_exists('start_session')) {
  function start_session(){ if(session_status() !== PHP_SESSION_ACTIVE) session_start(); }
}
if (!function_exists('csrf_token')) {
  function csrf_token(){
    start_session();
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf_token'];
  }
}
if (!function_exists('check_csrf_or_redirect')) {
  function check_csrf_or_redirect(){
    start_session();
    $ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    if (!$ok){ header('Location: ' . basename($_SERVER['PHP_SELF']) . '?e=csrf'); exit; }
  }
}
if (!function_exists('current_user')) {
  function current_user(){
    start_session();
    if (empty($_SESSION['uid'])) return null;
    return ['id'=>$_SESSION['uid'], 'name'=>$_SESSION['uname'] ?? '', 'role'=>$_SESSION['role'] ?? 'admin'];
  }
}
if (!function_exists('require_login')) {
  function require_login(){
    if (!current_user()){ header('Location: login.php'); exit; }
  }
}
if (!function_exists('paginate')) {
  function paginate(PDO $pdo, string $sqlBase, array $params, int $page=1, int $perPage=20): array {
    $page=max(1,$page); $perPage=max(1,min(200,$perPage));
    $st=$pdo->prepare("SELECT COUNT(*) FROM (" . $sqlBase . ") t"); $st->execute($params); $total=(int)$st->fetchColumn();
    $pages=(int)ceil($total/$perPage); $off=($page-1)*$perPage;
    $st=$pdo->prepare($sqlBase . " LIMIT $perPage OFFSET $off"); $st->execute($params); $rows=$st->fetchAll();
    return ['rows'=>$rows,'total'=>$total,'pages'=>$pages,'page'=>$page,'perPage'=>$perPage];
  }
}
if (!function_exists('logger')) {
  function logger(string $action, string $entity, ?int $entity_id=null){
    try{
      $pdo = db();
      $pdo->exec("CREATE TABLE IF NOT EXISTS event_log ( id INT AUTO_INCREMENT PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, user_id INT NULL, action VARCHAR(128) NOT NULL, entity VARCHAR(64) NOT NULL, entity_id INT NULL, meta JSON NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $uid = (current_user()['id'] ?? null);
      $st=$pdo->prepare("INSERT INTO event_log (user_id, action, entity, entity_id, meta) VALUES (?,?,?,?,JSON_OBJECT())");
      $st->execute([$uid,$action,$entity,$entity_id]);
    }catch(Throwable $e){ /* noop */ }
  }
}
?>