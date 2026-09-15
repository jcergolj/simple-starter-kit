<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\NormalizeEmail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends AppFormRequest
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
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(Invitation::class)
                    ->whereNull('accepted_at')
                    ->where(function ($query) {
                        return $query->where('expires_at', '>', now());
                    }),
                Rule::unique(User::class, 'email'),
            ],
        ];
    }
}
