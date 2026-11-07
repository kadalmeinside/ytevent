<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Http\Requests\Manager\StoreOfficialRequest;
use App\Models\Team;
use App\Models\TeamOfficial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfficialController extends Controller
{
    use ResolvesActiveTeam;

    public function index(Request $request): Response
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        $officials = [];
        if ($team) {
            $officials = $team->officials()
                ->orderBy('id')
                ->get()
                ->map(fn (TeamOfficial $o) => [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                    'role' => $o->role,
                    'role_label' => TeamOfficial::ROLES[$o->role] ?? $o->role,
                    'license_number' => $o->license_number,
                    'phone' => $o->phone,
                    'screening_status' => $o->screening_status,
                    'verification_notes' => $o->verification_notes,
                    'accreditation_code' => $o->accreditation_code,
                    'photo_url' => $o->photo_path
                        ? route('private.official-photo', ['official' => $o->id])
                        : null,
                    'license_url' => $o->license_file_path
                        ? route('private.official-license', ['official' => $o->id])
                        : null,
                ])
                ->values();
        }

        return Inertia::render('Manager/Officials', [
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
            'officials' => $officials,
            'officialRoles' => TeamOfficial::ROLES,
        ]);
    }

    public function store(StoreOfficialRequest $request, Team $team): RedirectResponse
    {
        $team->loadMissing('event');
        $this->authorize('manageRoster', $team);

        if ($team->event && $team->officials()->count() >= $team->event->max_officials_per_team) {
            throw ValidationException::withMessages([
                'official_quota' => "Jumlah pelatih & ofisial telah mencapai batas maksimal kompetisi ({$team->event->max_officials_per_team} orang).",
            ]);
        }

        $validated = $request->validated();

        $licensePath = $request->hasFile('license_file')
            ? $request->file('license_file')->store('officials/licenses', 'private')
            : null;

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('officials/photos', 'private')
            : null;

        TeamOfficial::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => trim($validated['full_name']),
            'role' => $validated['role'],
            'license_number' => isset($validated['license_number']) ? trim($validated['license_number']) : null,
            'phone' => isset($validated['phone']) ? trim($validated['phone']) : null,
            'license_file_path' => $licensePath,
            'photo_path' => $photoPath,
            'screening_status' => 'draft',
        ]);

        if ($validated['role'] === 'Head Coach') {
            $team->update([
                'head_coach_name' => trim($validated['full_name']),
                'head_coach_license' => isset($validated['license_number']) ? trim($validated['license_number']) : null,
            ]);
        }

        return redirect()->back()->with('success', 'Data pelatih/ofisial tim berhasil ditambahkan.');
    }

    public function update(StoreOfficialRequest $request, Team $team, TeamOfficial $official): RedirectResponse
    {
        $this->authorize('manageRoster', $team);

        if ((int) $official->team_id !== (int) $team->id) {
            abort(403);
        }

        $validated = $request->validated();

        $updates = [
            'full_name' => trim($validated['full_name']),
            'role' => $validated['role'],
            'license_number' => isset($validated['license_number']) ? trim($validated['license_number']) : null,
            'phone' => isset($validated['phone']) ? trim($validated['phone']) : null,
        ];

        if ($request->hasFile('license_file')) {
            $newLicense = $request->file('license_file')->store('officials/licenses', 'private');
            if ($official->license_file_path) {
                Storage::disk('private')->delete($official->license_file_path);
            }
            $updates['license_file_path'] = $newLicense;
        }

        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('officials/photos', 'private');
            if ($official->photo_path) {
                Storage::disk('private')->delete($official->photo_path);
            }
            $updates['photo_path'] = $newPhoto;
        }

        if ($official->screening_status === 'rejected') {
            $updates['screening_status'] = 'draft';
            $updates['verification_notes'] = null;
        }

        $official->update($updates);

        if ($validated['role'] === 'Head Coach') {
            $team->update([
                'head_coach_name' => $updates['full_name'],
                'head_coach_license' => $updates['license_number'],
            ]);
        }

        return redirect()->back()->with('success', 'Data pelatih/ofisial berhasil diperbarui.');
    }

    public function destroy(Request $request, Team $team, TeamOfficial $official): RedirectResponse
    {
        $this->authorize('manageRoster', $team);

        if ((int) $official->team_id !== (int) $team->id) {
            abort(403);
        }

        if ($official->license_file_path) {
            Storage::disk('private')->delete($official->license_file_path);
        }
        if ($official->photo_path) {
            Storage::disk('private')->delete($official->photo_path);
        }

        $official->forceDelete();

        return redirect()->back()->with('success', 'Data ofisial tim berhasil dihapus.');
    }
}

