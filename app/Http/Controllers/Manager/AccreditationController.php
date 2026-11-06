<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Models\Team;
use App\Services\AccreditationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AccreditationController extends Controller
{
    use ResolvesActiveTeam;

    public function __construct(
        protected AccreditationService $accreditationService
    ) {}

    /**
     * Menampilkan dashboard akreditasi skuad & kelayakan penguncian roster.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $context = $this->resolveManagerContext($request);
        $team = $context['activeTeam'];

        if (! $team) {
            return redirect()->route('manager.dashboard')->with('error', 'Anda belum memiliki tim yang terdaftar.');
        }

        $this->authorize('viewAccreditation', $team);

        $readiness = $this->accreditationService->checkReadiness($team);

        $passedPlayers = $team->players()
            ->where('screening_status', 'passed')
            ->orderBy('jersey_number')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'jersey_name' => $p->jersey_name,
                'jersey_number' => $p->jersey_number,
                'position' => $p->position,
                'birth_date' => $p->birth_date?->format('d/m/Y'),
                'birth_place' => $p->birth_place,
                'age' => $p->birth_date ? (int) $p->birth_date->diffInYears(now()) : null,
                'nik' => $p->nik,
                'nisn' => $p->nisn,
                'photo_url' => $p->photo_path ? route('private.player-photo', ['player' => $p->id]) : null,
                'accreditation_code' => $p->accreditation_code,
                'verified_at' => $p->verified_at ? $p->verified_at->translatedFormat('d/m/Y H:i') : null,
            ]);

        $officials = $team->officials()->get()->map(fn ($o) => [
            'id' => $o->id,
            'full_name' => $o->full_name,
            'role' => $o->role,
            'license_number' => $o->license_number,
            'photo_url' => $o->photo_path ? route('private.official-photo', ['official' => $o->id]) : null,
        ]);

        return Inertia::render('Manager/Accreditation/Index', [
            'team' => $context['teamPayload'],
            'readiness' => $readiness,
            'passedPlayers' => $passedPlayers,
            'officials' => $officials,
            'teamsList' => $context['teamsList'],
        ]);
    }

    /**
     * Mengunci skuad final tim.
     */
    public function lockRoster(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('lockRoster', $team);

        $this->accreditationService->lockRoster($team, $request->user());

        return redirect()->back()->with('success', 'Skuad final berhasil dikunci resmi. Seluruh dokumen akreditasi resmi dan kartu ID pertandingan siap diunduh.');
    }

    /**
     * Mengunduh file PDF Team Roster Sheet resmi.
     */
    public function downloadRosterSheet(Request $request, Team $team): HttpResponse
    {
        $this->authorize('viewAccreditation', $team);

        if (! $team->isRosterLocked() && ! $request->user()->can('teams.view_all')) {
            abort(403, 'Skuad tim ini belum dikunci resmi oleh manajer.');
        }

        $pdf = $this->accreditationService->generateRosterSheetPdf($team);

        return $pdf->download("Album-Roster-{$team->slug}.pdf");
    }

    /**
     * Mengunduh file PDF ID Cards akreditasi turnamen.
     */
    public function downloadIdCards(Request $request, Team $team): HttpResponse
    {
        $this->authorize('viewAccreditation', $team);

        if (! $team->isRosterLocked() && ! $request->user()->can('teams.view_all')) {
            abort(403, 'Skuad tim ini belum dikunci resmi oleh manajer.');
        }

        $pdf = $this->accreditationService->generateIdCardsPdf($team);

        return $pdf->download("ID-Cards-{$team->slug}.pdf");
    }
}

