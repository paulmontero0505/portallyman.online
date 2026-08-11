<?php
$bcCurrent = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$bcMap = [
  'index.php'       => ['Turno Actual'],
  'tareas.php'      => ['Tareas'],
  'incidencias.php' => ['Control de Campo', 'Incidencias'],
  'reporte_inspeccion.php' => ['Control de Campo', 'Reporte de Inspección'],
  'evaluacion_desempeno.php' => ['Control de Campo', 'Evaluación de Desempeño'],
  'usuarios.php'    => ['Administración', 'Usuarios'],
  'sugerencias.php' => ['Administración', 'Sugerencias Tallyman'],
];
$bcPath = $bcMap[$bcCurrent] ?? [ucwords(str_replace(['_', '.php'], [' ', ''], $bcCurrent))];
?>
<header class="header">
  <button class="btn-toggle" id="btnToggle">
    <div class="hb"><span></span><span></span><span></span></div>
  </button>

  <div class="breadcrumb">
    <span>Estiba</span>
    <span class="bc-sep">›</span>
    <span>Shift Command</span>
    <?php $last = count($bcPath) - 1; foreach ($bcPath as $i => $crumb): ?>
      <span class="bc-sep">›</span>
      <span<?= $i === $last ? ' class="bc-active"' : '' ?>><?= htmlspecialchars($crumb) ?></span>
    <?php endforeach; ?>
  </div>

  <div class="status-chip">
    <span class="dot-green"></span>
    Operativo
  </div>

  <div class="h-actions">
    <div class="h-clock" id="hClock">00:00:00</div>
    <div class="v-div"></div>
    <div class="h-avatar"><?php
      $n = $_SESSION['user_name'] ?? 'U';
      echo strtoupper(substr($n, 0, 1) . (strpos($n, ' ') ? substr($n, strpos($n, ' ') + 1, 1) : ''));
    ?></div>
  </div>
</header>
<script>
(function(){
  function tickClock(){
    var d=new Date(), p=function(n){return String(n).padStart(2,'0');};
    var el=document.getElementById('hClock');
    if(el) el.textContent=p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds());
  }
  tickClock();
  setInterval(tickClock,1000);
})();
</script>
