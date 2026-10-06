<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O relatório sempre recorta um intervalo de emissão, vencimento ou pagamento
     * e pode restringir cliente e status. A data lidera cada índice para o intervalo;
     * cliente e status completam os filtros de igualdade sem varrer a tabela.
     */
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->index(['issued_on', 'customer_id', 'status'], 'billings_issued_report_index');
            $table->index(['due_on', 'customer_id', 'status'], 'billings_due_report_index');
            $table->index(['paid_on', 'customer_id', 'status'], 'billings_paid_report_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropIndex('billings_issued_report_index');
            $table->dropIndex('billings_due_report_index');
            $table->dropIndex('billings_paid_report_index');
        });
    }
};
