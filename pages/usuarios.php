<?php
require_once('../includes/auth.php');
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Usuarios · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">

  <style>
    .usr-wrap {
      --co-navy:       #00875A;
      --co-navy-700:   #005c3d;
      --co-red:        #dc2626;
      --co-deck:       #f5f8f7;
      --co-line:       rgba(0, 135, 90, 0.15);
      --co-line-bold:  rgba(0, 135, 90, 0.3);
      --co-ink:        #111827;
      --co-mute:       #4b5563;
      --co-faint:      #9ca3af;
      --st-ok-bg:#ECFDF3; --st-ok-fg:#057A55; --st-ok-dot:#12B76A;
      --st-er-bg:#FEF3F2; --st-er-fg:#B42318; --st-er-dot:#F04438;
      --mono: ui-monospace, Consolas, monospace;

      display: flex; flex-direction: column; gap: 18px;
      font-family: 'DM Sans', system-ui, sans-serif;
      color: var(--co-ink);
    }
    .usr-wrap *, .usr-wrap *::before, .usr-wrap *::after { box-sizing: border-box; }

    .usr-hero {
      background: linear-gradient(155deg, #005c3d 0%, #00875A 100%);
      color: #fff; border-radius: 20px; padding: 22px 28px;
      display: flex; align-items: center; justify-content: space-between;
      gap: 18px;
    }
    .usr-hero h1 { margin: 6px 0 4px; font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
    .usr-hero p  { margin: 0; color: rgba(255,255,255,.85); font-size: 13px; }
    .usr-hero .tag {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 5px 11px; border-radius: 999px;
      background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25);
      font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
    }

    .usr-btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 16px; border-radius: 10px; border: 1px solid var(--co-line-bold);
      background: #fff; cursor: pointer;
      font-family: inherit; font-size: 13px; font-weight: 600; color: var(--co-ink);
      transition: all .15s;
    }
    .usr-btn:hover { border-color: var(--co-navy); color: var(--co-navy); }
    .usr-btn.primary { background: var(--co-navy); color: #fff; border-color: var(--co-navy); }
    .usr-btn.primary:hover { background: var(--co-navy-700); color: #fff; }
    .usr-btn.danger { background: #fff; border-color: #FCA5A5; color: var(--st-er-fg); }
    .usr-btn.danger:hover { background: var(--st-er-bg); }
    .usr-btn svg { width: 14px; height: 14px; }

    .usr-toolbar {
      display: flex; gap: 10px; align-items: center;
      background: #fff; border: 1px solid var(--co-line);
      border-radius: 14px; padding: 10px 12px;
    }
    .usr-search {
      flex: 1; display: flex; align-items: center; gap: 8px;
      background: var(--co-deck); border: 1px solid transparent;
      border-radius: 10px; padding: 8px 12px;
    }
    .usr-search:focus-within { border-color: var(--co-navy); background: #fff; }
    .usr-search input {
      flex: 1; border: 0; outline: 0; background: transparent;
      font-family: inherit; font-size: 13.5px; color: var(--co-ink);
    }
    .usr-search svg { width: 15px; height: 15px; color: var(--co-mute); }

    .usr-table-wrap {
      background: #fff; border: 1px solid var(--co-line);
      border-radius: 14px; overflow-x: auto; overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .usr-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
    .usr-table thead tr {
      background: var(--co-deck);
      border-bottom: 1px solid var(--co-line-bold);
    }
    .usr-table th {
      padding: 11px 14px; text-align: left;
      font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase;
      color: var(--co-mute); font-weight: 700;
    }
    .usr-table tbody tr {
      border-bottom: 1px solid var(--co-line);
      transition: background .12s;
    }
    .usr-table tbody tr:last-child { border-bottom: 0; }
    .usr-table tbody tr:hover { background: #f8fafc; }
    .usr-table td { padding: 11px 14px; vertical-align: middle; }
    .usr-cell-name { display: flex; align-items: center; gap: 10px; }
    .usr-avatar {
      width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
      background: var(--co-navy); color: #fff;
      display: grid; place-items: center;
      font-size: 13px; font-weight: 700;
    }
    .usr-name { font-weight: 600; color: var(--co-ink); }
    .usr-email { font-size: 11px; color: var(--co-faint); font-family: var(--mono); }

    .usr-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 8px; border-radius: 6px;
      font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    }
    .usr-badge .dot { width: 6px; height: 6px; border-radius: 50%; }
    .usr-badge.is-admin    { background: #EFF6FF; color: #1D4ED8; }
    .usr-badge.is-admin .dot { background: #2563EB; }
    .usr-badge.is-op       { background: var(--co-deck); color: var(--co-navy); }
    .usr-badge.is-op .dot  { background: var(--co-navy); }
    .usr-badge.is-active   { background: var(--st-ok-bg); color: var(--st-ok-fg); }
    .usr-badge.is-active .dot { background: var(--st-ok-dot); }
    .usr-badge.is-inactive { background: var(--st-er-bg); color: var(--st-er-fg); }
    .usr-badge.is-inactive .dot { background: var(--st-er-dot); }

    .usr-act-btn {
      padding: 5px 10px; border-radius: 7px; border: 1px solid var(--co-line-bold);
      background: #fff; cursor: pointer;
      font-family: inherit; font-size: 12px; font-weight: 600; color: var(--co-ink);
      transition: all .12s;
    }
    .usr-act-btn:hover { border-color: var(--co-navy); color: var(--co-navy); }
    .usr-act-btn.danger:hover { border-color: var(--co-red); color: var(--co-red); }
    .usr-cell-actions { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }

    .usr-modal-back {
      position: fixed; inset: 0; background: rgba(11,31,58,.48);
      display: grid; place-items: center; z-index: 995;
      opacity: 0; pointer-events: none; transition: opacity .2s;
    }
    .usr-modal-back.open { opacity: 1; pointer-events: auto; }
    .usr-modal {
      background: #fff; border-radius: 18px; width: 480px; max-width: 94vw;
      box-shadow: 0 24px 64px -16px rgba(0,43,92,.35);
      transform: translateY(12px) scale(.97); transition: transform .22s cubic-bezier(.25,.46,.45,.94);
      max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
    }
    .usr-modal-back.open .usr-modal { transform: translateY(0) scale(1); }
    .usr-modal-head {
      padding: 18px 20px 14px; border-bottom: 1px solid var(--co-line);
      display: flex; align-items: center; justify-content: space-between;
    }
    .usr-modal-head h3 { margin: 0; font-size: 17px; font-weight: 700; }
    .usr-modal-head .sub { font-size: 12px; color: var(--co-mute); margin-top: 2px; }
    .usr-modal-close {
      width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--co-line);
      background: #fff; cursor: pointer; display: grid; place-items: center; color: var(--co-mute);
    }
    .usr-modal-close:hover { color: var(--co-red); border-color: var(--co-red); }
    .usr-modal-body {
      padding: 18px 20px; display: flex; flex-direction: column; gap: 12px;
      overflow-y: auto; flex: 1;
    }
    .usr-modal-foot {
      padding: 14px 20px; border-top: 1px solid var(--co-line);
      display: flex; justify-content: flex-end; gap: 8px;
    }
    .usr-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .usr-field { display: flex; flex-direction: column; gap: 5px; }
    .usr-field label {
      font-size: 11px; font-weight: 600; color: var(--co-mute);
      letter-spacing: .04em; text-transform: uppercase;
    }
    .usr-field input, .usr-field select {
      font-family: inherit; font-size: 13.5px; color: var(--co-ink);
      background: #fff; border: 1px solid var(--co-line-bold); border-radius: 8px;
      padding: 9px 11px; outline: 0; transition: border-color .15s, box-shadow .15s;
    }
    .usr-field input:focus, .usr-field select:focus {
      border-color: var(--co-navy); box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.12);
    }
    .usr-field .hint { font-size: 11px; color: var(--co-faint); margin-top: 2px; }

    .usr-toast {
      position: fixed; bottom: 24px; right: 24px; z-index: 999;
      background: #001b3a; color: #fff;
      padding: 12px 18px; border-radius: 10px;
      font-size: 13px; font-weight: 600;
      box-shadow: 0 8px 24px -8px rgba(0,0,0,.4);
      transform: translateY(120%); opacity: 0; transition: all .25s;
    }
    .usr-toast.show { transform: translateY(0); opacity: 1; }
    .usr-toast.is-error { background: var(--st-er-fg); }

    .content { padding: 24px 28px 60px; overflow-y: auto; }

    body .usr-wrap .usr-btn.primary,
    body .usr-modal .usr-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      border: none !important;
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(0, 135, 90, 0.22) !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    body .usr-wrap .usr-btn.primary:hover,
    body .usr-modal .usr-btn.primary:hover {
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 18px rgba(0, 135, 90, 0.35) !important;
      opacity: 0.95 !important;
    }
    body .usr-wrap .usr-btn.primary:active,
    body .usr-modal .usr-btn.primary:active {
      transform: translateY(0.5px) !important;
    }

    body .usr-wrap .usr-toolbar {
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.15) !important;
      border-radius: 16px !important;
      padding: 12px 14px !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
    }
    body .usr-wrap .usr-search {
      background-color: #f5f8f7 !important;
      border: 1px solid rgba(0, 135, 90, 0.1) !important;
      transition: all 0.25s ease !important;
      border-radius: 10px !important;
      padding: 9px 14px !important;
      box-shadow: none !important;
    }
    body .usr-wrap .usr-search:focus-within {
      border-color: #00875A !important;
      background-color: #ffffff !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.12) !important;
    }
    body .usr-wrap .usr-search input {
      color: #111827 !important;
      font-size: 13.5px !important;
    }
    body .usr-wrap .usr-search input::placeholder {
      color: #9ca3af !important;
    }
    body .usr-wrap .usr-search svg {
      color: #00875A !important;
    }

    body .usr-wrap .usr-table-wrap {
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.15) !important;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03) !important;
      border-radius: 16px !important;
    }
    body .usr-wrap .usr-table thead tr {
      background-color: #f5f8f7 !important;
      border-bottom: 2px solid rgba(0, 135, 90, 0.2) !important;
    }
    body .usr-wrap .usr-table th {
      padding: 14px 18px !important;
      color: #4b5563 !important;
      font-size: 11.5px !important;
      font-weight: 700 !important;
    }
    body .usr-wrap .usr-table tbody tr {
      border-bottom: 1px solid rgba(0, 135, 90, 0.08) !important;
      transition: all 0.2s ease !important;
    }
    body .usr-wrap .usr-table tbody tr:hover {
      background: rgba(0, 135, 90, 0.04) !important;
    }
    body .usr-wrap .usr-table td {
      padding: 15px 18px !important;
      color: #111827 !important;
    }

    body .usr-wrap .usr-name {
      font-size: 14px !important;
      font-weight: 600 !important;
      color: #111827 !important;
    }
    body .usr-wrap .usr-avatar {
      width: 36px !important;
      height: 36px !important;
      border-radius: 10px !important;
      background: linear-gradient(135deg, rgba(0, 135, 90, 0.12) 0%, rgba(0, 135, 90, 0.05) 100%) !important;
      border: 1px solid rgba(0, 135, 90, 0.25) !important;
      color: #00875A !important;
      font-size: 13px !important;
      font-weight: 700 !important;
      box-shadow: none !important;
    }
    body .usr-wrap .usr-email-txt {
      font-family: var(--mono) !important;
      font-size: 12.5px !important;
      color: #4b5563 !important;
    }
    body .usr-wrap .usr-date-txt {
      font-size: 12.5px !important;
      color: #4b5563 !important;
    }

    body .usr-wrap .usr-act-btn {
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.22) !important;
      color: #00875A !important;
      font-size: 12.5px !important;
      font-weight: 600 !important;
      padding: 6px 12px !important;
      border-radius: 8px !important;
      transition: all 0.2s ease !important;
    }
    body .usr-wrap .usr-act-btn:hover {
      background: rgba(0, 135, 90, 0.08) !important;
      border-color: #00875A !important;
      color: #005c3d !important;
    }
    body .usr-wrap .usr-act-btn.danger {
      background: rgba(239, 68, 68, 0.05) !important;
      border: 1px solid rgba(239, 68, 68, 0.25) !important;
      color: #ef4444 !important;
    }
    body .usr-wrap .usr-act-btn.danger:hover {
      background: #ef4444 !important;
      border-color: #ef4444 !important;
      color: #ffffff !important;
    }
    body .usr-wrap .usr-act-btn:disabled,
    body .usr-wrap .usr-act-btn:disabled:hover {
      background: #f3f4f6 !important;
      border-color: #e5e7eb !important;
      color: #9ca3af !important;
      cursor: not-allowed !important;
    }

    body .usr-wrap .usr-badge,
    body .usr-modal .usr-badge {
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      padding: 4px 10px !important;
      border-radius: 20px !important;
      font-size: 11px !important;
      font-weight: 700 !important;
      text-transform: uppercase !important;
      border: 1px solid transparent !important;
    }
    body .usr-wrap .usr-badge .dot,
    body .usr-modal .usr-badge .dot {
      width: 6px !important;
      height: 6px !important;
      border-radius: 50% !important;
      display: inline-block !important;
    }

    body .usr-wrap .usr-badge.is-admin {
      background: #f5f3ff !important;
      border: 1px solid #ede9fe !important;
      color: #6d28d9 !important;
    }
    body .usr-wrap .usr-badge.is-admin .dot {
      background: #8b5cf6 !important;
    }
    body .usr-wrap .usr-badge.is-coord {
      background: #ecfeff !important;
      border: 1px solid #cffafe !important;
      color: #0891b2 !important;
    }
    body .usr-wrap .usr-badge.is-coord .dot {
      background: #06b6d4 !important;
    }
    body .usr-wrap .usr-badge.is-sop {
      background: rgba(124,58,237,.10) !important;
      border: 1px solid rgba(124,58,237,.22) !important;
      color: #7c3aed !important;
    }
    body .usr-wrap .usr-badge.is-sop .dot {
      background: #7c3aed !important;
    }
    body .usr-wrap .usr-badge.is-super {
      background: #eff6ff !important;
      border: 1px solid #dbeafe !important;
      color: #1d4ed8 !important;
    }
    body .usr-wrap .usr-badge.is-super .dot {
      background: #3b82f6 !important;
    }
    body .usr-wrap .usr-badge.is-op {
      background: #f3f4f6 !important;
      border: 1px solid #e5e7eb !important;
      color: #4b5563 !important;
    }
    body .usr-wrap .usr-badge.is-op .dot {
      background: #9ca3af !important;
    }

    body .usr-wrap .usr-badge.is-active {
      background: #ecfdf5 !important;
      border: 1px solid #d1fae5 !important;
      color: #057a55 !important;
    }
    body .usr-wrap .usr-badge.is-active .dot {
      background: #10b981 !important;
    }
    body .usr-wrap .usr-badge.is-inactive {
      background: #fef2f2 !important;
      border: 1px solid #fee2e2 !important;
      color: #b42318 !important;
    }
    body .usr-wrap .usr-badge.is-inactive .dot {
      background: #ef4444 !important;
    }

    body .usr-modal {
      background-color: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.18) !important;
      box-shadow: 0 24px 64px -16px rgba(0, 135, 90, 0.2) !important;
      border-radius: 18px !important;
    }
    body .usr-modal-head {
      border-bottom: 1px solid rgba(0, 135, 90, 0.12) !important;
      padding: 20px 24px !important;
      background-color: #ffffff !important;
    }
    body .usr-modal-head h3 {
      color: #111827 !important;
      font-size: 18px !important;
      font-weight: 700 !important;
    }
    body .usr-modal-head .sub {
      color: #4b5563 !important;
      font-size: 12px !important;
    }
    body .usr-modal-body {
      padding: 24px !important;
    }
    body .usr-modal-foot {
      border-top: 1px solid rgba(0, 135, 90, 0.12) !important;
      padding: 16px 24px !important;
      background-color: #ffffff !important;
    }

    body .usr-modal .usr-field {
      margin-bottom: 4px !important;
    }
    body .usr-modal .usr-field label {
      color: #4b5563 !important;
      font-size: 11px !important;
      font-weight: 600 !important;
      margin-bottom: 6px !important;
    }
    body .usr-modal .usr-field input,
    body .usr-modal .usr-field select {
      background-color: #ffffff !important;
      border: 1.5px solid #cbd5e1 !important;
      color: #111827 !important;
      border-radius: 10px !important;
      padding: 11px 14px !important;
      font-family: inherit !important;
      font-size: 13.5px !important;
      outline: none !important;
      transition: all 0.25s ease !important;
    }
    body .usr-modal .usr-field input:focus,
    body .usr-modal .usr-field select:focus {
      border-color: #00875A !important;
      background-color: #ffffff !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.12) !important;
    }
    body .usr-modal .usr-field input::placeholder {
      color: #9ca3af !important;
    }
    body .usr-modal .usr-field .hint {
      color: #6b7280 !important;
      font-size: 11px !important;
      margin-top: 4px !important;
    }

    body .usr-modal .usr-modal-close {
      background: #ffffff !important;
      border: 1px solid rgba(0, 135, 90, 0.18) !important;
      color: #4b5563 !important;
      width: 32px !important;
      height: 32px !important;
      border-radius: 8px !important;
      cursor: pointer !important;
      display: grid !important;
      place-items: center !important;
      transition: all 0.2s ease !important;
    }
    body .usr-modal .usr-modal-close:hover {
      background: rgba(239, 68, 68, 0.08) !important;
      border-color: rgba(239, 68, 68, 0.25) !important;
      color: #ef4444 !important;
    }

    body .usr-modal .usr-btn:not(.primary) {
      background: #ffffff !important;
      border: 1px solid #cbd5e1 !important;
      color: #4b5563 !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      padding: 10px 18px !important;
      border-radius: 10px !important;
      cursor: pointer !important;
      transition: all 0.2s ease !important;
    }
    body .usr-modal .usr-btn:not(.primary):hover {
      background: #f9fafb !important;
      border-color: #9ca3af !important;
      color: #111827 !important;
    }

    body .usr-toast {
      background-color: #111827 !important;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      color: #ffffff !important;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
      padding: 12px 20px !important;
      border-radius: 10px !important;
    }
    body .usr-toast.is-error {
      border-color: rgba(239, 68, 68, 0.5) !important;
      background-color: #fef2f2 !important;
      color: #b42318 !important;
    }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php
    $sb_base = '..';
    include('../includes/sidebar.php');
  ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="usr-wrap">

        <section class="usr-hero">
          <div>
            <span class="tag">ADMINISTRACIÓN · USUARIOS</span>
            <h1>Gestión de usuarios</h1>
            <p>Alta, edición y eliminación de cuentas. Las contraseñas se guardan hasheadas (bcrypt).</p>
          </div>
          <button class="usr-btn primary" id="btnNewUser">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo usuario
          </button>
        </section>

        <div class="usr-toolbar">
          <div class="usr-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="usrSearch" type="text" placeholder="Buscar por nombre, correo o rol…">
          </div>
        </div>

        <div class="usr-table-wrap">
          <table class="usr-table">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Último acceso</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="usrTbody">
              <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<div class="usr-modal-back" id="usrModalBack">
  <div class="usr-modal">
    <div class="usr-modal-head">
      <div>
        <h3 id="usrModalTitle">Nuevo usuario</h3>
        <div class="sub">Las contraseñas se hashean con bcrypt antes de guardar.</div>
      </div>
      <button class="usr-modal-close" id="usrModalClose">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="usr-modal-body">
      <input type="hidden" id="um-id">
      <div class="usr-field">
        <label>Nombre completo</label>
        <input id="um-nombre" type="text" placeholder="Apellidos y Nombres">
      </div>
      <div class="usr-field">
        <label>Correo electrónico</label>
        <input id="um-email" type="email" placeholder="email@dominio.com">
      </div>
      <div class="usr-row2">
        <div class="usr-field">
          <label>Rol</label>
          <select id="um-rol">
            <option value="Coordinador">Coordinador</option>
            <option value="Soporte">Tally Soporte</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Administrador">Administrador</option>
            <option value="Operador">Operador</option>
          </select>
        </div>
        <div class="usr-field">
          <label>Estado</label>
          <select id="um-estado">
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </div>
      </div>
      <div class="usr-field" id="um-soporte-wrap" style="display:none">
        <label>Coordinador a cargo</label>
        <select id="um-soporte-de"><option value="">— Selecciona —</option></select>
        <span class="hint">El Tally Soporte es apoyo directo de este coordinador, que verá sus tareas en solo lectura.</span>
      </div>
      <div class="usr-field">
        <label>Contraseña</label>
        <input id="um-password" type="password" placeholder="Mín. 6 caracteres" autocomplete="new-password">
        <span class="hint" id="um-pass-hint">Mínimo 6 caracteres.</span>
      </div>
    </div>
    <div class="usr-modal-foot">
      <button class="usr-btn" id="usrModalCancel">Cancelar</button>
      <button class="usr-btn primary" id="usrModalSave">Guardar</button>
    </div>
  </div>
