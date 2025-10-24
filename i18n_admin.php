<?php
require_once __DIR__ . '/auth.php'; require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/common_helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
function ensure_csrf_cookie_admin(): string {
  $t=$_COOKIE['XSRF-TOKEN'] ?? ''; if(!preg_match('/^[a-f0-9]{64}$/',$t)){ $t=bin2hex(random_bytes(32)); setcookie('XSRF-TOKEN',$t,['expires'=>time()+604800,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),'httponly'=>false,'samesite'=>'Lax']); $_COOKIE['XSRF-TOKEN']=$t; }
  $_SESSION['csrf_token']=$t; return $t;
}
function check_csrf_or_back_admin(){
  $cookie=trim((string)($_COOKIE['XSRF-TOKEN']??'')); $post=trim((string)($_POST['csrf_token']??''));
  if(!($cookie&&$post&&hash_equals($cookie,$post))){ $_SESSION['flash']=['type'=>'danger','msg'=>'CSRF inválido']; header('Location: i18n_admin.php'); exit; }
}
ensure_csrf_cookie_admin();
function list_tables(PDO $pdo): array { $db=$pdo->query("SELECT DATABASE()")->fetchColumn(); $st=$pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME"); $st->execute([$db]); return array_map(fn($r)=>$r['TABLE_NAME'],$st->fetchAll(PDO::FETCH_ASSOC)?:[]); }
function get_cols(PDO $pdo,string $t): array { $db=$pdo->query("SELECT DATABASE()")->fetchColumn(); $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION"); $st->execute([$db,$t]); return array_map(fn($r)=>$r['COLUMN_NAME'],$st->fetchAll(PDO::FETCH_ASSOC)?:[]); }
function load_trans(PDO $pdo,string $scope,array $keys): array { if(!$keys)return[]; $in=implode(',',array_fill(0,count($keys),'?')); $st=$pdo->prepare("SELECT `key`, lang, `value` FROM i18n_translations WHERE scope=? AND `key` IN ($in)"); $st->execute(array_merge([$scope],$keys)); $map=[]; foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $map[$r['key']][$r['lang']]=$r['value']; } foreach($keys as $k){ if(!isset($map[$k]))$map[$k]=[]; foreach(i18n_langs() as $lg){ if(!isset($map[$k][$lg]))$map[$k][$lg]=''; } } return $map; }
if($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action']??'')==='save')){ check_csrf_or_back_admin(); $scope=$_POST['scope']??'column'; $pairs=$_POST['k']??[]; $ins=$pdo->prepare("INSERT INTO i18n_translations (scope, `key`, lang, `value`) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"); foreach($pairs as $i=>$k){ $k=trim((string)$k); if($k==='') continue; foreach(i18n_langs() as $lg){ $val=trim((string)($_POST[$lg][$i]??'')); $ins->execute([$scope,$k,$lg,$val]); } } $_SESSION['flash']=['type'=>'success','msg'=>'Guardado']; header('Location: i18n_admin.php?scope='.urlencode($scope).'&table='.urlencode($_POST['table']??'')); exit; }
$scope=$_GET['scope']??'column'; $table=$_GET['table']??''; $tables=list_tables($pdo); $cols=$table?get_cols($pdo,$table):[]; $keys=[]; if($scope==='column'&&$table){ foreach($cols as $c)$keys[]=$table.'.'.$c.'.label'; } else { $keys=['menu.productos','menu.pedidos','menu.albaranes','menu.empresas','btn.nuevo','btn.editar','btn.guardar','btn.configuracion','buscar.libre','buscar.buscar','buscar.limpiar','col.acciones','modal.titulo']; }
$map=load_trans($pdo,$scope==='column'?'column':'ui',$keys);
include __DIR__ . '/partials/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-translate"></i> Gestión de Idiomas</h4>
<?php if(isset($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?><div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body">
<form method="get" class="row g-2">
  <div class="col-md-3"><label class="form-label">Ámbito</label><select class="form-select form-select-sm" name="scope"><option value="column" <?= $scope==='column'?'selected':'' ?>>Columnas</option><option value="ui" <?= $scope==='ui'?'selected':'' ?>>UI</option></select></div>
  <div class="col-md-4"><label class="form-label">Tabla</label><select class="form-select form-select-sm" name="table" <?= $scope!=='column'?'disabled':'' ?>><option value="">—</option><?php foreach($tables as $t): ?><option value="<?= h($t) ?>" <?= $t===$table?'selected':'' ?>><?= h($t) ?></option><?php endforeach; ?></select></div>
  <div class="col d-flex align-items-end justify-content-end"><button class="btn btn-sm btn-outline-primary">Cargar</button></div>
</form></div></div>
<?php if($keys): ?>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= h($_COOKIE['XSRF-TOKEN'] ?? '') ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="scope" value="<?= h($scope==='column'?'column':'ui') ?>"><input type="hidden" name="table" value="<?= h($table) ?>">
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Clave</th><?php foreach(i18n_langs() as $lg): ?><th><?= strtoupper($lg) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach($keys as $i=>$k): $vals=$map[$k]??[]; ?><tr><td style="width:35%"><input class="form-control form-control-sm" name="k[]" value="<?= h($k) ?>" readonly></td>
  <?php foreach(i18n_langs() as $lg): ?><td><input class="form-control form-control-sm" name="<?= $lg ?>[]" value="<?= h($vals[$lg]??'') ?>"></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="d-flex justify-content-end"><button class="btn btn-primary btn-sm">Guardar</button></div>
</form>
<?php else: ?><div class="alert alert-info">Selecciona una tabla/ámbito.</div><?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
