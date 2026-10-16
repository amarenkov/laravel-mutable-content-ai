<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\Approval;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.approvals', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Approval::FIELD_USER_ID)->type('bigint');
            $table->fieldExtract(Approval::FIELD_RUN_ID)->type('bigint');

            $table->index(Approval::FIELD_USER_ID);
            $table->index(Approval::FIELD_RUN_ID);
        });
    }
};
