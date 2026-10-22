import {GeneralEventListener} from '../utils/listeners.js';

$(document).ready(function () {
    const d_sucursales = USER_PERMISSIONS.can_view_sucursales ? '' : 'd-none';
    const d_permisos = USER_PERMISSIONS.can_view_permisos ? '' : 'd-none';
    const d_usuarios = USER_PERMISSIONS.can_view_usuarios ? '' : 'd-none';
    const d_ferreterias = USER_PERMISSIONS.can_view_ferreterias ? '' : 'd-none';
    console.log('Permisos de usuario:', USER_PERMISSIONS);

    GeneralEventListener('mostrar-configuraciones', 'click', ventanaConfiguraciones)

    function ventanaConfiguraciones(){
        Swal.fire({
            title: 'Configuraciones generales',
            html: `
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="list-group">
                            <a href="${BASE_URL}sucursales" class="${d_sucursales} list-group-item list-group-item-action">Sucursales</a>
                            <a href="${BASE_URL}panel_permisos" class="${d_permisos} list-group-item list-group-item-action">Permisos</a>
                            <a href="${BASE_URL}roles" class="${d_permisos} list-group-item list-group-item-action">Roles</a>
                            <a href="${BASE_URL}usuarios" class="${d_usuarios} list-group-item list-group-item-action">Usuarios</a>
                            <a href="${BASE_URL}ferreterias" class="${d_ferreterias} list-group-item list-group-item-action">Negocios</a>
                        </div>
                    </div>
                </div>
            </div>
            `,
            showCloseButton: true,
            showCancelButton:false,
            showConfirmButton: false
          })
    }
})
