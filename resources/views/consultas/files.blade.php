@extends('layouts.app')

@section('title', 'Archivos Procesados')

@section('content')
<div class="card">
    <div class="card-title">
        <span class="icon">📊</span>
        Archivos Procesados
    </div>
    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;">
        Listado de archivos que han sido procesados. Puede exportar los resultados a Excel.
    </p>

    @if($consultas->isEmpty())
        <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📂</div>
            <p>No hay archivos procesados aún.</p>
        </div>
    @else
        <div class="results-table-wrapper">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Archivo</th>
                        <th>Fecha</th>
                        <th>Total Cédulas</th>
                        <th>Exitosas</th>
                        <th>Fallidas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($consultas as $consulta)
                    <tr>
                        <td>{{ $consulta->id }}</td>
                        <td>{{ $consulta->filename ?? 'Consulta #' . $consulta->id }}</td>
                        <td>{{ $consulta->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $consulta->total_cedulas }}</td>
                        <td style="color: var(--success);">{{ $consulta->successful }}</td>
                        <td style="color: var(--error);">{{ $consulta->failed }}</td>
                        <td>
                            <a href="{{ route('consultas.files.export', $consulta->id) }}" class="btn btn-success btn-xs">
                                📊 Exportar Excel
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
