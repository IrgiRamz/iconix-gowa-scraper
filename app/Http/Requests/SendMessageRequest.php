<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validasi request untuk endpoint /send (text, image, file).
 */
class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'min:1', 'max:255'],
            'number'    => ['required_without_all:phone', 'nullable', 'string', 'min:1'],
            'phone'     => ['required_without_all:number', 'nullable', 'string', 'min:1'],
            'message'   => ['nullable', 'string', 'max:65535'],
            'file'      => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_id.required'        => 'Parameter device_id wajib diisi.',
            'number.required_without'   => 'Parameter number wajib diisi.',
            'phone.required_without'    => 'Parameter phone wajib diisi.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => [],
        ], 400));
    }

    /**
     * Get the target phone number from either 'number' or 'phone' param.
     *
     * @return string|null
     */
    public function getTargetPhone(): ?string
    {
        return $this->input('number') ?? $this->input('phone');
    }
}
