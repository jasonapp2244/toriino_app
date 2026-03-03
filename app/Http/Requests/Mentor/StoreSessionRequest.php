<?php

namespace App\Http\Requests\Mentor;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'type'             => 'required|in:individual,group',
            'start_time'       => 'required|date|after:now',
            'end_time'         => 'required|date|after:start_time',
            'max_seats'        => 'required|integer|min:1',
            'language'         => 'required|string',
            'price'            => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15',
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.after' => 'Session must be scheduled in the future.',
            'end_time.after'   => 'End time must be after start time.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
