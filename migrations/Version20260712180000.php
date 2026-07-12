<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agenda MVP — tabela clinic_agendamento';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_agendamento (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, paciente_id INT NOT NULL, medico_id INT DEFAULT NULL, inicio DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', fim DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(16) NOT NULL, origem VARCHAR(16) NOT NULL, titulo VARCHAR(180) DEFAULT NULL, observacao VARCHAR(500) DEFAULT NULL, protocolo_dia INT DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_AGEND_EMPRESA (empresa_id), INDEX IDX_CLINIC_AGEND_PACIENTE (paciente_id), INDEX IDX_CLINIC_AGEND_MEDICO (medico_id), INDEX IDX_CLINIC_AGEND_EMPRESA_INICIO (empresa_id, inicio), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_agendamento ADD CONSTRAINT FK_CLINIC_AGEND_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_agendamento ADD CONSTRAINT FK_CLINIC_AGEND_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_agendamento ADD CONSTRAINT FK_CLINIC_AGEND_MEDICO FOREIGN KEY (medico_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_agendamento DROP FOREIGN KEY FK_CLINIC_AGEND_EMPRESA');
        $this->addSql('ALTER TABLE clinic_agendamento DROP FOREIGN KEY FK_CLINIC_AGEND_PACIENTE');
        $this->addSql('ALTER TABLE clinic_agendamento DROP FOREIGN KEY FK_CLINIC_AGEND_MEDICO');
        $this->addSql('DROP TABLE clinic_agendamento');
    }
}
