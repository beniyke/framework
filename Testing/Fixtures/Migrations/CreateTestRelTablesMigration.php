<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelTablesMigration extends BaseMigration
{
    public function up(): void
    {
        if (!Schema::hasTable('test_rel_users')) {
            Schema::create('test_rel_users', function (SchemaBuilder $table) {
                $table->id();
                $table->integer('country_id')->nullable();
                $table->string('name');
                $table->string('email');
                $table->dateTimestamps();
            });
        }

        if (!Schema::hasTable('test_rel_profiles')) {
            Schema::create('test_rel_profiles', function (SchemaBuilder $table) {
                $table->id();
                $table->integer('user_id');
                $table->string('bio');
                $table->string('avatar')->nullable();
                $table->dateTimestamps();
            });
        }

        if (!Schema::hasTable('test_rel_posts')) {
            Schema::create('test_rel_posts', function (SchemaBuilder $table) {
                $table->id();
                $table->integer('user_id');
                $table->string('title');
                $table->text('content');
                $table->dateTimestamps();
            });
        }

        if (!Schema::hasTable('test_rel_roles')) {
            Schema::create('test_rel_roles', function (SchemaBuilder $table) {
                $table->id();
                $table->string('name');
                $table->dateTimestamps();
            });
        }

        if (!Schema::hasTable('test_rel_user_roles')) {
            Schema::create('test_rel_user_roles', function (SchemaBuilder $table) {
                $table->integer('user_id');
                $table->integer('role_id');
                $table->dateTimestamps();
            });
        }

        if (!Schema::hasTable('test_rel_countries')) {
            Schema::create('test_rel_countries', function (SchemaBuilder $table) {
                $table->id();
                $table->string('name');
            });
        }

        if (!Schema::hasTable('test_rel_images')) {
            Schema::create('test_rel_images', function (SchemaBuilder $table) {
                $table->id();
                $table->string('url');
                $table->integer('imageable_id');
                $table->string('imageable_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_images');
        Schema::dropIfExists('test_rel_countries');
        Schema::dropIfExists('test_rel_user_roles');
        Schema::dropIfExists('test_rel_roles');
        Schema::dropIfExists('test_rel_posts');
        Schema::dropIfExists('test_rel_profiles');
        Schema::dropIfExists('test_rel_users');
    }
}
