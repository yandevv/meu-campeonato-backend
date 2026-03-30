<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachTournamentTeamsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_ids' => ['required', 'array', 'min:1', 'max:8'],
            'team_ids.*' => ['required', 'uuid', 'distinct:strict', Rule::exists('teams', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_ids' => 'teams',
            'team_ids.*' => 'team',
        ];
    }
}