</div>

<div class="usr-toast" id="usrToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const myId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;

  let usuarios = [];
  let coordinadores = [];
  let query = '';
  let editingId = null;

  function toast(msg, type) {
    const t = $('usrToast');
    t.textContent = msg;
    t.className = 'usr-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 2800);
  }

  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
  function initials(n){ const w = n.trim().split(/\s+/); return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase(); }
  function fmtDate(s){
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return '—';
    return d.toLocaleString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  function render() {
    const q = query.trim().toLowerCase();
    const list = usuarios.filter(u =>
      !q || [u.nombre, u.email, u.rol].some(v => String(v ?? '').toLowerCase().includes(q))
    );
    const tbody = $('usrTbody');
    tbody.innerHTML = '';
    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--co-faint)">Sin resultados.</td></tr>`;
      return;
    }
    list.forEach(u => {
      const isActive = u.estado === 'Activo';
      const isMe = (Number(u.id) === myId);

      const roleClass = u.rol === 'Administrador' ? 'is-admin' :
                        u.rol === 'Coordinador' ? 'is-coord' :
                        u.rol === 'Soporte' ? 'is-sop' :
                        u.rol === 'Supervisor' ? 'is-super' : 'is-op';
      const rolTxt = u.rol === 'Soporte' ? 'Tally Soporte' : u.rol;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="usr-cell-name">
            <div class="usr-avatar">${esc(initials(u.nombre))}</div>
            <div>
              <div class="usr-name">${esc(u.nombre)}${isMe ? ' <span style="color:var(--co-faint);font-weight:500;font-size:11px">(tú)</span>' : ''}</div>
              <div class="usr-email">ID: ${esc(u.id)}</div>
            </div>
          </div>
        </td>
        <td><span class="usr-email-txt">${esc(u.email)}</span></td>
        <td><span class="usr-badge ${roleClass}"><span class="dot"></span>${esc(rolTxt)}${
              u.soporte_de_nombre ? `<span style="font-weight:500;opacity:.75"> · ${esc(u.soporte_de_nombre)}</span>` : ''
            }</span></td>
        <td><span class="usr-badge ${isActive ? 'is-active' : 'is-inactive'}"><span class="dot"></span>${esc(u.estado)}</span></td>
        <td><span class="usr-date-txt">${fmtDate(u.ultimo_acceso)}</span></td>
        <td>
          <div class="usr-cell-actions">
            <button class="usr-act-btn" data-action="edit" data-id="${esc(u.id)}">Editar</button>
            <button class="usr-act-btn danger" data-action="del" data-id="${esc(u.id)}" ${isMe ? 'disabled title="No puedes eliminarte"' : ''}>Eliminar</button>
          </div>
        </td>`;
      tbody.append(tr);
    });
  }

  async function cargar() {
    try {
      const res = await fetch('../api/get_usuarios.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
      usuarios = data.data || [];
      coordinadores = data.coordinadores || [];
      render();
    } catch (e) {
      toast('Error de red', 'error');
    }
  }

  function pintarCoordinadores(sel) {
    const s = $('um-soporte-de');
    s.innerHTML = '<option value="">— Selecciona —</option>';
    coordinadores.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.activo ? c.nombre : `${c.nombre} (inactivo)`;
      s.append(o);
    });
    s.value = sel ? String(sel) : '';
  }

  function toggleSoporte() {
    $('um-soporte-wrap').style.display = ($('um-rol').value === 'Soporte') ? '' : 'none';
  }

  function openModal(id) {
    editingId = id || null;
    const u = id ? usuarios.find(x => Number(x.id) === Number(id)) : null;
    $('usrModalTitle').textContent = u ? 'Editar usuario' : 'Nuevo usuario';
    $('um-id').value       = u ? u.id : '';
    $('um-nombre').value   = u ? u.nombre : '';
    $('um-email').value    = u ? u.email : '';
    $('um-rol').value      = u ? u.rol : 'Operador';
    $('um-estado').value   = u ? u.estado : 'Activo';
    $('um-password').value = '';
    $('um-pass-hint').textContent = u
      ? 'Dejar en blanco para no cambiar. Mínimo 6 caracteres si la cambias.'
      : 'Mínimo 6 caracteres.';
    pintarCoordinadores(u ? u.soporte_de_id : null);
    toggleSoporte();
    $('usrModalBack').classList.add('open');
    setTimeout(() => $('um-nombre').focus(), 80);
  }
  function closeModal() { $('usrModalBack').classList.remove('open'); editingId = null; }

  async function guardar() {
    const payload = {
      id:       parseInt($('um-id').value, 10) || 0,
      nombre:   $('um-nombre').value.trim(),
      email:    $('um-email').value.trim(),
      rol:      $('um-rol').value,
      estado:   $('um-estado').value,
      password: $('um-password').value,
      soporte_de_id: $('um-rol').value === 'Soporte' ? ($('um-soporte-de').value || '') : '',
    };

    if (!payload.nombre) { toast('El nombre es obligatorio', 'error'); $('um-nombre').focus(); return; }
    if (!payload.email)  { toast('El correo es obligatorio', 'error'); $('um-email').focus(); return; }
    if (payload.rol === 'Soporte' && !payload.soporte_de_id) {
      toast('Un Tally Soporte necesita un Coordinador a cargo', 'error');
      $('um-soporte-de').focus(); return;
    }
    if (!payload.id && payload.password.length < 6) {
      toast('Contraseña mín. 6 caracteres', 'error'); $('um-password').focus(); return;
    }
    if (payload.id && payload.password !== '' && payload.password.length < 6) {
      toast('Contraseña mín. 6 caracteres', 'error'); $('um-password').focus(); return;
    }

    const btn = $('usrModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch('../api/save_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Usuario actualizado' : 'Usuario creado');
        closeModal();
        cargar();
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  }

  async function eliminar(id) {
    const u = usuarios.find(x => Number(x.id) === Number(id));
    if (!u) return;
    if (!confirm(`¿Eliminar a "${u.nombre}" (${u.email})?\nEsta acción no se puede deshacer.`)) return;
    try {
      const res = await fetch('../api/delete_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      });
      const data = await res.json();
      if (data.success) { toast('Usuario eliminado'); cargar(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) {
      toast('Error de red', 'error');
    }
  }

  $('btnNewUser').addEventListener('click', () => openModal(null));
  $('usrSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('usrModalClose').addEventListener('click', closeModal);
  $('usrModalCancel').addEventListener('click', closeModal);
  $('usrModalBack').addEventListener('click', e => { if (e.target === $('usrModalBack')) closeModal(); });
  $('usrModalSave').addEventListener('click', guardar);
  $('um-rol').addEventListener('change', toggleSoporte);
  $('usrTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    const id = b.dataset.id;
    if (b.dataset.action === 'edit') openModal(id);
    if (b.dataset.action === 'del')  eliminar(id);
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $('usrModalBack').classList.contains('open')) closeModal();
  });

  cargar();
})();
</script>

</body>
</html>
