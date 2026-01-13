<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113092759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE utilisateur_musique (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, date_ajout DATETIME NOT NULL, utilisateur_id INT NOT NULL, musique_id INT NOT NULL, INDEX IDX_9416A12EFB88E14F (utilisateur_id), INDEX IDX_9416A12E25E254A1 (musique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`;');
        $this->addSql('ALTER TABLE utilisateur_musique ADD CONSTRAINT FK_9416A12EFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id);');
        $this->addSql('ALTER TABLE utilisateur_musique ADD CONSTRAINT FK_9416A12E25E254A1 FOREIGN KEY (musique_id) REFERENCES musique (id);');
        $this->addSql('ALTER TABLE musique ADD path_to_file VARCHAR(255) NOT NULL;');
        $this->addSql('ALTER TABLE utilisateur CHANGE roles roles JSON NOT NULL;');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
