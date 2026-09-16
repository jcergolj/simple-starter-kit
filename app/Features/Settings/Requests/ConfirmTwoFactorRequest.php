<?php

declare(strict_types=1);

namespace App\Features\Settings\Requests;

use App\Http\Requests\AppFormRequest;

class ConfirmTwoFactorRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'min:6'],
        ];
    }
}
