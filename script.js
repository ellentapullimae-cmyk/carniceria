// ---------- Menu movil ----------
const navToggle = document.getElementById('navToggle');
const navMenu = document.getElementById('navMenu');

navToggle.addEventListener('click', () => navMenu.classList.toggle('activo'));
navMenu.querySelectorAll('a').forEach(a =>
  a.addEventListener('click', () => navMenu.classList.remove('activo'))
);

// ---------- Toast ----------
const toastEl = document.getElementById('toast');
let toastTimer;
function toast(mensaje) {
  toastEl.textContent = mensaje;
  toastEl.classList.add('visible');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toastEl.classList.remove('visible'), 3200);
}

// ---------- Pedir ahora ----------
document.querySelectorAll('.btn-pedir').forEach(btn =>
  btn.addEventListener('click', () => {
    toast(`"${btn.dataset.producto}" agregado a tu pedido. Te contactaremos por WhatsApp.`);
  })
);

// ---------- Formulario de contacto ----------
const form = document.getElementById('contactoForm');
const nota = document.getElementById('formNota');

form.addEventListener('submit', e => {
  e.preventDefault();
  const nombre = document.getElementById('fNombre').value.trim();
  const telefono = document.getElementById('fTelefono').value.trim();
  const mensaje = document.getElementById('fMensaje').value.trim();

  nota.className = 'form-nota';
  if (!nombre || !telefono || !mensaje) {
    nota.textContent = 'Completa todos los campos antes de enviar.';
    nota.classList.add('err');
    return;
  }
  if (!/^[0-9+\s-]{7,15}$/.test(telefono)) {
    nota.textContent = 'Ingresa un telefono valido (ej: 987 654 321).';
    nota.classList.add('err');
    return;
  }

  nota.textContent = `Gracias ${nombre}! Recibimos tu mensaje y te llamaremos pronto.`;
  nota.classList.add('ok');
  form.reset();
  toast('Mensaje enviado correctamente.');
});

// ---------- Sombra en la cabecera al hacer scroll ----------
const header = document.querySelector('.header');
window.addEventListener('scroll', () => {
  header.style.boxShadow = window.scrollY > 40 ? '0 8px 24px rgba(0,0,0,.3)' : 'none';
});
