<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config clínica em banco, convite portal, data de nascimento do paciente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_empresa_config (empresa_id INT NOT NULL, produtos JSON NOT NULL, guias JSON NOT NULL, integracoes JSON NOT NULL, PRIMARY KEY (empresa_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_empresa_config ADD CONSTRAINT FK_CLINIC_CFG_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD data_nascimento DATE DEFAULT NULL, ADD portal_invite_token VARCHAR(64) DEFAULT NULL, ADD portal_invite_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POSOP_PORTAL_INVITE ON pos_operatorio_paciente (portal_invite_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_POSOP_PORTAL_INVITE ON pos_operatorio_paciente');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP data_nascimento, DROP portal_invite_token, DROP portal_invite_expires_at');
        $this->addSql('ALTER TABLE clinic_empresa_config DROP FOREIGN KEY FK_CLINIC_CFG_EMPRESA');
        $this->addSql('DROP TABLE clinic_empresa_config');
    }
}
