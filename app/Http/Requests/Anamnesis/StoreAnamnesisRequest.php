<?php

namespace App\Http\Requests\Anamnesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnamnesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight'                => ['required', 'numeric', 'min:20', 'max:500'],
            'height'                => ['required', 'numeric', 'min:0.5', 'max:3'],
            'body_fat_percentage'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'objective'             => ['required', 'string', 'in:weight_loss,muscle_gain,health,endurance,maintenance'],
            'physical_activity_level' => ['required', 'string', 'in:sedentary,lightly_active,moderately_active,very_active,extremely_active'],
            'health_conditions'     => ['nullable', 'array'],
            'health_conditions.*'   => ['string', 'max:100'],
            'medications'           => ['nullable', 'string', 'max:1000'],
            'food_restrictions'     => ['nullable', 'array'],
            'food_restrictions.*'   => ['string', 'max:100'],
            'food_preferences'      => ['nullable', 'array'],
            'food_preferences.*'    => ['string', 'max:100'],
            'meals_per_day'         => ['required', 'integer', 'min:1', 'max:10'],
            'water_intake_liters'   => ['nullable', 'numeric', 'min:0', 'max:20'],
            'sleep_hours'           => ['nullable', 'numeric', 'min:0', 'max:24'],
            'stress_level'          => ['nullable', 'integer', 'min:1', 'max:10'],
            'additional_notes'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_notes')) {
            $this->merge([
                'additional_notes' => strip_tags(trim($this->additional_notes ?? '')),
                'medications'      => strip_tags(trim($this->medications ?? '')),
            ]);
        }
    }
}
