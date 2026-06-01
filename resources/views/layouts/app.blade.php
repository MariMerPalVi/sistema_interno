<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('processes.index') }}">
            <img class="brand-logo" src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves Cooperativa de Ahorro y Credito">
            <span>
                <strong>Las Naves</strong>
                <small>Sistema interno</small>
            </span>
        </a>
        <div class="session">
            <span>Asesor Demo</span>
            <span class="pill">Rol: Asesor</span>
        </div>
    </header>

    <main class="shell">
        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
