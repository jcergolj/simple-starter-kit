<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\NormalizeEmail;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends AppFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($email = $this->input('email'))) {
            $this->merge(['email' => NormalizeEmail::normalize($email)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->route('user')),
            ],
        ];
    }
}
