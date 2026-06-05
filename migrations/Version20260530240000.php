<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub TI — flags Helia aplicado/revisado em ti_chamado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_chamado ADD helia_aplicado TINYINT(1) NOT NULL DEFAULT 0, ADD helia_revisado TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_chamado DROP helia_aplicado, DROP helia_revisado');
    }
}
