<?php

class FacturamaClient
{
    private string $baseUrl;
    private string $apiKey;
    private $transport;

    public function __construct(?callable $transport = null, ?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string)($_ENV['PIKERO_FACTURACION_API_URL'] ?? (getenv('PIKERO_FACTURACION_API_URL') ?: '')), '/');
        $this->apiKey = $apiKey ?? (string)($_ENV['PIKERO_FACTURACION_API_KEY'] ?? (getenv('PIKERO_FACTURACION_API_KEY') ?: ''));
        $this->transport = $transport;
    }

    public function emisores(string $clientId): array
    {
        if ($clientId === '') {
            return ['estatus' => false, 'mensaje' => 'No se pudo identificar la empresa autenticada.'];
        }

        $response = $this->request('GET', '/emisores.php?client_id=' . rawurlencode($clientId));
        /* print_r($this->baseUrl . '/emisores.php?client_id=' . rawurlencode($clientId));
        die(); */
        if (!$response['ok'] || !($response['body']['estatus'] ?? false) || !is_array($response['body']['data'] ?? null)) {
            return ['estatus' => false, 'mensaje' => 'No se pudieron consultar los emisores. Inténtalo más tarde o contacta al administrador.'];
        }

        $issuers = array_values(array_filter($response['body']['data'], static function ($issuer) use ($clientId): bool {
            return is_array($issuer)
                && (int)($issuer['issuer_id'] ?? 0) > 0
                && (string)($issuer['client_id'] ?? '') === $clientId;
        }));

        return ['estatus' => true, 'data' => $issuers];
    }

    public function catalogoSAT(string $catalogo, string $keyword = ''): array
    {
        $catalogos = [
            'ProductsOrServices',
            'Units',
            'PostalCodes',
            'FiscalRegimens',
            'CfdiUses',
            'PaymentForms',
            'PaymentMethods'
        ];
        if (!in_array($catalogo, $catalogos, true)) {
            return ['estatus' => false, 'mensaje' => 'El catálogo SAT solicitado no es válido.'];
        }

        $query = http_build_query(array_filter([
            'catalogo' => $catalogo,
            'keyword' => trim($keyword)
        ], static fn($value) => $value !== ''));
        $response = $this->request('GET', '/catalogos.php?' . $query);

        if (!$response['ok']) {
            return ['estatus' => false, 'mensaje' => 'No se pudo consultar el catálogo SAT. Inténtalo de nuevo.', 'response' => $response, 'query' => $query];
        }
        if (array_key_exists('estatus', $response['body']) && !$response['body']['estatus']) {
            return ['estatus' => false, 'mensaje' => 'No se pudo consultar el catálogo SAT. Inténtalo de nuevo.', 'response' => $response, 'query' => $query];
        }

        $data = $response['body']['data'] ?? $response['body'];
        if (!is_array($data)) {
            return ['estatus' => false, 'mensaje' => 'La respuesta del catálogo SAT no es válida.'];
        }

        return ['estatus' => true, 'data' => array_values($data), 'response' => $response, 'query' => $query];
    }

    public static function seleccionarEmisor(array $issuers, ?int $issuerId): array
    {
        if (empty($issuers)) {
            return ['estatus' => false, 'mensaje' => 'Esta empresa no tiene emisores asignados. Contacta al administrador.'];
        }

        if ($issuerId === null && count($issuers) === 1) {
            return ['estatus' => true, 'data' => $issuers[0]];
        }

        if ($issuerId === null) {
            return ['estatus' => false, 'mensaje' => 'Selecciona un emisor para continuar.'];
        }

        foreach ($issuers as $issuer) {
            if ((int)($issuer['issuer_id'] ?? 0) === $issuerId) {
                return ['estatus' => true, 'data' => $issuer];
            }
        }

        return ['estatus' => false, 'mensaje' => 'El emisor seleccionado no está asignado a esta empresa.'];
    }

    public function facturar(string $clientId, int $issuerId, array $cfdiPayload, array $delivery = []): array
{
    if ($clientId === '' || $issuerId <= 0) {
        return [
            'estatus' => false,
            'http_code' => 400,
            'mensaje' => 'No se pudo validar la empresa o el emisor seleccionado.'
        ];
    }

    $requestPayload = [
        'client_id' => $clientId,
        'issuer_id' => $issuerId,
        'cfdi_payload' => $cfdiPayload
    ];

    if ($delivery !== []) {
        $requestPayload['delivery'] = $delivery;
    }

    $response = $this->request('POST', '/facturar.php', $requestPayload);

   if (!$response['ok'] || !($response['body']['estatus'] ?? false)) {

    $mensaje = 'No fue posible emitir la factura. Verifica los datos fiscales e inténtalo de nuevo.';

    // Respuesta recibida del microservicio.
    $body = $response['body'] ?? [];

    // Buscar los errores de validación en la estructura real.
    $modelState = $body['detalles']['ModelState'] ?? [];

    // Compatibilidad con posibles respuestas anidadas.
    if (empty($modelState)) {
        $modelState = $body['response']['body']['detalles']['ModelState'] ?? [];
    }

    if (is_array($modelState) && !empty($modelState)) {

        $errores = [];

        foreach ($modelState as $campo => $mensajes) {
            foreach ((array) $mensajes as $detalle) {
                if (is_string($detalle) && trim($detalle) !== '') {
                    $errores[] = trim($detalle);
                }
            }
        }

        if (!empty($errores)) {
            $mensaje = implode(' ', array_unique($errores));
        }

    } else {
        // Si no existen errores específicos, utilizar el mensaje general.
        $mensajeGeneral = $body['mensaje'] ?? null;

        if (is_string($mensajeGeneral) && trim($mensajeGeneral) !== '') {
            $mensaje = trim($mensajeGeneral);
        }
    }

    return [
        'estatus' => false,
        'http_code' => (int) ($response['status'] ?? 0),
        'mensaje' => $mensaje
    ];
}

    return [
        'estatus' => true,
        'data' => $response['body']['data'] ?? $response['body']
    ];
}

    public function descargarCfdi(string $clientId, int $issuerId, string $cfdiId, string $formato): array
    {
        if ($clientId === '' || $issuerId <= 0 || $cfdiId === '' || !in_array($formato, ['pdf', 'xml'], true)) {
            return ['estatus' => false, 'mensaje' => 'Los datos de descarga de la factura no son válidos'];
        }
        if ($this->baseUrl === '' || $this->apiKey === '') {
            return ['estatus' => false, 'mensaje' => 'El servicio de facturación no está configurado'];
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'issuer_id' => $issuerId,
            'id' => $cfdiId,
            'formato' => $formato
        ]);
        $response = $this->requestBinary('/cfdi.php?' . $query);
        if (!$response['ok']) {
            $body = json_decode($response['body'], true);
            return [
                'estatus' => false,
                'http_code' => $response['status'],
                'mensaje' => trim((string)($body['mensaje'] ?? '')) ?: 'No se pudo descargar la factura; inténtalo de nuevo.'
            ];
        }

        return [
            'estatus' => true,
            'contenido' => $response['body'],
            'content_type' => $formato === 'pdf' ? 'application/pdf' : 'application/xml',
            'filename' => $formato === 'pdf' ? 'factura.pdf' : 'factura.xml'
        ];
    }

    public function enviarCfdiPorCorreo(string $clientId, int $issuerId, string $cfdiId, string $email, ?string $issuerEmail = null, ?string $subject = null, ?string $comments = null): array
    {
        if ($clientId === '' || $issuerId <= 0 || $cfdiId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['estatus' => false, 'mensaje' => 'Los datos para enviar la factura por correo no son válidos'];
        }
        $response = $this->request('POST', '/cfdi.php', [
            'action' => 'email',
            'client_id' => $clientId,
            'issuer_id' => $issuerId,
            'id' => $cfdiId,
            'email' => $email,
            'issuer_email' => $issuerEmail,
            'subject' => $subject,
            'comments' => $comments
        ]);
        if (!$response['ok'] || !($response['body']['estatus'] ?? false)) {
            return [
                'estatus' => false,
                'http_code' => (int)($response['status'] ?? 0),
                'mensaje' => trim((string)($response['body']['mensaje'] ?? '')) ?: 'No se pudo enviar la factura por correo.'
            ];
        }
        return ['estatus' => true, 'mensaje' => $response['body']['mensaje'] ?? 'Factura enviada correctamente'];
    }

  public function cancelarCfdi(string $clientId, int $issuerId, string $cfdiId, string $motivo, string $uuidSustituto = ''): array
{
    if ($this->baseUrl === '' || $this->apiKey === '') {
        return ['estatus' => false, 'mensaje' => 'El servicio de facturación no está configurado'];
    }

    $response = $this->request('POST', '/cancelar.php', [
        'client_id'        => $clientId,
        'issuer_id'        => $issuerId,
        'id'               => $cfdiId,
        'motive'           => $motivo,
        'uuid_replacement' => $uuidSustituto,
    ]);

    if (!$response['ok'] || !($response['body']['estatus'] ?? false)) {
        return [
            'estatus'   => false,
            'http_code' => (int)($response['status'] ?? 0),
            'mensaje'   => trim((string)($response['body']['mensaje'] ?? '')) ?: 'No se pudo cancelar la factura.'
        ];
    }

    return [
        'estatus'    => true,
        'sat_status' => $response['body']['sat_status'] ?? 'canceled',
        'mensaje'    => $response['body']['mensaje'] ?? 'Factura cancelada correctamente'
    ];
}

    // ---------------------------------------------------------------
    // 2) Método nuevo: consulta por Folio + Date en el microservicio
    // ---------------------------------------------------------------
    public function buscarEmitidaPorFolio(string $clientId, int $issuerId, string $folio, string $date = '', bool $quick = false): array
    {
        $response = $this->request('POST', '/buscar_cfdi.php', [
            'client_id' => $clientId,
            'issuer_id' => $issuerId,
            'folio' => $folio,
            'date' => $date,
            'quick'     => $quick,
        ]);

        if (!$response['ok'] || !($response['body']['estatus'] ?? false)) {
            return ['estatus' => false, 'http_code' => (int)($response['status'] ?? 0), 'mensaje'=>$response['body']['mensaje'] ?? 'Error de comunicación', 'resp' => $response];
        }
        // data = null si no existe; ['Id' => ..., 'TaxStamp' => ['Uuid' => ...]] si existe
        return ['estatus' => true, 'data' => $response['body']['data'] ?? null];
    }
    
    private function request(string $method, string $path, ?array $payload = null): array
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            return ['ok' => false, 'status' => 0, 'body' => []];            // <- status
        }

        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ];
        $body = null;
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($payload);
        }

        $status = 0;                                                         // <- fuera del try
        try {
            if ($this->transport !== null) {
                $result = ($this->transport)($method, $url, $headers, $body);
                $status = (int)($result['status'] ?? 0);
                $rawBody = $result['body'] ?? '';
            } else {
                $context = stream_context_create(['http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'content' => $body ?? '',
                    'ignore_errors' => true,
                    'timeout' => 60                                          // <- antes 20; > 45 s del microservicio
                ]]);
                $rawBody = @file_get_contents($url, false, $context);
                foreach ($http_response_header ?? [] as $header) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                        $status = (int)$matches[1];
                    }
                }
            }
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => 0, 'body' => []];
        }

        $decoded = is_array($rawBody) ? $rawBody : json_decode((string)$rawBody, true);
        return [
            'ok' => $status >= 200 && $status < 300 && is_array($decoded),
            'status' => $status,                                             // <- nuevo
            'body' => is_array($decoded) ? $decoded : [],
            'result' => $result ?? $rawBody
        ];
    }

    private function requestBinary(string $path): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/pdf, application/xml, application/octet-stream'
        ];
        $status = 0;
        $contentType = '';
        try {
            if ($this->transport !== null) {
                $response = ($this->transport)('GET', $url, $headers, null);
                $status = (int)($response['status'] ?? 0);
                $body = (string)($response['body'] ?? '');
                $contentType = (string)($response['content_type'] ?? '');
            } else {
                $responseHeaders = [];
                $context = stream_context_create(['http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                    'timeout' => 60
                ]]);
                $body = @file_get_contents($url, false, $context);
                foreach ($http_response_header ?? [] as $header) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) $status = (int)$matches[1];
                    if (stripos($header, 'Content-Type:') === 0) $contentType = trim(substr($header, strlen('Content-Type:')));
                    $responseHeaders[] = $header;
                }
                if ($body === false) $body = '';
            }
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'content_type' => ''];
        }

        return [
            'ok' => $status >= 200 && $status < 300 && $body !== '',
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType
        ];
    }
}
