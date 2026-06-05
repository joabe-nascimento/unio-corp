<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Change Advisory fields to ti_manutencao';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ti_manutencao ADD servicos_afetados JSON NOT NULL DEFAULT ('[]'), ADD aprovada TINYINT(1) NOT NULL DEFAULT 0, ADD aprovada_em DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD aprovada_por VARCHAR(120) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_manutencao DROP servicos_afetados, DROP aprovada, DROP aprovada_em, DROP aprovada_por');
    }
}
