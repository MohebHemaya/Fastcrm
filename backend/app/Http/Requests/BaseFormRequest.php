<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get custom attributes for validator errors.
     * Subclasses can merge with or override these.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('validation.attributes') ?: [];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->messages();

        throw new HttpResponseException(response()->json([
            'data' => null,
            'meta' => [
                'errors' => $errors,
            ],
            'message' => __('api.validation_error'),
            'errors' => $errors,
        ], 422));
    }
}
