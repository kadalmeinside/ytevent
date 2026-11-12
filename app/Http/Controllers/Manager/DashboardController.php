<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Http\Requests\Manager\UpdateTeamProfileRequest;
use App\Models\EventAnnouncement;
use App\Models\Team;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected ImageOptimizationService $imageOptimization
    ) {}

    use ResolvesActiveTeam;

    public function index(Request $request): Response
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        $summary = null;
        if ($team) {
            $team->loadMissing('event');
            $latestPayment = $team->latestPayment;
            $players = $team->players()->with('documents')->get();
            $players->each(fn ($p) => $p->setRelation('event', $team->event));
            $officialsCount = $team->officials()->count();

            $summary = [
                'latest_payment' => $latestPayment ? [
                    'id' => $latestPayment->id,
                    'invoice_number' => $latestPayment->invoice_number,
                    'total_amount' => (float) $latestPayment->total_amount,
                    'status' => $latestPayment->status,
                    'method' => $latestPayment->method,
                    'expires_at' => $latestPayment->expires_at?->toIso8601String(),
                    'paid_at' => $latestPayment->paid_at?->toIso8601String(),
                    'rejection_reason' => $latestPayment->rejection_reason,
                ] : null,
                'players_total' => $players->count(),
                'players_draft' => $players->where('screening_status', 'draft')->count(),
                'players_submitted' => $players->where('screening_status', 'submitted')->count(),
                'players_passed' => $players->where('screening_status', 'passed')->count(),
                'players_rejected' => $players->where('screening_status', 'rejected')->count(),
                'players_docs_complete' => $players->filter(fn ($p) => $p->isCompleteForSubmission())->count(),
                'officials_total' => $officialsCount,
                'announcements' => EventAnnouncement::query()
                    ->where(function ($q) use ($team) {
                        $q->where('event_id', $team->event_id)
                            ->orWhereNull('event_id');
                    })
                    ->orderByDesc('is_pinned')
                    ->latest('id')
                    ->take(5)
                    ->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'content' => $a->content,
                        'meeting_link' => $a->meeting_link,
                        'is_pinned' => $a->is_pinned,
                        'created_at' => $a->created_at?->translatedFormat('d M Y, H:i'),
                    ])
                    ->values(),
            ];
        }

        return Inertia::render('Manager/Dashboard', [
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
            'summary' => $summary,
        ]);
    }

    public function switchTeam(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('view', $team);
        $request->session()->put('active_manager_team_id', $team->id);

        return redirect()->back()
            ->with('info', "Konteks tim aktif dialihkan ke {$team->name}.");
    }

    public function teamProfile(Request $request): Response
    {
        $ctx = $this->resolveManagerContext($request);

        return Inertia::render('Manager/TeamProfile', [
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
        ]);
    }

    public function updateTeamProfile(UpdateTeamProfileRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);
        $validated = $request->validated();

        $updates = [
            'origin_association' => trim($validated['origin_association']),
            'secretariat_address' => $validated['secretariat_address'] ?? null,
            'manager_name' => trim($validated['manager_name']),
            'manager_whatsapp' => trim($validated['manager_whatsapp']),
            'head_coach_name' => trim($validated['head_coach_name']),
            'head_coach_license' => $validated['head_coach_license'] ?? null,
            'home_jersey_color' => $validated['home_jersey_color'] ?? null,
            'away_jersey_color' => $validated['away_jersey_color'] ?? null,
            'gk_jersey_color' => $validated['gk_jersey_color'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $newLogo = $this->imageOptimization->storeWithOptimizedVariant($request->file('logo'), 'teams/logos', 'public', 1200, 85);
            if ($team->logo_path) {
                Storage::disk('public')->delete($team->logo_path);
            }
            if ($team->logo_optimized_path && $team->logo_optimized_path !== $team->logo_path) {
                Storage::disk('public')->delete($team->logo_optimized_path);
            }
            $updates['logo_path'] = $newLogo['original_path'];
            $updates['logo_optimized_path'] = $newLogo['optimized_path'];
        }

        $team->update($updates);

        // Sinkronkan juga data Head Coach di tabel team_officials jika ada
        $team->officials()->where('role', 'Head Coach')->update([
            'full_name' => $updates['head_coach_name'],
            'license_number' => $updates['head_coach_license'],
        ]);

        return redirect()->back()->with('success', 'Profil tim dan kontak penanggung jawab berhasil diperbarui.');
    }

    /**
     * Memperbarui kata sandi akun Manajer Tim secara mandiri.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini yang Anda masukkan tidak sesuai.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 8 karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung kombinasi huruf dan angka.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Kata sandi akun Anda berhasil diperbarui.');
    }
}
