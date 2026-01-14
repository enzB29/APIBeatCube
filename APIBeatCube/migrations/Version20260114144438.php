<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114144438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE utilisateur_ban (id INT AUTO_INCREMENT NOT NULL, banned_at DATETIME NOT NULL, banned_until DATETIME DEFAULT NULL, reason VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, user_id INT DEFAULT NULL, banned_by_id INT DEFAULT NULL, INDEX IDX_EFB26C96A76ED395 (user_id), INDEX IDX_EFB26C96386B8E7 (banned_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE utilisateur_ban ADD CONSTRAINT FK_EFB26C96A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur_ban ADD CONSTRAINT FK_EFB26C96386B8E7 FOREIGN KEY (banned_by_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur_ban DROP FOREIGN KEY FK_EFB26C96A76ED395');
        $this->addSql('ALTER TABLE utilisateur_ban DROP FOREIGN KEY FK_EFB26C96386B8E7');
        $this->addSql('DROP TABLE utilisateur_ban');
    }
}
