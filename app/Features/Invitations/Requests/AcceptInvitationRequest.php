<?php

declare(strict_types=1);

namespace App\Features\Invitations\Requests;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
