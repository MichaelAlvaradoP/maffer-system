
/**
 * ============================================================
 * SNIPPET 404 — MAFFER: PÁGINA "NO ENCONTRADA" PERSONALIZADA
 * Nombre en WPCode: Maffer - Página 404
 * Tipo: PHP Snippet
 * Inserción: Run Everywhere
 * ============================================================
 * Reemplaza la página 404 por defecto de WordPress con un
 * diseño coherente con el Sistema de Alimentación Maffer.
 * ============================================================
 */

add_filter( 'template_include', function( $template ) {
    if ( ! is_404() ) {
        return $template;
    }

    // Mostrar el 404 personalizado y detener la carga del tema
    status_header( 404 );
    nocache_headers();

    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Página no encontrada — Sistema de Alimentación Maffer</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
body{
    background:#1c1710;
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    padding:24px;
}
.card{
    background:#fffaf1;
    border:1px solid #e6d8bf;
    border-radius:24px;
    box-shadow:0 0 0 1px rgba(255,255,255,.04),0 40px 120px -20px rgba(0,0,0,.6);
    padding:48px 40px 40px;
    max-width:480px;
    width:100%;
    text-align:center;
}
.logo-wrap{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:6px;
    margin-bottom:32px;
}
.logo-wrap img{
    width:120px;
    height:auto;
    filter:brightness(0);
}
.logo-wrap span{
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:11px;
    font-weight:700;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:#97897a;
}
.code-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#fdf1e5;
    border:1px solid #f5d9b5;
    border-radius:16px;
    padding:8px 20px;
    margin-bottom:20px;
}
.code-badge span{
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:48px;
    font-weight:800;
    letter-spacing:-.04em;
    color:#E67E22;
    line-height:1;
}
h1{
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:22px;
    font-weight:800;
    letter-spacing:-.02em;
    color:#2a231a;
    margin-bottom:10px;
}
p{
    font-size:14px;
    color:#6b5d4c;
    line-height:1.6;
    margin-bottom:28px;
}
.divider{
    height:1px;
    background:#e6d8bf;
    margin:0 0 24px;
}
.btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:linear-gradient(180deg,#ef8a2d,#E67E22 60%,#d26a10);
    border:0;
    color:#fff;
    font-family:'Plus Jakarta Sans',sans-serif;
    font-weight:700;
    font-size:14px;
    padding:12px 22px;
    border-radius:10px;
    box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(230,126,34,.6);
    text-decoration:none;
    transition:transform .1s;
    cursor:pointer;
}
.btn:hover{transform:translateY(-1px)}
@media(max-width:500px){
    .card{padding:36px 24px 32px}
    .code-badge span{font-size:38px}
    h1{font-size:19px}
}
</style>
</head>
<body>
<div class="card">

    <!-- Logo -->
    <div class="logo-wrap">
        <img src="<?php echo esc_url( content_url( '/uploads/2026/04/LOGO-MAFFER-scaled.webp' ) ); ?>"
             alt="Maffer"
             onerror="this.style.display='none'">
        <span>Sistema de Alimentación</span>
    </div>

    <!-- 404 code -->
    <div class="code-badge">
        <span>404</span>
    </div>

    <!-- Title & message -->
    <h1>Página no encontrada</h1>
    <p>El enlace que seguiste no existe o fue movido.<br>Verifica la URL o regresa al inicio.</p>

    <div class="divider"></div>

    <!-- CTA -->
    <a href="<?php echo esc_url( home_url() ); ?>" class="btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Ir al inicio
    </a>


</div>
</body>
</html><?php

    exit;
}, 99 );