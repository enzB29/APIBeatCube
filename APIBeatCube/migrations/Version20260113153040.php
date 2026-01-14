<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113153040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE musique ADD uuid VARCHAR(36) NOT NULL, DROP path_to_file');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EE1D56BCD17F50A6 ON musique (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_EE1D56BCD17F50A6 ON musique');
        $this->addSql('ALTER TABLE musique ADD path_to_file VARCHAR(255) NOT NULL, DROP uuid');
    }
}
