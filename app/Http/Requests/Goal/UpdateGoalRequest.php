<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'         => ['sometimes', 'string', 'min:3', 'max:150'],
            'description'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'target_value'  => ['sometimes', 'numeric', 'min:0', 'max:9999999'],
            'current_value' => ['sometimes', 'numeric', 'min:0', 'max:9999999'],
            'unit'          => ['sometimes', 'string', 'max:30'],
            'category'      => ['sometimes', 'string', 'in:weight,body_fat,muscle_mass,endurance,strength,habit,other'],
            'deadline'      => ['sometimes', 'nullable', 'date', 'after:today'],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge(['title' => strip_tags(trim($this->title))]);
        }
        if ($this->has('description')) {
            $this->merge(['description' => strip_tags(trim($this->description))]);
        }
    }
}
