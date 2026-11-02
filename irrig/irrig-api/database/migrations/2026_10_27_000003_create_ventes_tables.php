<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module M1 : devis et commandes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('commercial_id')->constrained('users');
            $table->date('date');
            $table->json('lignes'); // [{designation, quantite, prix_unitaire}]
            $table->decimal('montant_ht', 12, 2);
            $table->decimal('montant_ttc', 12, 2);
            $table->enum('statut', ['brouillon', 'envoye', 'accepte', 'refuse'])->default('envoye');
            $table->string('motif_refus')->nullable();
            $table->timestamps();
        });

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->unique()->constrained('devis'); // un devis -> au plus une commande
            $table->date('date');
            $table->enum('statut', ['en_cours', 'installee', 'annulee'])->default('en_cours');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
        Schema::dropIfExists('devis');
    }
};
