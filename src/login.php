<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="<?php echo STATIC_URL; ?>img/icons/icon-48x48.png" />

    <title>Iniciar sesión | Pikero</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo STATIC_URL; ?>css/app.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

    <style>
        :root {
            --pikero-navy: #1f2a37;
            --pikero-slate: #334155;
            --pikero-orange: #e24b26;
        }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top right, rgba(226, 75, 38, 0.35), transparent 32%),
                linear-gradient(160deg, #0f1720 0%, #1f2a37 48%, #111827 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 32, 0.28);
            backdrop-filter: blur(6px);
            z-index: 0;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            padding: 2.5rem;
        }

        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--pikero-orange);
            box-shadow: 0 0 0 4px rgba(226, 75, 38, 0.18);
        }

        .btn-pikero {
            background-color: var(--pikero-navy);
            color: white;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-pikero:hover {
            background-color: #15202b;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(31, 42, 55, 0.35);
            color: white;
        }

        .preloader {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease-in-out infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .brand-logo {
            transition: transform 0.3s ease;
        }

        .brand-logo:hover {
            transform: scale(1.05);
        }

        .bird-mark {
            width: 72px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="text-center mb-6 animate__animated animate__fadeInDown">
            <h1 class="h2 text-white fw-bold mb-1">¡Hola de nuevo!</h1>
            <p class="text-white-50">Ingresa tus credenciales para continuar</p>
        </div>

        <div class="glass-card animate__animated animate__zoomIn">
            <div class="text-center mb-4">
                <img src="<?php echo STATIC_URL; ?>img/logo_pajaro.png" alt="Pikero" class="bird-mark" />
                <img src="<?php echo STATIC_URL; ?>img/titulo_logo.png" alt="Pikero Logo" class="brand-logo img-fluid" width="220" />
            </div>

            <form id="login-form">
                <div class="mb-4">
                    <label for="user" class="form-label fw-semibold text-secondary">Usuario</label>
                    <input class="form-control animate__animated" type="text" name="user" id="user" placeholder="nombre.usuario" required />
                </div>

                <div class="mb-4">
                    <label for="pass" class="form-label fw-semibold text-secondary">Contraseña</label>
                    <input class="form-control animate__animated" type="password" name="pass" id="pass" placeholder="••••••••" required />
                </div>

                <div class="text-center mt-6">
                          <button id="btn-login"
                              type="submit"
                              class="btn-pikero animate__animated">
                        <span id="text-btn-login">Entrar al sistema</span>
                        <div class="preloader" id="login-loader"></div>
                          </button>
                </div>
            </form>
        </div>

        <div class="text-center mt-4 animate__animated animate__fadeInUp animate__delay-1s">
            <p class="text-white-50 small">
                &copy; <?php echo date('Y'); ?> Pikero - Software para ferreterías
            </p>
        </div>
    </div>

    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
        const API_URL = "<?php echo STATIC_URL; ?>api/";
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="<?php echo STATIC_URL; ?>js/app.js"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo STATIC_URL; ?>js/login/login.js"></script>

    <script>
        function toggleLoader() {
            const loader = document.getElementById('login-loader');
            loader.style.display = (loader.style.display === 'none' || loader.style.display === '') ? 'block' : 'none';
        }
    </script>
</body>
</html>
