<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\Event;
use App\Services\QuotaExpirationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->withOccupiedSlotsCount()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'city' => $event->city,
                'venue_name' => $event->venue_name,
                'venue_maps_url' => $event->venue_maps_url,
                'format' => $event->format,
                'format_label' => Event::FORMATS[$event->format] ?? $event->format,
                'age_category' => $event->age_category,
                'max_teams' => $event->max_teams,
                'occupied_slots' => $event->occupiedSlotsCount(),
                'active_teams' => (int) ($event->active_teams_count ?? 0),
                'registration_fee' => (float) $event->registration_fee,
                'payment_deadline_hours' => $event->payment_deadline_hours,
                'match_start_date' => $event->match_start_date?->toDateString(),
                'status' => $event->status,
            ]);

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
        ]);
    }

    public function resyncSlots(QuotaExpirationService $quotaExpiration): RedirectResponse
    {
        $this->authorize('viewAny', Event::class);

        $expiredCount = $quotaExpiration->sweepAllEvents();

        Event::query()->get()->each(fn (Event $event) => $event->syncQuotaStatus());

        if ($expiredCount > 0) {
            return back()->with('success', "Sinkronisasi selesai. Berhasil melepas {$expiredCount} slot kedaluwarsa & memperbarui status kuota event.");
        }

        return back()->with('info', 'Sinkronisasi selesai. Seluruh slot tim masih aktif atau telah lunas.');
    }

    public function resyncEventSlots(Event $event, QuotaExpirationService $quotaExpiration): RedirectResponse
    {
        $this->authorize('view', $event);

        $expiredCount = DB::transaction(function () use ($event, $quotaExpiration) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            return $lockedEvent ? $quotaExpiration->sweepExpiredForEvent($lockedEvent) : 0;
        });

        if ($expiredCount > 0) {
            return back()->with('success', "Sinkronisasi berhasil: {$expiredCount} slot kedaluwarsa pada event {$event->name} telah dilepas.");
        }

        return back()->with('info', "Kapasitas slot event {$event->name} sudah sesuai. Tidak ada pendaftaran kedaluwarsa.");
    }

    public function create(): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Admin/Events/Form', [
            'event' => null,
            'formats' => Event::FORMATS,
            'statuses' => Event::STATUSES,
            'documentTypes' => Event::DOCUMENT_TYPES,
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $this->authorize('create', Event::class);
        $validated = $request->validated();

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 2;
        while (Event::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $bannerPath = $request->hasFile('banner_file')
            ? $request->file('banner_file')->store('events/banners', 'public')
            : null;

        $pdfPath = $request->hasFile('regulation_pdf')
            ? $request->file('regulation_pdf')->store('events/regulations', 'public')
            : null;

        $event = Event::create([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'banner_path' => $bannerPath,
            'short_description' => $this->sanitizeDescription($validated['short_description'] ?? null),
            'venue_name' => trim($validated['venue_name']),
            'venue_maps_url' => ! empty($validated['venue_maps_url']) ? trim($validated['venue_maps_url']) : null,
            'city' => trim($validated['city']),
            'format' => $validated['format'],
            'age_category' => trim($validated['age_category']),
            'min_birth_date' => $validated['min_birth_date'] ?? null,
            'max_birth_date' => $validated['max_birth_date'] ?? null,
            'min_players_per_team' => (int) $validated['min_players_per_team'],
            'max_players_per_team' => (int) $validated['max_players_per_team'],
            'max_officials_per_team' => (int) $validated['max_officials_per_team'],
            'required_documents' => array_values($validated['required_documents']),
            'max_teams' => (int) $validated['max_teams'],
            'registration_fee' => $validated['registration_fee'],
            'prize_info' => $validated['prize_info'] ?? null,
            'lock_roster_until_paid' => (bool) $validated['lock_roster_until_paid'],
            'payment_deadline_hours' => (int) $validated['payment_deadline_hours'],
            'reupload_grace_hours' => (int) $validated['reupload_grace_hours'],
            'registration_open_at' => $validated['registration_open_at'] ?? null,
            'registration_close_at' => $validated['registration_close_at'] ?? null,
            'match_start_date' => $validated['match_start_date'] ?? null,
            'match_end_date' => $validated['match_end_date'] ?? null,
            'regulation_pdf_path' => $pdfPath,
            'contact_person' => $validated['contact_person'] ?? null,
            'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.events.index')
            ->with('success', "Event {$event->name} dengan kuota maksimal {$event->max_teams} tim berhasil dibuat.");
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);
        $event->loadCount([
            'teams as occupied_slots_count' => fn ($q) => $q->occupyingQuotaAt(now()),
        ]);

        return Inertia::render('Admin/Events/Form', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'short_description' => $event->short_description,
                'venue_name' => $event->venue_name,
                'venue_maps_url' => $event->venue_maps_url,
                'city' => $event->city,
                'format' => $event->format,
                'age_category' => $event->age_category,
                'min_birth_date' => $event->min_birth_date?->toDateString(),
                'max_birth_date' => $event->max_birth_date?->toDateString(),
                'min_players_per_team' => $event->min_players_per_team,
                'max_players_per_team' => $event->max_players_per_team,
                'max_officials_per_team' => $event->max_officials_per_team,
                'required_documents' => $event->required_documents ?? [],
                'max_teams' => $event->max_teams,
                'occupied_slots' => $event->occupiedSlotsCount(),
                'registration_fee' => (float) $event->registration_fee,
                'prize_info' => $event->prize_info,
                'lock_roster_until_paid' => $event->lock_roster_until_paid,
                'payment_deadline_hours' => $event->payment_deadline_hours,
                'reupload_grace_hours' => $event->reupload_grace_hours,
                'registration_open_at' => $event->registration_open_at?->format('Y-m-d\TH:i'),
                'registration_close_at' => $event->registration_close_at?->format('Y-m-d\TH:i'),
                'match_start_date' => $event->match_start_date?->toDateString(),
                'match_end_date' => $event->match_end_date?->toDateString(),
                'contact_person' => $event->contact_person,
                'contact_whatsapp' => $event->contact_whatsapp,
                'status' => $event->status,
                'banner_url' => $event->banner_path ? Storage::disk('public')->url($event->banner_path) : null,
                'regulation_pdf_url' => $event->regulation_pdf_path ? Storage::disk('public')->url($event->regulation_pdf_path) : null,
            ],
            'formats' => Event::FORMATS,
            'statuses' => Event::STATUSES,
            'documentTypes' => Event::DOCUMENT_TYPES,
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $validated = $request->validated();

        DB::transaction(function () use ($event, $validated, $request) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $occupied = $lockedEvent->teams()->occupyingQuotaAt(now())->count();

            if ((int) $validated['max_teams'] < $occupied) {
                throw ValidationException::withMessages([
                    'max_teams' => "Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot ({$occupied} tim).",
                ]);
            }

            $updates = [
                'name' => trim($validated['name']),
                'short_description' => $this->sanitizeDescription($validated['short_description'] ?? null),
                'venue_name' => trim($validated['venue_name']),
                'venue_maps_url' => ! empty($validated['venue_maps_url']) ? trim($validated['venue_maps_url']) : null,
                'city' => trim($validated['city']),
                'format' => $validated['format'],
                'age_category' => trim($validated['age_category']),
                'min_birth_date' => $validated['min_birth_date'] ?? null,
                'max_birth_date' => $validated['max_birth_date'] ?? null,
                'min_players_per_team' => (int) $validated['min_players_per_team'],
                'max_players_per_team' => (int) $validated['max_players_per_team'],
                'max_officials_per_team' => (int) $validated['max_officials_per_team'],
                'required_documents' => array_values($validated['required_documents']),
                'max_teams' => (int) $validated['max_teams'],
                'registration_fee' => $validated['registration_fee'],
                'prize_info' => $validated['prize_info'] ?? null,
                'lock_roster_until_paid' => (bool) $validated['lock_roster_until_paid'],
                'payment_deadline_hours' => (int) $validated['payment_deadline_hours'],
                'reupload_grace_hours' => (int) $validated['reupload_grace_hours'],
                'registration_open_at' => $validated['registration_open_at'] ?? null,
                'registration_close_at' => $validated['registration_close_at'] ?? null,
                'match_start_date' => $validated['match_start_date'] ?? null,
                'match_end_date' => $validated['match_end_date'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
                'status' => $validated['status'],
            ];

            if ($request->hasFile('banner_file')) {
                $newBanner = $request->file('banner_file')->store('events/banners', 'public');
                if ($lockedEvent->banner_path) {
                    Storage::disk('public')->delete($lockedEvent->banner_path);
                }
                $updates['banner_path'] = $newBanner;
            }

            if ($request->hasFile('regulation_pdf')) {
                $newPdf = $request->file('regulation_pdf')->store('events/regulations', 'public');
                if ($lockedEvent->regulation_pdf_path) {
                    Storage::disk('public')->delete($lockedEvent->regulation_pdf_path);
                }
                $updates['regulation_pdf_path'] = $newPdf;
            }

            $lockedEvent->update($updates);
            $lockedEvent->syncQuotaStatus($occupied);
        });

        return redirect()->route('admin.events.index')
            ->with('success', 'Pengaturan kompetisi dan kuota maksimal tim berhasil diperbarui.');
    }

    protected function sanitizeDescription(?string $html): ?string
    {
        if (! $html) {
            return null;
        }

        $cleaned = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><span><div><hr>');
        $cleaned = preg_replace('/(\son[a-zA-Z]+\s*=\s*([\'"][^\'"]*[\'"]|[^\s>]+))/i', '', $cleaned);
        $cleaned = preg_replace('/href\s*=\s*([\'"])\s*(javascript|data):[^\'"]*([\'"])/i', 'href="#"', $cleaned);

        return trim($cleaned);
    }
}

