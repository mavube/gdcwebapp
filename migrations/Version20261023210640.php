<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline schema for the GDC application: training catalogue, inbound leads
 * and command-centre operators.
 *
 * Written against the DBAL Schema API rather than raw SQL so the same migration
 * runs on SQLite (development and tests) and PostgreSQL or MySQL (deployment).
 */
final class Version20261023210640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create training programs, course inquiries and admin users.";
    }

    public function up(Schema $schema): void
    {
        $programs = $schema->createTable("gdc_training_programs");
        $programs->addColumn("id", "integer", ["autoincrement" => true]);
        $programs->addColumn("slug", "string", ["length" => 150]);
        $programs->addColumn("title", "string", ["length" => 255]);
        $programs->addColumn("tagline", "string", ["length" => 255]);
        $programs->addColumn("description", "text");
        $programs->addColumn("duration", "string", ["length" => 50]);
        $programs->addColumn("delivery_method", "string", ["length" => 50]);
        $programs->addColumn("pdu_count", "integer");
        $programs->addColumn("created_at", "datetime_immutable");
        $programs->setPrimaryKey(["id"]);
        $programs->addUniqueIndex(["slug"]);

        $inquiries = $schema->createTable("gdc_course_inquiries");
        $inquiries->addColumn("id", "integer", ["autoincrement" => true]);
        $inquiries->addColumn("full_name", "string", ["length" => 100]);
        $inquiries->addColumn("company_name", "string", ["length" => 150, "notnull" => false]);
        $inquiries->addColumn("email", "string", ["length" => 180]);
        $inquiries->addColumn("phone", "string", ["length" => 30]);
        $inquiries->addColumn("organization_scope", "string", ["length" => 150, "notnull" => false]);
        $inquiries->addColumn("intent_tier", "string", ["length" => 32, "notnull" => false]);
        $inquiries->addColumn("inquiry_type", "string", ["length" => 20, "notnull" => false]);
        // No database default here: the entity initialises status in PHP, and a
        // DB-level default would show up as a permanent schema diff.
        $inquiries->addColumn("status", "string", ["length" => 32]);
        $inquiries->addColumn("estimated_value_usd", "integer", ["notnull" => false]);
        $inquiries->addColumn("message", "text", ["notnull" => false]);
        $inquiries->addColumn("preferred_date", "datetime", ["notnull" => false]);
        $inquiries->addColumn("created_at", "datetime_immutable");
        $inquiries->addColumn("training_program_id", "integer", ["notnull" => false]);
        $inquiries->setPrimaryKey(["id"]);
        // Doctrine derives these names deterministically from the table and
        // column, so naming them explicitly keeps schema:validate green.
        $inquiries->addIndex(["training_program_id"], "IDX_AD7F6F9F8406BD6C");
        $inquiries->addForeignKeyConstraint(
            "gdc_training_programs",
            ["training_program_id"],
            ["id"],
            ["onDelete" => "SET NULL"],
            "FK_AD7F6F9F8406BD6C",
        );

        $admins = $schema->createTable("gdc_admin_users");
        $admins->addColumn("id", "integer", ["autoincrement" => true]);
        $admins->addColumn("email", "string", ["length" => 180]);
        $admins->addColumn("roles", "json");
        $admins->addColumn("password", "string", ["length" => 255]);
        $admins->addColumn("display_name", "string", ["length" => 100]);
        $admins->addColumn("created_at", "datetime_immutable");
        $admins->setPrimaryKey(["id"]);
        $admins->addUniqueIndex(["email"], "uniq_admin_email");
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable("gdc_admin_users");
        $schema->dropTable("gdc_course_inquiries");
        $schema->dropTable("gdc_training_programs");
    }
}
