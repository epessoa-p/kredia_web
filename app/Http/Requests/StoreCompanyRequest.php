<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->is_super_admin;
    }

    public function rules(): array
    {
        $company = $this->route('company');
        $companyId = $company ? (is_object($company) ? $company->id : $company) : null;

        return [
            'name' => 'required|string|max:255',
            'ruc' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('companies', 'ruc')->ignore($companyId),
            ],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'interest_next_day' => 'nullable|boolean',
            // Sin comillas ni < > \ para poder usarlo sin riesgo en HTML y JavaScript.
            'currency_symbol' => ['required', 'string', 'max:10', 'regex:/^[^<>"\'`\\\\]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es requerido',
            'ruc.unique' => 'Este RUC ya está registrado',
            'currency_symbol.required' => 'Indica el símbolo de la moneda (ej. Bs).',
            'currency_symbol.max' => 'El símbolo de la moneda no puede tener más de 10 caracteres.',
            'currency_symbol.regex' => 'El símbolo de la moneda no puede tener comillas ni los caracteres < > \\.',
        ];
    }
}
