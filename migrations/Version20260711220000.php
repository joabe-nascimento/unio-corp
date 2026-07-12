<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plataforma clínica — branding, auditoria, recepção, unidades, API e dependentes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_empresa_config ADD branding JSON NOT NULL, ADD planos_limites JSON NOT NULL, ADD onboarding JSON NOT NULL');

        $this->addSql('CREATE TABLE clinic_unidade (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(120) NOT NULL, codigo VARCHAR(16) NOT NULL, endereco VARCHAR(255) DEFAULT NULL, telefone VARCHAR(40) DEFAULT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CLINIC_UNIDADE_EMPRESA (empresa_id), UNIQUE INDEX UNIQ_CLINIC_UNIDADE_CODIGO (empresa_id, codigo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_unidade ADD CONSTRAINT FK_CLINIC_UNIDADE_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE clinic_documento_emissao (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, paciente_id INT NOT NULL, emitido_por_id INT DEFAULT NULL, tipo VARCHAR(24) NOT NULL, codigo_verificacao VARCHAR(32) NOT NULL, plano VARCHAR(24) DEFAULT NULL, acao VARCHAR(16) NOT NULL, hash_documento VARCHAR(64) DEFAULT NULL, meta JSON NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_DOC_EMPRESA (empresa_id), INDEX IDX_CLINIC_DOC_PACIENTE (paciente_id), INDEX IDX_CLINIC_DOC_CODIGO (codigo_verificacao), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_documento_emissao ADD CONSTRAINT FK_CLINIC_DOC_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_documento_emissao ADD CONSTRAINT FK_CLINIC_DOC_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_documento_emissao ADD CONSTRAINT FK_CLINIC_DOC_EMITIDO_POR FOREIGN KEY (emitido_por_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_verificacao_log (id INT AUTO_INCREMENT NOT NULL, empresa_id INT DEFAULT NULL, paciente_id INT DEFAULT NULL, codigo VARCHAR(32) NOT NULL, tipo VARCHAR(24) DEFAULT NULL, status VARCHAR(16) NOT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, origem VARCHAR(24) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_VERIF_CODIGO (codigo), INDEX IDX_CLINIC_VERIF_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_verificacao_log ADD CONSTRAINT FK_CLINIC_VERIF_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_verificacao_log ADD CONSTRAINT FK_CLINIC_VERIF_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_checkin (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, paciente_id INT NOT NULL, unidade_id INT DEFAULT NULL, recepcionista_id INT DEFAULT NULL, metodo VARCHAR(16) NOT NULL, codigo_usado VARCHAR(32) DEFAULT NULL, observacao VARCHAR(255) DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_CHECKIN_EMPRESA (empresa_id), INDEX IDX_CLINIC_CHECKIN_PACIENTE (paciente_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_checkin ADD CONSTRAINT FK_CLINIC_CHECKIN_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_checkin ADD CONSTRAINT FK_CLINIC_CHECKIN_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_checkin ADD CONSTRAINT FK_CLINIC_CHECKIN_UNIDADE FOREIGN KEY (unidade_id) REFERENCES clinic_unidade (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_checkin ADD CONSTRAINT FK_CLINIC_CHECKIN_RECEPCIONISTA FOREIGN KEY (recepcionista_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_api_token (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(80) NOT NULL, token_hash VARCHAR(64) NOT NULL, escopos JSON NOT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, ultimo_uso_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CLINIC_API_TOKEN_HASH (token_hash), INDEX IDX_CLINIC_API_TOKEN_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_api_token ADD CONSTRAINT FK_CLINIC_API_TOKEN_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD titular_cpf VARCHAR(11) DEFAULT NULL, ADD is_sandbox TINYINT(1) DEFAULT 0 NOT NULL, ADD consentimento_carteirinha_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD comprovante_hash VARCHAR(64) DEFAULT NULL, ADD unidade_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_UNIDADE FOREIGN KEY (unidade_id) REFERENCES clinic_unidade (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_POSOP_PAC_TITULAR_CPF ON pos_operatorio_paciente (empresa_id, titular_cpf)');
        $this->addSql('CREATE INDEX IDX_POSOP_PAC_SANDBOX ON pos_operatorio_paciente (empresa_id, is_sandbox)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_UNIDADE');
        $this->addSql('DROP INDEX IDX_POSOP_PAC_TITULAR_CPF ON pos_operatorio_paciente');
        $this->addSql('DROP INDEX IDX_POSOP_PAC_SANDBOX ON pos_operatorio_paciente');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP titular_cpf, DROP is_sandbox, DROP consentimento_carteirinha_em, DROP comprovante_hash, DROP unidade_id');

        $this->addSql('DROP TABLE clinic_api_token');
        $this->addSql('DROP TABLE clinic_checkin');
        $this->addSql('DROP TABLE clinic_verificacao_log');
        $this->addSql('DROP TABLE clinic_documento_emissao');
        $this->addSql('DROP TABLE clinic_unidade');

        $this->addSql('ALTER TABLE clinic_empresa_config DROP branding, DROP planos_limites, DROP onboarding');
    }
}
