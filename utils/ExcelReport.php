<?php
ini_set('display_errors', '0');

class ExcelReport {
    public static function download(string $module, array $report): void {
        if (empty($report['rows'])) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estatus' => false, 'mensaje' => 'No hay registros para los filtros y fechas seleccionados']);
            return;
        }
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estatus' => false, 'mensaje' => 'El servidor no tiene soporte ZIP para generar Excel']);
            return;
        }

        $headers = array_values($report['columns']);
        $keys = array_keys($report['columns']);
        $rows = [];
        foreach ($report['rows'] as $row) {
            $cells = [];
            foreach ($keys as $key) {
                $value = $row[$key] ?? '';
                if ($key === 'estatus') {
                    $activo = (int)$value === 1;
                    $value = in_array($module, ['clientes', 'usuarios'], true)
                        ? ($activo ? 'Activo' : 'Inactivo')
                        : ($activo ? 'Completada' : 'Cancelada');
                }
                if ($key === 'es_publico') $value = (int)$value === 1 ? 'Sí' : 'No';
                if ($key === 'tipo_venta') $value = $value === 'credito' ? 'Crédito' : 'Contado';
                $cells[] = $value;
            }
            $rows[] = $cells;
        }

        $numericKeys = ['id', 'id_producto', 'total', 'saldo_pendiente', 'existencia', 'minimo', 'cantidad', 'existencia_antes', 'existencia_despues', 'precio_compra', 'precio_venta'];
        $sheetRows = [];
        $sheetRows[] = self::rowXml(1, $headers, []);
        foreach ($rows as $index => $row) {
            $sheetRows[] = self::rowXml($index + 2, $row, $numericKeys, $keys);
        }
        $lastColumn = self::columnName(count($headers));
        $lastRow = count($rows) + 1;
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/><sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $filename = sprintf('%s_%s_a_%s.xlsx', $module, $_POST['fecha_inicio'] ?? '', $_POST['fecha_fin'] ?? '');
        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
        $temporary = tempnam(sys_get_temp_dir(), 'pikero_report_');
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporary);
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estatus' => false, 'mensaje' => 'No se pudo crear el archivo Excel']);
            return;
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temporary));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($temporary);
        @unlink($temporary);
    }

    private static function rowXml(int $number, array $values, array $numericKeys, array $keys = []): string {
        $cells = [];
        foreach ($values as $index => $value) {
            $reference = self::columnName($index + 1) . $number;
            $key = $keys[$index] ?? '';
            if ($number > 1 && in_array($key, $numericKeys, true) && is_numeric($value)) {
                $cells[] = '<c r="' . $reference . '" t="n"><v>' . (string)(0 + $value) . '</v></c>';
            } else {
                $text = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells[] = '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
        }
        return '<row r="' . $number . '">' . implode('', $cells) . '</row>';
    }

    private static function columnName(int $number): string {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }
}
