<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\Memory;
use Amarenkov\MutableContentAi\Models\Run;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createSchema('ai');

        Schema::createWithLog('ai.memories', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Memory::FIELD_USER_ID)->type('bigint');

            $table->index(Memory::FIELD_USER_ID);
        });

        Schema::createWithLog('ai.runs', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Run::FIELD_USER_ID)->type('bigint');
            $table->fieldExtract(Run::FIELD_CONVERSATION)->type('varchar(191)');

            $table->index([Run::FIELD_USER_ID, 'created_at']);
            $table->index([Run::FIELD_CONVERSATION, Run::FIELD_USER_ID]);
        });
    }
};
