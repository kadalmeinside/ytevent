<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamOfficial extends Model
{
    use SoftDeletes;

    public const ROLES = [
        'Head Coach' => 'Pelatih Kepala (Head Coach)',
        'Assistant Coach' => 'Asisten Pelatih',
        'Goalkeeper Coach' => 'Pelatih Penjaga Gawang',
        'Physiotherapist' => 'Fisioterapis / Medis',
        'Team Manager' => 'Manajer Tim',
        'Kitman' => 'Kitman / Perlengkapan',
    ];

    protected $fillable = [
        'team_id',
        'event_id',
        'full_name',
        'role',
        'license_number',
        'license_file_path',
        'photo_path',
        'phone',
        'screening_status',
        'verification_notes',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

