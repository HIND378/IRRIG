<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agences + utilisateurs.
 * L'héritage du diagramme de classes (Utilisateur -> Technicien, Commercial,
 * ResponsableAgence -> Directeur) est stocké dans une seule table `users`
 * avec une colonne `role` (stratégie « table unique »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->string('ville')->unique();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->enum('role', [
                'agriculteur', 'commercial', 'responsable', 'directeur',
                'technicien', 'magasinier', 'admin',
            ]);
            // nul pour le directeur et l'administrateur (périmètre : les 3 agences)
            $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
            // renseigné uniquement pour un agriculteur (compte du portail client)
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('specialite')->nullable(); // technicien
            $table->string('zone')->nullable();       // technicien
            $table->boolean('actif')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('agences');
    }
};
