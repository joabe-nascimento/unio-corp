<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clínica: solicitações públicas de agenda, assinaturas e tarefas administrativas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_agenda_solicitacao (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            paciente_id INT DEFAULT NULL,
            agendamento_id INT DEFAULT NULL,
            nome VARCHAR(160) NOT NULL,
            telefone VARCHAR(40) NOT NULL,
            email VARCHAR(120) DEFAULT NULL,
            motivo VARCHAR(32) NOT NULL,
            data_preferida DATE DEFAULT NULL,
            periodo VARCHAR(16) NOT NULL,
            observacao LONGTEXT DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CLINIC_AG_SOL_EMPRESA (empresa_id),
            INDEX IDX_CLINIC_AG_SOL_STATUS (empresa_id, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_agenda_solicitacao ADD CONSTRAINT FK_CLINIC_AG_SOL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_agenda_solicitacao ADD CONSTRAINT FK_CLINIC_AG_SOL_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_agenda_solicitacao ADD CONSTRAINT FK_CLINIC_AG_SOL_AGENDA FOREIGN KEY (agendamento_id) REFERENCES clinic_agendamento (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_assinatura_documento (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            paciente_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            tipo VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            assinado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CLINIC_ASSIN_EMPRESA (empresa_id),
            INDEX IDX_CLINIC_ASSIN_STATUS (empresa_id, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_assinatura_documento ADD CONSTRAINT FK_CLINIC_ASSIN_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_assinatura_documento ADD CONSTRAINT FK_CLINIC_ASSIN_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE clinic_tarefa (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            responsavel_id INT DEFAULT NULL,
            criado_por_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao LONGTEXT DEFAULT NULL,
            vencimento DATE DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            concluida_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CLINIC_TAREFA_EMPRESA (empresa_id),
            INDEX IDX_CLINIC_TAREFA_STATUS (empresa_id, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_tarefa ADD CONSTRAINT FK_CLINIC_TAREFA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_tarefa ADD CONSTRAINT FK_CLINIC_TAREFA_RESP FOREIGN KEY (responsavel_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_tarefa ADD CONSTRAINT FK_CLINIC_TAREFA_CRIADOR FOREIGN KEY (criado_por_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_agenda_solicitacao DROP FOREIGN KEY FK_CLINIC_AG_SOL_EMPRESA');
        $this->addSql('ALTER TABLE clinic_agenda_solicitacao DROP FOREIGN KEY FK_CLINIC_AG_SOL_PACIENTE');
        $this->addSql('ALTER TABLE clinic_agenda_solicitacao DROP FOREIGN KEY FK_CLINIC_AG_SOL_AGENDA');
        $this->addSql('DROP TABLE clinic_agenda_solicitacao');
        $this->addSql('ALTER TABLE clinic_assinatura_documento DROP FOREIGN KEY FK_CLINIC_ASSIN_EMPRESA');
        $this->addSql('ALTER TABLE clinic_assinatura_documento DROP FOREIGN KEY FK_CLINIC_ASSIN_PACIENTE');
        $this->addSql('DROP TABLE clinic_assinatura_documento');
        $this->addSql('ALTER TABLE clinic_tarefa DROP FOREIGN KEY FK_CLINIC_TAREFA_EMPRESA');
        $this->addSql('ALTER TABLE clinic_tarefa DROP FOREIGN KEY FK_CLINIC_TAREFA_RESP');
        $this->addSql('ALTER TABLE clinic_tarefa DROP FOREIGN KEY FK_CLINIC_TAREFA_CRIADOR');
        $this->addSql('DROP TABLE clinic_tarefa');
    }
}
