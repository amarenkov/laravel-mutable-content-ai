<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\BalanceEntry;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.balance_entries', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(BalanceEntry::FIELD_USER_ID)->type('bigint');

            $table->index(BalanceEntry::FIELD_USER_ID);
        });
    }
};
