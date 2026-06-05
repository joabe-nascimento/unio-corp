<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530340000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create integ_domain_event table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE integ_domain_event (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, tipo VARCHAR(80) NOT NULL, payload JSON NOT NULL, origem VARCHAR(64) DEFAULT NULL, status VARCHAR(32) NOT NULL, erro_processamento LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', processado_em DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql('ALTER TABLE integ_domain_event ADD CONSTRAINT FK_DE_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_DE_EMPRESA_TIPO ON integ_domain_event (empresa_id, tipo)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_domain_event DROP FOREIGN KEY FK_DE_EMPRESA');
        $this->addSql('DROP TABLE integ_domain_event');
    }
}
