<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->date('payment_date'); // Fecha del pago
            $table->decimal('amount', 15, 2); // Monto del pago (positivo si se paga al cliente, negativo si el cliente paga)
            $table->enum('type', ['paid_to_client', 'received_from_client']); // Tipo de pago
            $table->text('notes')->nullable(); // Notas opcionales
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Usuario que registró el pago
            $table->timestamps();
            
            // Índices para mejorar las consultas
            $table->index('client_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payments');
    }
};
