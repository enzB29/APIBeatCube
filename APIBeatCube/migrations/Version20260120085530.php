<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120085530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE success (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE utilisateur_success (id INT AUTO_INCREMENT NOT NULL, obtained_at DATETIME NOT NULL, utilisateur_id INT DEFAULT NULL, success_id INT DEFAULT NULL, INDEX IDX_150B2820FB88E14F (utilisateur_id), INDEX IDX_150B2820A63B36F1 (success_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE utilisateur_success ADD CONSTRAINT FK_150B2820FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur_success ADD CONSTRAINT FK_150B2820A63B36F1 FOREIGN KEY (success_id) REFERENCES success (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur_success DROP FOREIGN KEY FK_150B2820FB88E14F');
        $this->addSql('ALTER TABLE utilisateur_success DROP FOREIGN KEY FK_150B2820A63B36F1');
        $this->addSql('DROP TABLE success');
        $this->addSql('DROP TABLE utilisateur_success');
    }
}
