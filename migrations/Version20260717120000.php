<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClinicConta: campos de pagamento Asaas (Pix/link)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_conta ADD payment_provider VARCHAR(24) DEFAULT NULL, ADD payment_external_id VARCHAR(64) DEFAULT NULL, ADD payment_url VARCHAR(512) DEFAULT NULL, ADD payment_method VARCHAR(16) DEFAULT NULL, ADD payment_status VARCHAR(24) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CLINIC_CONTA_PAYMENT_EXT ON clinic_conta (payment_external_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_CLINIC_CONTA_PAYMENT_EXT ON clinic_conta');
        $this->addSql('ALTER TABLE clinic_conta DROP payment_provider, DROP payment_external_id, DROP payment_url, DROP payment_method, DROP payment_status');
    }
}
