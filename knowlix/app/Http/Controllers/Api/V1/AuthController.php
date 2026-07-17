<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterWorkspaceRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterWorkspaceRequest $request)
    {
        $result = DB::transaction(function () use ($request) {
            $workspace = Workspace::create([
                'name' => $request->workspace_name,
                'slug' => Str::slug($request->workspace_name) . '-' . Str::random(4),
            ]);
            $user = User::create([
                'workspace_id' => $workspace->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // hashed automatically by the 'hashed' cast
                'role' => UserRole::Admin,
            ]);

            return [$workspace, $user];
        });

        [$workspace, $user] = $result;
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'workspace' => $workspace,
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {

            throw ValidationException::withMessages([
                    'email' => ['These credentials do not match our records.'],
                ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json([
            'user' => $user,
            // 'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function logout(\Illuminate\Http\Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(\Illuminate\Http\Request $request)
    {
        return response()->json($request->user());
    }
}
