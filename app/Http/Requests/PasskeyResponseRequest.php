<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validasi bentuk assertion WebAuthn (format PublicKeyCredential.toJSON()).
 * Verifikasi tanda tangan dilakukan oleh GoWA, bukan di sini.
 */
class PasskeyResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
            'id' => ['required', 'string'],
            'rawId' => ['required', 'string'],
            'type' => ['required', 'in:public-key'],
            'response' => ['required', 'array'],
            'response.clientDataJSON' => ['required', 'string'],
            'response.authenticatorData' => ['required', 'string'],
            'response.signature' => ['required', 'string'],
            'response.userHandle' => ['nullable', 'string'],
        ];
    }

    /**
     * Gagal validasi dikembalikan dengan format standar adapter.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => [],
        ], 200));
    }
}
