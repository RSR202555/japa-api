<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'min:3', 'max:150'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'target_value'  => ['required', 'numeric', 'min:0', 'max:9999999'],
            'current_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'unit'          => ['required', 'string', 'max:30'],
            'category'      => ['required', 'string', 'in:weight,body_fat,muscle_mass,endurance,strength,habit,other'],
            'deadline'      => ['nullable', 'date', 'after:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title'       => strip_tags(trim($this->title ?? '')),
            'description' => strip_tags(trim($this->description ?? '')),
        ]);
    }
}
