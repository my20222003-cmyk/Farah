@extends('admin.layout')

@section('title', 'Providers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>All providers</h2>
    <a href="{{ route('admin.providers.pending') }}" class="btn btn-outline-primary">Pending approvals</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Business, owner, or email">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            @foreach(['pending', 'approved', 'rejected', 'suspended'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="category_id" class="form-select">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">Clear</a></div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Business</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($providers as $provider)
                    <tr>
                        <td>{{ $provider->business_name ?? '—' }}</td>
                        <td>{{ $provider->user?->name ?? '—' }}</td>
                        <td><span class="badge bg-warning text-dark">{{ $provider->status }}</span></td>
                        <td><a href="{{ route('admin.provider.detail', $provider) }}" class="btn btn-sm btn-primary">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-center text-muted">No providers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $providers->links() }}
@endsection
