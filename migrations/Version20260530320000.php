<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530320000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create integ_slo table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE integ_slo (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, flow_key VARCHAR(64) NOT NULL, titulo VARCHAR(180) NOT NULL, meta_uptime DECIMAL(5,2) NOT NULL, meta_latencia_ms INT NOT NULL, uptime_atual DECIMAL(5,2) NOT NULL, latencia_atual_ms INT DEFAULT NULL, em_brecha TINYINT(1) NOT NULL DEFAULT 0, criado_em DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE integ_slo ADD CONSTRAINT FK_SLO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_slo DROP FOREIGN KEY FK_SLO_EMPRESA');
        $this->addSql('DROP TABLE integ_slo');
    }
}
