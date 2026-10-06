<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,investigating,resolved,archived',
            'observation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O estado é obrigatório.',
            'status.in' => 'Estado inválido.',
            'observation.required' => 'A observação é obrigatória.',
        ];
    }
}
