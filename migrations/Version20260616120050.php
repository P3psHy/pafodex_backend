<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616120050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE set_card (set_id INT NOT NULL, card_id BIGINT NOT NULL, PRIMARY KEY (set_id, card_id))');
        $this->addSql('CREATE INDEX IDX_83D37A5A10FB0D18 ON set_card (set_id)');
        $this->addSql('CREATE INDEX IDX_83D37A5A4ACC9A20 ON set_card (card_id)');
        $this->addSql('ALTER TABLE set_card ADD CONSTRAINT FK_83D37A5A10FB0D18 FOREIGN KEY (set_id) REFERENCES set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE set_card ADD CONSTRAINT FK_83D37A5A4ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE set_card DROP CONSTRAINT FK_83D37A5A10FB0D18');
        $this->addSql('ALTER TABLE set_card DROP CONSTRAINT FK_83D37A5A4ACC9A20');
        $this->addSql('DROP TABLE set_card');
    }
}
