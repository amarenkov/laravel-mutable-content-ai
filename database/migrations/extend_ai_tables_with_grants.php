<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;

use Amarenkov\MutableContentAi\Models\Grant;
use Amarenkov\MutableContentAi\Models\Run;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.grants', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Grant::FIELD_OWNER_ID)->type('bigint');
            $table->fieldExtract(Grant::FIELD_USER_ID)->type('bigint');
            $table->fieldExtract(Grant::FIELD_CONVERSATION)->type('varchar(191)');

            $table->index(Grant::FIELD_OWNER_ID);
            $table->index([Grant::FIELD_USER_ID, Grant::FIELD_CONVERSATION]);
            $table->index(Grant::FIELD_CONVERSATION);
        });

        Schema::table(DatabaseHelper::tableName('ai.runs'), function (Blueprint $table) {
            $table->fieldExtract(Run::FIELD_PAYER_ID)->type('bigint');
            $table->fieldExtract(Run::FIELD_GRANT_ID)->type('bigint');

            $table->index([Run::FIELD_PAYER_ID, 'created_at']);
            $table->index([Run::FIELD_GRANT_ID, 'created_at']);
        });
    }
};
