<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\Prompt;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.prompts', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Prompt::FIELD_CODE)->type('varchar(191)');

            $table->unique(Prompt::FIELD_CODE);
        });
    }
};
