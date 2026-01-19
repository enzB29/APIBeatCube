<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260115101122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE upload (id INT AUTO_INCREMENT NOT NULL, upload_at DATETIME NOT NULL, utilisateur_id INT NOT NULL, musique_id INT DEFAULT NULL, INDEX IDX_17BDE61FFB88E14F (utilisateur_id), INDEX IDX_17BDE61F25E254A1 (musique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE upload ADD CONSTRAINT FK_17BDE61FFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE upload ADD CONSTRAINT FK_17BDE61F25E254A1 FOREIGN KEY (musique_id) REFERENCES musique (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE upload DROP FOREIGN KEY FK_17BDE61FFB88E14F');
        $this->addSql('ALTER TABLE upload DROP FOREIGN KEY FK_17BDE61F25E254A1');
        $this->addSql('DROP TABLE upload');
    }
}
