<?php

namespace App\Http\Requests\Manager;

use App\Models\Player;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UpdatePlayerRequest extends StorePlayerRequest
{
    public function authorize(): bool
    {
        /** @var Player|null $player */
        $player = $this->route('player');

        return $player && ($this->user()?->can('update', $player) ?? false);
    }

    public function rules(): array
    {
        /** @var Player|null $player */
        $player = $this->route('player');
        $team = $player?->team;

        $rules = parent::rules();

        $rules['jersey_number'] = [
            'required',
            'integer',
            'min:1',
            'max:99',
            $team
                ? Rule::unique('players', 'jersey_number')
                    ->where('team_id', $team->id)
                    ->whereNull('deleted_at')
                    ->ignore($player?->id)
                : 'integer',
        ];

        $rules['nik'] = [
            'required',
            'string',
            'regex:/^\d{16}$/',
            $team
                ? Rule::unique('players', 'nik')
                    ->where('event_id', $team->event_id)
                    ->whereNull('deleted_at')
                    ->ignore($player?->id)
                : 'string',
        ];

        $rules['photo'] = ['nullable', File::image()->max(2048), 'mimes:jpg,jpeg,png'];

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Player|null $player */
                $player = $this->route('player');
                $this->validateAgeCutOff($validator, $player?->event);
            },
        ];
    }
}
