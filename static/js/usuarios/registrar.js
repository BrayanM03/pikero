import { GeneralEventListener } from '../utils/listeners.js';
import Toast from '../utils/toast.js';
import {validarImagenLocal} from '../utils/images.js';
import { toggleLoading } from '../utils/ui.js';

/* // Vinculamos el input con tu función de utilidad
GeneralEventListener(
    'input-avatar', 
    'change', 
    subirFoto('api/usuarios.php?tipo=subir_temporal', 'avatar', 'avatars', 'perfil')
);
 */

// --- Escuchar el cambio en el input de la foto ---
document.getElementById('input-avatar').addEventListener('change', function(e) {
  // 1. Primero validamos (Usa la función importada)
  const esValido = validarImagenLocal(e);
  
  // 2. Si es válido, ejecutamos TU código de previsualización
  if (esValido && this.files[0]) {
      const reader = new FileReader();
      const preview = document.getElementById('avatar-preview');
      
      reader.onload = function(e) {
          preview.src = e.target.result;
      }
      reader.readAsDataURL(this.files[0]);
  } else {
      // Si no es válido, reseteamos la previa a la default
      document.getElementById('avatar-preview').src = STATIC_URL + 'img/avatars/default.png';
  }
});

const selectorFerreteria = document.querySelector('[name="id_ferreteria"]');
if (selectorFerreteria) {
    const selectorSucursal = document.querySelector('[name="id_sucursal"]');
    selectorFerreteria.addEventListener('change', function() {
        const idFerreteria = this.value;
        selectorSucursal.innerHTML = '<option value="" selected disabled>Selecciona una sucursal...</option>';
        selectorSucursal.disabled = true;
        $(selectorSucursal).selectpicker('refresh');
        if (!idFerreteria) return;

        fetch(`${BASE_URL}api/sucursales.php?tipo=combo_ferreteria&id_ferreteria=${encodeURIComponent(idFerreteria)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.estatus) throw new Error(data.mensaje || 'No se pudieron cargar las sucursales');
                data.data.forEach(sucursal => {
                    const option = document.createElement('option');
                    option.value = sucursal.id;
                    option.textContent = sucursal.nombre;
                    selectorSucursal.appendChild(option);
                });
                selectorSucursal.disabled = data.data.length === 0;
                $(selectorSucursal).selectpicker('refresh');
                if (data.data.length === 0) Toast.fire({ icon: 'info', title: 'Este negocio no tiene sucursales activas' });
            })
            .catch(error => {
                console.error('Error:', error);
                Toast.fire({ icon: 'error', title: error.message || 'Error al cargar las sucursales' });
            });
    });
}

// --- Envío del Formulario ---
document.getElementById('form-registro-usuario').addEventListener('submit', function(e) {
  e.preventDefault();

  const form = e.target;
  const btnId = 'btn-registrar-usuario';
  
  // FormData automáticamente incluirá el archivo del input 'avatar' 
  // solo si pasó la validación de arriba (porque si falló, limpiamos el input)
  const formData = new FormData(form); 

  toggleLoading(btnId, true, "Registrando...");

  fetch(BASE_URL + 'api/usuarios.php?tipo=registrar', {
      method: 'POST',
      body: formData
  })
  .then(response => response.json())
  .then(data => {
      if (data.estatus === true) {
          Toast.fire({ icon: "success", title: data.mensaje });
          form.reset(); 
          document.getElementById('avatar-preview').src = STATIC_URL + 'img/avatars/default.png'; 
      } else {
          Toast.fire({ icon: "error", title: data.mensaje });
      }
  })
  .catch(error => {
      console.error('Error:', error);
      Toast.fire({ icon: "error", title: "Error en la conexión" });
  })
  .finally(() => {
      toggleLoading(btnId, false);
  });
});

  