<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInviteRequest;
use App\Models\User;
use Illuminate\Support\Facades\Password;

class InvitationController extends Controller
{
    public function accept(AcceptInviteRequest $request)
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save(); // 'hashed' cast hashes it automatically
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Invalid or expired invitation link'], 422);
        }

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }
}
