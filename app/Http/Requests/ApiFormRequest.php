<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ApiFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // By default, we'll return true. Override this in child classes if needed
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Override this method in child classes
        return [];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        // Override this method in child classes to customize error messages
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        // Override this method in child classes to customize attribute names
        return [];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException($this->formatErrorResponse($validator));
    }

    /**
     * Format the error response
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return \Illuminate\Http\JsonResponse
     */
    protected function formatErrorResponse(Validator $validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'The given data was invalid.',
            'errors' => $this->formatErrors($validator),
            'meta' => [
                'timestamp' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'user' => Auth::check() ? Auth::user()->email : null,
                'request_id' => uniqid('req_'),
            ]
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Format the errors from the validator
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return array
     */
    protected function formatErrors(Validator $validator): array
    {
        return array_map(function ($message) {
            return [
                'message' => $message[0],
                'code' => 'VALIDATION_ERROR'
            ];
        }, $validator->errors()->toArray());
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action.',
                'errors' => [
                    'authorization' => [
                        'message' => 'Unauthorized access.',
                        'code' => 'UNAUTHORIZED'
                    ]
                ],
                'meta' => [
                    'timestamp' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                    'user' => Auth::check() ? Auth::user()->email : null,
                    'request_id' => uniqid('req_'),
                ]
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for all inputs
        $this->merge(array_map(function ($value) {
            return $value === '' ? null : $value;
        }, $this->all()));
    }

    /**
     * Get sanitized input from the request.
     *
     * @param  array|null  $keys
     * @return array
     */
    public function sanitized(?array $keys = null): array
    {
        $data = $this->validated();
        
        if ($keys) {
            return array_intersect_key($data, array_flip($keys));
        }

        return $data;
    }

    /**
     * Check if any of the given fields exist in the request.
     *
     * @param  string[]  $keys
     * @return bool
     */
    public function hasAnyRequest(array $keys): bool
    {
        return collect($keys)->some(fn ($key) => $this->has($key));
    }

    /**
     * Get the error message format.
     *
     * @param  string  $field
     * @param  string  $message
     * @param  string  $code
     * @return array
     */
    protected function errorFormat(string $field, string $message, string $code = 'VALIDATION_ERROR'): array
    {
        return [
            $field => [
                'message' => $message,
                'code' => $code
            ]
        ];
    }
}