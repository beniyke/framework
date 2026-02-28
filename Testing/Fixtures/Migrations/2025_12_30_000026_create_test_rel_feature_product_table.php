<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelFeatureProductTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_feature_product', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_feature_product');
    }
}
