<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\Settings;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.settings', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Settings::FIELD_USER_ID)->type('bigint');

            $table->unique(Settings::FIELD_USER_ID);
        });
    }
};
