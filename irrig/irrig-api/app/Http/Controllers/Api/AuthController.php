<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** POST /api/login : renvoie un jeton Sanctum et le profil (rôle, agence). */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'appareil' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects.']);
        }
        if (! $user->actif) {
            throw ValidationException::withMessages(['email' => 'Ce compte est désactivé.']);
        }

        $token = $user->createToken($data['appareil'] ?? 'web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('agence', 'client'),
        ]);
    }

    /** GET /api/me */
    public function me(Request $request)
    {
        return $request->user()->load('agence', 'client');
    }

    /** POST /api/logout : révoque le jeton courant. */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
