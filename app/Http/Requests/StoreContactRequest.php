<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'custom_fields_values' => ['required', 'array'],
            'custom_fields_values.email' => ['required', 'email', 'max:255'],
            'custom_fields_values.phone' => ['required', 'phone'],
            'custom_fields_values.age' => ['required', 'numeric', 'max:100'],
            'custom_fields_values.gender' => ['required', 'string', 'max:60'],
        ];
    }
}
