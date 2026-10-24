<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the service catalogue and the funnel metadata a lead carries.
 *
 * Written against the DBAL Schema API rather than raw SQL so the same migration
 * runs on SQLite (development and tests) and PostgreSQL or MySQL (deployment).
 * The auto-generated version of this file emitted SQLite-only statements,
 * including a TEMPORARY TABLE rebuild that no other platform understands.
 *
 * The column and index names below must match what Doctrine derives from the
 * entity mapping, or doctrine:schema:validate will report a permanent diff.
 */
final class Version20261024164246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the service catalogue and add funnel metadata to inquiries.';
    }

    public function up(Schema $schema): void
    {
        $services = $schema->createTable('gdc_services');
        $services->addColumn('id', 'integer', ['autoincrement' => true]);
        $services->addColumn('slug', 'string', ['length' => 150]);
        $services->addColumn('title', 'string', ['length' => 255]);
        $services->addColumn('tagline', 'string', ['length' => 255]);
        $services->addColumn('description', 'text');
        $services->addColumn('pillar', 'string', ['length' => 32]);
        $services->addColumn('funnel_track', 'string', ['length' => 32]);
        $services->addColumn('duration', 'string', ['length' => 50, 'notnull' => false]);
        $services->addColumn('delivery_method', 'string', ['length' => 50, 'notnull' => false]);
        $services->addColumn('pdu_count', 'integer', ['notnull' => false]);
        $services->addColumn('price_label', 'string', ['length' => 60, 'notnull' => false]);
        $services->addColumn('price_note', 'string', ['length' => 60, 'notnull' => false]);
        $services->addColumn('minimum_participants', 'integer', ['notnull' => false]);
        $services->addColumn('cta_label', 'string', ['length' => 60]);
        // No database defaults on the boolean and order columns: the entity
        // initialises them in PHP, and a DB-level default shows up as a
        // permanent schema diff.
        $services->addColumn('featured', 'boolean');
        $services->addColumn('active', 'boolean');
        $services->addColumn('sort_order', 'integer');
        $services->addColumn('created_at', 'datetime_immutable');
        $services->addColumn('updated_at', 'datetime_immutable');
        $services->setPrimaryKey(['id']);
        $services->addUniqueIndex(['slug']);

        $inquiries = $schema->getTable('gdc_course_inquiries');
        $inquiries->addColumn('department', 'string', ['length' => 120, 'notnull' => false]);
        $inquiries->addColumn('team_size', 'string', ['length' => 20, 'notnull' => false]);
        $inquiries->addColumn('preferred_channel', 'string', ['length' => 60, 'notnull' => false]);
        $inquiries->addColumn('funnel_track', 'string', ['length' => 32, 'notnull' => false]);
        $inquiries->addColumn('service_id', 'integer', ['notnull' => false]);

        // Doctrine derives these names deterministically from the table and
        // column, so naming them explicitly keeps schema:validate green.
        $inquiries->addIndex(['service_id'], 'IDX_AD7F6F9FED5CA9E6');
        $inquiries->addForeignKeyConstraint(
            'gdc_services',
            ['service_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'FK_AD7F6F9FED5CA9E6',
        );
    }

    public function down(Schema $schema): void
    {
        $inquiries = $schema->getTable('gdc_course_inquiries');

        // Drop the constraint before the column it depends on: some platforms
        // refuse to drop a column that is still referenced.
        if ($inquiries->hasForeignKey('FK_AD7F6F9FED5CA9E6')) {
            $inquiries->removeForeignKey('FK_AD7F6F9FED5CA9E6');
        }
        if ($inquiries->hasIndex('IDX_AD7F6F9FED5CA9E6')) {
            $inquiries->dropIndex('IDX_AD7F6F9FED5CA9E6');
        }

        foreach (['service_id', 'funnel_track', 'preferred_channel', 'team_size', 'department'] as $column) {
            if ($inquiries->hasColumn($column)) {
                $inquiries->dropColumn($column);
            }
        }

        $schema->dropTable('gdc_services');
    }
}
