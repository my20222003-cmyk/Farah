<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminFinanceController extends Controller
{
    public function index()
    {
        $payments = BookingPayment::with(['booking.service', 'customer', 'provider'])
            ->where('status', 'submitted')
            ->latest()
            ->paginate(20);

        return view('admin.finance-payments', ['payments' => $payments]);
    }

    public function proof(BookingPayment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404, 'Payment proof not found.');

        return Storage::disk('local')->response(
            $payment->proof_path,
            'payment-proof-'.$payment->id.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store, max-age=0']
        );
    }

    public function review(Request $request, BookingPayment $payment, NotificationService $notifications)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:confirmed,rejected'],
            'review_note' => ['nullable', 'string', 'min:5', 'max:2000', 'required_if:decision,rejected'],
        ]);

        DB::transaction(function () use ($payment, $data, $notifications, $request): void {
            $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = BookingPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPayment->status === 'submitted' && $booking->workflow_status === 'review_under_proof', 409, 'This payment proof has already been reviewed.');

            $beforePayment = $lockedPayment->only(['status', 'review_note', 'reviewed_by', 'reviewed_at']);
            $beforeBooking = $booking->only(['status', 'workflow_status', 'confirmed_at', 'payment_due_at', 'expires_at']);
            $lockedPayment->update([
                'status' => $data['decision'],
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            $confirmed = $data['decision'] === 'confirmed';
            $booking->update($confirmed
                ? ['status' => 'confirmed', 'workflow_status' => 'confirmed', 'confirmed_at' => now(), 'expires_at' => null]
                : ['workflow_status' => 'payment_awaiting', 'payment_due_at' => now()->addDay(), 'expires_at' => now()->addDay()]);

            AdminAuditLog::create([
                'actor_id' => Auth::id(),
                'user_id' => $lockedPayment->user_id,
                'actor_role' => Auth::user()?->getRoleNames()->first(),
                'action' => 'payment_proof_'.$data['decision'],
                'resource_type' => 'booking_payment',
                'resource_id' => $lockedPayment->id,
                'before' => ['payment' => $beforePayment, 'booking' => $beforeBooking],
                'after' => [
                    'payment' => $lockedPayment->fresh()->only(['status', 'review_note', 'reviewed_by', 'reviewed_at']),
                    'booking' => $booking->fresh()->only(['status', 'workflow_status', 'confirmed_at', 'payment_due_at', 'expires_at']),
                ],
                'ip_address' => $request->ip(),
            ]);

            $notifications->send(
                $lockedPayment->user_id,
                $confirmed ? 'payment_confirmed' : 'payment_rejected',
                $confirmed ? 'تم تأكيد الدفع والحجز' : 'تعذر اعتماد إثبات الدفع',
                $confirmed ? 'تم تأكيد حجزك بنجاح.' : ($data['review_note'] ?? 'راجع بيانات التحويل وارفع إثباتًا جديدًا.'),
                'booking',
                $booking->id,
                ['reference' => $booking->reference]
            );
        });

        return redirect()->route('admin.finance.payments')->with('success', 'Payment proof reviewed.');
    }
}
