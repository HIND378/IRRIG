<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module M4 : catalogue de pièces, stock par agence, transferts inter-agences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('libelle');
            $table->decimal('prix', 10, 2);
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained();
            $table->foreignId('piece_id')->constrained();
            $table->unsignedInteger('quantite')->default(0);
            $table->unsignedInteger('seuil_alerte')->default(5);
            $table->unique(['agence_id', 'piece_id']); // une ligne de stock par pièce et par agence
            $table->timestamps();
        });

        Schema::create('transferts_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_source_id')->constrained('agences');
            $table->foreignId('agence_dest_id')->constrained('agences');
            $table->foreignId('piece_id')->constrained();
            $table->unsignedInteger('quantite');
            $table->foreignId('demandeur_id')->constrained('users');
            $table->foreignId('directeur_id')->nullable()->constrained('users'); // valideur
            $table->enum('statut', ['demande', 'valide', 'refuse'])->default('demande');
            $table->timestamp('date_validation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferts_stock');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('pieces');
    }
};
