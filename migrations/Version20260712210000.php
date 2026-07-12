<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E4 TISS fundação: clinic_convenio, clinic_guia_tiss, clinic_guia_item + conta convenio/glosado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_convenio (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(180) NOT NULL, registro_ans VARCHAR(20) DEFAULT NULL, ativo TINYINT(1) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_CONVENIO_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE clinic_guia_tiss (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, conta_id INT NOT NULL, atendimento_id INT DEFAULT NULL, paciente_id INT NOT NULL, convenio_id INT NOT NULL, numero_guia VARCHAR(40) NOT NULL, senha_autorizacao VARCHAR(40) DEFAULT NULL, status VARCHAR(16) NOT NULL, motivo_glosa LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CLINIC_GUIA_CONTA (conta_id), INDEX IDX_CLINIC_GUIA_EMPRESA (empresa_id), INDEX IDX_CLINIC_GUIA_ATENDIMENTO (atendimento_id), INDEX IDX_CLINIC_GUIA_PACIENTE (paciente_id), INDEX IDX_CLINIC_GUIA_CONVENIO (convenio_id), INDEX IDX_CLINIC_GUIA_EMPRESA_STATUS (empresa_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE clinic_guia_item (id INT AUTO_INCREMENT NOT NULL, guia_id INT NOT NULL, codigo_tuss VARCHAR(20) DEFAULT NULL, descricao VARCHAR(255) NOT NULL, quantidade INT NOT NULL, valor_centavos INT DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_CLINIC_GUIA_ITEM_GUIA (guia_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_conta ADD convenio_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE clinic_conta ADD CONSTRAINT FK_CLINIC_CONTA_CONVENIO FOREIGN KEY (convenio_id) REFERENCES clinic_convenio (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CLINIC_CONTA_CONVENIO ON clinic_conta (convenio_id)');
        $this->addSql('ALTER TABLE clinic_convenio ADD CONSTRAINT FK_CLINIC_CONVENIO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD CONSTRAINT FK_CLINIC_GUIA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD CONSTRAINT FK_CLINIC_GUIA_CONTA FOREIGN KEY (conta_id) REFERENCES clinic_conta (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD CONSTRAINT FK_CLINIC_GUIA_ATENDIMENTO FOREIGN KEY (atendimento_id) REFERENCES clinic_atendimento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD CONSTRAINT FK_CLINIC_GUIA_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD CONSTRAINT FK_CLINIC_GUIA_CONVENIO FOREIGN KEY (convenio_id) REFERENCES clinic_convenio (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE clinic_guia_item ADD CONSTRAINT FK_CLINIC_GUIA_ITEM_GUIA FOREIGN KEY (guia_id) REFERENCES clinic_guia_tiss (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_conta DROP FOREIGN KEY FK_CLINIC_CONTA_CONVENIO');
        $this->addSql('DROP INDEX IDX_CLINIC_CONTA_CONVENIO ON clinic_conta');
        $this->addSql('ALTER TABLE clinic_conta DROP convenio_id');
        $this->addSql('ALTER TABLE clinic_guia_item DROP FOREIGN KEY FK_CLINIC_GUIA_ITEM_GUIA');
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP FOREIGN KEY FK_CLINIC_GUIA_EMPRESA');
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP FOREIGN KEY FK_CLINIC_GUIA_CONTA');
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP FOREIGN KEY FK_CLINIC_GUIA_ATENDIMENTO');
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP FOREIGN KEY FK_CLINIC_GUIA_PACIENTE');
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP FOREIGN KEY FK_CLINIC_GUIA_CONVENIO');
        $this->addSql('ALTER TABLE clinic_convenio DROP FOREIGN KEY FK_CLINIC_CONVENIO_EMPRESA');
        $this->addSql('DROP TABLE clinic_guia_item');
        $this->addSql('DROP TABLE clinic_guia_tiss');
        $this->addSql('DROP TABLE clinic_convenio');
    }
}
