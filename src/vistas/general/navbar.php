<?php 
$sucursales_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_SUCURSALES->value);
$permisos_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_PANEL_PERMISOS->value);
$usuarios_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_USUARIOS->value);
$ventas_p = $controller_permiso->validarAcceso(2, CPERMISO::VER_VENTAS->value);
$permisos_usuario = $controller_permiso->obtener_permisos_x_usuario(2, $_SESSION['id']);
$user_P = $permisos_usuario['data'] ?? []; 
?>
<style>
	body {
		transition: background-color 0.3s ease, color 0.3s ease;
	}

	#darkmode-switch {
		width: 2.5rem;
		height: 1.25rem;
		cursor: pointer;
	}

	#darkmode-switch~label {
		cursor: pointer;
		margin-left: .3rem;
	}
</style>
<nav class="navbar navbar-expand navbar-light navbar-bg">
	<a class="sidebar-toggle js-sidebar-toggle">
		<i class="hamburger align-self-center"></i>
	</a>

	<div class="navbar-collapse collapse">
		<ul class="navbar-nav navbar-align">
			<li class="nav-item d-flex align-items-center me-2">
				<div class="form-check form-switch mb-0" title="Modo oscuro">
					<input class="form-check-input" type="checkbox" id="darkmode-switch" role="switch">
					<label class="form-check-label" for="darkmode-switch">
						<i class="align-middle" data-feather="moon"></i>
					</label>
				</div>
			</li>
			<li class="nav-item dropdown">
				<a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown">
					<i class="align-middle" data-feather="settings"></i>
				</a>

				<a class="nav-link dropdown-toggle d-none d-sm-inline-block" href="#" data-bs-toggle="dropdown">
					<img src="<?php echo STATIC_URL; ?>img/avatars/<?= isset($_SESSION['foto_perfil']) && $_SESSION['foto_perfil'] != ''  ? $_SESSION['foto_perfil'] : 'default.png' ?>" id="foto_usuario_navbar" class="avatar img-fluid rounded me-1" alt="foto_usuario" /> <span class="text-dark" id="nombre_usuario_navbar" id_user="<?php echo $_SESSION["id"] ?>"><?php echo $_SESSION["nombre"] . " " . $_SESSION["apellido"] ?></span>
				</a>
				<div class="dropdown-menu dropdown-menu-end">
					<div class="profile dropdown-item"><a href="<?php echo BASE_URL; ?>perfil">
							<div class="info">
								<p class="m-0 p-0"><b><?php echo $_SESSION["user"]; ?></b></p>
								<small class="text-muted"><?php echo ($_SESSION['rol_nombre']); ?></small>
							</div>
						</a>
					</div>
					<div class="dropdown-item" style="cursor:pointer" id="mostrar-configuraciones"><i class="align-middle me-1" data-feather="settings"></i> Configuración</div>
					<a class="dropdown-item" href="<?php echo BASE_URL; ?>servidor/database/cerrar-sesion.php"><i class="align-middle me-1" data-feather="log-out"></i> Cerrar Sesion</a>
				</div>
			</li>
		</ul>
	</div>
</nav>
<script> 
	<?php
		/** @var array $crear_clientes_p */
		/** @var array $ferreterias_p */
		/** @var array $editar_clientes_p */
		/** @var array $cancelar_clientes_p */
		/** @var array $crear_productos_p */
		/** @var array $editar_productos_p */
		/** @var array $cancelar_productos_p */
	?>
	const BASE_URL = "<?php echo BASE_URL; ?>";
	const STATIC_URL = "<?php echo STATIC_URL; ?>";
	const SUPABASE_URL = "<?php echo SUPABASE_URL; ?>";
	const SUPABASE_STORAGE_URL = "<?php echo SUPABASE_STORAGE_URL; ?>";
	const USER_PERMISSIONS = {
		can_view_sucursales: <?php echo $sucursales_p['estatus'] ? 'true' : 'false'; ?>,
		can_view_permisos: <?php echo $permisos_p['estatus'] ? 'true' : 'false'; ?>,
		can_view_usuarios: <?php echo $usuarios_p['estatus'] ? 'true' : 'false'; ?>,
		can_view_ventas: <?php echo $ventas_p['estatus'] ? 'true' : 'false'; ?>,
		can_view_ferreterias: <?php echo $ferreterias_p['estatus'] && ($_SESSION['rol_slug'] ?? '') === 'superadmin' ? 'true' : 'false'; ?>,
		can_create_clientes: <?php echo $crear_clientes_p['estatus'] ? 'true' : 'false'; ?>,
		can_edit_clientes: <?php echo $editar_clientes_p['estatus'] ? 'true' : 'false'; ?>,
		can_cancel_clientes: <?php echo $cancelar_clientes_p['estatus'] ? 'true' : 'false'; ?>,
		can_create_productos: <?php echo $crear_productos_p['estatus'] ? 'true' : 'false'; ?>,
		can_edit_productos: <?php echo $editar_productos_p['estatus'] ? 'true' : 'false'; ?>,
		can_cancel_productos: <?php echo $cancelar_productos_p['estatus'] ? 'true' : 'false'; ?>,
		can_create_ferreterias: <?php echo $controller_permiso->validarAcceso(2, CPERMISO::CREAR_FERRETERIAS->value)['estatus'] && ($_SESSION['rol_slug'] ?? '') === 'superadmin' ? 'true' : 'false'; ?>,
		can_edit_ferreterias: <?php echo $controller_permiso->validarAcceso(2, CPERMISO::EDITAR_FERRETERIAS->value)['estatus'] && ($_SESSION['rol_slug'] ?? '') === 'superadmin' ? 'true' : 'false'; ?>,
		can_cancel_ferreterias: <?php echo $controller_permiso->validarAcceso(2, CPERMISO::CANCELAR_FERRETERIAS->value)['estatus'] && ($_SESSION['rol_slug'] ?? '') === 'superadmin' ? 'true' : 'false'; ?>
	};
	const permisos_usuario = <?=  json_encode($user_P,  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);?>;
	const perfil_sesion = <?php echo json_encode($_SESSION); ?>;

	document.addEventListener('DOMContentLoaded', function() {
		const THEME_KEY = 'adminkit_theme';
		const switchEl = document.getElementById('darkmode-switch');

		function aplicarTema(tema) {
            document.body.classList.toggle('dark', tema === 'dark');
            switchEl.checked = (tema === 'dark');
            const contenedorIcono = switchEl.nextElementSibling;
            if (contenedorIcono) {
                const iconoNombre = tema === 'dark' ? 'sun' : 'moon';
                contenedorIcono.innerHTML = `<i data-feather="${iconoNombre}"></i>`;
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        }

		const temaGuardado = localStorage.getItem(THEME_KEY) || 'light';
		aplicarTema(temaGuardado);

		switchEl.addEventListener('change', function() {
			const nuevoTema = this.checked ? 'dark' : 'light';
			localStorage.setItem(THEME_KEY, nuevoTema);
			aplicarTema(nuevoTema);
		});
	});
</script>
<script type="module" src="<?php echo STATIC_URL; ?>js/config/configuraciones.js"></script>
