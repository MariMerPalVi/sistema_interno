<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="login-shell">
        <section class="login-panel">
            <img src="{{ asset('images/logo-las-naves.png') }}" alt="Cooperativa Las Naves">
            <div>
                <p class="eyebrow">Sistema interno</p>
                <h1>Ingresar</h1>
                <p>Acceda con el usuario asignado a su agencia.</p>
            </div>

            @if ($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="login-form">
                @csrf
                <label>Usuario
                    <input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
                </label>
                <label>Contraseña
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <label class="check">
                    <input type="checkbox" name="remember" value="1">
                    Mantener sesión iniciada
                </label>
                <button class="button primary" type="submit">Ingresar <i data-lucide="arrow-right"></i></button>
            </form>
        </section>
    </main>
</body>
</html>
