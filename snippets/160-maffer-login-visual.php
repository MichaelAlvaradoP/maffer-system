
/**
 * ============================================================
 * SNIPPET LOGIN — MAFFER: ESTILO VISUAL PÁGINA DE LOGIN
 * Nombre en WPCode: Maffer - Login Visual
 * Tipo: PHP Snippet
 * Insercion: Run Everywhere
 * ============================================================
 * SOLO CSS visual — no modifica ninguna lógica de WordPress.
 * ============================================================
 */

// ── Logo personalizado ───────────────────────────────────────
add_filter( 'login_headerurl', function () {
    return home_url();
} );

add_filter( 'login_headertext', function () {
    return 'Sistema de Alimentacion Maffer';
} );

// ── CSS visual del login ─────────────────────────────────────
add_action( 'login_enqueue_scripts', function () {
    wp_enqueue_style(
        'maffer-login-fonts',
        'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap',
        array(),
        null
    );
} );

add_action( 'login_head', function () {
    ?>
    <style>
    /* ── FONDO ── */
    html, body.login {
        height: 100% !important;
        margin: 0 !important;
    }
    body.login {
        background: #1c1710 !important;
        font-family: 'Inter', system-ui, sans-serif !important;
        -webkit-font-smoothing: antialiased;
        padding: 0 !important;
    }

    /* ── CENTRADO VERTICAL — solo el wrapper de login, no el body ── */
    body.login #login {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 420px !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* ── SELECTOR DE IDIOMA — oculto, no se utiliza ── */
    body.login #language-switcher,
    body.login .language-switcher {
        display: none !important;
    }

    /* ── LOGO ── */
    #login h1 a {
        background-image: url('http://127.0.0.1:8081/wp-content/uploads/2026/04/LOGO-MAFFER-scaled.webp') !important;
        background-size: contain !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        width: 300px !important;
        height: 150px !important;
        display: block !important;
        margin: 0 auto 8px !important;
        filter: brightness(0) invert(1) !important;
    }

    /* ── TÍTULO ── */
    #login h1 {
        text-align: center !important;
        margin-bottom: 0 !important;
    }
    #login h1::after {
        content: 'Sistema de Alimentación Maffer';
        display: block;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 20px;
        font-weight: 700;
        color: rgba(255,255,255,.75);
        letter-spacing: -.02em;
        margin-top: 8px;
        margin-bottom: 24px;
    }

    /* ── FORMULARIO ── */
    #loginform,
    #lostpasswordform,
    #registerform {
        background: #fffaf1 !important;
        border: 1px solid #e6d8bf !important;
        border-radius: 20px !important;
        box-shadow: 0 40px 80px rgba(0,0,0,.5) !important;
        padding: 28px 28px 24px !important;
        margin-top: 0 !important;
    }

    /* ── LABELS ── */
    #loginform label,
    #lostpasswordform label {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: .08em !important;
        color: #97897a !important;
        margin-bottom: 6px !important;
        display: block !important;
    }

    /* ── INPUTS ── */
    #loginform input[type="text"],
    #loginform input[type="password"],
    #loginform input[type="email"],
    #lostpasswordform input[type="text"],
    #lostpasswordform input[type="email"] {
        background: #fbf3e4 !important;
        border: 1.5px solid #e6d8bf !important;
        border-radius: 10px !important;
        padding: 11px 14px !important;
        font-family: 'Inter', sans-serif !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #2a231a !important;
        width: 100% !important;
        box-shadow: none !important;
        outline: none !important;
        transition: border-color .15s, box-shadow .15s, background .15s !important;
        box-sizing: border-box !important;
        height: auto !important;
    }

    #loginform input[type="text"]:focus,
    #loginform input[type="password"]:focus,
    #loginform input[type="email"]:focus,
    #lostpasswordform input[type="text"]:focus,
    #lostpasswordform input[type="email"]:focus {
        border-color: #E67E22 !important;
        background: #fffaf1 !important;
        box-shadow: 0 0 0 3px rgba(230,126,34,.16) !important;
    }

    /* ── CHECKBOX RECORDAR ── */
    #loginform .forgetmenot {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin-bottom: 0 !important;
    }
    #loginform .forgetmenot label {
        font-size: 12px !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        color: #6b5d4c !important;
        font-weight: 500 !important;
        margin-bottom: 0 !important;
    }
    #loginform input[type="checkbox"] {
        accent-color: #E67E22 !important;
        width: 15px !important;
        height: 15px !important;
    }

    /* ── BOTÓN SUBMIT ── */
    #loginform .button-primary,
    #lostpasswordform .button-primary,
    .login #wp-submit {
        background: linear-gradient(180deg, #ef8a2d, #E67E22 60%, #d26a10) !important;
        border: none !important;
        border-radius: 10px !important;
        box-shadow: 0 1px 0 rgba(255,255,255,.3) inset,
                    0 -2px 0 rgba(0,0,0,.12) inset,
                    0 6px 14px -4px rgba(230,126,34,.6) !important;
        color: #fff !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        letter-spacing: -.01em !important;
        padding: 12px 20px !important;
        width: 100% !important;
        height: auto !important;
        text-shadow: none !important;
        transition: transform .1s, box-shadow .1s !important;
        cursor: pointer !important;
    }
    #loginform .button-primary:hover,
    #lostpasswordform .button-primary:hover,
    .login #wp-submit:hover {
        background: linear-gradient(180deg, #f39234, #e9832a 60%, #d97015) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 1px 0 rgba(255,255,255,.3) inset,
                    0 -2px 0 rgba(0,0,0,.12) inset,
                    0 8px 20px -4px rgba(230,126,34,.7) !important;
    }
    #loginform .button-primary:active,
    .login #wp-submit:active {
        transform: translateY(0) !important;
    }

    /* ── SEPARADOR SUBMIT / OLVIDÉ CONTRASEÑA ── */
    #loginform #login_form_bottom,
    .submit {
        margin-top: 18px !important;
    }

    /* ── ENLACE "¿OLVIDASTE CONTRASEÑA?" ── */
    #nav a,
    #backtoblog a,
    .login #nav a,
    .login #backtoblog a {
        color: rgba(255,255,255,.5) !important;
        font-size: 12px !important;
        font-family: 'Inter', sans-serif !important;
        text-decoration: none !important;
        transition: color .15s !important;
    }
    #nav a:hover,
    #backtoblog a:hover,
    .login #nav a:hover,
    .login #backtoblog a:hover {
        color: #E67E22 !important;
    }

    /* ── MENSAJES DE ERROR ── */
    #login_error,
    .login .notice,
    .login .message {
        background: #fbebe6 !important;
        border-left: 4px solid #b94a32 !important;
        border-radius: 10px !important;
        color: #b94a32 !important;
        font-family: 'Inter', sans-serif !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        padding: 12px 16px !important;
        margin-bottom: 16px !important;
        box-shadow: none !important;
    }
    .login .message {
        background: #eef3e4 !important;
        border-left-color: #5a7a3a !important;
        color: #5a7a3a !important;
    }

    /* ── NAV INFERIOR ── */
    #nav, #backtoblog {
        text-align: center !important;
        margin-top: 14px !important;
        padding: 0 !important;
    }

    /* ── OCULTAR POWERED BY WORDPRESS ── */
    .login #backtoblog {
        display: none !important;
    }

    /* ── OCULTAR ÓVALO / ELEMENTOS SOBRANTES ── */
    body.login::after,
    body.login::before,
    #login::after,
    #login::before {
        display: none !important;
        content: none !important;
    }
    /* Scroll indicator de algunos navegadores / tema */
    body.login ::-webkit-scrollbar {
        display: none !important;
    }
    /* Div vacío que algunos plugins de login dejan */
    body.login #login_form_bottom:empty,
    body.login .login-form-bottom:empty {
        display: none !important;
    }

    /* ── RESPONSIVE MÓVIL ── */
    @media (max-width: 600px) {
        /* Volver a flujo normal en móvil — position:absolute causa solapamiento */
        body.login #login {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            transform: none !important;
            width: calc(100vw - 32px) !important;
            margin: 0 auto !important;
            padding-bottom: 20px !important;
        }

        /* El body vuelve a flujo normal con padding */
        body.login {
            display: block !important;
            padding: 40px 16px 20px !important;
        }

        /* Logo más contenido en móvil */
        #login h1 a {
            width: 200px !important;
            height: 100px !important;
        }

        /* Título un poco más pequeño */
        #login h1::after {
            font-size: 17px !important;
            margin-bottom: 18px !important;
        }

        /* Formulario con buen padding interno */
        #loginform,
        #lostpasswordform {
            padding: 24px 20px 20px !important;
        }

    }
    </style>
    <?php
} );