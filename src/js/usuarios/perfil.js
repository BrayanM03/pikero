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
}