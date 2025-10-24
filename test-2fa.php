<?php
require_once __DIR__ . '/vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$google2fa = new Google2FA();
$secret = $google2fa->generateSecretKey();
$qrUrl = $google2fa->getQRCodeUrl('InventarioApp', 'user@example.com', $secret);

$qrCode = new QrCode($qrUrl);
$writer = new PngWriter();
$result = $writer->write($qrCode);

// Codificar la imagen en base64 para incrustarla en HTML
$base64 = base64_encode($result->getString());
?>

<!DOCTYPE html>
<html>
<head>
    <title>Código QR - 2FA</title>
</head>
<body>
    <h2>Escanea este código con tu app de autenticación (Google Authenticator, Authy, etc.)</h2>
    <img src="data:image/png;base64,<?php echo $base64; ?>" alt="Código QR">
    <p><strong>Clave secreta (por si acaso):</strong> <?php echo htmlspecialchars($secret); ?></p>
</body>
</html>