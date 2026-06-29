<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $partnerId = $this->route('partner');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'bin_iin' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('partners', 'bin_iin')->ignore($partnerId)],
            'bank_details' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
        ];
    }
}
