<?php
date_default_timezone_set('America/Matamoros');

class Date {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    function obtenerFechaRegistro() {
        return date('Y-m-d H:i:s');
    }

    function fecha() {
        return date('Y-m-d');
    }

    function formatearFechaEspanol($fecha) {
        if (!$fecha || $fecha == '0000-00-00') return "Fecha no válida";

        $meses = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
        $timestamp = strtotime($fecha);
        $numDia = date('d', $timestamp);
        $nombreMes = $meses[date('n', $timestamp)];
        $anio = date('Y', $timestamp);

        return $numDia . " de " . $nombreMes . " " . $anio;
    }

     function esFechaValida(string $fecha): bool
    {
        $d = DateTime::createFromFormat('Y-m-d H:i', $fecha);
        return $d && $d->format('Y-m-d H:i') === $fecha;
    }
}
