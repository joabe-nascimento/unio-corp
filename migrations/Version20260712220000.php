<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Histórico de glosas (JSON) em clinic_guia_tiss';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD historico_glosas JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP historico_glosas');
    }
}
