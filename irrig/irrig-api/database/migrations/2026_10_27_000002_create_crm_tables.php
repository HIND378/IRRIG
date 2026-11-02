<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module M1/M2 : clients, exploitations et parc installé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences');
            $table->string('nom');
            $table->enum('type', ['petit_exploitant', 'cooperative', 'entreprise'])->default('petit_exploitant');
            $table->string('telephone', 20)->unique(); // format normalisé +212XXXXXXXXX
            $table->string('email')->nullable();
            $table->enum('langue', ['fr', 'ar'])->default('ar');
            $table->timestamps();
        });

        // Clé étrangère users.client_id ajoutée après coup (la table clients n'existait pas encore).
        // SQLite (utilisé par les tests) ne sait pas ajouter une clé étrangère à une table existante.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            });
        }

        Schema::create('exploitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('nom')->nullable();
            $table->string('commune');
            $table->decimal('superficie_ha', 8, 2)->nullable();
            $table->string('culture')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('equipements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exploitation_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['goutte_a_goutte', 'pompe_solaire', 'capteur_humidite', 'programmateur', 'filtration']);
            $table->string('modele')->nullable();
            $table->string('numero_serie')->unique();
            $table->date('date_installation');
            $table->date('fin_garantie')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements');
        Schema::dropIfExists('exploitations');
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['client_id']);
            });
        }
        Schema::dropIfExists('clients');
    }
};
