<?php
require(__DIR__ . '/../config/config.php');
require(__DIR__ . '/../vendor/autoload.php');

//require('fpdf/fpdf.php'); // Ajusta la ruta de tu librería FPDF

class PDF_VentaReporte extends FPDF {
    protected $venta;
    protected $items;
    protected $abonos;
    protected $ferreteria;
    protected $sucursal;

    function __construct($data) {
        parent::__construct('P', 'mm', 'A4');
        $this->venta = $data['venta'];
        $this->items = $data['items'];
        $this->abonos = $data['abonos'];
        $this->ferreteria = $data['venta'];
        $this->sucursal = $data;

       // Depuración: muestra los datos del negocio
    }

    function Header() {
        // Cargar logo desde Supabase (convirtiendo WebP a PNG temporal)
        $logoPath = '';
        if (!empty($this->ferreteria['ferreteria_logo'])) {
            $supabaseUrl = SUPABASE_URL."/storage/v1/object/public/pikero_public/" . $this->ferreteria['ferreteria_logo'];
            $imgContent = @file_get_contents($supabaseUrl);
            if ($imgContent) {
                $im = @imagecreatefromstring($imgContent);
                if ($im) {
                    $tmpLogo = tempnam(sys_get_temp_dir(), 'logo_') . '.png';
                    imagepng($im, $tmpLogo);
                    $logoPath = $tmpLogo;
                }
            }
        }

        if ($logoPath && file_exists($logoPath)) {
            $this->Image($logoPath, 10, 10, 25);
        }

        // Datos de la Ferretería
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(30);
        $this->Cell(100, 5, fpdf_utf8($this->ferreteria['ferreteria_nombre']), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(30);
        $this->Cell(100, 4, fpdf_utf8('Razón Social: ' . ($this->ferreteria['ferreteria_razon_social'] ?? 'N/D')), 0, 1, 'L');
        $this->Cell(30);
        $this->Cell(100, 4, fpdf_utf8('RFC: ' . ($this->ferreteria['ferreteria_rfc'] ?? 'N/D')), 0, 1, 'L');
        $this->Cell(30);
        $this->Cell(100, 4, fpdf_utf8('Dir: ' . ($this->ferreteria['ferreteria_direccion'] ?? 'N/D')), 0, 1, 'L');

        // Folio y Datos a la derecha
        $this->SetXY(135, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(65, 5, fpdf_utf8('NOTA DE VENTA'), 0, 1, 'R');
        $this->SetXY(135, 15);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(65, 5, fpdf_utf8('Folio: ' . $this->venta['folio']), 0, 1, 'R');
        $this->SetFont('Arial', '', 8);
        $this->SetXY(135, 20);
        $this->Cell(65, 4, fpdf_utf8('Fecha: ' . $this->venta['fecha_registro']), 0, 1, 'R');
        $this->SetXY(135, 24);
        $this->Cell(65, 4, fpdf_utf8('Sucursal: ' . ($this->venta['sucursal_nombre'] ?? 'Principal')), 0, 1, 'R');
        $this->SetXY(135, 28);
        $this->Cell(65, 4, fpdf_utf8('Tipo: ' . ucfirst($this->venta['tipo_venta'])), 0, 1, 'R');

        if ($logoPath && file_exists($logoPath)) {
            @unlink($logoPath);
        }

        $this->Ln(8);

        // Bloque Cliente / Cajero
        $this->SetFillColor(245, 245, 245);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(190, 5, fpdf_utf8(' DATOS GENERALES'), 1, 1, 'L', true);
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 5, fpdf_utf8(' Cliente: ' . $this->venta['cliente']), 1, 0, 'L');
        $this->Cell(95, 5, fpdf_utf8(' Cajero: ' . ($this->venta['cajero'] ?? 'N/D')), 1, 1, 'L');
        $this->Cell(95, 5, fpdf_utf8(' Forma de Pago: ' . ($this->venta['forma_pago'] ?? 'N/D')), 1, 0, 'L');
        $this->Cell(95, 5, fpdf_utf8(' Estado: ' . ($this->venta['estatus'] == 1 ? 'Completada' : 'Cancelada')), 1, 1, 'L');
        
        $this->Ln(5);

        // Si es crédito, mostrar resumen de saldos y abonos
        if ($this->venta['tipo_venta'] === 'credito') {
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(190, 5, fpdf_utf8(' ESTADO DE CRÉDITO'), 1, 1, 'L', true);
            $this->SetFont('Arial', '', 8);
            $this->Cell(63, 5, fpdf_utf8('Total: $' . number_format($this->venta['total'], 2)), 1, 0, 'C');
            $this->Cell(63, 5, fpdf_utf8('Pagado: $' . number_format($this->venta['total_pagado'], 2)), 1, 0, 'C');
            $this->Cell(64, 5, fpdf_utf8('Saldo: $' . number_format($this->venta['saldo_pendiente'], 2)), 1, 1, 'C');
            $this->Ln(3);
        }

        // Cabecera de la Tabla de Productos
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(40, 40, 40);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(85, 6, fpdf_utf8(' Producto / SKU'), 1, 0, 'L', true);
        $this->Cell(25, 6, fpdf_utf8(' Cantidad'), 1, 0, 'C', true);
        $this->Cell(25, 6, fpdf_utf8(' Unidad'), 1, 0, 'C', true);
        $this->Cell(25, 6, fpdf_utf8(' Precio'), 1, 0, 'R', true);
        $this->Cell(30, 6, fpdf_utf8(' Importe'), 1, 1, 'R', true);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 6, fpdf_utf8('Página ' . $this->PageNo() . '/{nb} - Pikero Sistema Ferretero'), 0, 0, 'C');
    }

    
}
function fpdf_utf8($text) {
    return mb_convert_encoding($text ?? '', 'ISO-8859-1', 'UTF-8');
}