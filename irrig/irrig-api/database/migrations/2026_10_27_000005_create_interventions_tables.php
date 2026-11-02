<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module M3 : interventions, pièces consommées, photos et avis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agence_id')->constrained();
            $table->foreignId('technicien_id')->nullable()->constrained('users');
            $table->foreignId('demandeur_id')->nullable()->constrained('users');
            $table->enum('type', ['installation', 'maintenance', 'panne']);
            $table->enum('statut', ['nouvelle', 'planifiee', 'en_cours', 'cloturee', 'annulee'])->default('nouvelle');
            $table->enum('canal', ['portail', 'telephone', 'whatsapp', 'agence'])->default('portail');
            $table->text('description');
            $table->boolean('sous_garantie')->default(false);
            $table->dateTime('date_planifiee')->nullable();
            $table->dateTime('date_cloture')->nullable();
            $table->text('rapport')->nullable();
            $table->string('signature_client')->nullable(); // nom saisi ou chemin de l'image
            $table->uuid('uuid_mobile')->nullable()->unique(); // évite les doublons lors de la synchro hors ligne
            $table->timestamps();
        });

        Schema::create('lignes_consommation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained();
            $table->unsignedInteger('quantite');
            $table->decimal('prix_unitaire', 10, 2); // prix figé au moment de l'intervention
            $table->timestamps();
        });

        Schema::create('photos_intervention', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->string('chemin_fichier');
            $table->timestamps();
        });

        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->unique()->constrained()->cascadeOnDelete(); // au plus un avis
            $table->unsignedTinyInteger('note'); // 1 à 5
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
        Schema::dropIfExists('photos_intervention');
        Schema::dropIfExists('lignes_consommation');
        Schema::dropIfExists('interventions');
    }
};
