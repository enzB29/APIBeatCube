<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113122952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE utilisateur_musique');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE musique');
        $this->addSql('CREATE TABLE musique (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, singer VARCHAR(255) NOT NULL, year INT NOT NULL, path_to_file VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE utilisateur_musique (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, date_ajout DATETIME NOT NULL, utilisateur_id INT NOT NULL, musique_id INT NOT NULL, INDEX IDX_9416A12EFB88E14F (utilisateur_id), INDEX IDX_9416A12E25E254A1 (musique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE utilisateur_musique ADD CONSTRAINT FK_9416A12EFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE utilisateur_musique ADD CONSTRAINT FK_9416A12E25E254A1 FOREIGN KEY (musique_id) REFERENCES musique (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur_musique DROP FOREIGN KEY FK_9416A12EFB88E14F');
        $this->addSql('ALTER TABLE utilisateur_musique DROP FOREIGN KEY FK_9416A12E25E254A1');
        $this->addSql('DROP TABLE musique');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE utilisateur_musique');
    }
}
