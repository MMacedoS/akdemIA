<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCE_REL_INDEX = 'ex_rel_src_type_idx';
    private const TARGET_REL_INDEX = 'ex_rel_tgt_type_idx';

    public function up(): void
    {
        if (! Schema::hasTable('exercise_relationships')) {
            Schema::create('exercise_relationships', function (Blueprint $table) {
                $table->id();
                $table->string('source_exercise_id');
                $table->string('target_exercise_id');
                $table->string('relationship_type');
                $table->decimal('score', 5, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['source_exercise_id', 'relationship_type'], self::SOURCE_REL_INDEX);
                $table->index(['target_exercise_id', 'relationship_type'], self::TARGET_REL_INDEX);
            });

            return;
        }

        Schema::table('exercise_relationships', function (Blueprint $table) {
            if (! Schema::hasColumn('exercise_relationships', 'source_exercise_id')) {
                $table->string('source_exercise_id');
            }

            if (! Schema::hasColumn('exercise_relationships', 'target_exercise_id')) {
                $table->string('target_exercise_id');
            }

            if (! Schema::hasColumn('exercise_relationships', 'relationship_type')) {
                $table->string('relationship_type');
            }

            if (! Schema::hasColumn('exercise_relationships', 'score')) {
                $table->decimal('score', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('exercise_relationships', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('exercise_relationships', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('exercise_relationships', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->ensureIndex('exercise_relationships', self::SOURCE_REL_INDEX, function () {
            Schema::table('exercise_relationships', function (Blueprint $table) {
                $table->index(['source_exercise_id', 'relationship_type'], self::SOURCE_REL_INDEX);
            });
        });

        $this->ensureIndex('exercise_relationships', self::TARGET_REL_INDEX, function () {
            Schema::table('exercise_relationships', function (Blueprint $table) {
                $table->index(['target_exercise_id', 'relationship_type'], self::TARGET_REL_INDEX);
            });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_relationships');
    }

    private function ensureIndex(string $table, string $indexName, callable $creator): void
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $indexes = $connection->select(
            'select index_name from information_schema.statistics where table_schema = ? and table_name = ?',
            [$databaseName, $table],
        );

        foreach ($indexes as $existingIndex) {
            if (($existingIndex->index_name ?? null) === $indexName) {
                return;
            }
        }

        $creator();
    }
};
