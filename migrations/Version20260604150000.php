<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recrutamento ATS: origem, notas, vaga enriquecida (tipo contrato, local, requisitos)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('rh_vaga', 'tipo_contrato')) {
            $this->addSql('ALTER TABLE rh_vaga ADD tipo_contrato VARCHAR(24) DEFAULT NULL');
        }
        if (!$this->columnExists('rh_vaga', 'local_trabalho')) {
            $this->addSql('ALTER TABLE rh_vaga ADD local_trabalho VARCHAR(120) DEFAULT NULL');
        }
        if (!$this->columnExists('rh_vaga', 'requisitos')) {
            $this->addSql('ALTER TABLE rh_vaga ADD requisitos LONGTEXT DEFAULT NULL');
        }
        if (!$this->columnExists('rh_vaga', 'vagas_quantidade')) {
            $this->addSql('ALTER TABLE rh_vaga ADD vagas_quantidade INT NOT NULL DEFAULT 1');
        }

        if (!$this->columnExists('rh_candidato', 'origem')) {
            $this->addSql("ALTER TABLE rh_candidato ADD origem VARCHAR(32) NOT NULL DEFAULT 'MANUAL'");
        }
        if (!$this->columnExists('rh_candidato', 'observacoes')) {
            $this->addSql('ALTER TABLE rh_candidato ADD observacoes LONGTEXT DEFAULT NULL');
        }
        if (!$this->columnExists('rh_candidato', 'motivo_reprovacao')) {
            $this->addSql('ALTER TABLE rh_candidato ADD motivo_reprovacao LONGTEXT DEFAULT NULL');
        }
        if (!$this->columnExists('rh_candidato', 'avaliacao')) {
            $this->addSql('ALTER TABLE rh_candidato ADD avaliacao SMALLINT DEFAULT NULL');
        }
        if (!$this->columnExists('rh_candidato', 'linkedin')) {
            $this->addSql('ALTER TABLE rh_candidato ADD linkedin VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->columnExists('rh_candidato', 'linkedin')) {
            $this->addSql('ALTER TABLE rh_candidato DROP linkedin');
        }
        if ($this->columnExists('rh_candidato', 'avaliacao')) {
            $this->addSql('ALTER TABLE rh_candidato DROP avaliacao');
        }
        if ($this->columnExists('rh_candidato', 'motivo_reprovacao')) {
            $this->addSql('ALTER TABLE rh_candidato DROP motivo_reprovacao');
        }
        if ($this->columnExists('rh_candidato', 'observacoes')) {
            $this->addSql('ALTER TABLE rh_candidato DROP observacoes');
        }
        if ($this->columnExists('rh_candidato', 'origem')) {
            $this->addSql('ALTER TABLE rh_candidato DROP origem');
        }
        if ($this->columnExists('rh_vaga', 'vagas_quantidade')) {
            $this->addSql('ALTER TABLE rh_vaga DROP vagas_quantidade');
        }
        if ($this->columnExists('rh_vaga', 'requisitos')) {
            $this->addSql('ALTER TABLE rh_vaga DROP requisitos');
        }
        if ($this->columnExists('rh_vaga', 'local_trabalho')) {
            $this->addSql('ALTER TABLE rh_vaga DROP local_trabalho');
        }
        if ($this->columnExists('rh_vaga', 'tipo_contrato')) {
            $this->addSql('ALTER TABLE rh_vaga DROP tipo_contrato');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist([$table])) {
            return false;
        }

        return isset($sm->listTableColumns($table)[$column]);
    }
}
