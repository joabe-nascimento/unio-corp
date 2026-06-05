<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530330000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add resolucao/resolvidoEm/resolvidoPor to integ_schema_drift; add traceId to integ_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_schema_drift ADD resolucao VARCHAR(32) DEFAULT NULL, ADD resolvido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD resolvido_por VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE integ_log ADD trace_id VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_schema_drift DROP resolucao, DROP resolvido_em, DROP resolvido_por');
        $this->addSql('ALTER TABLE integ_log DROP trace_id');
    }
}
