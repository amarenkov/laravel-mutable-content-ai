<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentAi\Models\GrantConsent;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('ai.grant_consents', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(GrantConsent::FIELD_GRANT_ID)->type('bigint');
            $table->fieldExtract(GrantConsent::FIELD_USER_ID)->type('bigint');

            $table->unique([GrantConsent::FIELD_GRANT_ID, GrantConsent::FIELD_USER_ID]);
            $table->index(GrantConsent::FIELD_USER_ID);
        });
    }
};
