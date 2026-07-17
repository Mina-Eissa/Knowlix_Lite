<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InviteUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function build()
    {
        $acceptUrl = config('app.frontend_url') . '/accept-invite'
            . '?email=' . urlencode($this->user->email)
            . '&token=' . $this->token;

        return $this->subject("You've been invited to {$this->user->workspace->name} on Knowlix")
            ->view('emails.invite-user', [
                'user' => $this->user,
                'acceptUrl' => $acceptUrl,
            ]);
    }
}
