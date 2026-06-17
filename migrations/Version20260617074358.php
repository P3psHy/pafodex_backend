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
        // les colonnes name/number/image sont déjà créées par Version20260616100038
        // il ne reste plus que la suppression du défaut sur set.color à appliquer
        $this->addSql('ALTER TABLE set ALTER color DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE set ALTER color SET DEFAULT \'#FFFFFF\'');
    }
}