@extends('admin.layout')

@section('title', 'Provider Review')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>{{ $provider->business_name }}</h2>
        <p class="text-muted mb-0">Submitted by {{ $provider->user?->name }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(in_array(auth()->user()->getRoleNames()->first(), ['ops.moderator', 'system.admin'], true) && $provider->status === 'pending')
            <form method="POST" action="{{ route('admin.provider.approve', $provider) }}">@csrf <button class="btn btn-success" type="submit">Approve</button></form>
        @endif
        @if(in_array(auth()->user()->getRoleNames()->first(), ['ops.moderator', 'system.admin'], true) && $provider->status === 'pending')
            <form method="POST" action="{{ route('admin.provider.reject', $provider) }}" class="d-flex gap-2 align-items-center">@csrf <input type="text" name="reason" class="form-control" placeholder="Rejection reason" required> <button class="btn btn-danger" type="submit">Reject</button></form>
        @endif
        @if(auth()->user()->hasAnyRole(['ops.moderator', 'system.admin']) && $provider->status === 'approved')
            <form method="POST" action="{{ route('admin.provider.suspend', $provider) }}" class="d-flex gap-2">@csrf <input name="reason" class="form-control" placeholder="Suspension reason" required> <button class="btn btn-warning" type="submit">Suspend</button></form>
        @endif
        @if(auth()->user()->hasAnyRole(['ops.moderator', 'system.admin']) && $provider->status === 'suspended')
            <form method="POST" action="{{ route('admin.provider.reactivate', $provider) }}" class="d-flex gap-2">@csrf <input name="reason" class="form-control" placeholder="Reactivation reason" required> <button class="btn btn-outline-success" type="submit">Reactivate</button></form>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Provider details</h5>
                <dl class="row mt-3 mb-0">
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $provider->user?->email }}</dd>
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $provider->phone ?? '—' }}</dd>
                    <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $provider->category?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-secondary">{{ $provider->status }}</span></dd>
                    <dt class="col-sm-4">City</dt><dd class="col-sm-8">{{ $provider->city?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">Decision</dt><dd class="col-sm-8">{{ $provider->decision_reason ?: 'No decision recorded yet.' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Business description</h5>
                <p class="mb-0">{{ $provider->description ?: 'No description provided.' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Identity document</h5>
                @if($provider->identity_document_path)
                    <a href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.provider.document', now()->addMinutes(5), ['provider' => $provider->id, 'document' => 'identity_document']) }}" class="btn btn-sm btn-outline-primary mb-2" target="_blank" rel="noopener">Open secure document (5 min)</a>
                @else
                    <p class="text-muted mb-0">No document uploaded.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Commercial register</h5>
                @if($provider->commercial_register_path)
                    <a href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.provider.document', now()->addMinutes(5), ['provider' => $provider->id, 'document' => 'commercial_register']) }}" class="btn btn-sm btn-outline-primary mb-2" target="_blank" rel="noopener">Open secure document (5 min)</a>
                @else
                    <p class="text-muted mb-0">No document uploaded.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
