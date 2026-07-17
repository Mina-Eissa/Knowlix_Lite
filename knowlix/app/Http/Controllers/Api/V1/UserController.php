<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Models\User;
use App\Mail\InviteUserMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class UserController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', User::class);

        return User::all(); // BelongsToWorkspace already scopes this to the caller's workspace
    }

    public function store(InviteUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Str::password(16), // random temp password; invite flow would email a reset link
            'role' => $request->role,
        ]);

        $token = Password::createToken($user);

        Mail::to($user->email)->send(new InviteUserMail($user, $token));

        return response()->json($user, 201);
    }
    public function resendInvite(User $user)
    {
        $this->authorize('delete', $user); // reuse the admin-only check; same privilege level as removing a user

        $token = Password::createToken($user);
        Mail::to($user->email)->send(new InviteUserMail($user, $token));

        return response()->json(['message' => 'Invitation resent'], 200);
    }
    public function show(User $user)
    {
        return $user; // 404 automatically if user belongs to another workspace
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'User removed'], 200);
    }
}
