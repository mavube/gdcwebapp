<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds editable content blocks, so page copy lives in the database rather than
 * in Twig templates.
 *
 * Written against the DBAL Schema API rather than raw SQL so the same migration
 * runs on SQLite (development and tests) and PostgreSQL or MySQL (deployment).
 */
final class Version20261024164454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create editable content blocks for backend-managed page copy.';
    }

    public function up(Schema $schema): void
    {
        $blocks = $schema->createTable('gdc_content_blocks');
        $blocks->addColumn('id', 'integer', ['autoincrement' => true]);
        // Named block_key rather than key: "key" is a reserved word in MySQL,
        // where it would need quoting in every generated statement.
        $blocks->addColumn('block_key', 'string', ['length' => 120]);
        $blocks->addColumn('label', 'string', ['length' => 150]);
        $blocks->addColumn('heading', 'string', ['length' => 255, 'notnull' => false]);
        $blocks->addColumn('eyebrow', 'string', ['length' => 120, 'notnull' => false]);
        $blocks->addColumn('body', 'text', ['notnull' => false]);
        $blocks->addColumn('cta_label', 'string', ['length' => 60, 'notnull' => false]);
        $blocks->addColumn('cta_target', 'string', ['length' => 120, 'notnull' => false]);
        $blocks->addColumn('active', 'boolean');
        $blocks->addColumn('sort_order', 'integer');
        $blocks->addColumn('updated_at', 'datetime_immutable');
        $blocks->setPrimaryKey(['id']);
        $blocks->addUniqueIndex(['block_key']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('gdc_content_blocks');
    }
}
