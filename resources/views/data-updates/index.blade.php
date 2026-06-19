@extends('layouts.app')

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Actualización de datos</p>
            <h1>Trámites de actualización</h1>
        </div>
        <a class="button primary" href="#nuevo"><i data-lucide="file-pen-line"></i> Nuevo trámite</a>
    </section>

    <section id="nuevo" class="panel">
        <div class="panel-head">
            <h2>Crear trámite</h2>
            <span class="agency-label"><i data-lucide="building-2"></i> {{ auth()->user()->agencyName() }}</span>
        </div>
        <form method="post" action="{{ route('data-updates.store') }}" class="data-update-create">
            @csrf
            <label>Número o nombre único del expediente
                <input name="file_name" value="{{ old('file_name') }}" placeholder="Ej. 14115-actualizacion" required>
            </label>
            <label>Cédula o RUC del socio
                <input name="member_identification" value="{{ old('member_identification') }}" placeholder="Ej. 0202217303" required>
            </label>
            <label>Nombre del socio
                <input name="member_name" value="{{ old('member_name') }}" placeholder="Opcional">
            </label>
            <label class="span-2">Observación inicial
                <input name="observations" value="{{ old('observations') }}" placeholder="Motivo u observación breve">
            </label>

            <fieldset class="choice-panel span-2">
                <legend>Datos que se actualizarán</legend>
                @foreach ([
                    'direccion' => 'Dirección',
                    'contacto' => 'Teléfono / correo',
                    'estado_civil' => 'Estado civil',
                    'actividad_economica' => 'Actividad económica',
                    'datos_personales' => 'Datos personales',
                    'residencia_fiscal' => 'Residencia fiscal',
                ] as $value => $label)
                    <label class="check">
                        <input type="checkbox" name="selected_changes[]" value="{{ $value }}" @checked(in_array($value, old('selected_changes', []), true))>
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>

            <button class="button primary" type="submit"><i data-lucide="arrow-right"></i> Crear y continuar</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Últimos trámites</h2>
            <span class="hint">Solo se muestran los trámites de su agencia, salvo administrador.</span>
        </div>
        <div class="table-list">
            @forelse ($processes as $process)
                <a class="table-row" href="{{ route('data-updates.show', $process) }}">
                    <strong>{{ $process->file_name }}</strong>
                    <span>{{ $process->member_identification }}</span>
                    <span>{{ $process->member_name ?: 'Sin nombre registrado' }}</span>
                    @include('partials.badge', ['status' => $process->status])
                </a>
            @empty
                <p class="hint">Todavía no existen trámites de actualización.</p>
            @endforelse
        </div>
    </section>
@endsection
