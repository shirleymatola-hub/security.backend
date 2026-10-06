<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManagerUpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,investigating,resolved,archived',
            'priority' => 'required|in:low,medium,high,urgent',
            'observation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O estado é obrigatório.',
            'status.in' => 'Estado inválido.',
            'priority.required' => 'A prioridade é obrigatória.',
            'priority.in' => 'Prioridade inválida.',
            'observation.required' => 'A observação é obrigatória.',
        ];
    }
}
