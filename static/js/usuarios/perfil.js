import Toast from '../utils/toast.js';
import { toggleLoading } from '../utils/ui.js';

function enviarFormulario(form, tipo, boton, recargar = false) {
  form.addEventListener('submit', function(event) {
    event.preventDefault();
    toggleLoading(boton, true, 'Guardando...');

    fetch(`${BASE_URL}api/usuarios.php?tipo=${tipo}`, {
      method: 'POST',
      body: new FormData(form)
    })
      .then(response => response.json())
      .then(data => {
        Toast.fire({
          icon: data.estatus ? 'success' : 'error',
          title: data.mensaje
        });
        if (!data.estatus) return;
        if (recargar) {
          window.setTimeout(() => window.location.reload(), 800);
        } else if (form.id === 'form-password') {
          form.reset();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Toast.fire({ icon: 'error', title: 'Error en la conexión' });
      })
      .finally(() => toggleLoading(boton, false));
  });
}

const formularioPerfil = document.getElementById('form-perfil');
if (formularioPerfil) {
  enviarFormulario(formularioPerfil, 'actualizar_perfil', 'btn-guardar-perfil', true);
}

const formularioPassword = document.getElementById('form-password');
if (formularioPassword) {
  const passwordNueva = formularioPassword.elements.password_nueva;
  const passwordConfirmacion = formularioPassword.elements.password_confirmacion;
  const validarConfirmacion = () => {
    passwordConfirmacion.setCustomValidity(passwordNueva.value === passwordConfirmacion.value ? '' : 'Las contraseñas no coinciden');
  };
  passwordNueva.addEventListener('input', validarConfirmacion);
  passwordConfirmacion.addEventListener('input', validarConfirmacion);
  enviarFormulario(formularioPassword, 'actualizar_perfil', 'btn-guardar-password');
}
const formularioFerreteria = document.getElementById('form-ferreteria-activa');
if (formularioFerreteria) {
  enviarFormulario(formularioFerreteria, 'cambiar_ferreteria_activa', 'btn-cambiar-ferreteria', true);
  
  (async function() {
    let ferreteriaIdActual = document.getElementById('perfil-ferreteria').value;
    
    // Esperamos a que carguen y se rendericen las sucursales
    await cargarSucursalesAsync({ target: { value: ferreteriaIdActual } });
    
    // Ahora sí, el elemento ya existe de forma segura
    const selectSucursal = document.getElementById('sucursal_activa');
    if (selectSucursal) {
      selectSucursal.value = perfil_sesion.id_sucursal;
    }
  })();

  document.getElementById('perfil-ferreteria').addEventListener('change', function(event) {
    cargarSucursalesAsync(event);
  });
}

// Modificamos ligeramente la función para que retorne la cadena de promesas
async function cargarSucursalesAsync(event) {
  const ferreteriaId = event.target.value;
  const areaSucursales = document.getElementById('area_sucursales');
  areaSucursales.innerHTML = ''; 

  if (!ferreteriaId) return Promise.resolve();

  try {
    const response = await fetch(`${BASE_URL}api/sucursales.php?tipo=combo_ferreteria&id_ferreteria=${ferreteriaId}`);
    const data = await response.json();
    data.sucursales = data.data;
    if (data.estatus && data.sucursales.length > 0) {
      const select = document.createElement('select');
      select.className = 'form-select';
      select.name = 'sucursal_activa';
      select.id = 'sucursal_activa';
      select.required = true;

      data.sucursales.forEach(sucursal => {
        const option = document.createElement('option');
        option.value = sucursal.id;
        option.textContent = sucursal.nombre;
        select.appendChild(option);
      });

      areaSucursales.appendChild(select);
    } else {
      areaSucursales.textContent = 'No hay sucursales disponibles para esta ferretería.';
    }
  } catch (error) {
    console.error('Error al obtener sucursales:', error);
    areaSucursales.textContent = 'Error al cargar las sucursales.';
  }
}

