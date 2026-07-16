<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unio Saude: cadastros clinicos completos (unidades, profissionais, procedimentos, salas, pacotes, estoque, orcamentos, SOAP, paciente/convenio ricos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_convenio ADD cnpj VARCHAR(14) DEFAULT NULL, ADD codigo_prestador VARCHAR(40) DEFAULT NULL, ADD versao_tiss VARCHAR(16) DEFAULT NULL, ADD contato_faturamento VARCHAR(120) DEFAULT NULL, ADD email_faturamento VARCHAR(120) DEFAULT NULL, ADD telefone_faturamento VARCHAR(40) DEFAULT NULL, ADD prazo_glosa_dias SMALLINT DEFAULT 30 NOT NULL, ADD observacoes LONGTEXT DEFAULT NULL');

        $this->addSql("ALTER TABLE pos_operatorio_paciente ADD sexo VARCHAR(1) DEFAULT NULL, ADD rg VARCHAR(20) DEFAULT NULL, ADD cns VARCHAR(15) DEFAULT NULL, ADD cep VARCHAR(8) DEFAULT NULL, ADD logradouro VARCHAR(180) DEFAULT NULL, ADD numero_endereco VARCHAR(20) DEFAULT NULL, ADD complemento VARCHAR(80) DEFAULT NULL, ADD bairro VARCHAR(80) DEFAULT NULL, ADD cidade VARCHAR(80) DEFAULT NULL, ADD uf VARCHAR(2) DEFAULT NULL, ADD convenio_id INT DEFAULT NULL, ADD numero_carteirinha_convenio VARCHAR(40) DEFAULT NULL, ADD validade_carteirinha_convenio DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ADD origem_clinica VARCHAR(40) DEFAULT NULL, ADD indicado_por VARCHAR(160) DEFAULT NULL, ADD titular_nome VARCHAR(160) DEFAULT NULL, ADD parentesco_titular VARCHAR(40) DEFAULT NULL, ADD anamnese JSON DEFAULT NULL, ADD profissional_id INT DEFAULT NULL, ADD procedimento_id INT DEFAULT NULL, ADD pacote_id INT DEFAULT NULL");

        $this->addSql('CREATE TABLE clinic_profissional (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, user_id INT DEFAULT NULL, nome VARCHAR(160) NOT NULL, conselho VARCHAR(16) NOT NULL, numero_conselho VARCHAR(32) NOT NULL, uf_conselho VARCHAR(2) DEFAULT NULL, especialidade VARCHAR(120) DEFAULT NULL, telefone VARCHAR(40) DEFAULT NULL, email VARCHAR(120) DEFAULT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_PROF_EMP (empresa_id), INDEX IDX_CLINIC_PROF_USER (user_id), UNIQUE INDEX UNIQ_CLINIC_PROF_CONSELHO (empresa_id, conselho, numero_conselho), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_profissional ADD CONSTRAINT FK_CLINIC_PROF_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_profissional ADD CONSTRAINT FK_CLINIC_PROF_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_procedimento (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(180) NOT NULL, codigo_interno VARCHAR(32) DEFAULT NULL, codigo_tuss VARCHAR(20) DEFAULT NULL, valor_centavos INT DEFAULT NULL, duracao_minutos SMALLINT DEFAULT 30 NOT NULL, descricao LONGTEXT DEFAULT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_PROC_EMP (empresa_id), UNIQUE INDEX UNIQ_CLINIC_PROC_CODIGO (empresa_id, codigo_interno), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_procedimento ADD CONSTRAINT FK_CLINIC_PROC_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE clinic_sala (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, unidade_id INT DEFAULT NULL, nome VARCHAR(120) NOT NULL, codigo VARCHAR(16) NOT NULL, tipo VARCHAR(24) DEFAULT \'consultorio\' NOT NULL, capacidade SMALLINT DEFAULT 1 NOT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CLINIC_SALA_EMP (empresa_id), INDEX IDX_CLINIC_SALA_UNI (unidade_id), UNIQUE INDEX UNIQ_CLINIC_SALA_CODIGO (empresa_id, codigo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_sala ADD CONSTRAINT FK_CLINIC_SALA_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_sala ADD CONSTRAINT FK_CLINIC_SALA_UNI FOREIGN KEY (unidade_id) REFERENCES clinic_unidade (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_soap_template (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(120) NOT NULL, procedimento_tipo VARCHAR(120) DEFAULT NULL, queixa LONGTEXT DEFAULT NULL, exame LONGTEXT DEFAULT NULL, hipotese LONGTEXT DEFAULT NULL, conduta LONGTEXT DEFAULT NULL, cid10_sugerido VARCHAR(16) DEFAULT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CLINIC_SOAP_EMP (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_soap_template ADD CONSTRAINT FK_CLINIC_SOAP_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE clinic_pacote (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(160) NOT NULL, descricao LONGTEXT DEFAULT NULL, valor_centavos INT DEFAULT NULL, itens JSON NOT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_PACOTE_EMP (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_pacote ADD CONSTRAINT FK_CLINIC_PACOTE_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE clinic_estoque_item (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, unidade_id INT DEFAULT NULL, nome VARCHAR(160) NOT NULL, sku VARCHAR(32) DEFAULT NULL, unidade_medida VARCHAR(16) DEFAULT \'un\' NOT NULL, quantidade INT DEFAULT 0 NOT NULL, minimo INT DEFAULT 0 NOT NULL, ativo TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CLINIC_EST_EMP (empresa_id), INDEX IDX_CLINIC_EST_UNI (unidade_id), UNIQUE INDEX UNIQ_CLINIC_EST_SKU (empresa_id, sku), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_estoque_item ADD CONSTRAINT FK_CLINIC_EST_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_estoque_item ADD CONSTRAINT FK_CLINIC_EST_UNI FOREIGN KEY (unidade_id) REFERENCES clinic_unidade (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_orcamento (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, paciente_id INT DEFAULT NULL, lead_nome VARCHAR(160) DEFAULT NULL, lead_telefone VARCHAR(40) DEFAULT NULL, lead_email VARCHAR(120) DEFAULT NULL, status VARCHAR(24) DEFAULT \'rascunho\' NOT NULL, valor_centavos INT DEFAULT 0 NOT NULL, desconto_centavos INT DEFAULT 0 NOT NULL, itens JSON NOT NULL, validade DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', observacoes LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_ORC_EMP (empresa_id), INDEX IDX_CLINIC_ORC_PAC (paciente_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_orcamento ADD CONSTRAINT FK_CLINIC_ORC_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_orcamento ADD CONSTRAINT FK_CLINIC_ORC_PAC FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE clinic_agendamento ADD sala_id INT DEFAULT NULL, ADD profissional_id INT DEFAULT NULL, ADD procedimento_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE clinic_agendamento ADD CONSTRAINT FK_CLINIC_AG_SALA FOREIGN KEY (sala_id) REFERENCES clinic_sala (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_agendamento ADD CONSTRAINT FK_CLINIC_AG_PROF FOREIGN KEY (profissional_id) REFERENCES clinic_profissional (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_agendamento ADD CONSTRAINT FK_CLINIC_AG_PROC FOREIGN KEY (procedimento_id) REFERENCES clinic_procedimento (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_CONV FOREIGN KEY (convenio_id) REFERENCES clinic_convenio (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_PROF FOREIGN KEY (profissional_id) REFERENCES clinic_profissional (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_PROC FOREIGN KEY (procedimento_id) REFERENCES clinic_procedimento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_PACOTE FOREIGN KEY (pacote_id) REFERENCES clinic_pacote (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_CONV');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_PROF');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_PROC');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_PACOTE');
        $this->addSql('ALTER TABLE clinic_agendamento DROP FOREIGN KEY FK_CLINIC_AG_SALA');
        $this->addSql('ALTER TABLE clinic_agendamento DROP FOREIGN KEY FK_CLINIC_AG_PROF');
        $this->addSql('ALTER TABLE clinic_agendamento DROP FOREIGN KEY FK_CLINIC_AG_PROC');
        $this->addSql('ALTER TABLE clinic_agendamento DROP sala_id, DROP profissional_id, DROP procedimento_id');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP sexo, DROP rg, DROP cns, DROP cep, DROP logradouro, DROP numero_endereco, DROP complemento, DROP bairro, DROP cidade, DROP uf, DROP convenio_id, DROP numero_carteirinha_convenio, DROP validade_carteirinha_convenio, DROP origem_clinica, DROP indicado_por, DROP titular_nome, DROP parentesco_titular, DROP anamnese, DROP profissional_id, DROP procedimento_id, DROP pacote_id');
        $this->addSql('ALTER TABLE clinic_convenio DROP cnpj, DROP codigo_prestador, DROP versao_tiss, DROP contato_faturamento, DROP email_faturamento, DROP telefone_faturamento, DROP prazo_glosa_dias, DROP observacoes');
        $this->addSql('DROP TABLE clinic_orcamento');
        $this->addSql('DROP TABLE clinic_estoque_item');
        $this->addSql('DROP TABLE clinic_pacote');
        $this->addSql('DROP TABLE clinic_soap_template');
        $this->addSql('DROP TABLE clinic_sala');
        $this->addSql('DROP TABLE clinic_procedimento');
        $this->addSql('DROP TABLE clinic_profissional');
    }
}
