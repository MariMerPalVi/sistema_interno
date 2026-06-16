@extends('layouts.app')

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Apertura de cuentas</p>
            <h1>Seleccione el tipo de cuenta</h1>
        </div>
        <a class="button ghost" href="{{ route('processes.index') }}">Volver</a>
    </section>

    <section class="agency-selector">
        <div class="agency-static">
            <i data-lucide="building-2"></i>
            <span>
                <strong>Agencia</strong>
                <small>Los expedientes se guardarán automáticamente en esta agencia.</small>
            </span>
        </div>
        <strong class="agency-current">{{ auth()->user()->agencyName() }}</strong>
    </section>

    <section class="account-type-grid">
        @foreach ($accountTypes as $type)
            <form class="account-type-card" method="post" action="{{ route('accounts.store') }}">
                @csrf
                <input type="hidden" name="account_type_id" value="{{ $type->id }}">
                <div class="account-card-head">
                    <h2>{{ $type->name }}</h2>
                    <p>{{ $type->notes }}</p>
                </div>
                <div class="account-card-form">
                    <label>Número o nombre único del expediente
                        <input name="file_name" value="{{ old('file_name') }}" placeholder="Ej. 14115" required>
                    </label>
                    <small class="hint">No puede repetirse en ninguna agencia.</small>
                </div>
                <div class="requirement-box">
                    <span class="hint">Requisitos</span>
                    <ul class="requirement-list">
                        @foreach ($type->requirements as $requirement)
                            <li>
                                <span class="dot"></span>
                                <span>{{ $requirement->label }}</span>
                                @if (!$requirement->is_required)
                                    <small>Opcional segun caso</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="account-card-action">
                    <button class="button primary" type="submit">Crear expediente</button>
                </div>
            </form>
        @endforeach
    </section>
@endsection
