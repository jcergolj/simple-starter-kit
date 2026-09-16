<?php

declare(strict_types=1);

namespace App\Features\Invitations\Requests;

use App\Http\Requests\AppFormRequest;
use App\Models\Invitation;
use App\Models\User;
use App\ValueObjects\EmailAddress;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends AppFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($email = $this->input('email'))) {
            $this->merge(['email' => EmailAddress::from($email)->toString()]);
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
