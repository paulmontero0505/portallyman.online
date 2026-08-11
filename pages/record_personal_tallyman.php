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
</style></head><body><div class="overlay" id="overlay"></div><div class="shell"><?php $sb_base='..';include('../includes/sidebar.php');?><div class="main-area"><?php include('../includes/header.php');?><main class="content"><div class="rp">
<section class="rp-hero"><div><div class="rp-tag">Control de campo · Consulta individual</div><h1>Record Personal Tallyman</h1><p>Consulta el historial operativo, disciplinario, participativo y de reconocimientos de cada tallyman.</p></div><div class="rp-search"><select id="persona"><option>Cargando tallyman…</option></select><button id="buscar" disabled>Consultar record</button></div></section>
<section class="rp-empty" id="empty"><b>Selecciona un tallyman</b>El record se mostrará con información consolidada de los módulos existentes.</section>
<div id="result" class="hide"><section class="rp-profile"><div class="rp-person"><div class="rp-avatar" id="avatar">—</div><div><h2 id="nombre">—</h2><p id="cargo">—</p></div></div><div class="rp-data"><div><b>COORDINADOR</b><span id="coord">Sin asignar</span></div><div><b>PRÓXIMO CUMPLEAÑOS</b><span id="cumple">Sin registrar</span></div><div><b>ANTIGÜEDAD</b><span id="ingreso">Sin registrar</span></div></div></section><section class="rp-kpis" id="kpis"></section><div class="rp-grid">
<section class="rp-section"><div class="rp-head"><h3>Incidencias</h3><span class="rp-count" id="c-incidencias">0</span></div><ul class="rp-list" id="l-incidencias"></ul></section><section class="rp-section"><div class="rp-head"><h3>Sanciones disciplinarias</h3><span class="rp-count" id="c-sanciones">0</span></div><ul class="rp-list" id="l-sanciones"></ul></section><section class="rp-section"><div class="rp-head"><h3>Propuestas de mejora reconocidas</h3><span class="rp-count" id="c-propuestas">0</span></div><ul class="rp-list" id="l-propuestas"></ul></section><section class="rp-section"><div class="rp-head"><h3>Reconocimientos</h3><span class="rp-count" id="c-reconocimientos">0</span></div><ul class="rp-list" id="l-reconocimientos"></ul></section><section class="rp-section"><div class="rp-head"><h3>Charlas preoperativas</h3><span class="rp-count" id="c-charlas">0</span></div><ul class="rp-list" id="l-charlas"></ul></section><section class="rp-section"><div class="rp-head"><h3>Capacitaciones</h3><span class="rp-count" id="c-capacitaciones">0</span></div><ul class="rp-list" id="l-capacitaciones"></ul></section>
</div></div></div></main></div></div>
<script>
const $=x=>document.getElementById(x),esc=x=>String(x??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])),dt=x=>{const m=String(x??'').match(/^(\\d{4}-\\d{2}-\\d{2})/);if(!m)return 'Sin fecha';const d=new Date(m[1]+'T00:00:00');return Number.isNaN(d.getTime())?'Sin fecha':new Intl.DateTimeFormat('es-PE',{day:'2-digit',month:'short',year:'numeric'}).format(d)},pl=(n,a,b)=>n+' '+(n===1?a:b),chip=(x,c='')=>'<span class="rp-chip '+c+'">'+esc(x)+'</span>';
function lista(tipo,filas,fn){$('c-'+tipo).textContent=filas.length;$('l-'+tipo).innerHTML=filas.length?filas.map(fn).join(''):'<li class="rp-none">No hay registros para este tallyman.</li>'}
function mostrar(d){let p=d.perfil,r=d.resumen,h=d.historial;$('empty').classList.add('hide');$('result').classList.remove('hide');$('avatar').textContent=(p.nombre||'—').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase();$('nombre').textContent=p.nombre||'—';$('cargo').textContent=[p.codigo,p.funcion_principal,p.cuadrilla].filter(Boolean).join(' · ');$('coord').textContent=p.coordinador_nombre||'Sin asignar';$('cumple').textContent=p.dias_para_cumpleanos===null?'Sin registrar':pl(p.dias_para_cumpleanos,'día','días');$('ingreso').textContent=p.dias_desde_ingreso===null?'Sin registrar':pl(p.dias_desde_ingreso,'día','días');$('kpis').innerHTML=[['Incidencias',r.incidencias],['Sanciones',r.sanciones],['Propuestas con puntaje',r.propuestas],['Reconocimientos aprobados',r.reconocimientos_aprobados],['Charlas',r.charlas],['Capacitaciones',r.capacitaciones]].map(x=>'<div class="rp-kpi"><div class="rp-n">'+x[1]+'</div><div class="rp-l">'+x[0]+'</div></div>').join('');
lista('incidencias',h.incidencias,x=>'<li><div class="rp-line"><span>'+esc(x.punto_mejorar)+'</span>'+chip(x.impacto,'impacto-'+x.impacto)+'</div><div class="rp-sub">'+esc(x.competencia)+' · '+dt(x.fecha)+(x.zona_trabajo?' · '+esc(x.zona_trabajo):'')+'</div></li>');lista('sanciones',h.sanciones,x=>'<li><div class="rp-line"><span>'+esc(x.tipo_sancion.replaceAll('_',' '))+'</span>'+chip(x.impacto,'impacto-'+x.impacto)+'</div><div class="rp-sub">'+esc(x.punto_mejorar)+' · '+dt(x.fecha_incidencia)+(Number(x.dias_sancion)>0?' · '+pl(Number(x.dias_sancion),'día','días'):'')+'</div></li>');lista('propuestas',h.propuestas,x=>'<li><div class="rp-line"><span>Propuesta reconocida</span>'+chip(x.puntaje+' pts','estado-aprobado')+'</div><div class="rp-sub">'+esc(x.detalle)+' · '+dt(x.puntaje_at||x.created_at)+(x.puntaje_comentario?' · '+esc(x.puntaje_comentario):'')+'</div></li>');lista('reconocimientos',h.reconocimientos,x=>'<li><div class="rp-line"><span>'+esc(x.competencia)+'</span>'+chip(x.estado,'estado-'+x.estado)+'</div><div class="rp-sub">'+esc(x.concepto)+' · '+dt(x.fecha)+'</div></li>');lista('charlas',h.charlas,x=>'<li><div class="rp-line"><span>'+esc(x.tema)+'</span>'+chip(x.estado==='tardanza'?'Tardanza':'Asistió',x.estado==='tardanza'?'estado-pendiente':'estado-aprobado')+'</div><div class="rp-sub">'+dt(x.fecha)+' · '+esc(x.tipo_reunion.replaceAll('_',' '))+(x.capacitador?' · '+esc(x.capacitador):'')+'</div></li>');lista('capacitaciones',h.capacitaciones,x=>'<li><div class="rp-line"><span>'+esc(x.titulo)+'</span>'+chip(x.estado_asistencia==='tardanza'?'Tardanza':'Asistió',x.estado_asistencia==='tardanza'?'estado-pendiente':'estado-aprobado')+'</div><div class="rp-sub">'+dt(x.fecha)+' · '+esc(x.estado_capacitacion.replaceAll('_',' '))+(x.duracion_min?' · '+x.duracion_min+' min':'')+'</div></li>')}
async function consultar(){let id=$('persona').value;if(!id)return;let b=$('buscar');b.disabled=true;b.textContent='Consultando…';try{let r=await fetch('../api/get_record_personal_tallyman.php?colaborador_id='+encodeURIComponent(id)),d=await r.json();if(!r.ok||!d.success)throw Error(d.error||'No se pudo consultar el record.');mostrar(d)}catch(e){$('empty').classList.remove('hide');$('empty').innerHTML='<b>No se pudo mostrar el record</b>'+esc(e.message);$('result').classList.add('hide')}finally{b.disabled=false;b.textContent='Consultar record'}}
async function cargar(){try{let r=await fetch('../api/get_record_personal_tallyman.php?lista=1'),d=await r.json();if(!d.success)throw Error(d.error);$('persona').innerHTML='<option value="">Selecciona un tallyman</option>'+d.data.map(x=>'<option value="'+x.id+'">'+esc(x.nombre)+(x.codigo?' · '+esc(x.codigo):'')+'</option>').join('');$('buscar').disabled=false}catch(e){$('persona').innerHTML='<option>No fue posible cargar el personal</option>';$('empty').innerHTML='<b>No se pudo cargar el personal</b>'+esc(e.message)}}$('buscar').onclick=consultar;$('persona').onchange=consultar;cargar();
</script><script>
  const mostrarRecordBase = mostrar;
  mostrar = function(data) {
    mostrarRecordBase(data);
    const n = data.resumen.tardanzas_charlas || 0;
    $('kpis').insertAdjacentHTML('beforeend', '<div class="rp-kpi"><div class="rp-n">' + n + '</div><div class="rp-l">Tardanzas en charlas</div></div>');
  };
</script></body></html>
