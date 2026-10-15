<?php

use PHPMailer\PHPMailer\PHPMailer;

class InvoiceMailer {
    public function enviarDocumentos(string $correo, string $folio, array $providerData): array {
        $documents = $providerData['Documents'] ?? $providerData['documents'] ?? [];
        $attachments = is_array($documents) ? array_merge($providerData, $documents) : $providerData;
        $pdf = $this->obtenerDocumento($attachments, ['PdfBase64', 'pdfBase64', 'Pdf', 'pdf', 'PdfContent', 'pdf_content'], true);
        $xml = $this->obtenerDocumento($attachments, ['XmlBase64', 'xmlBase64', 'Xml', 'xml', 'XmlContent', 'xml_content'], false);
        if ($pdf === null || $xml === null) {
            return ['estatus' => false, 'mensaje' => 'El servicio de facturación no devolvió ambos documentos.'];
        }

        $host = trim((string)($_ENV['PIKERO_SMTP_HOST'] ?? getenv('PIKERO_SMTP_HOST') ?: ''));
        $username = trim((string)($_ENV['PIKERO_SMTP_USER'] ?? getenv('PIKERO_SMTP_USER') ?: ''));
        $password = (string)($_ENV['PIKERO_SMTP_PASSWORD'] ?? getenv('PIKERO_SMTP_PASSWORD') ?: '');
        $from = trim((string)($_ENV['PIKERO_SMTP_FROM'] ?? getenv('PIKERO_SMTP_FROM') ?: $username));
        if ($host === '' || $username === '' || $password === '' || $from === '') {
            return ['estatus' => false, 'mensaje' => 'Configura las variables SMTP para usar el envío alternativo.'];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = (int)($_ENV['PIKERO_SMTP_PORT'] ?? getenv('PIKERO_SMTP_PORT') ?: 587);
            $encryption = strtolower(trim((string)($_ENV['PIKERO_SMTP_ENCRYPTION'] ?? getenv('PIKERO_SMTP_ENCRYPTION') ?: 'tls')));
            if ($encryption === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            elseif ($encryption === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            elseif ($encryption === 'none') $mail->SMTPSecure = '';
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($from, (string)($_ENV['PIKERO_SMTP_FROM_NAME'] ?? getenv('PIKERO_SMTP_FROM_NAME') ?: 'Pikero'));
            $mail->addAddress($correo);
            $mail->Subject = 'Documentos fiscales de la venta ' . $folio;
            $mail->isHTML(true);
            $safeFolio = htmlspecialchars($folio, ENT_QUOTES, 'UTF-8');
            $mail->Body = '<p>Hola,</p><p>Adjuntamos los documentos fiscales correspondientes a la venta <strong>' . $safeFolio . '</strong>.</p><p>Saludos,<br>Pikero</p>';
            $mail->AltBody = "Hola,\n\nAdjuntamos los documentos fiscales correspondientes a la venta {$folio}.\n\nSaludos,\nPikero";
            $mail->addStringAttachment($pdf, 'factura-' . $folio . '.pdf', PHPMailer::ENCODING_BASE64, 'application/pdf');
            $mail->addStringAttachment($xml, 'factura-' . $folio . '.xml', PHPMailer::ENCODING_BASE64, 'application/xml');
            $mail->send();
            return ['estatus' => true, 'mensaje' => 'Documentos enviados por correo.'];
        } catch (Throwable $exception) {
            return ['estatus' => false, 'mensaje' => 'No se pudo enviar el correo por SMTP.'];
        }
    }

    private function obtenerDocumento(array $data, array $keys, bool $isPdf): ?string {
        foreach ($keys as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') continue;
            $content = $data[$key];
            if ($isPdf && str_starts_with($content, '%PDF-')) return $content;
            if (!$isPdf && str_starts_with(ltrim($content), '<?xml')) return $content;
            $decoded = base64_decode($content, true);
            if ($decoded === false) continue;
            if ($isPdf && str_starts_with($decoded, '%PDF-')) return $decoded;
            if (!$isPdf && str_starts_with(ltrim($decoded), '<?xml')) return $decoded;
        }
        return null;
    }
}
