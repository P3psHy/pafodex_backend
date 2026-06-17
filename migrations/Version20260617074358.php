<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617074358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE card ADD number VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE card ADD image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE card DROP nom');
        $this->addSql('ALTER TABLE card DROP numero');
        $this->addSql('ALTER TABLE set ALTER color DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ADD nom VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE card ADD numero VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE card DROP name');
        $this->addSql('ALTER TABLE card DROP number');
        $this->addSql('ALTER TABLE card DROP image');
        $this->addSql('ALTER TABLE set ALTER color SET DEFAULT \'#FFFFFF\'');
    }
}
