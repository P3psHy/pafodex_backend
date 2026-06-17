<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ownership metadata to the card_library relation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_library ADD number_card INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE card_library ADD is_favorite BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_library DROP number_card');
        $this->addSql('ALTER TABLE card_library DROP is_favorite');
    }
}
