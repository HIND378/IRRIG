<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que le rôle de l'utilisateur connecté autorise la route.
 * Usage : ->middleware('role:responsable,directeur')
 *
 * Généralisation du diagramme de cas d'utilisation : le Directeur hérite
 * du Responsable d'agence, il passe donc toute route ouverte au responsable.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->actif) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (in_array('responsable', $roles, true)) {
            $roles[] = 'directeur';
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Action non autorisée pour votre rôle.'], 403);
        }

        return $next($request);
    }
}
