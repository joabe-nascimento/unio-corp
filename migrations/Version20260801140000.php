<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config de automação de publicações (prazo automático + WhatsApp alerta)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_publicacao_config (
            id INT AUTO_INCREMENT NOT NULL,
            prazo_automatico TINYINT(1) NOT NULL,
            alerta_whatsapp TINYINT(1) NOT NULL,
            telefone_alerta VARCHAR(20) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            UNIQUE INDEX UNIQ_JUR_PUB_CFG_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE juridico_publicacao_config ADD CONSTRAINT FK_JUR_PUB_CFG_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_publicacao_config DROP FOREIGN KEY FK_JUR_PUB_CFG_EMPRESA');
        $this->addSql('DROP TABLE juridico_publicacao_config');
    }
}
