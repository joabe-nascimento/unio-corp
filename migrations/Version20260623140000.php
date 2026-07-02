<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Núcleo Pós-Operatório Fase 1: protocolos, pacientes, questionários, alertas e eventos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pos_operatorio_protocolo (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(120) NOT NULL, tipo_procedimento VARCHAR(120) DEFAULT NULL, duracao_dias SMALLINT NOT NULL, checklist JSON NOT NULL, perguntas JSON NOT NULL, regras_alerta JSON NOT NULL, ativo TINYINT(1) NOT NULL, INDEX IDX_POSOP_PROT_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pos_operatorio_protocolo ADD CONSTRAINT FK_POSOP_PROT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE pos_operatorio_paciente (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, protocolo_id INT DEFAULT NULL, medico_responsavel_id INT DEFAULT NULL, portal_user_id INT DEFAULT NULL, codigo VARCHAR(16) NOT NULL, nome VARCHAR(160) NOT NULL, procedimento VARCHAR(120) DEFAULT NULL, data_cirurgia DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', status VARCHAR(24) NOT NULL, telefone_contato VARCHAR(40) DEFAULT NULL, consentimento_lgpd_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_POSOP_PAC_EMPRESA_STATUS (empresa_id, status), UNIQUE INDEX UNIQ_POSOP_PAC_CODIGO (empresa_id, codigo), INDEX IDX_POSOP_PAC_PROTOCOLO (protocolo_id), INDEX IDX_POSOP_PAC_MEDICO (medico_responsavel_id), INDEX IDX_POSOP_PAC_PORTAL (portal_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_PROTOCOLO FOREIGN KEY (protocolo_id) REFERENCES pos_operatorio_protocolo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_MEDICO FOREIGN KEY (medico_responsavel_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD CONSTRAINT FK_POSOP_PAC_PORTAL FOREIGN KEY (portal_user_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE pos_operatorio_questionario_resposta (id INT AUTO_INCREMENT NOT NULL, paciente_id INT NOT NULL, data_referencia DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', respostas JSON NOT NULL, score_risco SMALLINT NOT NULL, alerta_gerado TINYINT(1) NOT NULL, respondido_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_POSOP_QR_PAC_DATA (paciente_id, data_referencia), INDEX IDX_POSOP_QR_PACIENTE (paciente_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pos_operatorio_questionario_resposta ADD CONSTRAINT FK_POSOP_QR_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE pos_operatorio_alerta (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, paciente_id INT NOT NULL, responsavel_id INT DEFAULT NULL, prioridade VARCHAR(2) NOT NULL, motivo VARCHAR(255) NOT NULL, status VARCHAR(24) NOT NULL, sla_limite_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolvido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_POSOP_ALERT_EMPRESA_STATUS (empresa_id, status), INDEX IDX_POSOP_ALERT_PRI (prioridade, status), INDEX IDX_POSOP_ALERT_PACIENTE (paciente_id), INDEX IDX_POSOP_ALERT_RESP (responsavel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pos_operatorio_alerta ADD CONSTRAINT FK_POSOP_ALERT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pos_operatorio_alerta ADD CONSTRAINT FK_POSOP_ALERT_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pos_operatorio_alerta ADD CONSTRAINT FK_POSOP_ALERT_RESP FOREIGN KEY (responsavel_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE pos_operatorio_evento (id INT AUTO_INCREMENT NOT NULL, paciente_id INT NOT NULL, autor_id INT DEFAULT NULL, tipo VARCHAR(32) NOT NULL, descricao LONGTEXT NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_POSOP_EVT_PACIENTE (paciente_id, criado_em), INDEX IDX_POSOP_EVT_AUTOR (autor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pos_operatorio_evento ADD CONSTRAINT FK_POSOP_EVT_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pos_operatorio_evento ADD CONSTRAINT FK_POSOP_EVT_AUTOR FOREIGN KEY (autor_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_evento DROP FOREIGN KEY FK_POSOP_EVT_PACIENTE');
        $this->addSql('ALTER TABLE pos_operatorio_evento DROP FOREIGN KEY FK_POSOP_EVT_AUTOR');
        $this->addSql('DROP TABLE pos_operatorio_evento');

        $this->addSql('ALTER TABLE pos_operatorio_alerta DROP FOREIGN KEY FK_POSOP_ALERT_EMPRESA');
        $this->addSql('ALTER TABLE pos_operatorio_alerta DROP FOREIGN KEY FK_POSOP_ALERT_PACIENTE');
        $this->addSql('ALTER TABLE pos_operatorio_alerta DROP FOREIGN KEY FK_POSOP_ALERT_RESP');
        $this->addSql('DROP TABLE pos_operatorio_alerta');

        $this->addSql('ALTER TABLE pos_operatorio_questionario_resposta DROP FOREIGN KEY FK_POSOP_QR_PACIENTE');
        $this->addSql('DROP TABLE pos_operatorio_questionario_resposta');

        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_EMPRESA');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_PROTOCOLO');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_MEDICO');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP FOREIGN KEY FK_POSOP_PAC_PORTAL');
        $this->addSql('DROP TABLE pos_operatorio_paciente');

        $this->addSql('ALTER TABLE pos_operatorio_protocolo DROP FOREIGN KEY FK_POSOP_PROT_EMPRESA');
        $this->addSql('DROP TABLE pos_operatorio_protocolo');
    }
}
