<?php

namespace Tests\Feature\Tenant\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The columns expected cash reads from expenses and cash_movements, for tests
 * that build a minimal till schema by hand.
 */
trait CreatesDrawerCashTables
{
    protected function createDrawerCashTables(): void
    {
        $this->dropDrawerCashTables();

        Schema::connection('tenant')->create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->string('paid_from', 20)->nullable();
            $table->string('approval_status')->default('approved');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->string('direction', 3);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    protected function dropDrawerCashTables(): void
    {
        Schema::connection('tenant')->dropIfExists('cash_movements');
        Schema::connection('tenant')->dropIfExists('expenses');
    }
}
