<?php
class ReportDateRange {
    public static function validate(array $filters, bool $requireRange = false): array {
        $start = trim((string)($filters['fecha_inicio'] ?? ''));
        $end = trim((string)($filters['fecha_fin'] ?? ''));
        if ($start === '' && $end === '') {
            return $requireRange
                ? ['estatus' => false, 'mensaje' => 'Selecciona fecha de inicio y fecha fin para descargar el reporte']
                : ['estatus' => true, 'inicio' => null, 'fin_exclusivo' => null];
        }
        if ($start === '' || $end === '') {
            return ['estatus' => false, 'mensaje' => 'Debes seleccionar fecha de inicio y fecha fin'];
        }

        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$inicio || !$fin || $inicio->format('Y-m-d') !== $start || $fin->format('Y-m-d') !== $end) {
            return ['estatus' => false, 'mensaje' => 'Las fechas seleccionadas no son válidas'];
        }
        if ($fin < $inicio) {
            return ['estatus' => false, 'mensaje' => 'La fecha fin debe ser igual o posterior a la fecha inicio'];
        }
        $inicioMes = $inicio->modify('first day of this month')->modify('+6 months');
        $ultimoDiaMesLimite = (int)$inicioMes->modify('last day of this month')->format('d');
        $limite = $inicioMes->setDate(
            (int)$inicioMes->format('Y'),
            (int)$inicioMes->format('m'),
            min((int)$inicio->format('d'), $ultimoDiaMesLimite)
        );
        if ($fin > $limite) {
            return ['estatus' => false, 'mensaje' => 'El rango de fechas no puede ser mayor a 6 meses'];
        }
        return [
            'estatus' => true,
            'inicio' => $inicio->format('Y-m-d 00:00:00'),
            'fin_exclusivo' => $fin->modify('+1 day')->format('Y-m-d 00:00:00')
        ];
    }
}
