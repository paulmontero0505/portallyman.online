<?php
require_once('../includes/auth.php');
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Colaboradores · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">

  <style>
    /* ════════════════════════════════════════════════════════════════
       COLABORADORES · CRUD + IMPORT (prefijo .col-*)
       PREMIUM LIGHT EMERALD THEME
    ════════════════════════════════════════════════════════════════ */
    .col-wrap {
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif;
    }
    .col-wrap *, .col-wrap *::before, .col-wrap *::after { box-sizing:border-box; }

    /* Hero style with emerald green gradient */
    .col-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:24px 30px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .col-hero h1 { margin:6px 0 4px; font-size:24px; font-weight:700; letter-spacing:-.015em; }
    .col-hero p  { margin:0; color:rgba(255,255,255,0.85); font-size:13.5px; max-width:580px; }
    .col-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 12px; border-radius:999px;
      background:rgba(255, 255, 255, 0.12); border:1px solid rgba(255, 255, 255, 0.2);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }
    .col-hero-actions { display:flex; gap:10px; }

    /* Buttons */
    body .col-btn {
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 18px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3) !important;
      background: #ffffff !important; cursor:pointer;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A !important;
      transition:all .2s ease;
    }
    body .col-btn:hover {
      border-color: #005c3d !important;
      color: #005c3d !important;
      background: rgba(0, 135, 90, 0.05) !important;
      box-shadow: 0 4px 12px rgba(0, 135, 90, 0.08) !important;
    }
    body .col-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color:#fff !important; border:none !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 15px rgba(0, 135, 90, 0.2) !important;
    }
    body .col-btn.primary:hover {
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 20px rgba(0, 135, 90, 0.35) !important;
      filter: brightness(1.1) !important;
    }
    body .col-btn.primary:active {
      transform: translateY(0.5px) !important;
    }
    body .col-btn.ghost-light {
      background: rgba(255, 255, 255, 0.15) !important;
      color: #ffffff !important;
      border: 1.5px solid rgba(255, 255, 255, 0.25) !important;
    }
    body .col-btn.ghost-light:hover {
      background: rgba(255, 255, 255, 0.25) !important;
      border-color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.1) !important;
    }
    body .col-btn.danger {
      background: rgba(239, 68, 68, 0.05) !important;
      border-color: rgba(239, 68, 68, 0.25) !important;
      color: #ef4444 !important;
    }
    body .col-btn.danger:hover {
      background: #ef4444 !important;
      color: #ffffff !important;
      border-color: #ef4444 !important;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
    }
    body .col-btn svg { width:14px; height:14px; }

    .col-kpis { display:flex; gap:12px; flex-wrap:wrap; }

    /* Toolbar / Search */
    body .col-toolbar {
      display:flex; gap:10px; align-items:center;
      background: #ffffff !important;
      border: 1px solid var(--gris-borde) !important;
      border-radius: 14px !important; padding:10px 14px !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02) !important;
    }
    body .col-search {
      flex:1; display:flex; align-items:center; gap:8px;
      background: #ffffff !important;
      border:1px solid rgba(0, 135, 90, 0.2) !important;
      border-radius:10px; padding:9px 14px;
      transition: all 0.2s ease;
    }
    body .col-search:focus-within {
      border-color:#00875A !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.1) !important;
    }
    body .col-search input {
      flex:1; border:0; outline:0; background:transparent; font:inherit;
      font-size:13.5px; color:#111827 !important;
    }
    body .col-search input::placeholder {
      color: #9ca3af !important;
      opacity: 0.8;
    }
    body .col-search svg { width:15px; height:15px; color:#6b7280 !important; }
    
    body .col-filter {
      display:flex; gap:4px; background: #f3f4f6 !important;
      border: 1px solid #e5e7eb !important;
      border-radius:10px; padding:4px !important;
    }
    body .col-filter button {
      padding:6px 14px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:#6b7280 !important; cursor:pointer;
      transition: all 0.15s ease;
    }
    body .col-filter button.active {
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.3) !important;
      color: #00875A !important;
      box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    body .col-filter button:not(.active):hover {
      color: #111827 !important;
    }

    /* Filtro por Coordinador Tallyman a cargo */
    body .col-coord-filter {
      display:flex; align-items:center; gap:8px;
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      border-radius:10px; padding:0 10px 0 12px;
      transition: all 0.2s ease;
    }
    body .col-coord-filter svg { width:15px; height:15px; color:#6b7280 !important; flex-shrink:0; }
    body .col-coord-filter select {
      border:0; outline:0; background:transparent; font:inherit;
      font-size:12.5px; font-weight:600; color:#374151 !important;
      padding:9px 2px 9px 0; cursor:pointer; max-width:230px;
    }
    /* Resalta el filtro cuando hay un coordinador seleccionado */
    body .col-coord-filter.is-on {
      border-color: #00875A !important;
      background: rgba(0, 135, 90, 0.03) !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.08) !important;
    }
    body .col-coord-filter.is-on svg,
    body .col-coord-filter.is-on select { color:#00875A !important; }

    /* Table Wrap and Grid */
    body .col-table-wrap {
      background: #ffffff !important;
      border: 1px solid var(--gris-borde) !important;
      border-radius: 14px !important; overflow:hidden;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.02) !important;
    }
    body .col-table { width:100%; border-collapse:collapse; font-size:13px; }
    body .col-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom: 1px solid var(--gris-borde) !important; }
    body .col-table th {
      padding:14px 18px; text-align:left;
      font-size:11px; letter-spacing:.08em; text-transform:uppercase;
      color: #005c3d !important; font-weight:700; white-space:nowrap;
    }
    body .col-table tbody tr { border-bottom: 1px solid rgba(0, 135, 90, 0.06) !important; transition:all .2s ease; }
    body .col-table tbody tr:last-child { border-bottom:0 !important; }
    body .col-table tbody tr:hover { background: rgba(0, 135, 90, 0.02) !important; }
    body .col-table td { padding:14px 18px; vertical-align:middle; color: #111827 !important; }
    
    .col-cell-name { display:flex; align-items:center; gap:12px; }
    
    /* Avatars with gradients */
    .col-avatar {
      width:36px; height:36px; border-radius:50%; flex-shrink:0;
      background: linear-gradient(135deg, #00875A 0%, #00b377 100%) !important;
      color:#fff !important;
      display:grid; place-items:center; font-size:13px; font-weight:800;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 2px 6px rgba(0, 135, 90, 0.1) !important;
    }
    .col-avatar.inact {
      background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%) !important;
      border-color: #9ca3af !important;
      box-shadow: none !important;
      opacity: 0.8;
    }
    .col-name { font-weight:600; color: #111827 !important; font-size:14px; }
    .col-codigo { font-size:11px; color:#6b7280 !important; font-family:var(--mono); margin-top:2px; }

    /* Badges dynamic overrides */
    .col-badge {
      display:inline-flex; align-items:center; gap:6px;
      padding:4px 10px; border-radius:6px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
    }
    .col-badge .dot { width:6px; height:6px; border-radius:50%; }

    /* State Active / Inactive */
    .col-badge.is-active {
      background: rgba(16, 185, 129, 0.08) !important;
      border: 1px solid rgba(16, 185, 129, 0.25) !important;
      color: #059669 !important;
    }
    .col-badge.is-active .dot { background: #10b981 !important; }
    
    .col-badge.is-inactive {
      background: rgba(239, 68, 68, 0.08) !important;
      border: 1px solid rgba(239, 68, 68, 0.25) !important;
      color: #dc2626 !important;
    }
    .col-badge.is-inactive .dot { background: #ef4444 !important; }

    /* Dynamic team tags classes */
    .col-badge.team-diurno {
      background: rgba(245, 158, 11, 0.08) !important;
      border: 1px solid rgba(245, 158, 11, 0.25) !important;
      color: #d97706 !important;
    }
    .col-badge.team-diurno .dot { background: #f59e0b !important; }
    
    .col-badge.team-g1 {
      background: rgba(59, 130, 246, 0.08) !important;
      border: 1px solid rgba(59, 130, 246, 0.25) !important;
      color: #2563eb !important;
    }
    .col-badge.team-g1 .dot { background: #3b82f6 !important; }

    .col-badge.team-g2 {
      background: rgba(168, 85, 247, 0.08) !important;
      border: 1px solid rgba(168, 85, 247, 0.25) !important;
      color: #7c3aed !important;
    }
    .col-badge.team-g2 .dot { background: #a885f7 !important; }

    .col-badge.team-g3 {
      background: rgba(16, 185, 129, 0.08) !important;
      border: 1px solid rgba(16, 185, 129, 0.25) !important;
      color: #059669 !important;
    }
    .col-badge.team-g3 .dot { background: #10b981 !important; }

    .col-badge.team-g4 {
      background: rgba(244, 63, 94, 0.08) !important;
      border: 1px solid rgba(244, 63, 94, 0.25) !important;
      color: #e11d48 !important;
    }
    .col-badge.team-g4 .dot { background: #f43f5e !important; }

    .col-badge.team-other {
      background: rgba(107, 114, 128, 0.08) !important;
      border: 1px solid rgba(107, 114, 128, 0.25) !important;
      color: #4b5563 !important;
    }
    .col-badge.team-other .dot { background: #6b7280 !important; }

    /* Action buttons styling */
    body .col-act-btn {
      padding:6px 12px; border-radius:7px; border:1px solid rgba(0, 135, 90, 0.25) !important;
      background: rgba(0, 135, 90, 0.05) !important; cursor:pointer;
      font:inherit; font-size:12px; font-weight:600; color: #00875A !important;
      transition:all .2s ease;
    }
    body .col-act-btn:not(.danger):hover {
      background: #00875A !important;
      color: #ffffff !important;
      border-color: #00875A !important;
      box-shadow: 0 2px 8px rgba(0, 135, 90, 0.2) !important;
    }
    body .col-act-btn.danger {
      border-color: rgba(239, 68, 68, 0.25) !important;
      color: #ef4444 !important;
    }
    body .col-act-btn.danger:hover {
      background: #ef4444 !important;
      color: #ffffff !important;
      border-color: #ef4444 !important;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2) !important;
    }
    .col-cell-actions { display:flex; gap:8px; align-items:center; justify-content:flex-end; }

    /* Modal Form overrides */
    body .col-modal {
      background: #ffffff !important;
      border: 1px solid var(--gris-borde) !important;
      color: #111827 !important;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1) !important;
    }
    body .col-modal-head {
      border-bottom: 1px solid rgba(0, 135, 90, 0.08) !important;
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color: #ffffff !important;
    }
    body .col-modal-head h3 {
      color: #ffffff !important;
    }
    body .col-modal-head .sub {
      color: rgba(255, 255, 255, 0.8) !important;
    }
    body .col-modal-close {
      background: rgba(255, 255, 255, 0.1) !important;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      color: #ffffff !important;
    }
    body .col-modal-close:hover {
      background: rgba(255, 255, 255, 0.2) !important;
      color: #ffffff !important;
    }
    body .col-modal-body {
      background: #ffffff !important;
    }
    body .col-modal-foot {
      background: #ffffff !important;
      border-top: 1px solid rgba(0, 135, 90, 0.08) !important;
    }

    body .col-modal .col-field label {
      color: #374151 !important;
      font-weight: 600 !important;
      font-size: 11.5px !important;
    }
    body .col-modal .col-field input,
    body .col-modal .col-field select {
      background-color: #ffffff !important;
      border: 1.5px solid #d1d5db !important;
      color: #111827 !important;
      border-radius: 8px !important;
      padding: 10px 12px !important;
    }
    body .col-modal .col-field input:focus,
    body .col-modal .col-field select:focus {
      border-color: #00875A !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15) !important;
      background-color: #ffffff !important;
    }
    body .col-modal .col-field input::placeholder {
      color: #9ca3af !important;
    }

    /* Modal cancellation buttons */
    body .col-modal-foot button:not(.primary) {
      background: #f3f4f6 !important;
      border: 1px solid #d1d5db !important;
      color: #4b5563 !important;
    }
    body .col-modal-foot button:not(.primary):hover {
      background: #e5e7eb !important;
      border-color: #c4c5c9 !important;
      color: #1f2937 !important;
    }

    /* Dropzone Excel */
    body .col-drop {
      border: 2px dashed rgba(0, 135, 90, 0.3) !important;
      background: #f9fafb !important;
      color: #4b5563 !important;
    }
    body .col-drop:hover, body .col-drop.over {
      border-color: #00875A !important;
      background: rgba(0, 135, 90, 0.03) !important;
      color: #00875A !important;
      box-shadow: 0 0 15px rgba(0, 135, 90, 0.05) !important;
    }
    body .col-drop-title { color: #111827 !important; }
    body #impDownloadTpl { color: #00875A !important; }
    body #impDownloadTpl:hover { text-decoration: underline !important; }

    /* Import excel preview components */
    body .col-imp-summary {
      background: #f3f4f6 !important;
      border: 1px solid rgba(0, 135, 90, 0.1) !important;
    }
    body .col-imp-summary .new { color: #059669 !important; }
    body .col-imp-summary .new .dot { background: #10b981 !important; }
    body .col-imp-summary .upd { color: #d97706 !important; }
    body .col-imp-summary .upd .dot { background: #f59e0b !important; }
    body .col-imp-summary .err { color: #dc2626 !important; }
    body .col-imp-summary .err .dot { background: #ef4444 !important; }

    body .col-imp-filter {
      background: #f3f4f6 !important;
      border: 1px solid #d1d5db !important;
    }
    body .col-imp-filter button { color: #4b5563 !important; }
    body .col-imp-filter button.active {
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.25) !important;
      color: #00875A !important;
    }

    body .col-imp-table-wrap {
      border-color: #d1d5db !important;
      background: #ffffff !important;
    }
    body .col-imp-table td { border-bottom-color: #e5e7eb !important; color: #111827 !important; }
    body .col-imp-table thead { background: #f3f4f6 !important; }
    body .col-imp-table tr[data-status="new"] td { background: rgba(16, 185, 129, 0.04) !important; }
    body .col-imp-table tr[data-status="update"] td { background: rgba(245, 158, 11, 0.04) !important; }
    body .col-imp-table tr[data-status="error"] td { background: rgba(239, 68, 68, 0.04) !important; }

    body .col-imp-status.new { background: rgba(16, 185, 129, 0.1) !important; color: #059669 !important; border: 1px solid rgba(16, 185, 129, 0.2) !important; }
    body .col-imp-status.update { background: rgba(245, 158, 11, 0.1) !important; color: #d97706 !important; border: 1px solid rgba(245, 158, 11, 0.2) !important; }
    body .col-imp-status.error { background: rgba(239, 68, 68, 0.1) !important; color: #dc2626 !important; border: 1px solid rgba(239, 68, 68, 0.2) !important; }

    /* Toast */
    body .col-toast {
      background: #111827 !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
      color: #ffffff !important;
    }
    body .col-toast.is-error {
      background: #dc2626 !important;
      border-color: #ef4444 !important;
      color: #ffffff !important;
    }

    .content { padding:24px 28px 60px; overflow-y:auto; }

    /* ─── BASE LAYOUTS PARA MODAL, CAMPOS Y KPIs ─── */
    .col-modal-back {
      display:none; position:fixed; inset:0; z-index:1200;
      align-items:center; justify-content:center; padding:20px;
      background: rgba(0, 0, 0, 0.3) !important;
      backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    }
    .col-modal-back.open { display:flex; }

    .col-modal {
      width:100%; max-width:540px; max-height:90vh;
      border-radius:18px; display:flex; flex-direction:column; overflow:hidden;
    }
    #impModal { max-width:900px !important; }

    .col-modal-head {
      display:flex; align-items:flex-start; justify-content:space-between;
      gap:12px; padding:20px 24px 16px; flex-shrink:0;
    }
    .col-modal-head h3 { margin:0 0 4px; font-size:16px; font-weight:700; }
    .col-modal-head .sub { font-size:12px; }

    .col-modal-close {
      width:30px; height:30px; border-radius:7px; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      flex-shrink:0; transition:all .2s; font-family:inherit;
    }

    .col-modal-body {
      flex:1; overflow-y:auto; padding:20px 24px;
      display:flex; flex-direction:column; gap:14px;
    }

    .col-modal-foot {
      display:flex; align-items:center; justify-content:flex-end;
      gap:10px; padding:16px 24px; flex-shrink:0;
    }

    .col-field { display:flex; flex-direction:column; gap:5px; }
    .col-field label {
      font-size:11px; font-weight:600; text-transform:uppercase;
      letter-spacing:.5px;
    }
    .col-field input, .col-field select {
      width:100%; padding:10px 12px; border-radius:8px;
      font-size:13px; font-family:inherit; outline:none;
      transition:border-color .15s, box-shadow .15s; border:1.5px solid;
    }

    .col-row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .col-kpi {
      flex:1; min-width:110px;
      background: #ffffff !important;
      border:1px solid var(--gris-borde) !important;
      border-radius:14px; padding:16px 20px;
      position:relative; overflow:hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
      transition: border-color .2s, box-shadow .2s;
    }
    .col-kpi:hover {
      border-color: rgba(0, 135, 90, 0.35) !important;
      box-shadow: 0 6px 22px rgba(0, 135, 90, 0.05) !important;
    }
    .col-kpi::before {
      content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
      border-radius:14px 0 0 14px;
    }
    .col-kpi:nth-child(1)::before { background: #00875A; }
    .col-kpi:nth-child(2)::before { background: #059669; }
    .col-kpi:nth-child(3)::before { background: #dc2626; }
    .col-kpi:nth-child(4)::before { background: #7c3aed; }
    .col-kpi:nth-child(5)::before { background: #3b82f6; }
    .col-kpi:nth-child(6)::before { background: #8b5cf6; }
    .col-kpi:nth-child(7)::before { background: #f59e0b; }
    .col-kpi .lbl {
      font-size:10px; font-weight:600; letter-spacing:.8px;
      text-transform:uppercase; color:#6b7280; margin-bottom:6px;
    }
    .col-kpi:nth-child(1) .val { color:#00875A; }
    .col-kpi:nth-child(2) .val { color:#059669; }
    .col-kpi:nth-child(3) .val { color:#dc2626; }
    .col-kpi:nth-child(4) .val { color:#7c3aed; }
    .col-kpi:nth-child(5) .val { color:#3b82f6; }
    .col-kpi:nth-child(6) .val { color:#8b5cf6; }
    .col-kpi:nth-child(7) .val { color:#d97706; }
    .col-kpi .val { font-size:28px; font-weight:800; }

    /* Badge para columna Función en tabla */
    .col-funcion-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 10px; border-radius:6px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
      background:rgba(124, 58, 237, 0.08); border:1px solid rgba(124, 58, 237, 0.2); color:#7c3aed;
    }
    .col-puesto-text {
      font-size:12.5px; color:#374151; font-weight:500;
    }

    /* Chip del Coordinador Tallyman a cargo (columna junto a Puesto) */
    .col-coord-chip {
      display:inline-flex; align-items:center; gap:8px;
      padding:3px 12px 3px 3px; border-radius:999px; max-width:220px;
      background:rgba(59, 130, 246, 0.06); border:1px solid rgba(59, 130, 246, 0.2);
    }
    .col-coord-chip .ini {
      width:22px; height:22px; border-radius:50%; flex-shrink:0;
      background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color:#fff; display:grid; place-items:center;
      font-size:9.5px; font-weight:800;
    }
    .col-coord-chip .nm {
      font-size:12px; font-weight:600; color:#1d4ed8;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .col-coord-none { font-size:12px; color:#9ca3af; font-style:italic; }

    .col-drop {
      display:flex; flex-direction:column; align-items:center;
      justify-content:center; gap:10px; padding:40px;
      border-radius:14px; cursor:pointer; text-align:center; transition:all .2s;
    }
    .col-drop-title { font-size:15px; font-weight:600; }
    .col-drop-sub   { font-size:12.5px; }

    .col-imp-summary {
      display:flex; gap:20px; padding:12px 16px; border-radius:10px;
      margin-bottom:12px; font-size:12.5px; font-weight:600; flex-wrap:wrap;
    }
    .col-imp-summary span { display:flex; align-items:center; gap:6px; }
    .col-imp-summary .dot { width:7px; height:7px; border-radius:50%; }

    .col-imp-filter {
      display:flex; gap:4px; padding:4px; border-radius:10px; margin-bottom:8px;
    }
    .col-imp-filter button {
      padding:6px 12px; border:0; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; cursor:pointer;
      background:transparent; transition:all .15s;
    }

    .col-imp-table-wrap { overflow-x:auto; border-radius:10px; border:1px solid #d1d5db; }
    .col-imp-table { width:100%; border-collapse:collapse; font-size:12px; }
    .col-imp-table th {
      padding:10px 14px; text-align:left; font-size:11px;
      font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    }
    .col-imp-table td { padding:10px 14px; border-bottom:1px solid #e5e7eb; }
    .col-imp-table tbody tr:last-child td { border-bottom:0; }

    .col-imp-status {
      display:inline-flex; align-items:center; padding:2px 8px;
      border-radius:5px; font-size:11px; font-weight:700;
    }
    .col-imp-errmsg { display:block; font-size:10.5px; margin-top:3px; color:#dc2626; }

    .col-toast {
      position:fixed; bottom:24px; right:24px; z-index:9999;
      padding:12px 18px; border-radius:12px; font-size:13px; font-weight:500;
      min-width:220px; max-width:380px;
      transform:translateY(10px); opacity:0; pointer-events:none;
      transition:all .3s ease;
    }
    .col-toast.show { transform:translateY(0); opacity:1; pointer-events:all; }

    /* ══════════════════════════════════════
       MOBILE · Colaboradores (iOS & Android)
       ══════════════════════════════════════ */
    @media (max-width: 768px) {

      /* Hero: columna única */
      .col-hero {
        flex-direction: column; align-items: flex-start;
        padding: 18px; gap: 14px;
      }
      .col-hero h1 { font-size: 20px; }
      .col-hero p  { font-size: 13px; }
      .col-hero-actions { flex-wrap: wrap; width: 100%; }
      .col-hero-actions .col-btn { flex: 1 1 calc(50% - 5px); justify-content: center; font-size: 12px; padding: 9px 10px; }

      /* KPIs: 2 columnas */
      .col-kpis { gap: 8px; }
      .col-kpi  { min-width: 0; flex: 1 1 calc(50% - 4px); padding: 13px 14px; }
      .col-kpi .val { font-size: 24px; }

      /* Toolbar: wrap */
      body .col-toolbar { flex-wrap: wrap; padding: 8px 10px !important; gap: 8px !important; }
      body .col-search  { flex: 1 1 100%; min-width: 0; }
      body .col-filter  { flex-wrap: wrap; gap: 4px; }
      body .col-filter button { flex: 1 1 auto; padding: 6px 10px; min-height: 36px; }
      body .col-coord-filter  { flex: 1 1 100%; min-width: 0; }
      body .col-coord-filter select { flex: 1; max-width: none; }

      /* Tabla: scroll horizontal */
      body .col-table-wrap { overflow-x: auto; overscroll-behavior-x: contain; }
      body .col-table      { min-width: 720px; font-size: 12px; }
      body .col-table th   { padding: 11px 12px; }
      body .col-table td   { padding: 11px 12px; }

      /* Modal: ancho completo */
      .col-modal { width: min(520px, calc(100vw - 16px)) !important; }
      .col-row2  { grid-template-columns: 1fr !important; }

      /* .content padding más ajustado */
      .content { padding: 16px 12px 40px; }
    }

    @media (max-width: 480px) {
      .col-hero h1 { font-size: 18px; }
      .col-hero p  { display: none; }
      .col-kpi .val { font-size: 22px; }
      .col-kpi .lbl { font-size: 9.5px; }
      .col-hero-actions .col-btn { flex: 1 1 100%; }
    }
  </style>
  <script src="../js/vendor/xlsx.full.min.js"></script>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="col-wrap">

        <!-- HERO -->
        <section class="col-hero">
          <div>
            <span class="tag">ADMINISTRACIÓN · COLABORADORES</span>
            <h1>Catálogo maestro de colaboradores</h1>
            <p>Base maestra del personal de estiba. Alta manual o importación desde Excel. Los colaboradores aparecen luego en la Plantilla del turno.</p>
          </div>
          <div class="col-hero-actions">
            <button class="col-btn ghost-light" id="btnImport">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Importar Excel
            </button>
            <button class="col-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Nuevo colaborador
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="col-kpis">
          <div class="col-kpi"><div class="lbl">Total</div><div class="val" id="kpiTotal">0</div></div>
          <div class="col-kpi"><div class="lbl">Activos</div><div class="val" id="kpiActivos">0</div></div>
          <div class="col-kpi"><div class="lbl">Inactivos</div><div class="val" id="kpiInactivos">0</div></div>
          <div class="col-kpi"><div class="lbl">Teams</div><div class="val" id="kpiCuadrillas">0</div></div>
          <div class="col-kpi"><div class="lbl">Puestos</div><div class="val" id="kpiPuestos">0</div></div>
          <div class="col-kpi"><div class="lbl">Función</div><div class="val" id="kpiFunciones">0</div></div>
          <div class="col-kpi"><div class="lbl">Sin coordinador</div><div class="val" id="kpiSinCoord">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="col-toolbar">
          <div class="col-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="colSearch" type="text" placeholder="Buscar por nombre, código, función, team o coordinador…">
          </div>
          <div class="col-coord-filter" id="colCoordFilterWrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <select id="colCoordFilter">
              <option value="todos">Todos los coordinadores</option>
              <option value="sin">Sin asignar</option>
            </select>
          </div>
          <div class="col-filter" id="colFilter">
            <button class="active" data-f="todos">Todos</button>
            <button data-f="activo">Activos</button>
            <button data-f="inactivo">Inactivos</button>
          </div>
        </div>

        <!-- TABLA -->
        <div class="col-table-wrap">
          <table class="col-table">
            <thead>
              <tr>
                <th>Colaborador</th>
                <th>Puesto</th>
                <th>Coordinador</th>
                <th>Función</th>
                <th>Team</th>
                <th>Estado</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="colTbody">
              <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- MODAL: alta / edición -->
<div class="col-modal-back" id="colModalBack">
  <div class="col-modal">
    <div class="col-modal-head">
      <div>
        <h3 id="colModalTitle">Nuevo colaborador</h3>
        <div class="sub">El código identifica al colaborador y debe ser único.</div>
      </div>
      <button class="col-modal-close" id="colModalClose">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="col-modal-body">
      <input type="hidden" id="cm-id">
      <div class="col-field">
        <label>Nombre completo</label>
        <input id="cm-nombre" type="text" placeholder="Apellidos y Nombres" maxlength="150">
      </div>
      <div class="col-row2">
        <div class="col-field">
          <label>Código</label>
          <input id="cm-codigo" type="text" placeholder="Ej: A001" maxlength="20" pattern="[A-Za-z0-9]+">
        </div>
        <div class="col-field">
          <label>DNI (auto-registro)</label>
          <input id="cm-dni" type="text" inputmode="numeric" placeholder="8 dígitos" maxlength="8" pattern="\d{8}">
        </div>
      </div>
      <div class="col-row2">
        <div class="col-field">
          <label>Team</label>
          <input id="cm-cuadrilla" type="text" placeholder="G1 TEAM A, DIURNO…" maxlength="20">
        </div>
        <div class="col-field">
          <label>Coordinador Tallyman</label>
          <select id="cm-coordinador">
            <option value="">— Sin asignar —</option>
          </select>
        </div>
      </div>
      <div class="col-row2">
        <div class="col-field">
          <label>Puesto</label>
          <input id="cm-funcion" type="text" placeholder="Winchero, Estibador…" maxlength="60" list="cm-funcion-suggestions">
          <datalist id="cm-funcion-suggestions">
            <option value="Asistente de Estiba"></option>
            <option value="Analista de Trouble Desk"></option>
            <option value="Operario de Puerto Multipropósito"></option>
          </datalist>
        </div>
        <div class="col-field">
          <label>Función</label>
          <select id="cm-tipo-funcion">
            <option value="">— Seleccionar —</option>
            <option value="SOMBRA TALLY">SOMBRA TALLY</option>
            <option value="SOMBRA CI">SOMBRA CI</option>
            <option value="SOMBRA ZOP">SOMBRA ZOP</option>
            <option value="TALLY CALIFICADO">TALLY CALIFICADO</option>
          </select>
        </div>
      </div>
      <div class="col-field">
        <label>Estado</label>
        <select id="cm-activo">
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </div>
    </div>
    <div class="col-modal-foot">
      <button class="col-btn" id="colModalCancel">Cancelar</button>
      <button class="col-btn primary" id="colModalSave">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL: importar Excel -->
<div class="col-modal-back" id="impModalBack">
  <div class="col-modal" id="impModal" style="width:880px">
    <div class="col-modal-head">
      <div>
        <h3 id="impModalTitle">Importar colaboradores desde Excel</h3>
        <div class="sub" id="impModalSub">Selecciona un archivo .xlsx con las columnas: Código, Nombre, Función, Team.</div>
      </div>
      <button class="col-modal-close" id="impModalClose">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="col-modal-body">

      <!-- PASO 1: SELECCIÓN -->
      <div id="impStep1">
        <div class="col-drop" id="colDrop">
          <input type="file" id="impFile" accept=".xlsx" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="col-drop-title">Arrastra tu archivo .xlsx aquí</div>
          <div class="col-drop-sub">o haz click para seleccionarlo</div>
        </div>
        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--co-mute)">
          <span>Encabezados aceptados: <code>Código · Nombre · Función · Team</code></span>
          <a href="#" id="impDownloadTpl" style="color:var(--co-navy);font-weight:600;text-decoration:none">↓ Descargar plantilla Excel</a>
        </div>
      </div>

      <!-- PASO 2: PREVIEW -->
      <div id="impStep2" style="display:none">
        <div class="col-imp-summary" id="impSummary"></div>
        <div class="col-imp-filter">
          <button class="active" data-imp-f="all">Todos</button>
          <button data-imp-f="new">Solo nuevos</button>
          <button data-imp-f="update">Solo actualizar</button>
          <button data-imp-f="error">Solo errores</button>
        </div>
        <div class="col-imp-table-wrap">
          <table class="col-imp-table">
            <thead>
              <tr><th>#</th><th>Estado</th><th>Código</th><th>Nombre</th><th>Función</th><th>Team</th></tr>
            </thead>
            <tbody id="impTbody"></tbody>
          </table>
        </div>
      </div>

    </div>
    <div class="col-modal-foot" id="impFoot">
      <button class="col-btn" id="impBack" style="display:none">← Volver</button>
      <button class="col-btn" id="impCancel">Cancelar</button>
      <button class="col-btn primary" id="impConfirm" style="display:none">Confirmar 0 filas</button>
    </div>
  </div>
</div>

<div class="col-toast" id="colToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  let colaboradores = [];
  let coordinadores = [];   // usuarios con rol Coordinador (catálogo del selector)
  let query = '';
  let filtro = 'todos';
  let coordFiltro = 'todos';  // 'todos' | 'sin' | id del coordinador
  let editingId = null;

  function toast(msg, type) {
    const t = $('colToast');
    t.textContent = msg;
    t.className = 'col-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 2800);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function initials(n) {
    const w = String(n || '').trim().split(/\s+/);
    return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase();
  }

  function renderKpis() {
    $('kpiTotal').textContent      = colaboradores.length;
    $('kpiActivos').textContent    = colaboradores.filter(c => c.activo === 1).length;
    $('kpiInactivos').textContent  = colaboradores.filter(c => c.activo === 0).length;
    $('kpiCuadrillas').textContent = new Set(colaboradores.map(c => c.cuadrilla).filter(Boolean)).size;
    $('kpiPuestos').textContent    = new Set(colaboradores.map(c => c.funcion_principal).filter(Boolean)).size;
    $('kpiFunciones').textContent  = new Set(colaboradores.map(c => c.tipo_funcion).filter(Boolean)).size;
    $('kpiSinCoord').textContent   = colaboradores.filter(c => !c.coordinador_id).length;
  }

  function classForTeam(team) {
    const t = String(team || '').trim().toLowerCase();
    if (t.includes('diurno')) return 'team-diurno';
    if (t.includes('g1')) return 'team-g1';
    if (t.includes('g2')) return 'team-g2';
    if (t.includes('g3')) return 'team-g3';
    if (t.includes('g4')) return 'team-g4';
    return 'team-other';
  }

  function render() {
    const q = query.trim().toLowerCase();
    const list = colaboradores.filter(c => {
      if (filtro === 'activo'   && c.activo !== 1) return false;
      if (filtro === 'inactivo' && c.activo !== 0) return false;
      // Filtro por coordinador a cargo (se combina con los anteriores)
      if (coordFiltro === 'sin' && c.coordinador_id) return false;
      if (coordFiltro !== 'todos' && coordFiltro !== 'sin'
          && Number(c.coordinador_id) !== Number(coordFiltro)) return false;
      if (!q) return true;
      return [c.nombre, c.codigo, c.funcion_principal, c.cuadrilla, c.coordinador_nombre]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tbody = $('colTbody');
    tbody.innerHTML = '';
    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Sin resultados.</td></tr>`;
      return;
    }
    list.forEach(c => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="col-cell-name">
            <div class="col-avatar ${c.activo ? '' : 'inact'}">${esc(initials(c.nombre))}</div>
            <div>
              <div class="col-name">${esc(c.nombre)}</div>
              <div class="col-codigo">${esc(c.codigo)}</div>
            </div>
          </div>
        </td>
        <td><span class="col-puesto-text">${esc(c.funcion_principal)}</span></td>
        <td>${c.coordinador_nombre
              ? `<span class="col-coord-chip" title="${esc(c.coordinador_nombre)}"><span class="ini">${esc(initials(c.coordinador_nombre))}</span><span class="nm">${esc(c.coordinador_nombre)}</span></span>`
              : '<span class="col-coord-none">Sin asignar</span>'}</td>
        <td>${c.tipo_funcion ? `<span class="col-funcion-badge">${esc(c.tipo_funcion)}</span>` : '<span style="color:#475569">—</span>'}</td>
        <td><span class="col-badge ${classForTeam(c.cuadrilla)}"><span class="dot"></span>${esc(c.cuadrilla)}</span></td>
        <td><span class="col-badge ${c.activo ? 'is-active' : 'is-inactive'}"><span class="dot"></span>${c.activo ? 'Activo' : 'Inactivo'}</span></td>
        <td>
          <div class="col-cell-actions">
            <button class="col-act-btn" data-action="edit" data-id="${c.id}">Editar</button>
            <button class="col-act-btn danger" data-action="del" data-id="${c.id}">Eliminar</button>
          </div>
        </td>`;
      tbody.append(tr);
    });
  }

  async function cargar() {
    try {
      const res = await fetch('../api/get_colaboradores.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
      colaboradores = data.data || [];
      renderKpis(); render();
    } catch (e) {
      console.error('[colaboradores] cargar:', e);
      toast('Error de red', 'error');
    }
  }

  // ─── Coordinadores Tallyman (usuarios con rol Coordinador) ───

  /** Rellena el <select> del modal.
   *  Si el colaborador tiene asignado un coordinador que ya no aparece en la
   *  lista (dado de baja o sin el rol), se inyecta como opción extra para no
   *  perder la asignación silenciosamente al guardar. */
  function fillModalCoord(coordId, coordNombre) {
    let html = '<option value="">— Sin asignar —</option>'
             + coordinadores.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    if (coordId && !coordinadores.some(c => Number(c.id) === Number(coordId))) {
      html += `<option value="${Number(coordId)}">${esc(coordNombre || 'Coordinador #' + coordId)} · inactivo</option>`;
    }
    const sel = $('cm-coordinador');
    sel.innerHTML = html;
    sel.value = coordId ? String(coordId) : '';
  }

  function fillCoordFilter() {
    const f = $('colCoordFilter');
    f.innerHTML = '<option value="todos">Todos los coordinadores</option>'
                + coordinadores.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('')
                + '<option value="sin">Sin asignar</option>';
    f.value = coordFiltro;
  }

  async function cargarCoordinadores() {
    try {
      const res = await fetch('../api/get_coordinadores.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'No se pudo cargar los coordinadores', 'error'); return; }
      coordinadores = data.data || [];
      fillCoordFilter();
      fillModalCoord(null, null);
    } catch (e) {
      console.error('[colaboradores] coordinadores:', e);
      toast('No se pudo cargar la lista de coordinadores', 'error');
    }
  }

  // ─── Modal Alta/Edición ───
  function openModal(id) {
    editingId = id ? Number(id) : null;
    const c = editingId ? colaboradores.find(x => Number(x.id) === editingId) : null;
    $('colModalTitle').textContent = c ? 'Editar colaborador' : 'Nuevo colaborador';
    $('cm-id').value              = c ? c.id : '';
    $('cm-nombre').value          = c ? (c.nombre ?? '') : '';
    $('cm-codigo').value          = c ? (c.codigo ?? '') : '';
    $('cm-dni').value             = c ? (c.dni ?? '') : '';
    $('cm-funcion').value         = c ? (c.funcion_principal ?? '') : '';
    $('cm-tipo-funcion').value    = c ? (c.tipo_funcion ?? '') : '';
    $('cm-cuadrilla').value       = c ? (c.cuadrilla ?? '') : '';
    $('cm-activo').value          = c ? String(c.activo) : '1';
    fillModalCoord(c ? c.coordinador_id : null, c ? c.coordinador_nombre : null);
    $('colModalBack').classList.add('open');
    setTimeout(() => $('cm-nombre').focus(), 80);
  }
  function closeModal() { $('colModalBack').classList.remove('open'); editingId = null; }

  async function guardar() {
    const payload = {
      id:                Number($('cm-id').value || 0),
      codigo:            $('cm-codigo').value.trim(),
      dni:               $('cm-dni').value.trim(),
      nombre:            $('cm-nombre').value.trim(),
      funcion_principal: $('cm-funcion').value.trim(),
      tipo_funcion:      $('cm-tipo-funcion').value.trim(),
      cuadrilla:         $('cm-cuadrilla').value.trim(),
      coordinador_id:    Number($('cm-coordinador').value || 0),
      activo:            $('cm-activo').value === '1' ? 1 : 0,
    };
    if (!/^[A-Za-z0-9]+$/.test(payload.codigo) || payload.codigo.length > 20) {
      toast('Código requerido (solo letras y números)', 'error'); $('cm-codigo').focus(); return;
    }
    if (payload.dni && !/^\d{8}$/.test(payload.dni)) {
      toast('DNI inválido (8 dígitos)', 'error'); $('cm-dni').focus(); return;
    }
    if (payload.nombre.length < 3) { toast('Nombre requerido', 'error'); $('cm-nombre').focus(); return; }
    if (!payload.funcion_principal) { toast('Puesto requerido', 'error'); $('cm-funcion').focus(); return; }
    if (!payload.tipo_funcion) { toast('Función requerida', 'error'); $('cm-tipo-funcion').focus(); return; }
    if (!payload.cuadrilla) { toast('Team requerido', 'error'); $('cm-cuadrilla').focus(); return; }

    const btn = $('colModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch('../api/save_colaborador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Colaborador actualizado' : `Colaborador creado · ${data.codigo}`);
        closeModal();
        cargar();
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      console.error('[colaboradores] guardar:', e);
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  }

  async function eliminar(id) {
    const c = colaboradores.find(x => Number(x.id) === Number(id));
    if (!c) return;
    if (!confirm(`¿Eliminar al colaborador "${c.nombre}" (${c.codigo})?\nEsta acción no se puede deshacer.`)) return;
    try {
      const res = await fetch('../api/delete_colaborador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Colaborador eliminado'); cargar(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) {
      console.error('[colaboradores] eliminar:', e);
      toast('Error de red', 'error');
    }
  }

  // ════════════════════════════════════════════════════════════════════
  // MÓDULO DE IMPORTACIÓN
  // ════════════════════════════════════════════════════════════════════

  const HEADER_MAP = {
    codigo:    ['Código','Codigo','CODIGO','Cod.','COD.','Código Trabajador','Codigo Trabajador','ID'],
    nombre:    ['Nombre','NOMBRE','Nombres','Nombre completo','Apellidos y Nombres','APELLIDOS Y NOMBRES'],
    funcion:   ['Función','Funcion','FUNCION','Función Principal','Cargo','CARGO','Puesto'],
    cuadrilla: ['Team','TEAM','Teams','Cuadrilla','CUADRILLA','Turno','Grupo','Equipo']
  };

  let impRows = [];        // filas con status calculado
  let impFilter = 'all';

  function impOpen() {
    impRows = [];
    impFilter = 'all';
    $('impStep1').style.display = '';
    $('impStep2').style.display = 'none';
    $('impBack').style.display = 'none';
    $('impConfirm').style.display = 'none';
    $('impFile').value = '';
    $('impModalBack').classList.add('open');
  }
  function impClose() { $('impModalBack').classList.remove('open'); }

  // Normaliza un encabezado para comparar de forma tolerante a mayúsculas/espacios/acentos.
  function normHeader(h) {
    return String(h ?? '').trim().toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  // Pre-construye un mapa { encabezadoNormalizado → keyDeNuestroOut } a partir de HEADER_MAP.
  function buildHeaderLookup(headerKeys) {
    const lookup = {}; // normalizedKey → outKey ('codigo'|'nombre'|'funcion'|'cuadrilla')
    for (const outKey in HEADER_MAP) {
      for (const alias of HEADER_MAP[outKey]) {
        lookup[normHeader(alias)] = outKey;
      }
    }
    // De los encabezados del archivo, mapea su forma original al outKey correspondiente.
    const fileMap = {}; // outKey → rawHeaderName
    for (const h of headerKeys) {
      const outKey = lookup[normHeader(h)];
      if (outKey && !fileMap[outKey]) fileMap[outKey] = h;
    }
    return fileMap;
  }

  function normalizeRow(raw, fileMap) {
    const out = { codigo:'', nombre:'', funcion:'', cuadrilla:'' };
    for (const outKey in out) {
      const rawHeader = fileMap[outKey];
      if (rawHeader && raw[rawHeader] !== undefined && raw[rawHeader] !== null) {
        out[outKey] = String(raw[rawHeader]).trim();
      }
    }
    return out;
  }

  function validateRows(rawRows, fileMap) {
    const existingCodigoSet = new Set(colaboradores.map(c => c.codigo));
    const seenInFile = new Set();
    return rawRows.map((raw, idx) => {
      const row = normalizeRow(raw, fileMap);
      const errors = [];

      if (!row.codigo)                              errors.push('Código requerido');
      else if (!/^[A-Za-z0-9]+$/.test(row.codigo))  errors.push('Código inválido (solo letras y números)');
      else if (row.codigo.length > 20)              errors.push('Código demasiado largo (máx. 20)');
      if (!row.nombre || row.nombre.length < 3)     errors.push('Nombre requerido (≥3 caracteres)');
      if (!row.funcion)                             errors.push('Función requerida');
      if (!row.cuadrilla)                           errors.push('Team requerido');

      // Duplicado dentro del archivo
      if (row.codigo && seenInFile.has(row.codigo)) errors.push('Código duplicado dentro del archivo');
      else if (row.codigo) seenInFile.add(row.codigo);

      const status = errors.length ? 'error'
                   : existingCodigoSet.has(row.codigo) ? 'update'
                   : 'new';
      return { rowNumber: idx + 2, ...row, status, errors }; // +2 porque hoja Excel parte en 1 y tiene encabezado
    });
  }

  async function impHandleFile(file) {
    if (!file) return;
    if (!/\.xlsx$/i.test(file.name)) { toast('Solo se aceptan archivos .xlsx', 'error'); return; }
    try {
      const buf = await file.arrayBuffer();
      const wb = XLSX.read(buf, { type: 'array' });
      if (!wb.SheetNames.length) { toast('El archivo no tiene hojas', 'error'); return; }
      const sheet = wb.Sheets[wb.SheetNames[0]];
      const raw = XLSX.utils.sheet_to_json(sheet, { defval: '' });
      if (!raw.length) { toast('La hoja está vacía', 'error'); return; }

      // Construir mapa de encabezados normalizado (case/whitespace/accent insensitive).
      const headerKeys = Object.keys(raw[0]);
      const fileMap = buildHeaderLookup(headerKeys);
      if (!fileMap.codigo || !fileMap.nombre) {
        toast('No se reconocen las columnas Código / Nombre. Descarga la plantilla.', 'error');
        return;
      }
      if (!fileMap.cuadrilla) {
        toast('No se reconoce la columna Team. Descarga la plantilla.', 'error');
        return;
      }
      if (raw.length > 1000) {
        toast('Máximo 1000 filas por archivo. Divide la planilla.', 'error');
        return;
      }

      impRows = validateRows(raw, fileMap);
      impFilter = 'all';
      impRenderPreview();
      $('impStep1').style.display = 'none';
      $('impStep2').style.display = '';
      $('impBack').style.display = '';
      $('impConfirm').style.display = '';
    } catch (e) {
      console.error('[import] file parse:', e);
      toast('No se pudo leer el archivo (¿formato válido?)', 'error');
    }
  }

  function impRenderPreview() {
    const counts = { new:0, update:0, error:0 };
    impRows.forEach(r => counts[r.status]++);
    $('impSummary').innerHTML = `
      <span class="new"><span class="dot"></span>${counts.new} nuevos</span>
      <span class="upd"><span class="dot"></span>${counts.update} actualizar</span>
      <span class="err"><span class="dot"></span>${counts.error} con errores</span>
    `;
    const processable = counts.new + counts.update;
    $('impConfirm').textContent = `Confirmar ${processable} filas`;
    $('impConfirm').disabled = processable === 0;

    const visible = impRows.filter(r => impFilter === 'all' ? true : r.status === impFilter);
    const tb = $('impTbody'); tb.innerHTML = '';
    if (!visible.length) {
      tb.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--co-faint)">Sin filas para mostrar.</td></tr>`;
      return;
    }
    visible.forEach(r => {
      const tr = document.createElement('tr');
      tr.dataset.status = r.status;
      const label = r.status === 'new' ? '✓ Nuevo' : r.status === 'update' ? '↻ Actualizar' : '⚠ Error';
      tr.innerHTML = `
        <td style="font-family:var(--mono);color:var(--co-faint)">${r.rowNumber}</td>
        <td><span class="col-imp-status ${r.status}">${label}</span>${r.errors.length ? `<span class="col-imp-errmsg">${esc(r.errors.join(' · '))}</span>` : ''}</td>
        <td style="font-family:var(--mono)">${esc(r.codigo || '—')}</td>
        <td>${esc(r.nombre || '—')}</td>
        <td>${esc(r.funcion || '—')}</td>
        <td>${esc(r.cuadrilla || '—')}</td>`;
      tb.append(tr);
    });
  }

  function impDownloadTemplate(ev) {
    ev.preventDefault();
    const ws = XLSX.utils.aoa_to_sheet([
      ['Código','Nombre','Función','Team'],
      ['A001','Ejemplo Colaborador Uno','Estibador','G1 TEAM A'],
      ['A002','Ejemplo Colaborador Dos','Winchero','G2 TEAM B'],
    ]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Colaboradores');
    XLSX.writeFile(wb, 'plantilla_colaboradores.xlsx');
  }

  // ─── Eventos ───
  $('btnNew').addEventListener('click', () => openModal(null));
  $('btnImport').addEventListener('click', impOpen);
  $('colSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('colCoordFilter').addEventListener('change', e => {
    coordFiltro = e.target.value;
    $('colCoordFilterWrap').classList.toggle('is-on', coordFiltro !== 'todos');
    render();
  });
  $('colFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('colFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('colTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'edit') openModal(b.dataset.id);
    if (b.dataset.action === 'del')  eliminar(b.dataset.id);
  });
  $('colModalClose').addEventListener('click', closeModal);
  $('colModalCancel').addEventListener('click', closeModal);
  $('colModalBack').addEventListener('click', e => { if (e.target === $('colModalBack')) closeModal(); });
  $('colModalSave').addEventListener('click', guardar);
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if ($('colModalBack').classList.contains('open')) closeModal();
    if ($('impModalBack').classList.contains('open')) impClose();
  });

  // Wiring del modal de import
  $('impModalClose').addEventListener('click', impClose);
  $('impCancel').addEventListener('click', impClose);
  $('impBack').addEventListener('click', () => {
    $('impStep1').style.display = '';
    $('impStep2').style.display = 'none';
    $('impBack').style.display = 'none';
    $('impConfirm').style.display = 'none';
    $('impFile').value = '';
  });
  $('impModalBack').addEventListener('click', e => { if (e.target === $('impModalBack')) impClose(); });
  $('colDrop').addEventListener('click', () => $('impFile').click());
  $('colDrop').addEventListener('dragover', e => { e.preventDefault(); $('colDrop').classList.add('over'); });
  $('colDrop').addEventListener('dragleave', () => $('colDrop').classList.remove('over'));
  $('colDrop').addEventListener('drop', e => {
    e.preventDefault();
    $('colDrop').classList.remove('over');
    impHandleFile(e.dataTransfer.files[0]);
  });
  $('impFile').addEventListener('change', e => impHandleFile(e.target.files[0]));
  document.querySelector('.col-imp-filter')?.addEventListener('click', e => {
    const b = e.target.closest('button[data-imp-f]'); if (!b) return;
    impFilter = b.dataset.impF;
    document.querySelectorAll('.col-imp-filter button').forEach(x => x.classList.toggle('active', x === b));
    impRenderPreview();
  });
  $('impDownloadTpl').addEventListener('click', impDownloadTemplate);
  $('impConfirm').addEventListener('click', async () => {
      const payload = impRows
        .filter(r => r.status === 'new' || r.status === 'update')
        .map(r => ({ codigo: r.codigo, nombre: r.nombre, funcion: r.funcion, cuadrilla: r.cuadrilla }));

      if (!payload.length) { toast('No hay filas válidas para importar', 'error'); return; }

      const btn = $('impConfirm');
      btn.disabled = true;
      const original = btn.textContent;
      btn.textContent = 'Importando…';
      try {
        const res = await fetch('../api/import_colaboradores.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ rows: payload }),
        });
        const data = await res.json();
        if (data.success) {
          toast(`Importación completada · ${data.inserted} nuevos, ${data.updated} actualizados`);
          impClose();
          cargar();
        } else {
          toast(data.error || 'Error en la importación', 'error');
        }
      } catch (e) {
        console.error('[import] confirm:', e);
        toast('Error de red — ningún cambio guardado', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = original;
      }
  });

  cargarCoordinadores();
  cargar();

  window.__ColaboradoresPage = {
    reload: cargar,
    getList: () => colaboradores,
    getCoordinadores: () => coordinadores,
    toast,
  };
})();
</script>

</body>
</html>
