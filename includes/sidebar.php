<?php
if (session_status() === PHP_SESSION_NONE) @session_start();
$rol = $_SESSION['user_rol'] ?? 'Operador';
$cur = basename($_SERVER['PHP_SELF'] ?? '');
?>

<!-- ══════════ SIDEBAR · Estiba_Turno ══════════ -->
<aside class="sidebar" id="sidebar">

  <div class="sb-brand">
    <div class="brand-logo-wrap">
      <img src="<?= $sb_base ?? '..' ?>/img/logo.jpg" alt="EstibaDeck" style="width:44px;height:44px;object-fit:contain;border-radius:12px;border:2px solid #ffffff;">
    </div>
    <div class="brand-text">
      <span class="brand-name">Tallyman<span> Control</span></span>
      <span class="brand-sub">Shift Command</span>
    </div>
  </div>

  <nav class="sb-nav">

    <!-- DASHBOARD / TURNO ACTUAL -->
    <a href="<?= $sb_base ?? '..' ?>/index.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/>
          <path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/>
        </svg>
      </span>
      <span class="nav-label">Turno Actual</span>
      <span class="tip">Turno Actual</span>
    </a>
    <?php if ($rol === 'Administrador'): ?>
    <a href="<?= $sb_base ?? '..' ?>/pages/reporte_puntualidad.php" class="nav-item<?= ($cur === 'reporte_puntualidad.php') ? ' active' : '' ?>">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg></span>
      <span class="nav-label">Reporte de turno</span>
      <span class="tip">Reporte de turno</span>
    </a>
    <a href="<?= $sb_base ?? '..' ?>/pages/reporte_tardanzas_charlas.php" class="nav-item<?= ($cur === 'reporte_tardanzas_charlas.php') ? ' active' : '' ?>">
      <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/><path d="M12 3v2"/></svg></span>
      <span class="nav-label">Tardanzas en charlas</span>
      <span class="tip">Tardanzas en charlas</span>
    </a>
    <?php endif; ?>

    <?php if (in_array($rol, ['Administrador', 'Supervisor', 'Coordinador', 'Soporte'], true)): ?>
    <!-- TAREAS · primer nivel a propósito: es lo primero que abre un
         asignado cada día, no un submódulo de Control de Campo. -->
    <a href="<?= $sb_base ?? '..' ?>/pages/tareas.php" class="nav-item<?= ($cur === 'tareas.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 11l3 3 8-8"/>
          <path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>
        </svg>
      </span>
      <span class="nav-label">Tareas</span>
      <span class="tip">Tareas</span>
    </a>
    <?php endif; ?>

    <?php if (in_array($rol, ['Administrador', 'Supervisor', 'Coordinador'], true)): ?>
    <!-- OPERACIÓN · Coordinador / Supervisor / Administrador -->

    <!-- CONTROL DE CAMPO (grupo) · contiene Incidencias -->
    <?php $ccActive = in_array($cur, ['incidencias.php', 'reporte_inspeccion.php', 'evaluacion_desempeno.php', 'evades.php', 'asistencias.php', 'capacitaciones.php', 'reconocimientos.php', 'record_personal_tallyman.php'], true); ?>
    <a href="#" class="nav-item nav-toggle<?= $ccActive ? ' open' : '' ?>" data-submenu="submenu-cc">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="8" y="2" width="8" height="4" rx="1"/>
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
          <path d="M9 12h6M9 16h4"/>
        </svg>
      </span>
      <span class="nav-label">Control de Campo</span>
      <span class="nav-chevron">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </span>
      <span class="tip">Control de Campo</span>
    </a>
    <div class="submenu<?= $ccActive ? ' open' : '' ?>" id="submenu-cc">
      <a href="<?= $sb_base ?? '..' ?>/pages/incidencias.php" class="sub-item sub-item--icon<?= ($cur === 'incidencias.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </span>
        <span class="sub-label">Incidencias</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/reporte_inspeccion.php" class="sub-item sub-item--icon<?= ($cur === 'reporte_inspeccion.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 11l3 3L22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </span>
        <span class="sub-label">Reporte de Inspección</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/evaluacion_desempeno.php" class="sub-item sub-item--icon<?= ($cur === 'evaluacion_desempeno.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 20v-6M6 20V10M18 20V4"/>
          </svg>
        </span>
        <span class="sub-label">Evaluación Diaria</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/evades.php" class="sub-item sub-item--icon<?= ($cur === 'evades.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/>
          </svg>
        </span>
        <span class="sub-label">Evaluación de Desempeño</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/asistencias.php" class="sub-item sub-item--icon<?= ($cur === 'asistencias.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M16 11l2 2 4-4"/>
          </svg>
        </span>
        <span class="sub-label">Charlas</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/capacitaciones.php" class="sub-item sub-item--icon<?= ($cur === 'capacitaciones.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 9 12 4 2 9l10 5 10-5z"/>
            <path d="M6 11.5V17c0 1.1 2.7 2.5 6 2.5s6-1.4 6-2.5v-5.5"/>
            <path d="M22 9v6"/>
          </svg>
        </span>
        <span class="sub-label">Capacitaciones</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/reconocimientos.php" class="sub-item sub-item--icon<?= ($cur === 'reconocimientos.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="6"/>
            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
          </svg>
        </span>
        <span class="sub-label">Reconocimiento Tally</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/record_personal_tallyman.php" class="sub-item sub-item--icon<?= ($cur === 'record_personal_tallyman.php') ? ' active' : '' ?>">
        <span class="sub-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/><path d="M17 11h4M19 9v4"/></svg></span>
        <span class="sub-label">Récord personal</span>
      </a>
    </div>

    <!-- OPERACIONES (grupo) · Naves (programado/actual) + Histórico de naves -->
    <?php $opActive = (strpos($cur, 'operaciones') === 0); ?>
    <a href="#" class="nav-item nav-toggle<?= $opActive ? ' open' : '' ?>" data-submenu="submenu-op">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 22h18"/><path d="M5 22V10l7-5 7 5v12"/><path d="M9 22v-6h6v6"/>
        </svg>
      </span>
      <span class="nav-label">Operaciones</span>
      <span class="tip">Operaciones</span>
    </a>
    <div class="submenu<?= $opActive ? ' open' : '' ?>" id="submenu-op">
      <a href="<?= $sb_base ?? '..' ?>/pages/operaciones.php" class="sub-item sub-item--icon<?= ($cur === 'operaciones.php' || $cur === 'operaciones_nave.php' || $cur === 'operaciones_campos.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 20a6 6 0 0 0 3-1 6 6 0 0 0 7 0 6 6 0 0 0 7 0 6 6 0 0 0 3 1"/>
            <path d="M4 18l-2-6h20l-2 6"/><path d="M12 12V4"/><path d="M8 6h8"/>
          </svg>
        </span>
        <span class="sub-label">Naves</span>
      </a>
      <a href="<?= $sb_base ?? '..' ?>/pages/operaciones_naves.php" class="sub-item sub-item--icon<?= ($cur === 'operaciones_naves.php') ? ' active' : '' ?>">
        <span class="sub-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>
            <path d="M8 14h2"/><path d="M14 14h2"/><path d="M8 18h2"/><path d="M14 18h2"/>
          </svg>
        </span>
        <span class="sub-label">Histórico de naves</span>
      </a>
    </div>

    <a href="<?= $sb_base ?? '..' ?>/pages/tallyman.php" class="nav-item<?= ($cur === 'tallyman.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/>
          <path d="M9 12l2 2 4-4"/>
        </svg>
      </span>
      <span class="nav-label">Registro tallyman</span>
      <span class="tip">Registro tallyman</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/tallyman_relevo.php" class="nav-item<?= (strpos($cur, 'tallyman_relevo') === 0) ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>
        </svg>
      </span>
      <span class="nav-label">Relevo de turno</span>
      <span class="tip">Relevo de turno</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/indicadores.php" class="nav-item<?= ($cur === 'indicadores.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>
        </svg>
      </span>
      <span class="nav-label">Indicadores</span>
      <span class="tip">Indicadores</span>
    </a>
    <?php endif; ?>

    <?php if ($rol === 'Administrador'): ?>
    <!-- ADMINISTRACIÓN · solo Administrador -->
    <div class="nav-divider"></div>
    <span class="nav-sep">Administración</span>

    <a href="<?= $sb_base ?? '..' ?>/pages/colaboradores.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </span>
      <span class="nav-label">Colaboradores</span>
      <span class="tip">Colaboradores</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/jornadas.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>
        </svg>
      </span>
      <span class="nav-label">Jornadas</span>
      <span class="tip">Jornadas</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/usuarios.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="4"/>
          <path d="M4 21v-1a8 8 0 0 1 16 0v1"/>
        </svg>
      </span>
      <span class="nav-label">Usuarios</span>
      <span class="tip">Usuarios</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/sugerencias.php" class="nav-item<?= ($cur === 'sugerencias.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
        </svg>
      </span>
      <span class="nav-label">Sugerencias Tallyman</span>
      <span class="tip">Sugerencias Tallyman</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/whatsapp.php" class="nav-item<?= ($cur === 'whatsapp.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/><path d="M8.5 9.2c.3 2.2 2.1 4 4.3 4.3"/></svg>
      </span>
      <span class="nav-label">WhatsApp</span>
      <span class="tip">WhatsApp</span>
    </a>
    <?php endif; ?>

    <div class="nav-divider"></div>
    <span class="nav-sep">Sesión</span>

    <a href="#" class="nav-item" id="btnLogout">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </span>
      <span class="nav-label">Cerrar sesión</span>
      <span class="tip">Cerrar sesión</span>
    </a>

  </nav>

  <div class="sb-footer">
    <div class="user-card">
      <div class="user-avatar">
        <div class="avatar-circle"><?php
          $n = $_SESSION['user_name'] ?? 'U';
          echo strtoupper(substr($n, 0, 1) . substr($n, strpos($n, ' ') ? strpos($n, ' ') + 1 : 0, 1));
        ?></div>
      </div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></span>
        <span class="user-role"><?= htmlspecialchars($_SESSION['user_rol'] ?? 'Invitado') ?></span>
      </div>
      <div class="user-chevron-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
          stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>
    </div>
  </div>

</aside>

<script>
document.addEventListener("DOMContentLoaded", function () {

  const sidebar = document.getElementById("sidebar");
  const overlay = document.querySelector(".overlay");
  const btn = document.getElementById("btnToggle");

  /* ── Active item by URL (solo enlaces reales, no los toggles de grupo) ── */
  const currentPath = window.location.pathname.split("/").pop();
  document.querySelectorAll('.nav-item').forEach(link => {
    if (link.classList.contains('nav-toggle')) return; // los grupos no se marcan por URL
    const href = link.getAttribute('href');
    if (href && href !== '#' && href.indexOf(currentPath) !== -1 && currentPath !== '') {
      link.classList.add('active');
    }
  });

  /* ── Grupos expandibles (Control de Campo, etc.) ── */
  document.querySelectorAll('.nav-toggle').forEach(t => {
    t.addEventListener('click', e => {
      e.preventDefault();
      // En sidebar colapsado, primero se expande el sidebar para poder ver el submenú.
      if (sidebar.classList.contains('col')) {
        sidebar.classList.remove('col');
        localStorage.setItem('sidebar', 'open');
      }
      const sm = document.getElementById(t.getAttribute('data-submenu'));
      t.classList.toggle('open');
      if (sm) sm.classList.toggle('open');
    });
  });

  /* ── Sidebar collapse/expand ── */
  if (btn) {
    btn.addEventListener("click", function () {
      if (window.innerWidth <= 1024) {
        sidebar.classList.toggle("mob-open");
        if (overlay) overlay.classList.toggle("show");
      } else {
        sidebar.classList.toggle("col");
        localStorage.setItem("sidebar", sidebar.classList.contains("col") ? "col" : "open");
      }
    });
  }
  if (localStorage.getItem("sidebar") === "col") sidebar.classList.add("col");

  if (overlay) {
    overlay.addEventListener("click", function () {
      sidebar.classList.remove("mob-open");
      overlay.classList.remove("show");
    });
  }

  /* ── Logout ── */
  const btnLogout = document.getElementById('btnLogout');
  if (btnLogout) {
    btnLogout.addEventListener('click', async function (e) {
      e.preventDefault();
      if (!confirm('¿Estás seguro de que deseas cerrar sesión?')) return;
      try {
        await fetch('<?= $sb_base ?? '..' ?>/api/logout.php');
        sessionStorage.clear();
        window.location.href = '<?= $sb_base ?? '..' ?>/login.php';
      } catch (e) {
        window.location.href = '<?= $sb_base ?? '..' ?>/login.php';
      }
    });
  }
});
</script>
