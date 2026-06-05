<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530310000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create integ_dead_letter table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE integ_dead_letter (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, conector_id INT DEFAULT NULL, evento VARCHAR(120) NOT NULL, payload JSON NOT NULL, erro_mensagem LONGTEXT NOT NULL, tentativas SMALLINT NOT NULL, status VARCHAR(32) NOT NULL, proxima_retry_em DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', chamado_ti_codigo VARCHAR(64) DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', atualizado_em DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE integ_dead_letter ADD CONSTRAINT FK_DL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_dead_letter ADD CONSTRAINT FK_DL_CONECTOR FOREIGN KEY (conector_id) REFERENCES integ_conector (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_dead_letter DROP FOREIGN KEY FK_DL_EMPRESA');
        $this->addSql('ALTER TABLE integ_dead_letter DROP FOREIGN KEY FK_DL_CONECTOR');
        $this->addSql('DROP TABLE integ_dead_letter');
    }
}
