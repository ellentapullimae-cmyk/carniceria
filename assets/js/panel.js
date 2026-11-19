/* ============================================================
   LA TRADICION CARNICERIA - Panel JS
   Sidebar, dropdowns, pantalla completa, busqueda global,
   tablas (buscar/paginar) y graficas Chart.js.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  /* ---------- Sidebar ---------- */
  const sidebar = document.getElementById('sidebar');
  const btnSidebar = document.getElementById('btnSidebar');
  if (btnSidebar && sidebar) {
    btnSidebar.addEventListener('click', () => {
      if (window.innerWidth <= 900) {
        sidebar.classList.toggle('movil-abierta');
      } else {
        sidebar.classList.toggle('min');
        localStorage.setItem('sidebarMin', sidebar.classList.contains('min') ? '1' : '0');
        document.dispatchEvent(new CustomEvent('sidebar:toggle'));
      }
    });
    if (localStorage.getItem('sidebarMin') === '1' && window.innerWidth > 900) {
      sidebar.classList.add('min');
    }
  }
  // Cierra sidebar movil al navegar
  document.querySelectorAll('.nav-item').forEach(a =>
    a.addEventListener('click', () => sidebar && sidebar.classList.remove('movil-abierta')));

  /* ---------- Dropdowns (notificaciones / perfil) ---------- */
  const toggles = [
    ['btnNotif', 'cajaNotif'],
    ['btnPerfil', 'cajaPerfil'],
  ];
  toggles.forEach(([btnId, cajaId]) => {
    const btn = document.getElementById(btnId);
    const caja = document.getElementById(cajaId);
    if (!btn || !caja) return;
    btn.addEventListener('click', e => {
      e.stopPropagation();
      document.querySelectorAll('.dropdown-caja.visible').forEach(o => {
        if (o !== caja) o.classList.remove('visible');
      });
      caja.classList.toggle('visible');
    });
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.dropdown')) {
      document.querySelectorAll('.dropdown-caja.visible').forEach(o => o.classList.remove('visible'));
    }
    if (!e.target.closest('.buscador')) {
      const r = document.getElementById('resultadosBusqueda');
      if (r) r.classList.remove('visible');
    }
  });

  /* ---------- Pantalla completa ---------- */
  const btnFull = document.getElementById('btnPantalla');
  if (btnFull) {
    btnFull.addEventListener('click', () => {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
        btnFull.innerHTML = '<i data-lucide="minimize"></i>';
      } else {
        document.exitFullscreen();
        btnFull.innerHTML = '<i data-lucide="maximize"></i>';
      }
      if (window.lucide) lucide.createIcons();
    });
  }

  /* ---------- Buscador global (API real) ---------- */
  const bus = document.getElementById('buscadorGlobal');
  const cajaRes = document.getElementById('resultadosBusqueda');
  let temporizador = null;
  if (bus && cajaRes) {
    bus.addEventListener('input', () => {
      clearTimeout(temporizador);
      const q = bus.value.trim();
      if (q.length < 2) { cajaRes.classList.remove('visible'); return; }
      temporizador = setTimeout(async () => {
        try {
          const r = await fetch('buscar.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
          if (!r.ok) throw 0;
          const datos = await r.json();
          if (!datos.length) {
            cajaRes.innerHTML = '<div class="r-vacio">Sin resultados para "' + escapar(q) + '"</div>';
          } else {
            cajaRes.innerHTML = datos.map(x => `
              <a class="resultado" href="${x.url}">
                <i data-lucide="${x.icono}"></i>
                <span class="r-txt"><b>${escapar(x.titulo)}</b><small>${escapar(x.sub)}</small></span>
                <span class="r-tipo">${x.tipo}</span>
              </a>`).join('');
          }
          cajaRes.classList.add('visible');
          if (window.lucide) lucide.createIcons();
        } catch (e) {
          cajaRes.innerHTML = '<div class="r-vacio">Busqueda no disponible</div>';
          cajaRes.classList.add('visible');
        }
      }, 280);
    });
    bus.addEventListener('focus', () => {
      if (bus.value.trim().length >= 2) cajaRes.classList.add('visible');
    });
  }

  /* ---------- Tablas: buscar + paginar ---------- */
  document.querySelectorAll('[data-tabla]').forEach(env => {
    const tabla = env.querySelector('table');
    const busId = env.dataset.busca;
    const pagEnv = env.parentElement.querySelector('.paginacion');
    const porPagina = parseInt(env.dataset.ppag || '8', 10);
    let pagina = 1;
    const filas = () => Array.from(tabla.tBodies[0].rows);

    function aplicar() {
      const texto = busId ? (document.getElementById(busId)?.value || '').toLowerCase() : '';
      let visibles = filas().filter(f => f.textContent.toLowerCase().includes(texto));
      const total = visibles.length;
      const totalPag = Math.max(1, Math.ceil(total / porPagina));
      if (pagina > totalPag) pagina = totalPag;
      if (pagina < 1) pagina = 1;
      filas().forEach(f => f.style.display = 'none');
      visibles.slice((pagina - 1) * porPagina, pagina * porPagina).forEach(f => f.style.display = '');
      if (pagEnv) {
        const info = pagEnv.querySelector('.pag-info');
        if (info) {
          const ini = total === 0 ? 0 : (pagina - 1) * porPagina + 1;
          info.textContent = `Mostrando ${ini}-${Math.min(pagina * porPagina, total)} de ${total}`;
        }
        pagEnv.querySelectorAll('.pag-btn[data-p]').forEach(b => {
          const p = +b.dataset.p;
          b.classList.toggle('activo', p === pagina);
          b.style.display = p > totalPag ? 'none' : '';
        });
        const prev = pagEnv.querySelector('[data-nav="prev"]');
        const next = pagEnv.querySelector('[data-nav="next"]');
        if (prev) prev.disabled = pagina <= 1;
        if (next) next.disabled = pagina >= totalPag;
      }
    }
    if (busId) document.getElementById(busId)?.addEventListener('input', () => { pagina = 1; aplicar(); });
    pagEnv?.querySelectorAll('.pag-btn[data-p]').forEach(b => b.addEventListener('click', () => {
      pagina = +b.dataset.p; aplicar();
    }));
    pagEnv?.querySelector('[data-nav="prev"]')?.addEventListener('click', () => { pagina--; aplicar(); });
    pagEnv?.querySelector('[data-nav="next"]')?.addEventListener('click', () => { pagina++; aplicar(); });
    aplicar();
  });
});

