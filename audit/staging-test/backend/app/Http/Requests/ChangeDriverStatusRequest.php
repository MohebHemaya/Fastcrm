<?php

namespace App\Http\Requests;

use App\Enums\DriverStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeDriverStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DriverStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'حالة السائق مطلوبة.',
            'status.enum' => 'حالة السائق المحددة غير صحيحة.',
            'reason.required' => 'سبب تغيير الحالة مطلوب.',
            'reason.max' => 'السبب يجب ألا يتجاوز 500 حرف.',
        ];
    }
}
