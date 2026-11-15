<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TncVersion extends Model
{
    use HasFactory;

    public const SCOPES = [
        'all',
        'team',
        'individual',
        'audition',
        'coaching_clinic',
    ];

    protected $fillable = [
        'code',
        'title',
        'scope',
        'version',
        'version_number',
        'summary',
        'content',
        'content_hash',
        'is_active',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'tnc_version_id');
    }

    public function acceptedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'accepted_tnc_version_id');
    }

    public function acceptedParticipants(): HasMany
    {
        return $this->hasMany(AuditionParticipant::class, 'accepted_tnc_version_id');
    }

    public function isImmutable(): bool
    {
        return $this->acceptedTeams()->exists()
            || $this->acceptedParticipants()->exists();
    }

    public function scopeForEventScope($query, Event $event)
    {
        $preferredScopes = self::preferredScopesForEvent($event);

        return $query->whereIn('scope', $preferredScopes);
    }

    /**
     * @return array<int, string>
     */
    public static function preferredScopesForEvent(Event $event): array
    {
        if (! $event->isIndividual()) {
            return ['team', 'all'];
        }

        if ($event->event_type === 'coaching_clinic') {
            return ['coaching_clinic', 'individual', 'all'];
        }

        return ['audition', 'individual', 'all'];
    }

    public static function defaultScopeForEvent(Event $event): string
    {
        if (! $event->isIndividual()) {
            return 'team';
        }

        return $event->event_type === 'coaching_clinic' ? 'coaching_clinic' : 'audition';
    }

    public static function resolveForEvent(Event $event): self
    {
        if ($event->tnc_version_id) {
            $linked = $event->relationLoaded('tncVersion')
                ? $event->tncVersion
                : self::query()->find($event->tnc_version_id);

            if ($linked instanceof self) {
                return $linked;
            }
        }

        foreach (self::preferredScopesForEvent($event) as $scope) {
            $match = self::query()
                ->where('scope', $scope)
                ->where('is_active', true)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->first();

            if ($match instanceof self) {
                return $match;
            }
        }

        $any = self::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($any instanceof self) {
            return $any;
        }

        return self::ensureFallbackForEvent($event);
    }

    public static function ensureFallbackForEvent(Event $event): self
    {
        $scope = self::defaultScopeForEvent($event);

        $blueprints = self::defaultBlueprints();
        $blueprint = $blueprints[$scope] ?? $blueprints['team'];

        $existing = self::query()
            ->where('code', $blueprint['code'])
            ->orderByDesc('version_number')
            ->first();

        if ($existing instanceof self) {
            return $existing;
        }

        $cleanContent = HtmlSanitizer::clean($blueprint['content']);

        return self::create([
            'code' => $blueprint['code'],
            'title' => $blueprint['title'],
            'scope' => $blueprint['scope'],
            'version' => '1.0',
            'version_number' => 1,
            'summary' => $blueprint['summary'],
            'content' => $cleanContent,
            'content_hash' => hash('sha256', $cleanContent),
            'is_active' => true,
            'published_at' => now(),
            'created_by' => null,
        ]);
    }

    public static function ensureDefaultsExist(): void
    {
        if (self::query()->count() > 0) {
            return;
        }

        foreach (self::defaultBlueprints() as $blueprint) {
            $cleanContent = HtmlSanitizer::clean($blueprint['content']);
            self::create([
                'code' => $blueprint['code'],
                'title' => $blueprint['title'],
                'scope' => $blueprint['scope'],
                'version' => '1.0',
                'version_number' => 1,
                'summary' => $blueprint['summary'],
                'content' => $cleanContent,
                'content_hash' => hash('sha256', $cleanContent),
                'is_active' => true,
                'published_at' => now(),
                'created_by' => null,
            ]);
        }
    }

    public static function createNewVersion(
        string $title,
        string $content,
        string $scope = 'all',
        ?string $summary = null,
        ?string $code = null,
        ?int $createdBy = null,
        bool $isActive = true,
    ): self {
        $normalizedCode = Str::slug(trim((string) ($code ?: $title)), '-');
        if ($normalizedCode === '') {
            $normalizedCode = 'tnc-'.Str::lower(Str::random(6));
        }
        $normalizedCode = Str::limit($normalizedCode, 64, '');

        $latestNumber = (int) self::query()
            ->where('code', $normalizedCode)
            ->max('version_number');

        $nextNumber = $latestNumber + 1;
        $versionLabel = $nextNumber.'.0';
        $cleanContent = HtmlSanitizer::clean($content);

        return self::create([
            'code' => $normalizedCode,
            'title' => trim($title),
            'scope' => in_array($scope, self::SCOPES, true) ? $scope : 'all',
            'version' => $versionLabel,
            'version_number' => $nextNumber,
            'summary' => $summary !== null && trim($summary) !== '' ? trim($summary) : null,
            'content' => $cleanContent,
            'content_hash' => hash('sha256', $cleanContent),
            'is_active' => $isActive,
            'published_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    public function renderSummaryForEvent(Event $event): string
    {
        $raw = $this->summary ?: ('Saya telah membaca dan menyetujui seluruh ketentuan '.$this->title.' untuk event {{event_name}}.');

        return $this->replacePlaceholders($raw, $event, escapeValues: false);
    }

    public function renderContentForEvent(Event $event): string
    {
        $clean = self::formatDisplayHtml($this->content);

        return $this->replacePlaceholders($clean, $event, escapeValues: true);
    }

    public static function formatDisplayHtml(?string $html): string
    {
        $clean = HtmlSanitizer::clean($html) ?? '';
        if ($clean !== '' && $clean === strip_tags($clean)) {
            return nl2br($clean, false);
        }

        return $clean;
    }

    public function contentHashForEvent(Event $event): string
    {
        return hash('sha256', $this->renderContentForEvent($event));
    }

    public function toPublicPayload(Event $event): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'scope' => $this->scope,
            'version' => $this->version,
            'summary' => $this->renderSummaryForEvent($event),
            'content_html' => $this->renderContentForEvent($event),
            'content_hash' => $this->contentHashForEvent($event),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    private function replacePlaceholders(string $text, Event $event, bool $escapeValues): string
    {
        $eventName = $escapeValues ? e($event->name) : $event->name;
        $deadlineHours = (string) ($event->payment_deadline_hours ?? 24);

        return strtr($text, [
            '{{event_name}}' => $eventName,
            '{{payment_deadline_hours}}' => $deadlineHours,
        ]);
    }

    /**
     * @return array<string, array{code: string, title: string, scope: string, summary: string, content: string}>
     */
    public static function defaultBlueprints(): array
    {
        return [
            'team' => [
                'code' => 'team-competition-standard',
                'title' => 'Syarat & Ketentuan Kompetisi Tim',
                'scope' => 'team',
                'summary' => 'Saya menyatakan bahwa seluruh data tim dan pemain yang diisikan adalah benar, menyetujui regulasi teknis kompetisi {{event_name}}, serta memahami bahwa slot kuota hanya ditahan selama {{payment_deadline_hours}} jam hingga pembayaran diselesaikan.',
                'content' => '<h3>1. Kebenaran Data &amp; Dokumen Pemain</h3><p>Manajer dan perwakilan resmi tim menjamin bahwa seluruh data identitas tim, ofisial, dan daftar pemain yang didaftarkan pada kompetisi <strong>{{event_name}}</strong> adalah benar, sah, dan dapat dipertanggungjawabkan secara administratif maupun hukum.</p><h3>2. Regulasi Teknis &amp; Sportivitas</h3><p>Setiap tim wajib mematuhi regulasi pertandingan, batas kategori usia, ketentuan verifikasi daftar susunan pemain (screening), serta menjunjung tinggi nilai sportivitas selama seluruh rangkaian kompetisi berlangsung.</p><h3>3. Kuota Pendaftaran &amp; Batas Waktu Pembayaran</h3><p>Slot kuota tim bersifat sementara dan hanya ditahan selama <strong>{{payment_deadline_hours}} jam</strong> sejak pendaftaran dikirimkan. Apabila pembayaran belum diselesaikan dan diverifikasi hingga batas waktu berakhir, sistem berhak membebaskan kembali slot kuota secara otomatis.</p>',
            ],
            'audition' => [
                'code' => 'individual-audition-waiver',
                'title' => 'Syarat & Ketentuan Peserta Audisi',
                'scope' => 'audition',
                'summary' => 'Saya selaku orang tua / wali sah menyatakan bahwa peserta yang didaftarkan dalam kondisi sehat jasmani dan rohani untuk mengikuti aktivitas fisik seleksi sepak bola {{event_name}}, serta membebaskan panitia pelaksana dari tuntutan hukum atas risiko cedera olahraga selama kegiatan berlangsung.',
                'content' => '<h3>1. Pernyataan Kesiapan Fisik &amp; Kesehatan</h3><p>Saya selaku orang tua / wali sah atau peserta dewasa menyatakan bahwa peserta yang didaftarkan pada program <strong>{{event_name}}</strong> berada dalam kondisi sehat jasmani dan rohani, serta tidak memiliki riwayat kondisi medis berat yang melarang aktivitas fisik intensitas tinggi di lapangan.</p><h3>2. Risiko Aktivitas Olahraga Lapangan</h3><p>Saya memahami bahwa kegiatan seleksi sepak bola melibatkan aktivitas fisik intensif dan kontak olahraga yang memiliki risiko cedera wajar. Informasi riwayat alergi atau catatan medis khusus telah disampaikan secara jujur pada formulir pendaftaran.</p><h3>3. Pembebasan Tanggung Jawab Hukum (Liability Waiver)</h3><p>Dengan penuh kesadaran tanpa paksaan, saya membebaskan panitia pelaksana, tim pelatih, pengelola venue, dan pihak penyelenggara dari tuntutan hukum perdata maupun pidana atas risiko cedera olahraga yang terjadi selama rangkaian kegiatan berlangsung, sepanjang pertolongan pertama medis (P3K) di lapangan telah diberikan sesuai standar panitia.</p>',
            ],
            'coaching_clinic' => [
                'code' => 'coaching-clinic-standard',
                'title' => 'Syarat & Ketentuan Peserta Coaching Clinic',
                'scope' => 'coaching_clinic',
                'summary' => 'Saya menyatakan seluruh data peserta adalah benar, siap mematuhi tata tertib sesi pelatihan {{event_name}}, serta menyetujui pernyataan kesiapan fisik dan pembebasan tanggung jawab medis standar kegiatan lapangan.',
                'content' => '<h3>1. Kepatuhan Tata Tertib &amp; Keselamatan</h3><p>Peserta <strong>{{event_name}}</strong> wajib hadir tepat waktu sesuai sesi pelatihan yang ditetapkan, mengenakan perlengkapan olahraga standar keselamatan, serta mengikuti seluruh instruksi tim pelatih.</p><h3>2. Kesiapan Fisik &amp; Tanggung Jawab Medis</h3><p>Peserta (dan orang tua/wali apabila berusia di bawah umur) menyatakan peserta dalam kondisi sehat untuk mengikuti sesi latihan praktik maupun teori di lapangan, serta membebaskan panitia dari tuntutan atas risiko cedera olahraga wajar selama kegiatan berlangsung.</p>',
            ],
        ];
    }
}