function escapar(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

/* ============================================================
   Graficas Chart.js (tema premium oscuro)
   ============================================================ */
const TEMA = {
  grid: 'rgba(255,255,255,.07)',
  texto: '#A5A5A5',
  rojo: '#D51019',
  rojoOsc: '#850000',
  oro: '#E8B83F',
};

function estiloEjes(chart) {
  chart.options.plugins.legend.labels.color = TEMA.texto;
  chart.options.plugins.legend.labels.usePointStyle = true;
  chart.options.plugins.tooltip.backgroundColor = '#171717';
  chart.options.plugins.tooltip.borderColor = 'rgba(213,16,25,.5)';
  chart.options.plugins.tooltip.borderWidth = 1;
  chart.options.plugins.tooltip.titleColor = '#fff';
  chart.options.plugins.tooltip.bodyColor = '#A5A5A5';
  chart.options.plugins.tooltip.padding = 10;
  chart.options.plugins.tooltip.cornerRadius = 8;
  if (chart.options.scales) {
    Object.values(chart.options.scales).forEach(esc => {
      if (esc.ticks) esc.ticks.color = TEMA.texto;
      if (esc.grid) { esc.grid.color = TEMA.grid; esc.grid.drawBorder = false; }
    });
  }
  chart.update();
}

function crearGrafica(id, config) {
  const canvas = document.getElementById(id);
  if (!canvas || typeof Chart === 'undefined') return null;
  const chart = new Chart(canvas, config);
  estiloEjes(chart);
  return chart;
}

/** Rellena dias faltantes en la serie de ventas (ultimo n dias). */
function serieDias(datos, dias) {
  const salida = [];
  const hoy = new Date();
  for (let i = dias - 1; i >= 0; i--) {
    const f = new Date(hoy);
    f.setDate(hoy.getDate() - i);
    const clave = f.toISOString().slice(0, 10);
    const fila = datos.find(d => d.fecha === clave);
    salida.push({
      fecha: clave,
      etiqueta: f.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' }),
      total: fila ? parseFloat(fila.total) : 0,
    });
  }
  return salida;
}
