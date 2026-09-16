<?php

declare(strict_types=1);

namespace App\Features\Settings\Requests;

use App\Http\Requests\AppFormRequest;
use App\Rules\CurrentPassword;

class DeleteProfileRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', new CurrentPassword($this->user())],
        ];
    }
}
