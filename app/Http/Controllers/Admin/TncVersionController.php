<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTncVersionRequest;
use App\Models\AuditLog;
use App\Models\TncVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TncVersionController extends Controller
{
    public function index(Request $request): Response
    {
        TncVersion::ensureDefaultsExist();

        $search = trim((string) $request->query('search', ''));
        $scopeFilter = trim((string) $request->query('scope', ''));
        $statusFilter = trim((string) $request->query('status', ''));

        $query = TncVersion::query()
            ->with(['creator:id,name'])
            ->withCount(['events', 'acceptedTeams', 'acceptedParticipants']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        if ($scopeFilter !== '' && in_array($scopeFilter, TncVersion::SCOPES, true)) {
            $query->where('scope', $scopeFilter);
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $versions = $query
            ->orderBy('code')
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->get()
            ->map(function (TncVersion $version) {
                $acceptedTotal = (int) $version->accepted_teams_count + (int) $version->accepted_participants_count;

                return [
                    'id' => $version->id,
                    'code' => $version->code,
                    'title' => $version->title,
                    'scope' => $version->scope,
                    'version' => $version->version,
                    'version_number' => (int) $version->version_number,
                    'summary' => $version->summary,
                    'content_html' => TncVersion::formatDisplayHtml($version->content),
                    'content_hash' => $version->content_hash,
                    'is_active' => (bool) $version->is_active,
                    'is_immutable' => $acceptedTotal > 0,
                    'events_count' => (int) $version->events_count,
                    'accepted_teams_count' => (int) $version->accepted_teams_count,
                    'accepted_participants_count' => (int) $version->accepted_participants_count,
                    'accepted_total' => $acceptedTotal,
                    'creator_name' => $version->creator?->name ?? 'Sistem Bawaan',
                    'published_at' => $version->published_at?->toIso8601String(),
                ];
            })
            ->values();

        $allStats = TncVersion::query()
            ->withCount(['acceptedTeams', 'acceptedParticipants'])
            ->get();

        return Inertia::render('Admin/TncVersions/Index', [
            'versions' => $versions,
            'stats' => [
                'total_codes' => $allStats->pluck('code')->unique()->count(),
                'total_versions' => $allStats->count(),
                'active_versions' => $allStats->where('is_active', true)->count(),
                'total_acceptances' => $allStats->sum(fn ($item) => (int) $item->accepted_teams_count + (int) $item->accepted_participants_count),
            ],
            'scopes' => [
                'all' => 'Semua Jenis Event',
                'team' => 'Kompetisi Tim',
                'audition' => 'Audisi / Seleksi',
                'coaching_clinic' => 'Coaching Clinic',
                'individual' => 'Umum Perorangan',
            ],
            'filters' => [
                'search' => $search,
                'scope' => $scopeFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function store(StoreTncVersionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $created = DB::transaction(function () use ($validated, $request) {
            $version = TncVersion::createNewVersion(
                title: $validated['title'],
                content: $validated['content'],
                scope: $validated['scope'],
                summary: $validated['summary'] ?? null,
                code: $validated['code'] ?? null,
                createdBy: $request->user()?->id,
                isActive: (bool) ($validated['is_active'] ?? true),
            );

            AuditLog::record(
                entity: $version,
                action: 'tnc_version.created',
                actor: $request->user(),
                fromStatus: null,
                toStatus: $version->is_active ? 'active' : 'inactive',
                metadata: [
                    'code' => $version->code,
                    'version' => $version->version,
                    'scope' => $version->scope,
                    'content_hash' => $version->content_hash,
                ],
            );

            return $version;
        });

        return back()->with(
            'success',
            "Dokumen Syarat & Ketentuan \"{$created->title}\" versi v{$created->version} berhasil diterbitkan."
        );
    }

    public function toggleActive(Request $request, TncVersion $tncVersion): RedirectResponse
    {
        $from = $tncVersion->is_active ? 'active' : 'inactive';
        $nextState = ! $tncVersion->is_active;
        $to = $nextState ? 'active' : 'inactive';

        DB::transaction(function () use ($tncVersion, $nextState, $from, $to, $request) {
            $tncVersion->update(['is_active' => $nextState]);

            AuditLog::record(
                entity: $tncVersion,
                action: 'tnc_version.status_toggled',
                actor: $request->user(),
                fromStatus: $from,
                toStatus: $to,
                metadata: [
                    'code' => $tncVersion->code,
                    'version' => $tncVersion->version,
                ],
            );
        });

        $label = $nextState ? 'diaktifkan kembali' : 'dinonaktifkan dari pilihan event baru';

        return back()->with(
            'success',
            "Dokumen \"{$tncVersion->title}\" (v{$tncVersion->version}) berhasil {$label}."
        );
    }
}

