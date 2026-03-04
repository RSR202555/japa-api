<?php

namespace App\Http\Requests\Meal;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'min:2', 'max:100'],
            'meal_time'         => ['required', 'string', 'in:breakfast,morning_snack,lunch,afternoon_snack,dinner,supper'],
            'foods'             => ['required', 'array', 'min:1', 'max:20'],
            'foods.*.name'      => ['required', 'string', 'max:100'],
            'foods.*.quantity'  => ['required', 'numeric', 'min:0', 'max:10000'],
            'foods.*.unit'      => ['required', 'string', 'max:20'],
            'foods.*.calories'  => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'total_calories'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'total_protein'     => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'total_carbs'       => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'total_fat'         => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'notes'             => ['nullable', 'string', 'max:500'],
            'logged_at'         => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => strip_tags(trim($this->name ?? '')),
            'notes' => strip_tags(trim($this->notes ?? '')),
        ]);
    }
}
