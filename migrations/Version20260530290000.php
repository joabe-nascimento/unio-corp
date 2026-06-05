<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530290000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CSAT, playbook_steps, integ_conector_id to ti_chamado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ti_chamado 
            ADD csat_score SMALLINT DEFAULT NULL,
            ADD csat_comentario LONGTEXT DEFAULT NULL,
            ADD csat_em DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            ADD integ_conector_id VARCHAR(64) DEFAULT NULL,
            ADD playbook_steps JSON NOT NULL DEFAULT (JSON_ARRAY())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_chamado DROP csat_score, DROP csat_comentario, DROP csat_em, DROP integ_conector_id, DROP playbook_steps');
    }
}
