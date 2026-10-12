@extends('admin.layout')

@section('title', 'Audit Log')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>Audit log</h2>
        <p class="text-muted mb-0">Immutable admin actions and decisions.</p>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Time</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Target user</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->actor?->name ?? 'System' }}</td>
                        <td>{{ str_replace('_', ' ', $log->action) }}</td>
                        <td>{{ $log->targetUser?->name ?? '—' }}</td>
                        <td>{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-muted">No audit events recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $logs->links() }}
@endsection
