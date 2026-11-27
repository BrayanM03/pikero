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

	/* --- Menú de apps estilo iCloud --- */
	.app-launcher {
		position: relative;
	}

	.dropdown-menu.dropdown-menu-app {
		/* siempre en el DOM para poder animar */
		display: block;
		visibility: hidden;
		opacity: 0;
		pointer-events: none;

		position: absolute;
		top: calc(100% + 12px);
		right: 0;
		left: auto;
		margin: 0;
		width: 300px;
		padding: 20px 16px 14px;

		background: rgba(255, 255, 255, 0.82);
		-webkit-backdrop-filter: saturate(180%) blur(24px);
		backdrop-filter: saturate(180%) blur(24px);
		border: 1px solid rgba(255, 255, 255, 0.6);
		border-radius: 22px;
		box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18), 0 2px 8px rgba(0, 0, 0, 0.06);

		/* animación tipo popover de Apple: crece desde la esquina superior derecha */
		transform-origin: calc(100% - 22px) -12px;
		transform: scale(0.85) translateY(-6px);
		transition:
			opacity 0.22s ease,
			transform 0.38s cubic-bezier(0.32, 0.72, 0, 1),
			visibility 0s linear 0.38s;
		/* se oculta hasta que termina la salida */
		will-change: transform, opacity;
	}

	.dropdown-menu.dropdown-menu-app.show {
		visibility: visible;
		opacity: 1;
		pointer-events: auto;
		transform: scale(1) translateY(0);
		transition:
			opacity 0.22s ease,
			transform 0.38s cubic-bezier(0.32, 0.72, 0, 1),
			visibility 0s;
	}

	/* Flechita del popover */
	.dropdown-menu-app::before {
		content: "";
		position: absolute;
		top: -7px;
		right: 18px;
		width: 14px;
		height: 14px;
		background: inherit;
		border-top: 1px solid rgba(255, 255, 255, 0.6);
		border-left: 1px solid rgba(255, 255, 255, 0.6);
		border-top-left-radius: 4px;
		transform: rotate(45deg);
	}

	.app-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 6px 4px;
	}

	.app-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 6px;
		padding: 8px 4px;
		border-radius: 14px;
		text-decoration: none;
		color: #1d1d1f;
		-webkit-tap-highlight-color: transparent;

		/* entrada escalonada de cada icono */
		opacity: 0;
		transform: translateY(8px) scale(0.9);
		transition: opacity 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
	}

	.dropdown-menu-app.show .app-item {
		opacity: 1;
		transform: none;
		transition:
			opacity 0.3s ease calc(var(--i, 0) * 35ms + 60ms),
			transform 0.45s cubic-bezier(0.32, 0.72, 0, 1) calc(var(--i, 0) * 35ms + 60ms),
			background-color 0.2s ease;
	}

	.app-item:hover {
		background-color: rgba(0, 0, 0, 0.05);
		color: #1d1d1f;
	}

	.app-item:active .app-icon {
		transform: scale(0.92);
	}

	.app-icon {
		width: 54px;
		height: 54px;
		border-radius: 13px;
		/* squircle aproximado de iOS */
		display: flex;
		align-items: center;
		justify-content: center;
		color: #fff;
		background: linear-gradient(180deg, var(--g1, #0a84ff), var(--g2, #0060df));
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.35);
		transition: transform 0.2s cubic-bezier(0.32, 0.72, 0, 1);
	}

	.app-icon svg {
		width: 26px;
		height: 26px;
		stroke-width: 2;
	}

	.app-item span {
		font-size: 0.72rem;
		font-weight: 500;
		text-align: center;
		line-height: 1.15;
		color: inherit;
	}

	/* Modo oscuro */
	body.dark .dropdown-menu.dropdown-menu-app {
		background: rgba(44, 44, 46, 0.82);
		border-color: rgba(255, 255, 255, 0.1);
		box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55);
	}

	body.dark .dropdown-menu-app::before {
		border-color: rgba(255, 255, 255, 0.1);
	}

	body.dark .app-item,
	body.dark .app-item:hover {
		color: #f5f5f7;
	}

	body.dark .app-item:hover {
		background-color: rgba(255, 255, 255, 0.08);
	}

	/* Respeta usuarios con animaciones reducidas */
	@media (prefers-reduced-motion: reduce) {

		.dropdown-menu.dropdown-menu-app,
		.app-item,
		.app-icon {
			transition: none !important;
		}
	}

	/* Móvil: que no se salga de la pantalla */
	@media (max-width: 400px) {
		.dropdown-menu.dropdown-menu-app {
			width: calc(100vw - 24px);
			right: -8px;
		}
	}
</style>
<nav class="navbar navbar-expand navbar-light navbar-bg">
	<a class="sidebar-toggle js-sidebar-toggle">
		<i class="hamburger align-self-center"></i>
	</a>

	<div class="navbar-collapse collapse">
		<ul class="navbar-nav navbar-align">

			<!-- Selector de Apps estilo iCloud -->
			<li class="nav-item dropdown app-launcher">
				<a class="nav-link dropdown-toggle hide-arrow" href="#" id="appLauncherDropdown"
					data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="Aplicaciones">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="align-middle">
						<circle cx="5" cy="5" r="2" />
						<circle cx="12" cy="5" r="2" />
						<circle cx="19" cy="5" r="2" />
						<circle cx="5" cy="12" r="2" />
						<circle cx="12" cy="12" r="2" />
						<circle cx="19" cy="12" r="2" />
						<circle cx="5" cy="19" r="2" />
						<circle cx="12" cy="19" r="2" />
						<circle cx="19" cy="19" r="2" />
					</svg>
				</a>

				<div class="dropdown-menu dropdown-menu-app" aria-labelledby="appLauncherDropdown">
					<div class="app-grid">

						<a href="#" class="app-item" id="mostrar-configuraciones-grid" style="--i:0">
							<div class="app-icon" style="--g1:#8e8e93; --g2:#636366;">
								<i data-feather="settings"></i>
							</div>
							<span>Configuración</span>
						</a>

						<!-- Ejemplo de más apps (copia y cambia color/icono/link) -->
						<!--
			<a href="<?php echo BASE_URL; ?>ventas" class="app-item" style="--i:1">
				<div class="app-icon" style="--g1:#34c759; --g2:#248a3d;"><i data-feather="shopping-cart"></i></div>
				<span>Ventas</span>
			</a>
			-->
					</div>
				</div>
			</li>

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