<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturation et archivage des projets (GED, module M7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('commande_id')->nullable()->constrained();
            $table->foreignId('intervention_id')->nullable()->constrained();
            $table->decimal('montant_ttc', 12, 2);
            $table->enum('statut_paiement', ['impayee', 'payee'])->default('impayee');
            $table->date('date');
            $table->timestamps();
        });

        Schema::create('documents_projet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exploitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['plan', 'pv_reception', 'photo', 'devis', 'autre']);
            $table->string('titre');
            $table->string('chemin_fichier');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_projet');
        Schema::dropIfExists('factures');
    }
};
