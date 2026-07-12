<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E2 atendimento + E3 conta particular (clinic_atendimento, clinic_conta)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_atendimento (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, agendamento_id INT NOT NULL, paciente_id INT NOT NULL, medico_id INT DEFAULT NULL, status VARCHAR(16) NOT NULL, queixa LONGTEXT DEFAULT NULL, exame LONGTEXT DEFAULT NULL, conduta LONGTEXT DEFAULT NULL, observacao LONGTEXT DEFAULT NULL, iniciado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', finalizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CLINIC_ATEND_AGENDAMENTO (agendamento_id), INDEX IDX_CLINIC_ATEND_EMPRESA (empresa_id), INDEX IDX_CLINIC_ATEND_PACIENTE (paciente_id), INDEX IDX_CLINIC_ATEND_MEDICO (medico_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE clinic_conta (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, agendamento_id INT DEFAULT NULL, atendimento_id INT DEFAULT NULL, paciente_id INT NOT NULL, tipo VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL, valor_centavos INT DEFAULT NULL, descricao VARCHAR(255) DEFAULT NULL, pago_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CLINIC_CONTA_AGENDAMENTO (agendamento_id), UNIQUE INDEX UNIQ_CLINIC_CONTA_ATENDIMENTO (atendimento_id), INDEX IDX_CLINIC_CONTA_EMPRESA (empresa_id), INDEX IDX_CLINIC_CONTA_PACIENTE (paciente_id), INDEX IDX_CLINIC_CONTA_EMPRESA_STATUS (empresa_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_atendimento ADD CONSTRAINT FK_CLINIC_ATEND_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_atendimento ADD CONSTRAINT FK_CLINIC_ATEND_AGENDAMENTO FOREIGN KEY (agendamento_id) REFERENCES clinic_agendamento (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_atendimento ADD CONSTRAINT FK_CLINIC_ATEND_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_atendimento ADD CONSTRAINT FK_CLINIC_ATEND_MEDICO FOREIGN KEY (medico_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_conta ADD CONSTRAINT FK_CLINIC_CONTA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_conta ADD CONSTRAINT FK_CLINIC_CONTA_AGENDAMENTO FOREIGN KEY (agendamento_id) REFERENCES clinic_agendamento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_conta ADD CONSTRAINT FK_CLINIC_CONTA_ATENDIMENTO FOREIGN KEY (atendimento_id) REFERENCES clinic_atendimento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_conta ADD CONSTRAINT FK_CLINIC_CONTA_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_conta DROP FOREIGN KEY FK_CLINIC_CONTA_EMPRESA');
        $this->addSql('ALTER TABLE clinic_conta DROP FOREIGN KEY FK_CLINIC_CONTA_AGENDAMENTO');
        $this->addSql('ALTER TABLE clinic_conta DROP FOREIGN KEY FK_CLINIC_CONTA_ATENDIMENTO');
        $this->addSql('ALTER TABLE clinic_conta DROP FOREIGN KEY FK_CLINIC_CONTA_PACIENTE');
        $this->addSql('ALTER TABLE clinic_atendimento DROP FOREIGN KEY FK_CLINIC_ATEND_EMPRESA');
        $this->addSql('ALTER TABLE clinic_atendimento DROP FOREIGN KEY FK_CLINIC_ATEND_AGENDAMENTO');
        $this->addSql('ALTER TABLE clinic_atendimento DROP FOREIGN KEY FK_CLINIC_ATEND_PACIENTE');
        $this->addSql('ALTER TABLE clinic_atendimento DROP FOREIGN KEY FK_CLINIC_ATEND_MEDICO');
        $this->addSql('DROP TABLE clinic_conta');
        $this->addSql('DROP TABLE clinic_atendimento');
    }
}
