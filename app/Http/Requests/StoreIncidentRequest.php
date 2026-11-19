<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address_detail' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'incident_date' => 'nullable|date',
            'is_anonymous' => 'boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
            'audio_file' => 'nullable|file|mimes:webm,ogg,wav|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório.',
            'description.required' => 'A descrição é obrigatória.',
            'category_id.exists' => 'A categoria selecionada não existe.',
            'priority.in' => 'Prioridade inválida.',
            'latitude.required' => 'A latitude é obrigatória.',
            'latitude.numeric' => 'A latitude deve ser um número válido.',
            'latitude.between' => 'A latitude deve estar entre -90 e 90.',
            'longitude.required' => 'A longitude é obrigatória.',
            'longitude.numeric' => 'A longitude deve ser um número válido.',
            'longitude.between' => 'A longitude deve estar entre -180 e 180.',
            'neighborhood_id.exists' => 'O bairro selecionado não existe.',
            'attachments.max' => 'Máximo de 5 anexos permitidos.',
            'attachments.*.max' => 'Cada anexo deve ter no máximo 5MB.',
        ];
    }
}
