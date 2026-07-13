<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clinic outbound message log (WhatsApp Meta / canais)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_outbound_message (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            canal VARCHAR(24) NOT NULL,
            evento VARCHAR(64) NOT NULL,
            destino VARCHAR(32) NOT NULL,
            status VARCHAR(16) NOT NULL,
            provider VARCHAR(24) NOT NULL,
            provider_message_id VARCHAR(120) DEFAULT NULL,
            erro LONGTEXT DEFAULT NULL,
            corpo_preview VARCHAR(240) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_clinic_outbound_empresa_criado (empresa_id, criado_em),
            INDEX idx_clinic_outbound_empresa_status (empresa_id, status),
            INDEX IDX_CLINIC_OUTBOUND_EMP (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_outbound_message ADD CONSTRAINT FK_CLINIC_OUTBOUND_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_outbound_message DROP FOREIGN KEY FK_CLINIC_OUTBOUND_EMP');
        $this->addSql('DROP TABLE clinic_outbound_message');
    }
}
