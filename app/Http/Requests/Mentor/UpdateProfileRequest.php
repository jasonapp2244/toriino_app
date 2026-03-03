<?php

namespace App\Http\Requests\Mentor;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|max:255',
            'phone'            => 'sometimes|string|max:20',
            'bio'              => 'sometimes|string|max:1000',
            'city'             => 'sometimes|string|max:100',
            'country'          => 'sometimes|string|max:100',
            'language'         => 'sometimes|string|max:50',
            'specialization'   => 'sometimes|string|max:255',
            'intro'            => 'sometimes|string|max:1000',
            'price_per_hour'   => 'sometimes|numeric|min:0',
            'languages'        => 'sometimes|string|max:255',
            'experience_years' => 'sometimes|string|max:20',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
