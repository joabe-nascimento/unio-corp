<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RH completo: férias, folha, documentos, e-mail por empresa';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['rh_ferias'])) {
            $this->createRhTables();
        }
        $this->migrateFuncionarioEmailIndex($sm);
    }

    private function createRhTables(): void
    {
        $this->addSql('CREATE TABLE rh_ferias (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            funcionario_id INT NOT NULL,
            solicitante_user_id INT DEFAULT NULL,
            aprovador_user_id INT DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            data_inicio DATE NOT NULL,
            data_fim DATE NOT NULL,
            dias INT NOT NULL,
            observacoes LONGTEXT DEFAULT NULL,
            motivo_rejeicao VARCHAR(255) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            aprovado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_FERIAS_EMPRESA (empresa_id),
            INDEX IDX_FERIAS_FUNC (funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_ferias ADD CONSTRAINT FK_FERIAS_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_ferias ADD CONSTRAINT FK_FERIAS_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_ferias ADD CONSTRAINT FK_FERIAS_SOLICITANTE FOREIGN KEY (solicitante_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rh_ferias ADD CONSTRAINT FK_FERIAS_APROVADOR FOREIGN KEY (aprovador_user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE rh_folha_competencia (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            referencia VARCHAR(7) NOT NULL,
            status VARCHAR(16) NOT NULL,
            total_proventos NUMERIC(12, 2) NOT NULL,
            total_descontos NUMERIC(12, 2) NOT NULL,
            total_liquido NUMERIC(12, 2) NOT NULL,
            fechado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_FOLHA_EMPRESA_REF (empresa_id, referencia),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_folha_competencia ADD CONSTRAINT FK_FOLHA_COMP_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_folha_lancamento (
            id INT AUTO_INCREMENT NOT NULL,
            competencia_id INT NOT NULL,
            funcionario_id INT NOT NULL,
            tipo VARCHAR(16) NOT NULL,
            codigo VARCHAR(32) NOT NULL,
            descricao VARCHAR(150) NOT NULL,
            valor NUMERIC(12, 2) NOT NULL,
            INDEX IDX_LANC_COMP (competencia_id),
            INDEX IDX_LANC_FUNC (funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_folha_lancamento ADD CONSTRAINT FK_LANC_COMP FOREIGN KEY (competencia_id) REFERENCES rh_folha_competencia (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_folha_lancamento ADD CONSTRAINT FK_LANC_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_process_document (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            onboarding_id INT DEFAULT NULL,
            offboarding_id INT DEFAULT NULL,
            uploaded_by_id INT DEFAULT NULL,
            nome_original VARCHAR(255) NOT NULL,
            caminho VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) DEFAULT NULL,
            tamanho INT NOT NULL,
            categoria VARCHAR(32) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_DOC_EMPRESA (empresa_id),
            INDEX IDX_DOC_ONBOARD (onboarding_id),
            INDEX IDX_DOC_OFFBOARD (offboarding_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_process_document ADD CONSTRAINT FK_DOC_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_process_document ADD CONSTRAINT FK_DOC_ONBOARD FOREIGN KEY (onboarding_id) REFERENCES rh_onboarding_process (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_process_document ADD CONSTRAINT FK_DOC_OFFBOARD FOREIGN KEY (offboarding_id) REFERENCES rh_offboarding_process (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_process_document ADD CONSTRAINT FK_DOC_USER FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    private function migrateFuncionarioEmailIndex($sm): void
    {
        $table = $sm->introspectTable('funcionario');
        foreach ($table->getIndexes() as $index) {
            if (!$index->isUnique()) {
                continue;
            }
            $cols = $index->getColumns();
            if (\count($cols) === 1 && $cols[0] === 'email') {
                $this->addSql('DROP INDEX ' . $index->getName() . ' ON funcionario');
            }
        }
        if (!$table->hasIndex('UNIQ_FUNCIONARIO_EMPRESA_EMAIL')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_FUNCIONARIO_EMPRESA_EMAIL ON funcionario (empresa_id, email)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_FUNCIONARIO_EMPRESA_EMAIL ON funcionario');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BF670C8AE7927C74 ON funcionario (email)');

        $this->addSql('ALTER TABLE rh_process_document DROP FOREIGN KEY FK_DOC_EMPRESA');
        $this->addSql('ALTER TABLE rh_process_document DROP FOREIGN KEY FK_DOC_ONBOARD');
        $this->addSql('ALTER TABLE rh_process_document DROP FOREIGN KEY FK_DOC_OFFBOARD');
        $this->addSql('ALTER TABLE rh_process_document DROP FOREIGN KEY FK_DOC_USER');
        $this->addSql('DROP TABLE rh_process_document');

        $this->addSql('ALTER TABLE rh_folha_lancamento DROP FOREIGN KEY FK_LANC_COMP');
        $this->addSql('ALTER TABLE rh_folha_lancamento DROP FOREIGN KEY FK_LANC_FUNC');
        $this->addSql('DROP TABLE rh_folha_lancamento');

        $this->addSql('ALTER TABLE rh_folha_competencia DROP FOREIGN KEY FK_FOLHA_COMP_EMPRESA');
        $this->addSql('DROP TABLE rh_folha_competencia');

        $this->addSql('ALTER TABLE rh_ferias DROP FOREIGN KEY FK_FERIAS_EMPRESA');
        $this->addSql('ALTER TABLE rh_ferias DROP FOREIGN KEY FK_FERIAS_FUNC');
        $this->addSql('ALTER TABLE rh_ferias DROP FOREIGN KEY FK_FERIAS_SOLICITANTE');
        $this->addSql('ALTER TABLE rh_ferias DROP FOREIGN KEY FK_FERIAS_APROVADOR');
        $this->addSql('DROP TABLE rh_ferias');
    }
}
