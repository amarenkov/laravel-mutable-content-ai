<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\Feedback;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.feedback', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Feedback::FIELD_USER_ID)->type('bigint');
            $table->fieldExtract(Feedback::FIELD_RUN_ID)->type('bigint');
            $table->fieldExtract(Feedback::FIELD_SOURCE)->type('varchar(191)');

            $table->index([Feedback::FIELD_USER_ID, Feedback::FIELD_SOURCE]);
            $table->index(Feedback::FIELD_RUN_ID);
        });
    }
};
