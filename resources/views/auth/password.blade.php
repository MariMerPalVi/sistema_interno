@extends('layouts.app')

@section('content')
    <section class="password-panel">
        <div class="panel-head">
            <h1 class="panel-title"><i data-lucide="key-round"></i> Cambiar contraseña</h1>
        </div>

        <form method="post" action="{{ route('password.update') }}" class="login-form">
            @csrf
            @method('put')
            <label>Contraseña actual
                <input type="password" name="current_password" autocomplete="current-password" required autofocus>
            </label>
            <label>Nueva contraseña
                <input type="password" name="password" autocomplete="new-password" required>
            </label>
            <label>Confirmar nueva contraseña
                <input type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>
            <div class="toolbar">
                <a class="button ghost" href="{{ route('processes.index') }}">Cancelar</a>
                <button class="button primary" type="submit"><i data-lucide="save"></i> Guardar contraseña</button>
            </div>
        </form>
    </section>
@endsection
