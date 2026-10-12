<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ProviderProfile;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\NotificationService;

class AdminController extends Controller
{
    public function dashboard()
    {
        $providerPending = ProviderProfile::where('status', 'pending')->count();
        $role = Auth::user()->getRoleNames()->first() ?? 'system.admin';
        $stats = ['active_users' => User::where('status', 'active')->count()];
        if (in_array($role, ['ops.moderator', 'system.admin'], true)) {
            $stats += [
                'providers_pending' => $providerPending,
                'approved_providers' => ProviderProfile::where('status', 'approved')->count(),
                'providers' => User::where('user_type', 'provider')->count(),
                'bookings' => Booking::count(),
            ];
        }
        if (in_array($role, ['finance.reviewer', 'system.admin'], true)) {
            $stats['payment_proofs_pending'] = BookingPayment::where('status', 'submitted')->count();
        }

        return view('admin.dashboard', [
            'providerPending' => $providerPending,
            'role' => $role,
            'stats' => $stats,
        ]);
    }

    public function providers(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:pending,approved,rejected,suspended'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $providers = ProviderProfile::with('user')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('business_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.providers', ['providers' => $providers, 'filters' => $filters, 'categories' => Category::orderBy('name')->get(['id', 'name'])]);
    }

    public function providersPending(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'overdue' => ['nullable', 'boolean'],
        ]);
        $providers = ProviderProfile::with('user')
            ->where('status', 'pending')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('business_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['overdue'] ?? false), fn ($query) => $query->where('submitted_at', '<=', now()->subHours(24)))
            ->orderBy('submitted_at')
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.providers-pending', ['providers' => $providers, 'filters' => $filters]);
    }

    public function providerDetail(ProviderProfile $provider)
    {
        $provider->load(['user', 'category', 'city']);

        return view('admin.provider-detail', ['provider' => $provider]);
    }

    public function providerDocument(ProviderProfile $provider, string $document)
    {
        abort_unless(in_array($document, ['identity_document', 'commercial_register'], true), 404);

        $attribute = $document === 'identity_document' ? 'identity_document_path' : 'commercial_register_path';
        $path = $provider->{$attribute};

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'Document not found.');

        return Storage::disk('local')->response(
            $path,
            Str::slug($document).'.'.pathinfo($path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store, max-age=0']
        );
    }

    public function providerApprove(ProviderProfile $provider, NotificationService $notifications)
    {
        $this->authorizeAdminAction('approve-provider');

        DB::transaction(function () use ($provider, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'pending', 409, 'Only pending providers can be approved.');
            $user = User::whereKey($provider->user_id)->lockForUpdate()->first();
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $provider->update([
                'status' => 'approved',
                'registration_status' => 'approved',
                'decision_reason' => null,
                'decided_at' => now(),
            ]);
            $user?->update(['status' => 'active']);

            $trialPlan = SubscriptionPlan::where('slug', 'free-trial')->where('is_active', true)->firstOrFail();
            $trialCreated = false;
            if ($user && ! ProviderSubscription::where('provider_id', $provider->user_id)->exists()) {
                $trialEndsAt = now()->addMonthNoOverflow();
                ProviderSubscription::create([
                    'provider_id' => $provider->user_id,
                    'subscription_plan_id' => $trialPlan->id,
                    'status' => 'trialing',
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => now(),
                    'current_period_end' => $trialEndsAt,
                ]);
                $trialCreated = true;
            }

            $after = $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $after['free_trial_activated'] = $trialCreated;
            $this->writeAudit('provider_approved', $provider, $before, $after);
            if ($user) {
                $notifications->send(
                    $user,
                    'provider_approved',
                    'تم اعتماد حساب مزود الخدمة',
                    $trialCreated ? 'تم اعتماد حسابك وتفعيل الشهر المجاني.' : 'تم اعتماد حسابك. يمكنك متابعة إعداد اشتراكك.',
                    'provider_profile',
                    $provider->id
                );
            }
        });

        return redirect()->route('admin.providers.pending')->with('success', 'Provider approved successfully.');
    }

    public function providerReject(ProviderProfile $provider, Request $request, NotificationService $notifications)
    {
        $this->authorizeAdminAction('reject-provider');
        $request->validate(['reason' => 'required|string|min:5|max:2000']);

        DB::transaction(function () use ($provider, $request, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'pending', 409, 'Only pending providers can be rejected.');
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $reason = $request->string('reason')->trim()->toString();
            $provider->update([
                'status' => 'rejected',
                'registration_status' => 'rejected',
                'decision_reason' => $reason,
                'decided_at' => now(),
            ]);
            $this->writeAudit('provider_rejected', $provider, $before, $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']));
            if ($provider->user) {
                $notifications->send($provider->user, 'provider_rejected', 'يلزم تعديل بيانات التوثيق', $reason, 'provider_profile', $provider->id);
            }
        });

        return redirect()->route('admin.providers.pending')->with('success', 'Provider rejected.');
    }

    public function providerSuspend(ProviderProfile $provider, Request $request, NotificationService $notifications)
    {
        $this->authorizeAdminAction('suspend-provider');
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']])['reason'];
        abort_unless($provider->status === 'approved', 409, 'Only approved providers can be suspended.');
        DB::transaction(function () use ($provider, $reason, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'approved', 409, 'Only approved providers can be suspended.');
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $provider->update(['status' => 'suspended', 'registration_status' => 'suspended', 'decision_reason' => $reason, 'decided_at' => now()]);
            $this->writeAudit('provider_suspended', $provider, $before, $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']));
            if ($provider->user) {
                $notifications->send($provider->user, 'provider_suspended', 'تم إيقاف حساب مزود الخدمة', $reason, 'provider_profile', $provider->id);
            }
        });

        return back()->with('success', 'Provider suspended.');
    }

    public function providerReactivate(ProviderProfile $provider, Request $request, NotificationService $notifications)
    {
        $this->authorizeAdminAction('reactivate-provider');
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']])['reason'];
        abort_unless($provider->status === 'suspended', 409, 'Only suspended providers can be reactivated.');
        DB::transaction(function () use ($provider, $reason, $notifications): void {
            $provider = ProviderProfile::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->status === 'suspended', 409, 'Only suspended providers can be reactivated.');
            $before = $provider->only(['status', 'registration_status', 'decision_reason', 'decided_at']);
            $provider->update(['status' => 'approved', 'registration_status' => 'approved', 'decision_reason' => $reason, 'decided_at' => now()]);
            $this->writeAudit('provider_reactivated', $provider, $before, $provider->fresh()->only(['status', 'registration_status', 'decision_reason', 'decided_at']));
            if ($provider->user) {
                $notifications->send($provider->user, 'provider_reactivated', 'تمت إعادة تفعيل حساب مزود الخدمة', $reason, 'provider_profile', $provider->id);
            }
        });

        return back()->with('success', 'Provider reactivated.');
    }

    public function auditLog()
    {
        $this->authorizeAdminAction('view-audit-log');

        $logs = AdminAuditLog::with(['actor', 'targetUser'])->latest('created_at')->paginate(20);

        return view('admin.audit-log', ['logs' => $logs]);
    }

    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->session()->put('admin_last_activity', now()->timestamp);

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid admin credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function authorizeAdminAction(string $permission): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['ops.moderator', 'finance.reviewer', 'system.admin'])) {
            abort(403, 'You are not allowed to perform this action.');
        }

        if ($permission === 'approve-provider' && $user->hasRole('finance.reviewer')) {
            abort(403, 'Finance reviewers cannot approve providers.');
        }

        if ($permission === 'reject-provider' && $user->hasRole('finance.reviewer')) {
            abort(403, 'Finance reviewers cannot reject providers.');
        }

        if ($permission === 'view-audit-log' && ! $user->hasRole('system.admin')) {
            abort(403, 'Only the system administrator can view the audit log.');
        }

        if (in_array($permission, ['suspend-provider', 'reactivate-provider'], true) && ! $user->hasAnyRole(['ops.moderator', 'system.admin'])) {
            abort(403, 'Only ops moderators and system administrators can manage provider suspension.');
        }
    }

    private function writeAudit(string $action, ProviderProfile $provider, array $before, array $after): void
    {
        AdminAuditLog::create([
            'actor_id' => Auth::id(),
            'user_id' => $provider->user_id,
            'actor_role' => Auth::user()?->getRoleNames()->first(),
            'action' => $action,
            'resource_type' => 'provider_profile',
            'resource_id' => $provider->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
        ]);
    }
}
