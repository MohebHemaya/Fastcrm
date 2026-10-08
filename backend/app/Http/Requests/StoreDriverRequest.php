<?php

namespace App\Http\Requests;

use App\Models\Driver;
use App\Services\PhoneNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public ?string $normalizedPhone = null;

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
            'phone' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    $norm = PhoneNormalizer::normalize((string) $value);
                    if (! $norm->isValid || $norm->e164 === null) {
                        $fail('رقم الهاتف غير صالح للاستخدام.');

                        return;
                    }

                    $exists = Driver::where('phone', $norm->e164)->exists();
                    if ($exists) {
                        $fail('رقم الهاتف مسجل بالفعل لسائق آخر.');

                        return;
                    }

                    $this->normalizedPhone = $norm->e164;
                },
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'group_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب.',
            'name.max' => 'اسم السائق يجب ألا يتجاوز 255 حرفاً.',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 2000 حرف.',
        ];
    }
}
