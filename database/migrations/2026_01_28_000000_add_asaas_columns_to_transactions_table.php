<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAsaasColumnsToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('asaas_customer_id')->nullable()->after('paypal_payer_id');
            $table->string('asaas_payment_id')->nullable()->after('asaas_customer_id');
            $table->string('asaas_status')->nullable()->after('asaas_payment_id');
            $table->string('asaas_invoice_url')->nullable()->after('asaas_status');
            $table->text('asaas_pix_qr_code')->nullable()->after('asaas_invoice_url');
            $table->text('asaas_pix_payload')->nullable()->after('asaas_pix_qr_code');
            $table->json('asaas_raw')->nullable()->after('asaas_pix_payload');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'asaas_customer_id',
                'asaas_payment_id',
                'asaas_status',
                'asaas_invoice_url',
                'asaas_pix_qr_code',
                'asaas_pix_payload',
                'asaas_raw'
            ]);
        });
    }
}
