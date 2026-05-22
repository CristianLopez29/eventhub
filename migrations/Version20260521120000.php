<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for JWT authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            username VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            roles JSON NOT NULL,
            UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
