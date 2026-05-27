<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RH roadmap: portal, ATS, ponto, eSocial, assinatura, comunicação, organograma, provisões, folha legal, auditoria, workflows';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['funcionario']) && !$this->columnExists('funcionario', 'gestor_id')) {
            $this->addSql('ALTER TABLE funcionario ADD gestor_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE funcionario ADD CONSTRAINT FK_FUNC_GESTOR FOREIGN KEY (gestor_id) REFERENCES funcionario (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_FUNC_GESTOR ON funcionario (gestor_id)');
        }

        if ($sm->tablesExist(['rh_audit_log'])) {
            return;
        }

        $this->addSql('CREATE TABLE rh_audit_log (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            modulo VARCHAR(48) NOT NULL,
            acao VARCHAR(64) NOT NULL,
            entidade VARCHAR(64) DEFAULT NULL,
            entidade_id INT DEFAULT NULL,
            payload JSON DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_RH_AUDIT_EMPRESA (empresa_id),
            INDEX IDX_RH_AUDIT_MODULO (modulo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_audit_log ADD CONSTRAINT FK_RH_AUDIT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_audit_log ADD CONSTRAINT FK_RH_AUDIT_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE rh_workflow_template (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            codigo VARCHAR(48) NOT NULL,
            nome VARCHAR(120) NOT NULL,
            tipo_processo VARCHAR(32) NOT NULL,
            checklist JSON NOT NULL,
            ativo TINYINT(1) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_RH_WF_EMP_COD (empresa_id, codigo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_workflow_template ADD CONSTRAINT FK_RH_WF_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_comunicado (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            autor_user_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            corpo LONGTEXT NOT NULL,
            publicado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ativo TINYINT(1) NOT NULL,
            INDEX IDX_RH_COM_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_comunicado ADD CONSTRAINT FK_RH_COM_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_comunicado ADD CONSTRAINT FK_RH_COM_AUTOR FOREIGN KEY (autor_user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE rh_comunicado_leitura (
            id INT AUTO_INCREMENT NOT NULL,
            comunicado_id INT NOT NULL,
            funcionario_id INT NOT NULL,
            lido_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_RH_COM_LEITURA (comunicado_id, funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_comunicado_leitura ADD CONSTRAINT FK_RH_COM_LEITURA_COM FOREIGN KEY (comunicado_id) REFERENCES rh_comunicado (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_comunicado_leitura ADD CONSTRAINT FK_RH_COM_LEITURA_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_email_event (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            destinatario VARCHAR(180) NOT NULL,
            assunto VARCHAR(200) NOT NULL,
            template VARCHAR(64) NOT NULL,
            status VARCHAR(24) NOT NULL,
            payload JSON DEFAULT NULL,
            enviado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_RH_EMAIL_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_email_event ADD CONSTRAINT FK_RH_EMAIL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_vaga (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            titulo VARCHAR(150) NOT NULL,
            departamento VARCHAR(100) DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            descricao LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_RH_VAGA_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_vaga ADD CONSTRAINT FK_RH_VAGA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_candidato (
            id INT AUTO_INCREMENT NOT NULL,
            vaga_id INT NOT NULL,
            nome VARCHAR(150) NOT NULL,
            email VARCHAR(180) NOT NULL,
            telefone VARCHAR(24) DEFAULT NULL,
            etapa VARCHAR(32) NOT NULL,
            onboarding_process_id INT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_RH_CAND_VAGA (vaga_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_candidato ADD CONSTRAINT FK_RH_CAND_VAGA FOREIGN KEY (vaga_id) REFERENCES rh_vaga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_candidato ADD CONSTRAINT FK_RH_CAND_ONB FOREIGN KEY (onboarding_process_id) REFERENCES rh_onboarding_process (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE rh_ponto_registro (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            funcionario_id INT NOT NULL,
            tipo VARCHAR(16) NOT NULL,
            registrado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            origem VARCHAR(24) NOT NULL,
            observacao VARCHAR(255) DEFAULT NULL,
            INDEX IDX_RH_PONTO_EMPRESA (empresa_id),
            INDEX IDX_RH_PONTO_FUNC (funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_ponto_registro ADD CONSTRAINT FK_RH_PONTO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_ponto_registro ADD CONSTRAINT FK_RH_PONTO_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_esocial_lote (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            referencia VARCHAR(7) NOT NULL,
            tipo_evento VARCHAR(16) NOT NULL,
            status VARCHAR(24) NOT NULL,
            protocolo VARCHAR(64) DEFAULT NULL,
            payload JSON DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            enviado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_RH_ESOCIAL_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_esocial_lote ADD CONSTRAINT FK_RH_ESOCIAL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_assinatura_envelope (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            status VARCHAR(24) NOT NULL,
            documento_path VARCHAR(255) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            concluido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_RH_ASS_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_assinatura_envelope ADD CONSTRAINT FK_RH_ASS_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_provisao (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            referencia VARCHAR(7) NOT NULL,
            tipo VARCHAR(32) NOT NULL,
            valor NUMERIC(14, 2) NOT NULL,
            status VARCHAR(16) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_RH_PROV_EMP_REF_TIPO (empresa_id, referencia, tipo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_provisao ADD CONSTRAINT FK_RH_PROV_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_folha_rubrica (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            codigo VARCHAR(16) NOT NULL,
            descricao VARCHAR(120) NOT NULL,
            tipo VARCHAR(16) NOT NULL,
            incide_inss TINYINT(1) NOT NULL,
            incide_irrf TINYINT(1) NOT NULL,
            incide_fgts TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_RH_RUB_EMP_COD (empresa_id, codigo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_folha_rubrica ADD CONSTRAINT FK_RH_RUB_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE rh_folha_holerite (
            id INT AUTO_INCREMENT NOT NULL,
            competencia_id INT NOT NULL,
            funcionario_id INT NOT NULL,
            salario_bruto NUMERIC(12, 2) NOT NULL,
            inss NUMERIC(12, 2) NOT NULL,
            irrf NUMERIC(12, 2) NOT NULL,
            fgts NUMERIC(12, 2) NOT NULL,
            liquido NUMERIC(12, 2) NOT NULL,
            pdf_path VARCHAR(255) DEFAULT NULL,
            gerado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_RH_HOL_COMP_FUNC (competencia_id, funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_folha_holerite ADD CONSTRAINT FK_RH_HOL_COMP FOREIGN KEY (competencia_id) REFERENCES rh_folha_competencia (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_folha_holerite ADD CONSTRAINT FK_RH_HOL_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS rh_folha_holerite');
        $this->addSql('DROP TABLE IF EXISTS rh_folha_rubrica');
        $this->addSql('DROP TABLE IF EXISTS rh_provisao');
        $this->addSql('DROP TABLE IF EXISTS rh_assinatura_envelope');
        $this->addSql('DROP TABLE IF EXISTS rh_esocial_lote');
        $this->addSql('DROP TABLE IF EXISTS rh_ponto_registro');
        $this->addSql('DROP TABLE IF EXISTS rh_candidato');
        $this->addSql('DROP TABLE IF EXISTS rh_vaga');
        $this->addSql('DROP TABLE IF EXISTS rh_email_event');
        $this->addSql('DROP TABLE IF EXISTS rh_comunicado_leitura');
        $this->addSql('DROP TABLE IF EXISTS rh_comunicado');
        $this->addSql('DROP TABLE IF EXISTS rh_workflow_template');
        $this->addSql('DROP TABLE IF EXISTS rh_audit_log');
    }

    private function columnExists(string $table, string $column): bool
    {
        $cols = $this->connection->createSchemaManager()->listTableColumns($table);

        return isset($cols[$column]);
    }
}
