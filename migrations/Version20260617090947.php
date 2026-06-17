<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617090947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_library (card_id BIGINT NOT NULL, library_id INT NOT NULL, PRIMARY KEY (card_id, library_id))');
        $this->addSql('CREATE INDEX IDX_F69A12CA4ACC9A20 ON card_library (card_id)');
        $this->addSql('CREATE INDEX IDX_F69A12CAFE2541D7 ON card_library (library_id)');
        $this->addSql('ALTER TABLE card_library ADD CONSTRAINT FK_F69A12CA4ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_library ADD CONSTRAINT FK_F69A12CAFE2541D7 FOREIGN KEY (library_id) REFERENCES library (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_library DROP CONSTRAINT FK_F69A12CA4ACC9A20');
        $this->addSql('ALTER TABLE card_library DROP CONSTRAINT FK_F69A12CAFE2541D7');
        $this->addSql('DROP TABLE card_library');
    }
}
