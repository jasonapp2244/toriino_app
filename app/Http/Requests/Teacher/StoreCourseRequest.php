<?php

namespace App\Http\Requests\Teacher;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:course_categories,id',
            'level_id'    => 'required|exists:course_levels,id',
            'language'    => 'required|string|max:50',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'sometimes|string|max:50',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
