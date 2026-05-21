<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430123213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE events (id VARCHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, sell_mode VARCHAR(20) NOT NULL, INDEX idx_starts_at (starts_at), INDEX idx_ends_at (ends_at), INDEX idx_date_range (starts_at, ends_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE zones (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, price_cents INT NOT NULL, capacity INT NOT NULL, event_id VARCHAR(36) NOT NULL, INDEX IDX_85CAB16871F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE zones ADD CONSTRAINT FK_85CAB16871F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE zones DROP FOREIGN KEY FK_85CAB16871F7E88B');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE zones');
    }
}
