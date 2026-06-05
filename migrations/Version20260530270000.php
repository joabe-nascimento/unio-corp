<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530270000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub Integrações — Observatório Causal (traces de malha de fluxos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE integ_causal_trace (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, flow_key VARCHAR(64) NOT NULL, titulo VARCHAR(160) NOT NULL, status VARCHAR(16) NOT NULL, confiabilidade NUMERIC(5, 2) NOT NULL, impacto JSON NOT NULL, nos JSON NOT NULL, ultimo_evento_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_TRACE_EMPRESA (empresa_id), INDEX IDX_INTEG_TRACE_FLOW (flow_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE integ_causal_trace ADD CONSTRAINT FK_INTEG_TRACE_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_causal_trace DROP FOREIGN KEY FK_INTEG_TRACE_EMPRESA');
        $this->addSql('DROP TABLE integ_causal_trace');
    }
}
