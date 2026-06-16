<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616132009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // add the color column with a default value and backfill existing rows
        $this->addSql("ALTER TABLE \"set\" ADD color VARCHAR(7) DEFAULT '#FFFFFF'");
        $this->addSql("UPDATE \"set\" SET color = '#FFFFFF' WHERE color IS NULL");
        $this->addSql('ALTER TABLE "set" ALTER COLUMN color SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "set" DROP color');
    }
}
