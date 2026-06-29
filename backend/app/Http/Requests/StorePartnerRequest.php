<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = $this->route('partner')?->id;
        return [
            'name' => ['required', 'string', 'max:255'],
            'bin_iin' => ['required', 'string', 'regex:/^\d{12}$/', Rule::unique('partners', 'bin_iin')->ignore($partnerId)],
            'bank_details' => ['required', 'string', 'max:2000'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'bin_iin.regex' => 'BIN/IIN must be exactly 12 digits.',
            'bank_details.required' => 'Bank details are required for partner registration.',
            'email.required' => 'Partner email is required for notifications.',
        ];
    }
}
