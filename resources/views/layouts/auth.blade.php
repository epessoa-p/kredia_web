<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Kredia')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    {{-- Mismo estándar visual que el resto del sistema (escala de letra, botones) --}}
    <link rel="stylesheet" href="{{ asset('css/kredia-ui.css') }}?v={{ filemtime(public_path('css/kredia-ui.css')) }}">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            font-family: var(--k-font);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .auth-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: var(--fs-xl);
            margin: 0 auto 20px;
        }

        .auth-header h1 {
            font-size: var(--fs-xl);
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .auth-header p {
            color: #999;
            font-size: var(--fs-sm);
            margin-top: 10px;
        }

        .form-control {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: var(--fs-base);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: var(--fs-base);
        }

        .alert {
            border: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .input-group-text {
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: #666;
            font-size: var(--fs-sm);
        }

        .remember-me input {
            margin-right: 8px;
            cursor: pointer;
        }

        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #0d6efd;
            text-decoration: none;
            font-size: var(--fs-sm);
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 25px;
            }

            .auth-header h1 {
                font-size: var(--fs-lg);
            }

            .logo {
                width: 60px;
                height: 60px;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
