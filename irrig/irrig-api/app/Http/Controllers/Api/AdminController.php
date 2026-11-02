<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Module M8 : utilisateurs et rôles (administrateur), agences (directeur).
 */
class AdminController extends Controller
{
    /** GET /api/users?role= */
    public function users(Request $request)
    {
        $query = User::with('agence', 'client:id,nom')->orderBy('name');
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return $query->get();
    }

    /** GET /api/techniciens?agence_id= : liste utilisée par le responsable pour planifier. */
    public function techniciens(Request $request)
    {
        $user = $request->user();
        $query = User::where('role', 'technicien')->where('actif', true)->orderBy('name');
        $user->filtrerParAgence($query, $request->integer('agence_id') ?: null);

        return $query->withCount(['missions as missions_ouvertes' => fn ($q) => $q->whereIn('statut', ['planifiee', 'en_cours'])])
            ->get(['id', 'name', 'telephone', 'agence_id', 'specialite', 'zone']);
    }

    /** POST /api/users */
    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telephone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in(User::ROLES)],
            'agence_id' => 'nullable|exists:agences,id',
            'client_id' => 'nullable|exists:clients,id',
            'specialite' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
        ]);
        $this->verifierCoherence($data);

        return response()->json(User::create($data)->load('agence'), 201);
    }

    /** PUT /api/users/{user} */
    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'telephone' => 'nullable|string|max:20',
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'agence_id' => 'nullable|exists:agences,id',
            'client_id' => 'nullable|exists:clients,id',
            'specialite' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:255',
            'actif' => 'sometimes|boolean',
            'password' => 'nullable|string|min:8',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $this->verifierCoherence(array_merge($user->only('role', 'agence_id', 'client_id'), $data));
        abort_if(isset($data['actif']) && ! $data['actif'] && $user->id === $request->user()->id, 422, 'Vous ne pouvez pas désactiver votre propre compte.');

        if (isset($data['actif']) && ! $data['actif']) {
            $user->tokens()->delete(); // déconnexion immédiate
        }
        $user->update($data);

        return $user->load('agence');
    }

    /** GET /api/agences */
    public function agences()
    {
        return Agence::withCount(['clients', 'utilisateurs'])->orderBy('ville')->get();
    }

    /** POST /api/agences (directeur) */
    public function storeAgence(Request $request)
    {
        $data = $request->validate([
            'ville' => 'required|string|max:255|unique:agences,ville',
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
        ]);

        $agence = Agence::create($data);
        foreach (\App\Models\Piece::pluck('id') as $pieceId) {
            $agence->stocks()->create(['piece_id' => $pieceId, 'quantite' => 0]);
        }

        return response()->json($agence, 201);
    }

    /** PUT /api/agences/{agence} (directeur) */
    public function updateAgence(Request $request, Agence $agence)
    {
        $data = $request->validate([
            'ville' => ['sometimes', 'string', 'max:255', Rule::unique('agences', 'ville')->ignore($agence->id)],
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
        ]);
        $agence->update($data);

        return $agence;
    }

    /** Règles : les rôles d'agence ont une agence, l'agriculteur a un client. */
    private function verifierCoherence(array $data): void
    {
        $role = $data['role'] ?? null;
        if (in_array($role, ['commercial', 'responsable', 'technicien', 'magasinier'], true)) {
            abort_if(empty($data['agence_id']), 422, 'Ce rôle doit être rattaché à une agence.');
        }
        if ($role === 'agriculteur') {
            abort_if(empty($data['client_id']), 422, 'Un compte agriculteur doit être lié à une fiche client.');
        }
    }
}
