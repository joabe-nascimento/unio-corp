<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530350000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ti_catalogo_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE ti_catalogo_item (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, item_id VARCHAR(64) NOT NULL, titulo VARCHAR(180) NOT NULL, descricao LONGTEXT DEFAULT NULL, categoria VARCHAR(32) NOT NULL, prioridade_padrao VARCHAR(4) NOT NULL, sla_horas SMALLINT NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, criado_em DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql('ALTER TABLE ti_catalogo_item ADD CONSTRAINT FK_CI_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_catalogo_item DROP FOREIGN KEY FK_CI_EMPRESA');
        $this->addSql('DROP TABLE ti_catalogo_item');
    }
}
