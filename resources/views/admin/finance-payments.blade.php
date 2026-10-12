@extends('admin.layout')

@section('title', 'Payment Review')

@section('content')
<div class="mb-3">
    <h2>Payment proof review</h2>
    <p class="text-muted mb-0">Review submitted transfers. Approval confirms the booking; rejection returns it to payment awaiting.</p>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Provider</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Proof</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->reference }}</td>
                            <td>{{ $payment->customer?->name ?? '—' }}</td>
                            <td>{{ $payment->provider?->name ?? '—' }}</td>
                            <td>{{ $payment->booking?->service?->title ?? '—' }}</td>
                            <td>{{ number_format($payment->amount, 2) }} {{ $payment->booking?->service?->currency ?? '' }}</td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                                   href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.finance.payment.proof', now()->addMinutes(5), ['payment' => $payment->id]) }}">
                                    Open proof (5 min)
                                </a>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.finance.payment.review', $payment) }}" class="d-flex flex-column gap-2">
                                    @csrf
                                    <textarea name="review_note" class="form-control form-control-sm" rows="2" placeholder="Required if rejected"></textarea>
                                    <div class="d-flex gap-2">
                                        <button name="decision" value="confirmed" class="btn btn-sm btn-success">Approve</button>
                                        <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-center text-muted">No payment proofs waiting for review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
{{ $payments->links() }}
@endsection
