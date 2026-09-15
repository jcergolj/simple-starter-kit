<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Actions\NormalizeEmail;
use App\Http\Requests\AppFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends AppFormRequest
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
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
