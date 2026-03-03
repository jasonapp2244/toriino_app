<?php

namespace App\Http\Requests\Auth;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'   => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'password'    => 'required|string|min:8',
            'role'        => 'nullable|in:student,mentor,teacher',
            'timezone'    => 'nullable|string|max:50',
            'language'    => 'nullable|string|max:10',
            'device_id'   => 'nullable|string',
            'device_type' => 'nullable|in:ios,android',
            'fcm_token'   => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required.',
            'email.required'     => 'Email address is required.',
            'password.min'       => 'Password must be at least 8 characters.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
