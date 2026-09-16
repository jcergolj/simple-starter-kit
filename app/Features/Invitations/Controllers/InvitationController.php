<?php

namespace App\Features\Invitations\Controllers;

use App\Features\Invitations\Mail\InvitationMail;
use App\Features\Invitations\Requests\SendInvitationRequest;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class InvitationController extends Controller
{
    public function create(Request $request): View
    {
        $pendingInvitations = Invitation::pending()->get();

        return view('invitations::create', ['pendingInvitations' => $pendingInvitations]);
    }

    public function store(SendInvitationRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        try {
            $invitation = DB::transaction(function () use ($email): Invitation {
                $existingInvitation = Invitation::where('email', $email)
                    ->lockForUpdate()
                    ->first();

                return $existingInvitation?->renew() ?? Invitation::createFor($email);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('An invitation for this email address was just created.'),
            ]);
        }

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        InAppNotification::success(__('Invitation sent successfully.'));

        return to_route('invitations.create');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        if ($invitation->isPending()) {
            $invitation->delete();
        }

        InAppNotification::success(__('Invitation revoked.'));

        return to_route('invitations.create');
    }
}
