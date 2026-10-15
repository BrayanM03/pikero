<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Ferreteria.php';
require_once __DIR__ . '/../utils/Tenant.php';

$es_superadmin = Tenant::esSuperAdmin();
$modelo_usuario = new Usuario(Tenant::idFerreteria());
$perfil_usuario = $modelo_usuario->obtenerPerfil((int)$_SESSION['id']);
$ferreterias = $es_superadmin ? (new Ferreteria())->combo() : [];
$ferreteria_activa = (int)Tenant::idFerreteria();
include 'vistas/general/header.php';
?>
<body>
    <div class="wrapper">
        <?php include 'vistas/general/sidebar.php'; ?>
        <div class="main">
            <?php include 'vistas/general/navbar.php'; ?>
            <main class="content">
                <div class="container-fluid p-0 animate__animated animate__fadeIn animate__faster">
                    <div class="row mb-3">
                        <div class="col-12">
                            <h1 class="h3 mb-1">Mi perfil</h1>
                            <p class="text-muted mb-0">Actualiza tus datos de acceso y contacto.</p>
                        </div>
                    </div>

                    <?php if (!$perfil_usuario): ?>
                    <div class="alert alert-danger" role="alert">No fue posible cargar la información de tu perfil.</div>
                    <?php else: ?>
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <section class="card h-100">
                                <div class="card-header">
                                    <h2 class="h5 card-title mb-0">Información personal</h2>
                                </div>
                                <div class="card-body">
                                    <form id="form-perfil">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="perfil-nombre">Nombre(s)</label>
                                                <input class="form-control" id="perfil-nombre" name="nombre" value="<?= htmlspecialchars($perfil_usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="perfil-apellido">Apellidos</label>
                                                <input class="form-control" id="perfil-apellido" name="apellido" value="<?= htmlspecialchars($perfil_usuario['apellido'], ENT_QUOTES, 'UTF-8') ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="perfil-usuario">Nombre de usuario</label>
                                                <input class="form-control" id="perfil-usuario" name="usuario" value="<?= htmlspecialchars($perfil_usuario['usuario'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="perfil-correo">Correo</label>
                                                <input class="form-control" id="perfil-correo" name="correo" type="email" value="<?= htmlspecialchars($perfil_usuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="perfil-telefono">Teléfono</label>
                                                <input class="form-control" id="perfil-telefono" name="telefono" value="<?= htmlspecialchars($perfil_usuario['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="perfil-cargo">Cargo</label>
                                                <input class="form-control" id="perfil-cargo" name="cargo" value="<?= htmlspecialchars($perfil_usuario['cargo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label">Rol</label>
                                                <input class="form-control" value="<?= htmlspecialchars($perfil_usuario['nombre_rol'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <button class="btn btn-primary" id="btn-guardar-perfil" type="submit"><i data-feather="save" class="me-1"></i>Guardar cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </section>
                        </div>

                        <div class="col-lg-5">
                            <section class="card mb-4">
                                <div class="card-header">
                                    <h2 class="h5 card-title mb-0">Cambiar contraseña</h2>
                                </div>
                                <div class="card-body">
                                    <form id="form-password" autocomplete="off">
                                        <div class="mb-3">
                                            <label class="form-label" for="password-actual">Contraseña actual</label>
                                            <input class="form-control" id="password-actual" name="password_actual" type="password" required autocomplete="current-password">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="password-nueva">Nueva contraseña</label>
                                            <input class="form-control" id="password-nueva" name="password_nueva" type="password" minlength="8" required autocomplete="new-password">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="password-confirmacion">Confirmar nueva contraseña</label>
                                            <input class="form-control" id="password-confirmacion" name="password_confirmacion" type="password" minlength="8" required autocomplete="new-password">
                                        </div>
                                        <div class="text-end">
                                            <button class="btn btn-primary" id="btn-guardar-password" type="submit"><i data-feather="lock" class="me-1"></i>Actualizar contraseña</button>
                                        </div>
                                    </form>
                                </div>
                            </section>

                            <?php if ($es_superadmin): ?>
                            <section class="card">
                                <div class="card-header">
                                    <h2 class="h5 card-title mb-0">Ferretería activa</h2>
                                </div>
                                <div class="card-body">
                                    <form id="form-ferreteria-activa">
                                        <label class="form-label" for="perfil-ferreteria">Trabajando actualmente en</label>
                                        <select class="selectpicker w-100" id="perfil-ferreteria" name="id_ferreteria" data-live-search="true" required>
                                            <option value="" disabled>Selecciona una ferretería...</option>
                                            <?php foreach ($ferreterias as $ferreteria): ?>
                                                <option value="<?= (int)$ferreteria['id'] ?>" <?= (int)$ferreteria['id'] === $ferreteria_activa ? 'selected' : '' ?>><?= htmlspecialchars($ferreteria['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="form-text mt-2">El cambio aplica al contexto de esta sesión; tu cuenta superadmin no se reasigna.</p>
                                        <div class="text-end mt-3">
                                            <button class="btn btn-outline-primary" id="btn-cambiar-ferreteria" type="submit"><i data-feather="repeat" class="me-1"></i>Cambiar ferretería</button>
                                        </div>
                                    </form>
                                </div>
                            </section>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
            <?php include 'vistas/general/footer.php'; ?>
        </div>
    </div>
    <?php include 'vistas/general/scripts.php'; ?>
</body>
</html>