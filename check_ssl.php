<?php
echo "PHP Version: " . phpversion() . "\n";
echo "OpenSSL Version Text: " . OPENSSL_VERSION_TEXT . "\n";
echo "OpenSSL Version Number: " . OPENSSL_VERSION_NUMBER . "\n";

echo "\n--- Default SSL Context ---\n";
print_r(openssl_get_cert_locations());

echo "\n--- php.ini openssl.cafile ---\n";
echo ini_get('openssl.cafile') . "\n";

echo "\n--- php.ini curl.cainfo ---\n";
echo ini_get('curl.cainfo') . "\n";

echo "\n--- Testing Connection to getcomposer.org ---\n";
$contextOptions = [
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'cafile' => ini_get('openssl.cafile') // Usa la ruta de php.ini
    ]
];
$context = stream_context_create($contextOptions);

$result = @file_get_contents('https://getcomposer.org/versions', false, $context);

if ($result === false) {
    echo "ERROR: Failed to connect securely.\n";
    $error = error_get_last();
    if ($error) {
        echo "Error details: " . $error['message'] . "\n";
    }
} else {
    echo "SUCCESS: Connected securely and downloaded content.\n";
}
?>