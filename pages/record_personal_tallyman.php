<?php require_once('../includes/auth.php'); require_report(); ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Record Personal Tallyman · Tallyman Control</title><link rel="icon" href="../img/logo.jpg"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet"><link rel="stylesheet" href="../css/header.css"><link rel="stylesheet" href="../css/sidebar.css"><link rel="stylesheet" href="../css/layout.css"><link rel="stylesheet" href="../css/ui.css">
<style>
.rp{--g:#00875a;--d:#005c3d;--i:#18251f;--m:#62736b;--l:#dbe8e1;font-family:'DM Sans',sans-serif;color:var(--i);display:grid;gap:18px}.rp *{box-sizing:border-box}.rp-hero{background:#005c3d;color:#fff;border-radius:18px;padding:27px 30px;display:flex;gap:22px;align-items:center;justify-content:space-between}.rp-tag{font-size:10px;letter-spacing:.11em;font-weight:800;color:#b8ead0}.rp h1{font-size:25px;margin:5px 0 7px}.rp-hero p{margin:0;font-size:13px;line-height:1.5;color:#d9f3e5;max-width:610px}.rp-search{min-width:330px;padding:8px;background:#fff;border-radius:13px;display:flex;gap:8px}.rp-search select{flex:1;min-width:0;border:0;padding:8px;font:600 13px inherit}.rp-search button{border:0;background:var(--g);color:#fff;border-radius:9px;padding:9px 13px;font:700 12px inherit;cursor:pointer}.rp-search button:disabled{opacity:.6}.rp-empty,.rp-profile,.rp-section,.rp-kpi{background:#fff;border:1px solid var(--l);border-radius:15px}.rp-empty{padding:40px;text-align:center;color:var(--m)}.rp-empty b{display:block;color:var(--i);font-size:16px;margin-bottom:6px}.hide{display:none!important}.rp-profile{padding:19px 21px;display:grid;grid-template-columns:1.1fr 1fr;gap:20px}.rp-person{display:flex;gap:14px;align-items:center}.rp-avatar{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;background:#e7f5ed;color:var(--d);font-weight:800;font-size:19px}.rp-person h2{margin:0 0 3px;font-size:19px}.rp-person p,.rp-meta{margin:0;color:var(--m);font-size:13px}.rp-data{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.rp-data div{background:#f7fbf8;border-radius:8px;padding:9px 10px}.rp-data b{display:block;color:var(--m);font-size:10px}.rp-data span{font-size:13px;font-weight:700}.rp-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}.rp-kpi{padding:13px}.rp-n{font-size:25px;color:var(--d);font-weight:800}.rp-l{font-size:10px;font-weight:800;color:var(--m);text-transform:uppercase;line-height:1.3}.rp-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.rp-section{overflow:hidden}.rp-head{padding:15px 18px;border-bottom:1px solid var(--l);display:flex;justify-content:space-between;align-items:center}.rp-head h3{font-size:15px;margin:0}.rp-count,.rp-chip{border-radius:999px;padding:3px 8px;font-size:10px;font-weight:800}.rp-count{background:#e7f5ed;color:var(--d)}.rp-list{list-style:none;margin:0;padding:0}.rp-list li{padding:13px 18px;border-bottom:1px solid #edf3f0}.rp-list li:last-child{border:0}.rp-line{display:flex;justify-content:space-between;gap:10px;font-size:13px;font-weight:700}.rp-sub{margin-top:4px;color:var(--m);font-size:12px;line-height:1.4}.rp-chip{white-space:nowrap;background:#f1f5f3;color:#4e6258}.impacto-alto,.impacto-critico,.estado-rechazado{background:#feeceb;color:#a12b26}.impacto-moderado,.estado-pendiente{background:#fff5df;color:#9a6500}.impacto-bajo,.impacto-minimo,.estado-aprobado{background:#e8f6ef;color:#187247}.rp-none{color:var(--m);font-size:13px!important}@media(max-width:1100px){.rp-hero{align-items:stretch;flex-direction:column}.rp-search{min-width:0}.rp-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.content{padding:17px}.rp-profile,.rp-grid{grid-template-columns:1fr}.rp-data{grid-template-columns:1fr}.rp-kpis{grid-template-columns:repeat(2,1fr)}.rp-search{flex-direction:column}.rp-hero{padding:22px}}
</style><style>
  @media (max-width:760px) {
    html,body { max-width:100%; overflow-x:hidden; }
    .shell,.main-area,.content,.rp,#result { min-width:0!important; max-width:100%!important; }
    .main-area { width:100%!important; }
    .content { box-sizing:border-box; width:100%!important; padding:14px!important; overflow-x:hidden!important; }
    .rp { width:100%; gap:14px; }
    .rp-hero { width:100%; min-width:0; padding:20px; gap:16px; border-radius:16px; }
    .rp-hero > div { min-width:0; }
    .rp h1 { font-size:clamp(22px,7vw,27px); line-height:1.12; overflow-wrap:anywhere; }
    .rp-hero p { font-size:13px; overflow-wrap:anywhere; }
    .rp-search { width:100%; min-width:0!important; padding:7px; gap:7px; }
    .rp-search select,.rp-search button { width:100%; min-width:0; min-height:44px; }
    .rp-search select { font-size:13px; text-overflow:ellipsis; }
    .rp-search button { font-size:13px; }
    .rp-profile { width:100%; min-width:0; padding:15px; gap:15px; }
    .rp-person { min-width:0; align-items:flex-start; }
    .rp-person > div:last-child { min-width:0; }
    .rp-avatar { flex:0 0 52px; }
    .rp-person h2 { font-size:18px; line-height:1.25; overflow-wrap:anywhere; }
    .rp-person p { line-height:1.45; overflow-wrap:anywhere; }
    .rp-data { width:100%; min-width:0; gap:8px; }
    .rp-data div { min-width:0; }
    .rp-data span { overflow-wrap:anywhere; }
    .rp-kpis { width:100%; min-width:0; grid-template-columns:repeat(2,minmax(0,1fr))!important; gap:10px; }
    .rp-kpi { min-width:0; padding:13px 12px; }
    .rp-n { font-size:24px; }
    .rp-l { overflow-wrap:anywhere; }
    .rp-grid { width:100%; min-width:0; gap:14px; }
    .rp-section { min-width:0; }
    .rp-head { padding:14px; }
    .rp-head h3 { min-width:0; overflow-wrap:anywhere; }
    .rp-list li { padding:12px 14px; }
    .rp-line { align-items:flex-start; }
    .rp-line > span:first-child { min-width:0; overflow-wrap:anywhere; }
    .rp-chip { flex:0 0 auto; }
    .rp-sub { overflow-wrap:anywhere; }
  }
  @media (max-width:380px) {
    .content { padding:12px!important; }
    .rp-hero { padding:18px; }
    .rp-person { gap:11px; }
    .rp-avatar { flex-basis:46px; width:46px; height:46px; }
    .rp-kpi { padding:12px 10px; }
  }
</style><style>
  .rp-hero { padding:30px 34px; }
  .rp-search-wrap { position:relative; width:min(530px,100%); }
  .rp-search { width:100%; min-width:0; padding:7px; border:1px solid rgba(255,255,255,.5); box-shadow:0 10px 24px rgba(0,42,28,.16); }
  .rp-search input { flex:1; min-width:0; border:0; outline:0; padding:10px 12px; color:var(--i); font:600 14px inherit; background:transparent; }
  .rp-search input::placeholder { color:#718078; }
  .rp-suggestions { position:absolute; z-index:20; top:calc(100% + 8px); left:0; right:0; max-height:270px; overflow:auto; background:#fff; border:1px solid #cfe2d8; border-radius:12px; box-shadow:0 14px 32px rgba(10,48,31,.2); padding:6px; }
  .rp-suggestion { width:100%; display:flex; align-items:center; gap:10px; border:0; border-radius:8px; padding:10px; background:#fff; color:var(--i); text-align:left; cursor:pointer; font:inherit; }
  .rp-suggestion:hover,.rp-suggestion:focus { background:#edf8f2; outline:0; }
  .rp-suggestion-mark { width:32px; height:32px; display:grid; place-items:center; flex:0 0 auto; border-radius:9px; background:#e5f5ec; color:var(--d); font-size:11px; font-weight:800; }
  .rp-suggestion b { display:block; font-size:13px; }.rp-suggestion small { display:block; margin-top:2px; color:var(--m); font-size:11px; }
  .rp-performance { grid-column:1/-1; overflow:hidden; }
  .rp-performance-body { display:grid; grid-template-columns:minmax(300px,1fr) minmax(300px,.9fr); gap:14px; padding:14px 18px; }
  .rp-chart-wrap { min-width:0; }.rp-chart { width:100%; min-height:180px; display:block; overflow:visible; }
  .rp-chart-empty { min-height:180px; display:grid; place-items:center; color:var(--m); font-size:13px; background:#f7fbf8; border-radius:10px; }
  .rp-chart-grid { stroke:#dbe8e1; stroke-width:1; }.rp-chart-axis { fill:#718078; font:10px 'DM Sans',sans-serif; }.rp-chart-bar { fill:#a7dec0; }.rp-chart-line { fill:none; stroke:#00875a; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; }.rp-chart-dot { fill:#005c3d; stroke:#fff; stroke-width:2; }.rp-chart-score { fill:#005c3d; font:700 10px 'DM Sans',sans-serif; text-anchor:middle; }
  .rp-performance-notes { display:flex; flex-direction:column; gap:8px; max-height:220px; overflow:auto; padding-right:3px; }
  .rp-performance-note { padding:10px 11px; border-radius:10px; background:#f7fbf8; border:1px solid #e1eee7; }
  .rp-performance-note-head { display:flex; justify-content:space-between; gap:10px; font-size:12px; font-weight:800; color:var(--d); }.rp-performance-note p { margin:6px 0 0; color:#52645b; font-size:12px; line-height:1.45; }
  @media (max-width:900px) { .rp-performance-body { grid-template-columns:1fr; }.rp-performance-notes { max-height:none; }.rp-search-wrap { width:100%; } }
  @media (max-width:760px) { .rp-hero { flex-direction:column; align-items:stretch; }.rp-search-wrap { min-width:0; }.rp-performance-body { padding:14px; }.rp-chart { min-height:180px; } }
</style></head><body><div class="overlay" id="overlay"></div><div class="shell"><?php $sb_base='..';include('../includes/sidebar.php');?><div class="main-area"><?php include('../includes/header.php');?><main class="content"><div class="rp">
<section class="rp-hero"><div><div class="rp-tag">Control de campo · Consulta individual</div><h1>Record Personal Tallyman</h1><p>Consulta el historial operativo, disciplinario, participativo y de reconocimientos de cada tallyman.</p></div><div class="rp-search-wrap"><div class="rp-search"><input id="persona" placeholder="Escribe nombre o código…" autocomplete="off" disabled><button id="buscar" disabled>Consultar récord</button></div><div class="rp-suggestions hide" id="sugerenciasPersonas" role="listbox" aria-label="Tallyman filtrados"></div></div></section>
<section class="rp-empty" id="empty"><b>Selecciona un tallyman</b>El record se mostrará con información consolidada de los módulos existentes.</section>
<div id="result" class="hide"><section class="rp-profile"><div class="rp-person"><div class="rp-avatar" id="avatar">—</div><div><h2 id="nombre">—</h2><p id="cargo">—</p></div></div><div class="rp-data"><div><b>COORDINADOR</b><span id="coord">Sin asignar</span></div><div><b>PRÓXIMO CUMPLEAÑOS</b><span id="cumple">Sin registrar</span></div><div><b>ANTIGÜEDAD</b><span id="ingreso">Sin registrar</span></div></div></section><section class="rp-kpis" id="kpis"></section><div class="rp-grid">
<section class="rp-section rp-performance"><div class="rp-head"><h3>Evolución de desempeño</h3><span class="rp-count" id="c-evaluacionesDesempeno">0</span></div><div class="rp-performance-body"><div class="rp-chart-wrap" id="graficoDesempeno"></div><div class="rp-performance-notes" id="notasDesempeno"></div></div></section><section class="rp-section"><div class="rp-head"><h3>Incidencias</h3><span class="rp-count" id="c-incidencias">0</span></div><ul class="rp-list" id="l-incidencias"></ul></section><section class="rp-section"><div class="rp-head"><h3>Sanciones disciplinarias</h3><span class="rp-count" id="c-sanciones">0</span></div><ul class="rp-list" id="l-sanciones"></ul></section><section class="rp-section"><div class="rp-head"><h3>Propuestas de mejora reconocidas</h3><span class="rp-count" id="c-propuestas">0</span></div><ul class="rp-list" id="l-propuestas"></ul></section><section class="rp-section"><div class="rp-head"><h3>Reconocimientos</h3><span class="rp-count" id="c-reconocimientos">0</span></div><ul class="rp-list" id="l-reconocimientos"></ul></section><section class="rp-section"><div class="rp-head"><h3>Charlas preoperativas</h3><span class="rp-count" id="c-charlas">0</span></div><ul class="rp-list" id="l-charlas"></ul></section><section class="rp-section"><div class="rp-head"><h3>Capacitaciones</h3><span class="rp-count" id="c-capacitaciones">0</span></div><ul class="rp-list" id="l-capacitaciones"></ul></section>
</div></div></div></main></div></div>
<script>
const $=x=>document.getElementById(x),esc=x=>String(x??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])),dt=x=>{const m=String(x??'').match(/^(\\d{4}-\\d{2}-\\d{2})/);if(!m)return 'Sin fecha';const d=new Date(m[1]+'T00:00:00');return Number.isNaN(d.getTime())?'Sin fecha':new Intl.DateTimeFormat('es-PE',{day:'2-digit',month:'short',year:'numeric'}).format(d)},pl=(n,a,b)=>n+' '+(n===1?a:b),chip=(x,c='')=>'<span class="rp-chip '+c+'">'+esc(x)+'</span>';
function lista(tipo,filas,fn){$('c-'+tipo).textContent=filas.length;$('l-'+tipo).innerHTML=filas.length?filas.map(fn).join(''):'<li class="rp-none">No hay registros para este tallyman.</li>'}
function mostrar(d){let p=d.perfil,r=d.resumen,h=d.historial;$('empty').classList.add('hide');$('result').classList.remove('hide');$('avatar').textContent=(p.nombre||'—').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase();$('nombre').textContent=p.nombre||'—';$('cargo').textContent=[p.codigo,p.funcion_principal,p.cuadrilla].filter(Boolean).join(' · ');$('coord').textContent=p.coordinador_nombre||'Sin asignar';$('cumple').textContent=p.dias_para_cumpleanos===null?'Sin registrar':pl(p.dias_para_cumpleanos,'día','días');$('ingreso').textContent=p.dias_desde_ingreso===null?'Sin registrar':pl(p.dias_desde_ingreso,'día','días');$('kpis').innerHTML=[['Incidencias',r.incidencias],['Sanciones',r.sanciones],['Propuestas con puntaje',r.propuestas],['Reconocimientos aprobados',r.reconocimientos_aprobados],['Charlas',r.charlas],['Capacitaciones',r.capacitaciones]].map(x=>'<div class="rp-kpi"><div class="rp-n">'+x[1]+'</div><div class="rp-l">'+x[0]+'</div></div>').join('');
lista('incidencias',h.incidencias,x=>'<li><div class="rp-line"><span>'+esc(x.punto_mejorar)+'</span>'+chip(x.impacto,'impacto-'+x.impacto)+'</div><div class="rp-sub">'+esc(x.competencia)+' · '+dt(x.fecha)+(x.zona_trabajo?' · '+esc(x.zona_trabajo):'')+'</div></li>');lista('sanciones',h.sanciones,x=>'<li><div class="rp-line"><span>'+esc(x.tipo_sancion.replaceAll('_',' '))+'</span>'+chip(x.impacto,'impacto-'+x.impacto)+'</div><div class="rp-sub">'+esc(x.punto_mejorar)+' · '+dt(x.fecha_incidencia)+(Number(x.dias_sancion)>0?' · '+pl(Number(x.dias_sancion),'día','días'):'')+'</div></li>');lista('propuestas',h.propuestas,x=>'<li><div class="rp-line"><span>Propuesta reconocida</span>'+chip(x.puntaje+' pts','estado-aprobado')+'</div><div class="rp-sub">'+esc(x.detalle)+' · '+dt(x.puntaje_at||x.created_at)+(x.puntaje_comentario?' · '+esc(x.puntaje_comentario):'')+'</div></li>');lista('reconocimientos',h.reconocimientos,x=>'<li><div class="rp-line"><span>'+esc(x.competencia)+'</span>'+chip(x.estado,'estado-'+x.estado)+'</div><div class="rp-sub">'+esc(x.concepto)+' · '+dt(x.fecha)+'</div></li>');lista('charlas',h.charlas,x=>'<li><div class="rp-line"><span>'+esc(x.tema)+'</span>'+chip(x.estado==='tardanza'?'Tardanza':'Asistió',x.estado==='tardanza'?'estado-pendiente':'estado-aprobado')+'</div><div class="rp-sub">'+dt(x.fecha)+' · '+esc(x.tipo_reunion.replaceAll('_',' '))+(x.capacitador?' · '+esc(x.capacitador):'')+'</div></li>');lista('capacitaciones',h.capacitaciones,x=>'<li><div class="rp-line"><span>'+esc(x.titulo)+'</span>'+chip(x.estado_asistencia==='tardanza'?'Tardanza':'Asistió',x.estado_asistencia==='tardanza'?'estado-pendiente':'estado-aprobado')+'</div><div class="rp-sub">'+dt(x.fecha)+' · '+esc(x.estado_capacitacion.replaceAll('_',' '))+(x.duracion_min?' · '+x.duracion_min+' min':'')+'</div></li>')}
let personas=[];async function consultar(){const texto=$('persona').value.trim().toLowerCase();const persona=personas.find(x=>x.label.toLowerCase()===texto||x.nombre.toLowerCase()===texto);if(!persona)return;let b=$('buscar');b.disabled=true;b.textContent='Consultando…';try{let r=await fetch('../api/get_record_personal_tallyman.php?colaborador_id='+encodeURIComponent(persona.id)),d=await r.json();if(!r.ok||!d.success)throw Error(d.error||'No se pudo consultar el record.');mostrar(d)}catch(e){$('empty').classList.remove('hide');$('empty').innerHTML='<b>No se pudo mostrar el record</b>'+esc(e.message);$('result').classList.add('hide')}finally{b.disabled=false;b.textContent='Consultar record'}}
async function cargar(){try{let r=await fetch('../api/get_record_personal_tallyman.php?lista=1'),d=await r.json();if(!d.success)throw Error(d.error);personas=d.data.map(x=>({...x,label:x.nombre+(x.codigo?' · '+x.codigo:'')}));$('persona').disabled=false;$('buscar').disabled=false}catch(e){$('persona').placeholder='No fue posible cargar el personal';$('empty').innerHTML='<b>No se pudo cargar el personal</b>'+esc(e.message)}}$('buscar').onclick=consultar;$('persona').addEventListener('change',consultar);$('persona').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();consultar()}});cargar();
</script><script>
  const mostrarRecordBase = mostrar;
  mostrar = function(data) {
    mostrarRecordBase(data);
    const evaluaciones = data.historial.evaluacionesDesempeno || [];
    renderDesempeno(evaluaciones);
    $('kpis').insertAdjacentHTML('beforeend', '<div class="rp-kpi"><div class="rp-n">' + evaluaciones.length + '</div><div class="rp-l">Evaluaciones de desempeño</div></div>');
    const asistio = data.resumen.asistencias_charlas || 0;
    const tardanza = data.resumen.tardanzas_charlas || 0;
    const falta = data.resumen.faltas_charlas || 0;
    $('kpis').insertAdjacentHTML('beforeend', '<div class="rp-kpi"><div class="rp-n">' + asistio + '</div><div class="rp-l">Charlas asistidas</div></div><div class="rp-kpi"><div class="rp-n">' + tardanza + '</div><div class="rp-l">Tardanzas en charlas</div></div><div class="rp-kpi"><div class="rp-n">' + falta + '</div><div class="rp-l">Faltas en charlas</div></div>');
    const incidenciasCharlas = data.historial.charlas.filter(x => x.estado === 'tardanza' || x.estado === 'falta');
    $('c-charlas').textContent = asistio + tardanza;
    $('l-charlas').innerHTML = incidenciasCharlas.length ? incidenciasCharlas.map(x => {
      const estado = x.estado === 'tardanza' ? 'Tardanza' : x.estado === 'falta' ? 'Falta' : 'Asistió';
      const clase = x.estado === 'falta' ? 'impacto-critico' : x.estado === 'tardanza' ? 'estado-pendiente' : 'estado-aprobado';
      return '<li><div class="rp-line"><span>' + esc(x.tema) + '</span>' + chip(estado, clase) + '</div><div class="rp-sub">' + dt(x.fecha) + ' · ' + esc(x.tipo_reunion.replaceAll('_',' ')) + (x.capacitador ? ' · ' + esc(x.capacitador) : '') + '</div></li>';
    }).join('') : '<li class="rp-none">Asistió a ' + (asistio + tardanza) + ' charla(s). No registra tardanzas ni faltas.</li>';
  };
</script><script>
  function ordenarPeriodos(rows) {
    return [...rows].sort((a, b) => String(a.periodo || '').localeCompare(String(b.periodo || '')) || String(a.fecha_evaluacion || '').localeCompare(String(b.fecha_evaluacion || '')));
  }
  function renderDesempeno(rows) {
    const evaluaciones = ordenarPeriodos(rows || []);
    $('c-evaluacionesDesempeno').textContent = evaluaciones.length;
    if (!evaluaciones.length) {
      $('graficoDesempeno').innerHTML = '<div class="rp-chart-empty">No hay evaluaciones de desempeño registradas.</div>';
      $('notasDesempeno').innerHTML = '<div class="rp-chart-empty">Cuando se registre una evaluación, aquí aparecerán el evaluador y los aspectos de mejora.</div>';
      return;
    }
    const width = Math.max(420, evaluaciones.length * 88);
    const height = 180, left = 34, right = 14, top = 22, bottom = 30, chartHeight = height - top - bottom;
    const step = evaluaciones.length === 1 ? 0 : (width - left - right) / (evaluaciones.length - 1);
    const scoreY = score => top + (100 - Math.max(0, Math.min(100, Number(score) || 0))) * chartHeight / 100;
    const points = evaluaciones.map((row, index) => ({ x: evaluaciones.length === 1 ? width / 2 : left + index * step, y: scoreY(row.puntaje_total), score: Number(row.puntaje_total || 0) }));
    const grid = [0, 25, 50, 75, 100].map(score => { const y = scoreY(score); return `<line class="rp-chart-grid" x1="${left}" y1="${y}" x2="${width-right}" y2="${y}"/><text class="rp-chart-axis" x="4" y="${y + 3}">${score}</text>`; }).join('');
    const bars = points.map(point => `<rect class="rp-chart-bar" x="${point.x - 12}" y="${point.y}" width="24" height="${top + chartHeight - point.y}" rx="4"/>`).join('');
    const line = points.map(point => `${point.x},${point.y}`).join(' ');
    const labels = evaluaciones.map((row, index) => `<text class="rp-chart-axis" x="${points[index].x}" y="${height - 13}" text-anchor="middle">${esc(row.periodo || '—')}</text>`).join('');
    const dots = points.map(point => `<circle class="rp-chart-dot" cx="${point.x}" cy="${point.y}" r="5"/><text class="rp-chart-score" x="${point.x}" y="${point.y - 10}">${point.score.toFixed(0)}</text>`).join('');
    $('graficoDesempeno').innerHTML = `<svg class="rp-chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="Notas de desempeño por período"><title>Notas de desempeño por período</title>${grid}${bars}<polyline class="rp-chart-line" points="${line}"/>${dots}${labels}</svg>`;
    $('notasDesempeno').innerHTML = evaluaciones.map(row => `<article class="rp-performance-note"><div class="rp-performance-note-head"><span>${esc(row.periodo || 'Sin período')} · ${Number(row.puntaje_total || 0).toFixed(1)}/100</span><span>${dt(row.fecha_evaluacion)}</span></div><p><b>Evaluó:</b> ${esc(row.coordinador_nombre || 'Sin registrar')}</p><p><b>Aspectos a mejorar:</b> ${esc(row.aspectos_mejora || 'Sin observaciones registradas.')}</p></article>`).join('');
  }
  function mostrarSugerencias() {
    const input = $('persona'), panel = $('sugerenciasPersonas');
    const query = input.value.trim().toLowerCase();
    if (!query) { panel.classList.add('hide'); panel.innerHTML = ''; return; }
    const matches = personas.filter(persona => [persona.nombre, persona.codigo].some(value => String(value || '').toLowerCase().includes(query))).slice(0, 8);
    if (!matches.length) { panel.innerHTML = '<div class="rp-suggestion"><span>No se encontraron tallyman con ese criterio.</span></div>'; panel.classList.remove('hide'); return; }
    panel.innerHTML = matches.map(persona => `<button type="button" class="rp-suggestion" data-persona-id="${persona.id}" role="option"><span class="rp-suggestion-mark">${esc((persona.nombre || '?').split(/\s+/).slice(0, 2).map(part => part[0]).join(''))}</span><span><b>${esc(persona.nombre)}</b><small>${esc(persona.codigo || 'Sin código')}</small></span></button>`).join('');
    panel.classList.remove('hide');
  }
  $('persona').addEventListener('input', mostrarSugerencias);
  $('sugerenciasPersonas').addEventListener('click', event => {
    const option = event.target.closest('[data-persona-id]');
    if (!option) return;
    const persona = personas.find(item => Number(item.id) === Number(option.dataset.personaId));
    if (!persona) return;
    $('persona').value = persona.label;
    $('sugerenciasPersonas').classList.add('hide');
    consultar();
  });
  document.addEventListener('click', event => { if (!event.target.closest('.rp-search-wrap')) $('sugerenciasPersonas').classList.add('hide'); });
</script></body></html>
