@extends('admin.layout')

@section('title', 'Admin Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Admin dashboard</h2>
        <p class="text-muted mb-0">Operations overview</p>
    </div>
    <span class="badge bg-success">{{ $role }}</span>
</div>

<div class="row g-4 mb-4">
    @foreach ($stats as $key => $value)
        <div class="col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small">{{ str_replace('_', ' ', $key) }}</div>
                    <div class="display-6 fw-bold mt-2">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    @if(in_array($role, ['ops.moderator', 'system.admin'], true))
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Provider approval queue</h5>
                    <p class="text-muted">{{ $providerPending }} provider requests awaiting decision.</p>
                    <a href="{{ route('admin.providers.pending') }}" class="btn btn-primary">View pending providers</a>
                </div>
            </div>
        </div>
    @endif
    @if(in_array($role, ['finance.reviewer', 'system.admin'], true))
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Payment proof queue</h5>
                    <p class="text-muted">{{ $stats['payment_proofs_pending'] }} payment proofs awaiting review.</p>
                    <a href="{{ route('admin.finance.payments') }}" class="btn btn-primary">Review payments</a>
                </div>
            </div>
        </div>
    @endif
    @if($role === 'system.admin')
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Audit activity</h5>
                    <p class="text-muted">Review recorded administrative actions.</p>
                    <a href="{{ route('admin.audit-log') }}" class="btn btn-outline-secondary">Open audit log</a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
