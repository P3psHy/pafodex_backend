<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add API token expiration date to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD api_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE \"user\" SET api_token_expires_at = CURRENT_TIMESTAMP + INTERVAL '1 hour' WHERE api_token IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP api_token_expires_at');
    }
}
