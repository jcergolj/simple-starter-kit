<?php

declare(strict_types=1);

namespace App\Features\Invitations\Actions;

use App\Enums\RoleEnum;
use App\Features\Invitations\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SendInvitation
{
    public function handle(string $email, RoleEnum $role = RoleEnum::User, string $lang = 'en'): Invitation
    {
        try {
            $invitation = DB::transaction(function () use ($email, $role, $lang): Invitation {
                $existingInvitation = Invitation::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($existingInvitation !== null) {
                    $existingInvitation->renew();
                    $existingInvitation->update(['role' => $role, 'lang' => $lang]);

                    return $existingInvitation;
                }

                return Invitation::createFor($email, $role, $lang);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('An invitation for this email address was just created.'),
            ]);
        }

        Mail::to($invitation->email)
            ->locale($invitation->lang)
            ->send(new InvitationMail($invitation));

        return $invitation;
    }
}
