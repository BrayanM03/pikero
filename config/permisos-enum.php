<?php

enum CPERMISO: string {
    case VER_PANEL_PRINCIPAL = 'ver_panel_principal';

    case VER_PUNTO_VENTA = 'ver_punto_venta';
    case CREAR_VENTAS = 'crear_ventas';
    case VER_VENTAS = 'ver_ventas';
    case CANCELAR_VENTAS = 'cancelar_ventas';
    case CAMBIAR_FECHA_VENTAS = 'cambiar_fecha_ventas';

    case VER_PRODUCTOS = 'ver_productos';
    case CREAR_PRODUCTOS = 'crear_productos';
    case EDITAR_PRODUCTOS = 'editar_productos';
    case CANCELAR_PRODUCTOS = 'cancelar_productos';

    case VER_INVENTARIO = 'ver_inventario';
    case AJUSTAR_INVENTARIO = 'ajustar_inventario';

    case VER_CLIENTES = 'ver_clientes';
    case CREAR_CLIENTES = 'crear_clientes';
    case EDITAR_CLIENTES = 'editar_clientes';
    case CANCELAR_CLIENTES = 'cancelar_clientes';

    case VER_PROVEEDORES = 'ver_proveedores';
    case CREAR_PROVEEDORES = 'crear_proveedores';
    case EDITAR_PROVEEDORES = 'editar_proveedores';
    case CANCELAR_PROVEEDORES = 'cancelar_proveedores';

    case VER_COMPRAS = 'ver_compras';
    case CREAR_COMPRAS = 'crear_compras';
    case CANCELAR_COMPRAS = 'cancelar_compras';

    case VER_GASTOS = 'ver_gastos';
    case CREAR_GASTOS = 'crear_gastos';
    case EDITAR_GASTOS = 'editar_gastos';
    case CANCELAR_GASTOS = 'cancelar_gastos';

    case VER_CAJA = 'ver_caja';
    case ABRIR_CAJA = 'abrir_caja';
    case CERRAR_CAJA = 'cerrar_caja';

    case VER_USUARIOS = 'ver_usuarios';
    case CREAR_USUARIOS = 'crear_usuarios';
    case EDITAR_USUARIOS = 'editar_usuarios';
    case CANCELAR_USUARIOS = 'cancelar_usuarios';

    case VER_SUCURSALES = 'ver_sucursales';
    case CREAR_SUCURSALES = 'crear_sucursales';
    case EDITAR_SUCURSALES = 'editar_sucursales';
    case CANCELAR_SUCURSALES = 'cancelar_sucursales';
    case CAMBIAR_SUCURSAL = 'cambiar_sucursal';

    case VER_FERRETERIAS = 'ver_ferreterias';
    case CREAR_FERRETERIAS = 'crear_ferreterias';
    case EDITAR_FERRETERIAS = 'editar_ferreterias';
    case CANCELAR_FERRETERIAS = 'cancelar_ferreterias';

    case VER_CATALOGOS = 'ver_catalogos';
    case CREAR_CATALOGOS = 'crear_catalogos';
    case EDITAR_CATALOGOS = 'editar_catalogos';
    case CANCELAR_CATALOGOS = 'cancelar_catalogos';
    case VER_PERMISOS = 'ver_permisos';
    case CREAR_PERMISOS = 'crear_permisos';
    case ACTIVAR_PERMISOS = 'activar_permisos';
    case DESACTIVAR_PERMISOS = 'desactivar_permisos';
    case VER_PANEL_PERMISOS = 'ver_panel_permisos';
    case VER_PERMISOS_USUARIOS = 'ver_permisos_usuarios';

    case VER_ROLES = 'ver_roles';
    case CREAR_ROLES = 'crear_roles';
    case EDITAR_ROLES = 'editar_roles';
    case CANCELAR_ROLES = 'cancelar_roles';

    case VER_PERFIL = 'ver_perfil';
    case EDITAR_PERFIL = 'editar_perfil';

    //CFDis
    case CANCELAR_FACTURAS = 'cancelar_facturas';
}
