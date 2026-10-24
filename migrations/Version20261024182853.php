<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the repeatable content rows and the two-tone heading fields.
 *
 * Written against the DBAL Schema API rather than raw SQL so the same migration
 * runs on SQLite (development and tests) and PostgreSQL or MySQL (deployment).
 * The auto-generated version of this file emitted SQLite-only statements,
 * including a TEMPORARY TABLE rebuild in down() that no other platform
 * understands.
 *
 * The column and index names below must match what Doctrine derives from the
 * entity mapping, or doctrine:schema:validate will report a permanent diff.
 */
final class Version20261024182853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add repeatable content rows and two-tone heading fields.';
    }

    public function up(Schema $schema): void
    {
        $items = $schema->createTable('gdc_content_items');
        $items->addColumn('id', 'integer', ['autoincrement' => true]);
        $items->addColumn('section', 'string', ['length' => 60]);
        $items->addColumn('title', 'string', ['length' => 255, 'notnull' => false]);
        $items->addColumn('subtitle', 'string', ['length' => 255, 'notnull' => false]);
        $items->addColumn('body', 'text', ['notnull' => false]);
        $items->addColumn('image', 'string', ['length' => 255, 'notnull' => false]);
        $items->addColumn('image_alt', 'string', ['length' => 255, 'notnull' => false]);
        $items->addColumn('link_label', 'string', ['length' => 60, 'notnull' => false]);
        $items->addColumn('link_target', 'string', ['length' => 255, 'notnull' => false]);
        // No database defaults on the boolean and order columns: the entity
        // initialises them in PHP, and a DB-level default shows up as a
        // permanent schema diff.
        $items->addColumn('active', 'boolean');
        $items->addColumn('sort_order', 'integer');
        $items->addColumn('updated_at', 'datetime_immutable');
        $items->setPrimaryKey(['id']);
        $items->addIndex(['section', 'sort_order'], 'idx_content_items_section_order');

        $blocks = $schema->getTable('gdc_content_blocks');
        $blocks->addColumn('heading_accent', 'string', ['length' => 255, 'notnull' => false]);
        $blocks->addColumn('secondary_cta_label', 'string', ['length' => 60, 'notnull' => false]);
        $blocks->addColumn('secondary_cta_target', 'string', ['length' => 120, 'notnull' => false]);
        $blocks->addColumn('image', 'string', ['length' => 255, 'notnull' => false]);
        $blocks->addColumn('image_alt', 'string', ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $blocks = $schema->getTable('gdc_content_blocks');

        // Drop in reverse order of creation, and only if present, so the
        // migration is re-runnable against a partially applied schema.
        foreach (['image_alt', 'image', 'secondary_cta_target', 'secondary_cta_label', 'heading_accent'] as $column) {
            if ($blocks->hasColumn($column)) {
                $blocks->dropColumn($column);
            }
        }

        $schema->dropTable('gdc_content_items');
    }
}
