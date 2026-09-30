<?php

namespace App\Http\Requests;

use App\Game\GameCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLobbyRequest extends FormRequest
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
            'game_type' => ['sometimes', 'string', Rule::in(GameCatalog::supportedGameTypes())],
            'max_players' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'starting_chips' => ['sometimes', 'integer', 'min:1'],
            'default_bet' => ['sometimes', 'integer', 'min:2', 'multiple_of:2'],
            'is_private' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'required_if:is_private,true'],
        ];
    }
}
