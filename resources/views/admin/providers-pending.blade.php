@extends('admin.layout')

@section('title', 'Pending Providers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Pending approvals</h2>
    <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">All providers</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-6">
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Business, owner, or email">
    </div>
    <div class="col-auto">
        <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="overdue" value="1" @checked($filters['overdue'] ?? false)> Overdue (24+ hours)</label>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('admin.providers.pending') }}" class="btn btn-outline-secondary">Clear</a></div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Business</th>
                    <th>Owner</th>
                    <th>Submitted</th>
                    <th>SLA</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($providers as $provider)
                    <tr>
                        <td>{{ $provider->business_name }}</td>
                        <td>{{ $provider->user?->name }}</td>
                        <td>{{ $provider->submitted_at?->format('Y-m-d H:i') ?? $provider->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($provider->submitted_at && $provider->submitted_at->lte(now()->subHours(24)))
                                <span class="badge bg-danger">Overdue</span>
                            @else
                                <span class="badge bg-success">Within 24h</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.provider.detail', $provider) }}" class="btn btn-sm btn-primary">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-muted">No pending provider approvals.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $providers->links() }}
@endsection
