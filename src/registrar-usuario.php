<?php
$controller_permiso->validarAcceso(1, CPERMISO::CREAR_USUARIOS->value);
include "vistas/general/header.php";

require_once __DIR__ . '/../controllers/SucursalController.php';
require_once __DIR__ . '/../controllers/RolController.php';
require_once __DIR__ . '/../models/Ferreteria.php';
require_once __DIR__ . '/../utils/Tenant.php';

$controller_sucursal = new SucursalController;
$controller_rol = new RolController;
$es_superadmin = Tenant::esSuperAdmin();
print_r($es_superadmin);

$data_sucursales = $controller_sucursal->combo(2)['data'] ?? [];
$data_roles = $controller_rol->combo(2)['data'] ?? [];
$data_ferreterias = $es_superadmin ? (new Ferreteria())->combo() : [];
?>
<body>
    <div class="wrapper">
        <?php include "vistas/general/sidebar.php"; ?>
        <div class="main">
            <?php include "vistas/general/navbar.php"; ?>
            <main class="content">
                <div class="container-fluid p-0 animate__animated animate__fadeIn animate__faster">
                    <div class="row mb-2">
                        <div class="col-12">
                            <h1 class="h3 mb-3">Registrar usuario</h1>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">El usuario quedará ligado a <?= $es_superadmin ? 'la ferretería seleccionada' : 'tu ferretería' ?> y a una sucursal</h5>
                        </div>
                        <div class="card-body">
                            <form id="form-registro-usuario" enctype="multipart/form-data" autocomplete="off">
                                <div class="row">
                                    <div class="col-md-4 text-center border-end">
                                        <label class="form-label d-block">Foto de Perfil</label>
                                        <img id="avatar-preview" src="<?php echo STATIC_URL; ?>img/avatars/default.png"
                                             class="img-thumbnail rounded-circle mb-2"
                                             style="width: 150px; height: 150px; object-fit: cover;">
                                        <input type="file" class="form-control form-control-sm mt-2" id="input-avatar" name="avatar" accept="image/*">
                                        <small class="text-muted">Máximo 2MB (JPG, PNG, WEBP)</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nombre(s)</label>
                                                <input type="text" class="form-control" name="nombre" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Apellidos</label>
                                                <input type="text" class="form-control" name="apellidos" required>
                                            </div>
                                            <?php if ($es_superadmin): ?>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Ferretería</label>
                                                <select class="selectpicker w-100" name="id_ferreteria" required data-live-search="true">
                                                    <option value="" selected disabled>Selecciona un negocio...</option>
                                                    <?php foreach ($data_ferreterias as $ferreteria): ?>
                                                        <option value="<?= (int)$ferreteria['id'] ?>"><?= htmlspecialchars($ferreteria['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Sucursal</label>
                                                <select class="selectpicker w-100" name="id_sucursal" required data-live-search="true" >
                                                    <option value="" selected disabled>Selecciona una sucursal...</option>
                                                    <?php foreach ($es_superadmin ? [] : $data_sucursales as $suc): ?>
                                                        <option value="<?= $suc['id'] ?>"><?= htmlspecialchars($suc['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Rol</label>
                                                <select class="selectpicker w-100" name="rol" required data-live-search="true">
                                                    <option value="" selected disabled>Selecciona un rol...</option>
                                                    <?php foreach ($data_roles as $rol): ?>
                                                        <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Cargo</label>
                                                <input type="text" class="form-control" name="cargo" placeholder="Gerente, cajero, etc." required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Correo</label>
                                                <input type="email" class="form-control" name="correo">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Teléfono</label>
                                                <input type="text" class="form-control" name="telefono">
                                            </div>
                                            <hr>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nombre de usuario</label>
                                                <input type="text" class="form-control" name="usuario" required autocomplete="one-time-code">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Contraseña temporal</label>
                                                <input type="password" class="form-control" name="password" required autocomplete="new-password">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-end">
                                    <a href="<?= BASE_URL?>usuarios"><button type="button" class="btn btn-secondary me-2">Cancelar</button></a>
                                    <button type="submit" id="btn-registrar-usuario" class="btn btn-primary">Registrar usuario</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
            <?php include "vistas/general/footer.php"; ?>
        </div>
    </div>
    <?php include "vistas/general/scripts.php"; ?>
    <script src="<?php echo STATIC_URL; ?>js/usuarios/registrar.js" type="module"></script>
</body>
</html>
