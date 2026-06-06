<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recrutamento roadmap: carreiras públicas, CV, e-mail, entrevista, talentos, scorecards, aprovações, integrações';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('empresa', 'slug')) {
            $this->addSql('ALTER TABLE empresa ADD slug VARCHAR(80) DEFAULT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_EMPRESA_SLUG ON empresa (slug)');
        }
        if (!$this->columnExists('empresa', 'carreiras_ativo')) {
            $this->addSql('ALTER TABLE empresa ADD carreiras_ativo TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$this->columnExists('empresa', 'carreiras_titulo')) {
            $this->addSql('ALTER TABLE empresa ADD carreiras_titulo VARCHAR(180) DEFAULT NULL');
        }
        if (!$this->columnExists('empresa', 'carreiras_descricao')) {
            $this->addSql('ALTER TABLE empresa ADD carreiras_descricao LONGTEXT DEFAULT NULL');
        }
        if (!$this->columnExists('empresa', 'recruitment_integracoes')) {
            $this->addSql('ALTER TABLE empresa ADD recruitment_integracoes JSON DEFAULT NULL');
        }

        if (!$this->columnExists('rh_vaga', 'slug')) {
            $this->addSql('ALTER TABLE rh_vaga ADD slug VARCHAR(120) DEFAULT NULL');
        }
        if (!$this->columnExists('rh_vaga', 'publicada_em')) {
            $this->addSql('ALTER TABLE rh_vaga ADD publicada_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        if (!$this->columnExists('rh_vaga', 'recrutador_id')) {
            $this->addSql('ALTER TABLE rh_vaga ADD recrutador_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE rh_vaga ADD CONSTRAINT FK_RH_VAGA_RECRUTADOR FOREIGN KEY (recrutador_id) REFERENCES `user` (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_RH_VAGA_RECRUTADOR ON rh_vaga (recrutador_id)');
        }

        if (!$this->columnExists('rh_candidato', 'entrevista_em')) {
            $this->addSql('ALTER TABLE rh_candidato ADD entrevista_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        if (!$this->columnExists('rh_candidato', 'entrevista_link')) {
            $this->addSql('ALTER TABLE rh_candidato ADD entrevista_link VARCHAR(500) DEFAULT NULL');
        }
        if (!$this->columnExists('rh_candidato', 'scorecards')) {
            $this->addSql('ALTER TABLE rh_candidato ADD scorecards JSON DEFAULT NULL');
        }
        if (!$this->columnExists('rh_candidato', 'recrutador_id')) {
            $this->addSql('ALTER TABLE rh_candidato ADD recrutador_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE rh_candidato ADD CONSTRAINT FK_RH_CAND_RECRUTADOR FOREIGN KEY (recrutador_id) REFERENCES `user` (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_RH_CAND_RECRUTADOR ON rh_candidato (recrutador_id)');
        }
        if (!$this->columnExists('rh_candidato', 'no_banco_talentos')) {
            $this->addSql('ALTER TABLE rh_candidato ADD no_banco_talentos TINYINT(1) NOT NULL DEFAULT 0');
        }

        if (!$this->tableExists('rh_candidato_anexo')) {
            $this->addSql('CREATE TABLE rh_candidato_anexo (
                id INT AUTO_INCREMENT NOT NULL,
                candidato_id INT NOT NULL,
                empresa_id INT NOT NULL,
                nome_original VARCHAR(255) NOT NULL,
                caminho VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                tamanho INT NOT NULL,
                criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_RH_CAND_ANEXO_CAND (candidato_id),
                INDEX IDX_RH_CAND_ANEXO_EMP (empresa_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE rh_candidato_anexo ADD CONSTRAINT FK_RH_CAND_ANEXO_CAND FOREIGN KEY (candidato_id) REFERENCES rh_candidato (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE rh_candidato_anexo ADD CONSTRAINT FK_RH_CAND_ANEXO_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        }

        if (!$this->tableExists('rh_talento_pool')) {
            $this->addSql('CREATE TABLE rh_talento_pool (
                id INT AUTO_INCREMENT NOT NULL,
                empresa_id INT NOT NULL,
                email VARCHAR(180) NOT NULL,
                nome VARCHAR(150) NOT NULL,
                telefone VARCHAR(24) DEFAULT NULL,
                linkedin VARCHAR(255) DEFAULT NULL,
                tags JSON DEFAULT NULL,
                observacoes LONGTEXT DEFAULT NULL,
                criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_TALENTO_EMP_EMAIL (empresa_id, email),
                INDEX IDX_TALENTO_EMP (empresa_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE rh_talento_pool ADD CONSTRAINT FK_TALENTO_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        }

        if (!$this->tableExists('rh_candidato_aprovacao')) {
            $this->addSql('CREATE TABLE rh_candidato_aprovacao (
                id INT AUTO_INCREMENT NOT NULL,
                candidato_id INT NOT NULL,
                solicitante_id INT NOT NULL,
                aprovador_id INT DEFAULT NULL,
                etapa_destino VARCHAR(32) NOT NULL,
                status VARCHAR(24) NOT NULL,
                comentario LONGTEXT DEFAULT NULL,
                criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                decidido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_CAND_APROV_CAND (candidato_id),
                INDEX IDX_CAND_APROV_SOL (solicitante_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE rh_candidato_aprovacao ADD CONSTRAINT FK_CAND_APROV_CAND FOREIGN KEY (candidato_id) REFERENCES rh_candidato (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE rh_candidato_aprovacao ADD CONSTRAINT FK_CAND_APROV_SOL FOREIGN KEY (solicitante_id) REFERENCES `user` (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE rh_candidato_aprovacao ADD CONSTRAINT FK_CAND_APROV_APR FOREIGN KEY (aprovador_id) REFERENCES `user` (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS rh_candidato_aprovacao');
        $this->addSql('DROP TABLE IF EXISTS rh_talento_pool');
        $this->addSql('DROP TABLE IF EXISTS rh_candidato_anexo');
        // column drops omitted for brevity in down
    }

    private function columnExists(string $table, string $column): bool
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist([$table])) {
            return false;
        }

        return isset($sm->listTableColumns($table)[$column]);
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }
}
