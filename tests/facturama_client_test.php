<?php
require_once __DIR__ . '/../services/FacturamaClient.php';

function assertFacturama(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$issuerA = ['issuer_id' => 14, 'client_id' => 'tenant-7', 'client_name' => 'Empresa 7', 'rfc' => 'AAA010101AAA', 'name' => 'Emisor A', 'fiscal_regime' => '601'];
$issuerB = ['issuer_id' => 15, 'client_id' => 'tenant-7', 'client_name' => 'Empresa 7', 'rfc' => 'BBB010101BBB', 'name' => 'Emisor B', 'fiscal_regime' => '603'];

$emptySelection = FacturamaClient::seleccionarEmisor([], null);
assertFacturama(!$emptySelection['estatus'] && str_contains($emptySelection['mensaje'], 'Contacta al administrador'), 'Debe bloquearse cuando no hay emisor asignado.');

$singleSelection = FacturamaClient::seleccionarEmisor([$issuerA], null);
assertFacturama($singleSelection['estatus'] && $singleSelection['data']['issuer_id'] === 14, 'Un único emisor debe elegirse automáticamente.');

$multipleWithoutChoice = FacturamaClient::seleccionarEmisor([$issuerA, $issuerB], null);
assertFacturama(!$multipleWithoutChoice['estatus'], 'Con varios emisores debe requerirse una selección manual.');
$multipleSelection = FacturamaClient::seleccionarEmisor([$issuerA, $issuerB], 15);
assertFacturama($multipleSelection['estatus'] && $multipleSelection['data']['issuer_id'] === 15, 'Debe aceptar un emisor asignado que el usuario seleccionó.');
$unassignedSelection = FacturamaClient::seleccionarEmisor([$issuerA], 999);
assertFacturama(!$unassignedSelection['estatus'], 'Debe rechazarse un issuer_id no asignado al tenant autenticado.');

$requests = [];
$responses = [
    ['status' => 200, 'body' => ['estatus' => true, 'data' => [$issuerA]]],
    ['status' => 201, 'body' => ['estatus' => true, 'data' => ['Id' => 'doc-123', 'TaxStamp' => ['Uuid' => 'uuid-123']]]]
];
$transport = static function (string $method, string $url, array $headers, ?string $body) use (&$requests, &$responses): array {
    $requests[] = compact('method', 'url', 'headers', 'body');
    return array_shift($responses);
};
$client = new FacturamaClient($transport, 'https://facturacion.example.test', 'backend-only-key');
$issuerResult = $client->emisores('tenant-7');
assertFacturama($issuerResult['estatus'] && count($issuerResult['data']) === 1, 'Debe consultar emisores para el tenant solicitado.');

$cfdiPayload = ['CfdiType' => 'I', 'Receiver' => ['Rfc' => 'XAXX010101000'], 'Items' => [['Description' => 'Tornillo']]];
$delivery = ['email' => 'cliente@example.test', 'logo' => ['filename' => 'logo.png', 'mime_type' => 'image/png', 'content_base64' => 'aW1hZ2U=']];
$issueResult = $client->facturar('tenant-7', 14, $cfdiPayload, $delivery);
assertFacturama($issueResult['estatus'] && $issueResult['data']['Id'] === 'doc-123', 'Debe retornar el Id de la emisión exitosa.');
assertFacturama(count($requests) === 2, 'La consulta y emisión deben llamar al microservicio desde el backend.');
assertFacturama($requests[0]['method'] === 'GET' && str_contains($requests[0]['url'], '/emisores.php?client_id=tenant-7'), 'La consulta debe usar el endpoint de emisores con client_id.');
assertFacturama(in_array('Authorization: Bearer backend-only-key', $requests[0]['headers'], true), 'La consulta debe autenticar con la API key del backend.');
assertFacturama($requests[1]['method'] === 'POST' && str_ends_with($requests[1]['url'], '/facturar.php'), 'La emisión debe usar POST /facturar.php.');
assertFacturama(in_array('Content-Type: application/json', $requests[1]['headers'], true), 'La emisión debe enviar JSON.');
$sentPayload = json_decode((string)$requests[1]['body'], true);
assertFacturama($sentPayload['client_id'] === 'tenant-7' && $sentPayload['issuer_id'] === 14 && $sentPayload['cfdi_payload'] === $cfdiPayload && $sentPayload['delivery'] === $delivery, 'El POST debe transportar el CFDI y las opciones de entrega sin mezclarlas en el payload fiscal.');

$httpFailure = new FacturamaClient(static fn() => ['status' => 503, 'body' => ['estatus' => false, 'mensaje' => 'private internal diagnostic']], 'https://facturacion.example.test', 'backend-only-key');
$httpError = $httpFailure->emisores('tenant-7');
assertFacturama(!$httpError['estatus'] && !str_contains($httpError['mensaje'], 'private') && !array_key_exists('api_key', $httpError), 'Debe ocultar detalles internos y la API key ante HTTP no exitoso.');

$jsonFailure = new FacturamaClient(static fn() => ['status' => 200, 'body' => ['estatus' => false, 'mensaje' => 'private internal diagnostic']], 'https://facturacion.example.test', 'backend-only-key');
$jsonError = $jsonFailure->facturar('tenant-7', 14, $cfdiPayload);
assertFacturama(!$jsonError['estatus'] && !str_contains($jsonError['mensaje'], 'private'), 'Debe manejar estatus=false sin revelar detalles internos.');

$catalogRequests = [];
$catalogClient = new FacturamaClient(static function (string $method, string $url, array $headers, ?string $body) use (&$catalogRequests): array {
    $catalogRequests[] = compact('method', 'url', 'headers', 'body');
    return ['status' => 200, 'body' => ['estatus' => true, 'data' => [['Value' => '601', 'Name' => 'General de Ley Personas Morales']]]];
}, 'https://facturacion.example.test', 'backend-only-key');
$catalogResult = $catalogClient->catalogoSAT('FiscalRegimens', 'general');
assertFacturama($catalogResult['estatus'] && $catalogResult['data'][0]['Value'] === '601', 'Debe devolver las opciones del catálogo SAT.');
assertFacturama($catalogRequests[0]['method'] === 'GET' && str_contains($catalogRequests[0]['url'], '/catalogos.php?') && str_contains($catalogRequests[0]['url'], 'FiscalRegimens') && str_contains($catalogRequests[0]['url'], 'general'), 'Debe consultar el proxy con catálogo y palabra clave.');
$invalidCatalog = $catalogClient->catalogoSAT('UntrustedCatalog');
assertFacturama(!$invalidCatalog['estatus'] && count($catalogRequests) === 1, 'Debe rechazar nombres de catálogo no permitidos sin llamar al proxy.');

$binaryRequests = [];
$pdfBytes = "%PDF-1.4\nfixture-binary-content";
$binaryClient = new FacturamaClient(static function (string $method, string $url, array $headers, ?string $body) use (&$binaryRequests, $pdfBytes): array {
    $binaryRequests[] = compact('method', 'url', 'headers', 'body');
    return ['status' => 200, 'body' => $pdfBytes, 'content_type' => 'application/pdf'];
}, 'https://facturacion.example.test', 'backend-only-key');
$pdfResult = $binaryClient->descargarCfdi('tenant-7', 14, 'cfdi-456', 'pdf');
parse_str((string)parse_url($binaryRequests[0]['url'], PHP_URL_QUERY), $downloadQuery);
assertFacturama($pdfResult['estatus'] && $pdfResult['contenido'] === $pdfBytes && $pdfResult['content_type'] === 'application/pdf', 'Debe preservar intacta la respuesta binaria PDF.');
assertFacturama($binaryRequests[0]['method'] === 'GET' && str_ends_with((string)parse_url($binaryRequests[0]['url'], PHP_URL_PATH), '/cfdi.php'), 'La descarga debe usar GET /cfdi.php.');
assertFacturama($downloadQuery === ['client_id' => 'tenant-7', 'issuer_id' => '14', 'id' => 'cfdi-456', 'formato' => 'pdf'], 'La descarga debe enviar identidad CFDI y formato al backend.');
assertFacturama(in_array('Authorization: Bearer backend-only-key', $binaryRequests[0]['headers'], true), 'La descarga debe autenticarse desde backend.');

$emailRequests = [];
$emailClient = new FacturamaClient(static function (string $method, string $url, array $headers, ?string $body) use (&$emailRequests): array {
    $emailRequests[] = compact('method', 'url', 'headers', 'body');
    return ['status' => 200, 'body' => ['estatus' => true, 'mensaje' => 'Factura enviada']];
}, 'https://facturacion.example.test', 'backend-only-key');
$emailResult = $emailClient->enviarCfdiPorCorreo('tenant-7', 14, 'cfdi-456', 'facturacion@example.test');
$emailPayload = json_decode((string)$emailRequests[0]['body'], true);
assertFacturama($emailResult['estatus'] && $emailResult['mensaje'] === 'Factura enviada', 'Debe devolver la confirmación del envío de correo.');
assertFacturama($emailRequests[0]['method'] === 'POST' && str_ends_with((string)parse_url($emailRequests[0]['url'], PHP_URL_PATH), '/cfdi.php'), 'El envío debe usar POST /cfdi.php.');
assertFacturama($emailPayload === ['action' => 'email', 'client_id' => 'tenant-7', 'issuer_id' => 14, 'id' => 'cfdi-456', 'email' => 'facturacion@example.test'], 'El envío debe usar el contrato action=email del microservicio.');

echo "Pruebas Facturama: OK\n";