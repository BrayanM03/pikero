<?php
/**
 * CFDI 4.0 - constructor de conceptos editables para ingresos derivados de un ticket.
 * No cambia ventas ni detalle_ventas. El cliente envia campos descriptivos, precio y
 * reglas fiscales; todos los importes se RECONSTRUYEN y validan aqui.
 * v1: IVA, ISR e IEPS federal, sin impuestos locales ni complementos.
 */
namespace App\Services;
final class CfdiConceptEditor
{
    private static function r(float $n, int $d = 6): float { return round($n, $d, PHP_ROUND_HALF_UP); }
    private static function cents(float $n): int { return (int) round($n * 100, 0, PHP_ROUND_HALF_UP); }
    private static function value($v, string $label, float $min = 0, float $max = 100000000): float {
        if (!is_scalar($v) || !is_numeric($v)) throw new \InvalidArgumentException("{$label} debe ser numérico");
        $n = (float)$v;
        if (!is_finite($n) || $n < $min || $n > $max || abs($n - self::r($n, 6)) > 0.000000001)
            throw new \InvalidArgumentException("{$label} debe tener máximo seis decimales y estar dentro del rango permitido");
        return $n;
    }
    private static function text($x, string $label, int $max): string {
        if (!is_string($x) && !is_numeric($x)) throw new \InvalidArgumentException("{$label} inválido");
        $s = trim((string)$x);
        if ($s === '' || (function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s)) > $max) throw new \InvalidArgumentException("{$label}: longitud inválida");
        return $s;
    }

    public static function build(array $items, array $changes, float $ticketTotal, string $issuerRfc, string $issuerRegimen, string $receiverRfc): array
    {
        try {
            if (count($items) < 1 || count($items) > 300 || count($changes) !== count($items))
                throw new \InvalidArgumentException('El CFDI debe contener exactamente las partidas del ticket');
            $ids = array_fill_keys(array_map('strval', array_column($items, 'id')), true);
            foreach ($changes as $k => $v) {
                if (!isset($ids[(string)$k]) || !is_array($v)) throw new \InvalidArgumentException('Partida ajena a la venta');
            }
            $cfdi = []; $sub = 0.; $desc = 0.; $comercialCent = 0; $cfdiCent = 0;
            $gruposTraslado = []; $gruposRetencion = []; $detalle = [];
            foreach ($items as $item) {
                $key = (string)$item['id']; $c = $changes[$key] ?? null;
                if (!is_array($c)) throw new \InvalidArgumentException("Falta la configuración del concepto {$key}");
                $codigo = self::text($c['product_code'] ?? '', 'Clave SAT', 8);
                if (!preg_match('/^[0-9]{8}$/', $codigo)) throw new \InvalidArgumentException("Clave SAT de {$key}: deben ser ocho dígitos");
                $unidad = self::text($c['unit_code'] ?? '', 'Unidad SAT', 10);
                if (!preg_match('/^[A-Za-z0-9]{2,10}$/', $unidad)) throw new \InvalidArgumentException('Clave SAT de unidad inválida');
                $quantity = self::value($c['quantity'] ?? null, 'Cantidad', 0.000001, 1000000);
                $unitPrice = self::value($c['unit_price'] ?? null, 'Precio unitario sin impuestos', 0.000001, 100000000);
                $discount = self::value($c['discount'] ?? 0, 'Descuento fiscal');
                $subtotal = self::r($quantity * $unitPrice);
                if ($discount > $subtotal) throw new \InvalidArgumentException('Descuento mayor que subtotal');
                $base = self::r($subtotal - $discount);
                if ($base <= 0) throw new \InvalidArgumentException('La base fiscal debe ser mayor que cero en esta versión');
                $objeto = (string)($c['tax_object'] ?? '');
                if (!in_array($objeto, ['01','02'], true)) throw new \InvalidArgumentException('ObjetoImp soportado: 01 o 02');
                $taxes = $c['taxes'] ?? [];
                if (!is_array($taxes) || count($taxes) > 12) throw new \InvalidArgumentException('Número de impuestos inválido');
                if ($objeto === '01' && count($taxes)) throw new \InvalidArgumentException('ObjetoImp 01 no admite desglose de impuestos');
                $vatTransfer = 0; $used = []; $impuestos = []; $iepsSuma = 0.;
                // Preparar IEPS primero por si el IVA debe integrar IEPS en su base.
                foreach ($taxes as $tax) {
                    if (!is_array($tax)) throw new \InvalidArgumentException('Impuesto inválido');
                    if (($tax['name'] ?? '') === 'IEPS' && empty($tax['is_retention'])) {
                        $factor = $tax['factor'] ?? 'Tasa';
                        $rate = self::value($tax['rate'] ?? 0, 'IEPS tasa/cuota', 0.000001, $factor === 'Cuota' ? 1000 : 1);
                        if (!in_array($factor, ['Tasa','Cuota'], true)) throw new \InvalidArgumentException('Factor IEPS no soportado');
                        $iepsSuma += self::r(($factor === 'Cuota' ? $quantity : $base) * $rate);
                    }
                }
                $tras = 0.; $reten = 0.;
                foreach ($taxes as $tax) {
                    $name = (string)($tax['name'] ?? '');
                    $ret = ($tax['is_retention'] ?? false) === true;
                    $factor = (string)($tax['factor'] ?? 'Tasa');
                    if (!in_array($name, ['IVA','IVA Exento','IVA RET','ISR','IEPS'], true)) throw new \InvalidArgumentException('Clave de impuesto no soportada');
                    if (!in_array($factor, ['Tasa','Cuota','Exento'], true)) throw new \InvalidArgumentException('Factor de impuesto no soportado');
                    if ($name === 'IVA Exento' && ($ret || $factor !== 'Exento')) throw new \InvalidArgumentException('IVA Exento solo admite traslado Exento');
                    if ($name === 'IVA' && ($ret || $factor !== 'Tasa')) throw new \InvalidArgumentException('IVA traslado: factor Tasa');
                    if ($name === 'IVA RET' && (!$ret || $factor !== 'Tasa')) throw new \InvalidArgumentException('IVA RET debe ser una retención tipo Tasa');
                    if ($name === 'ISR' && (!$ret || $factor !== 'Tasa')) throw new \InvalidArgumentException('ISR debe ser retención tipo Tasa');
                    if ($name === 'IEPS' && $factor === 'Exento') throw new \InvalidArgumentException('IEPS Exento requiere integración PAC específica; no habilitado');
                    if ($name !== 'IEPS' && $factor === 'Cuota') throw new \InvalidArgumentException('Cuota solo se soporta para IEPS');
                    if ($name === 'IVA' || $name === 'IVA Exento') $vatTransfer++;
                    if ($vatTransfer > 1) throw new \InvalidArgumentException('No se permiten dos traslados IVA en el mismo concepto');
                    $rate = $factor === 'Exento' ? 0.0 : self::value($tax['rate'] ?? null, 'Tasa/cuota', 0, $factor === 'Cuota' ? 1000 : 1);
                    if ($factor !== 'Exento' && $rate <= 0 && $name !== 'IVA') throw new \InvalidArgumentException('Tasa del impuesto debe ser mayor a cero');
                    if ($name === 'IVA' && !in_array(self::r($rate), [0.,0.08,0.16], true)) throw new \InvalidArgumentException('IVA solo 0%, 8% o 16%');
                    if ($name === 'IVA RET' && !in_array(self::r($rate), [0.04,0.10,0.106667], true)) throw new \InvalidArgumentException('Retención IVA fuera de las tasas habilitadas');
                    if ($name === 'ISR' && !in_array(self::r($rate), [0.0125,0.10], true)) throw new \InvalidArgumentException('Retención ISR fuera de las tasas habilitadas');
                    if ($name === 'ISR' && self::r($rate) === 0.0125 &&
                        ($issuerRegimen !== '626' || strlen($issuerRfc) !== 13 || strlen($receiverRfc) !== 12)) {
                        throw new \InvalidArgumentException('ISR 1.25% exige emisor PF RESICO 626 y receptor persona moral');
                    }
                    if ($name === 'IEPS' && $factor !== 'Exento' && $rate <= 0) throw new \InvalidArgumentException('IEPS debe tener tasa/cuota positiva');
                    $mode = (string)($tax['base_mode'] ?? 'neto');
                    if (!in_array($mode, ['neto','cantidad','neto_mas_ieps'], true)) throw new \InvalidArgumentException('Modo de base no soportado');
                    if ($mode === 'cantidad' && !($name === 'IEPS' && $factor === 'Cuota')) throw new \InvalidArgumentException('Base cantidad solo corresponde a IEPS cuota');
                    if ($factor === 'Cuota' && $mode !== 'cantidad') throw new \InvalidArgumentException('La cuota IEPS requiere base cantidad');
                    if ($mode === 'neto_mas_ieps' && !($name === 'IVA' && !$ret)) throw new \InvalidArgumentException('Base + IEPS solo corresponde a traslado IVA');
                    $taxBase = self::r($mode === 'cantidad' ? $quantity : ($mode === 'neto_mas_ieps' ? $base + $iepsSuma : $base));
                    if ($taxBase <= 0) throw new \InvalidArgumentException('Base fiscal del impuesto inválida');
                    $unique = $name.'|'.($ret ? '1':'0').'|'.$factor.'|'.number_format($rate,6,'.','');
                    if (isset($used[$unique])) throw new \InvalidArgumentException('Impuesto duplicado en el concepto');
                    $used[$unique] = true;
                    $it = ['Name'=>$name,'Base'=>$taxBase,'IsRetention'=>$ret];
                    $amount = 0.;
                    if ($factor !== 'Exento') {
                        $amount = self::r($taxBase * $rate);
                        $it += ['Rate'=>$rate,'Total'=>$amount,'IsQuota'=>$factor === 'Cuota'];
                        if ($ret) $reten += $amount; else $tras += $amount;
                        $group = ($ret ? $name : $name.'|'.$factor.'|'.number_format($rate,6,'.',''));
                        if ($ret) $gruposRetencion[$group] = ($gruposRetencion[$group] ?? 0.) + $amount;
                        else $gruposTraslado[$group] = ($gruposTraslado[$group] ?? 0.) + $amount;
                    }
                    $impuestos[] = $it;
                }
                $gross = self::r($base + $tras, 2);
                $net = self::r($base + $tras - $reten, 2);
                $comercialCent += self::cents($gross);
                $cfdiCent += self::cents($net);
                $sub += $subtotal; $desc += $discount;
                $concept = [
                    'ProductCode'=>$codigo,
                    'IdentificationNumber'=>trim((string)($c['sku'] ?? '')),
                    'Description'=>self::text(trim((string)($c['description'] ?? '')).(trim((string)($c['details'] ?? '')) !== '' ? ' — '.trim((string)$c['details']) : ''), 'Descripción', 1000),
                    'Unit'=>self::text($c['unit'] ?? '', 'Unidad', 50),
                    'UnitCode'=>$unidad,
                    'Quantity'=>$quantity,
                    'UnitPrice'=>$unitPrice,
                    'Subtotal'=>$subtotal,
                    'TaxObject'=>$objeto,
                    'Total'=>$net,
                ];
                if ($discount > 0) $concept['Discount']=$discount;
                if ($impuestos) $concept['Taxes']=$impuestos;
                $predial = trim((string)($c['predial'] ?? ''));
                if ($predial !== '') {
                    if (!preg_match('/^[0-9a-zA-Z]{1,150}$/',$predial)) throw new \InvalidArgumentException('Cuenta predial inválida');
                    $concept['PropertyTaxIDNumber']=[$predial];
                }
                $pedimentos = $c['pedimentos'] ?? [];
                if (!is_array($pedimentos) || count($pedimentos) > 10) throw new \InvalidArgumentException('Pedimentos inválidos');
                $listaPed = [];
                foreach ($pedimentos as $ped) {
                    $ped = trim((string)$ped);
                    if (!preg_match('/^[0-9]{2} [0-9]{2} [0-9]{4} [0-9]{7}$/', $ped)) throw new \InvalidArgumentException('Pedimento debe tener formato 00 00 0000 0000000');
                    $listaPed[]=$ped;
                }
                if ($listaPed) $concept['NumerosPedimento']=array_values(array_unique($listaPed));
                $third=$c['third_party'] ?? null;
                if ($third !== null && $third !== '' && $third !== []) {
                    if (!is_array($third)) throw new \InvalidArgumentException('Datos a cuenta de terceros inválidos');
                    $trfc=strtoupper(self::text($third['rfc'] ?? '', 'RFC tercero', 13));
                    if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $trfc)) throw new \InvalidArgumentException('RFC tercero inválido');
                    $tname=self::text($third['name'] ?? '', 'Razón social tercero', 300);
                    $treg=self::text($third['regime'] ?? '', 'Régimen tercero', 3);
                    $tcp=self::text($third['zip'] ?? '', 'CP tercero', 5);
                    if (!preg_match('/^[0-9]{3}$/', $treg) || !preg_match('/^[0-9]{5}$/',$tcp))
                        throw new \InvalidArgumentException('Régimen o CP del tercero inválido');
                    $concept['ThirdPartyAccount']=['Rfc'=>$trfc,'Name'=>$tname,'FiscalRegime'=>$treg,'TaxZipCode'=>$tcp];
                }
                $cfdi[]=$concept;
                $detalle[]=['id_detalle'=>(int)$item['id'],'subtotal'=>$subtotal,'discount'=>$discount,'gross'=>$gross,'net'=>$net,'trasladado'=>self::r($tras,2),'retenido'=>self::r($reten,2)];
            }
            $sumaTraslado=0.;foreach ($gruposTraslado as $x) $sumaTraslado += self::r($x,2);
            $sumaRet=0.;foreach ($gruposRetencion as $x) $sumaRet += self::r($x,2);
            $totalCFDI=self::r(self::r($sub,2)-self::r($desc,2)+self::r($sumaTraslado,2)-self::r($sumaRet,2),2);
            if ($comercialCent !== self::cents($ticketTotal)) throw new \InvalidArgumentException(
                'Importe antes de retenciones ($'.number_format($comercialCent/100,2).') distinto del ticket ($'.number_format($ticketTotal,2).'). Ajusta conceptos sin alterar la operación comercial.');
            if ($cfdiCent !== self::cents($totalCFDI)) throw new \InvalidArgumentException('Descuadre entre suma de conceptos y total CFDI por redondeo; revisa precisión.');
            return ['estatus'=>true,'items'=>$cfdi,'resumen'=>[
                'subtotal'=>self::r($sub,2),'descuento'=>self::r($desc,2),
                'traslados'=>self::r($sumaTraslado,2),'retenciones'=>self::r($sumaRet,2),
                'total_cfdi'=>$totalCFDI,'total_ticket'=>$ticketTotal,'conceptos'=>$detalle
            ]];
        } catch (\InvalidArgumentException $e) {
            return ['estatus'=>false,'mensaje'=>$e->getMessage()];
        }
    }
}
