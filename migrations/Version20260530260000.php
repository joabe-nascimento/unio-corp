<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub Integrações — conectores, webhooks, logs, API keys e mapeamentos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE integ_conector (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, catalogo_id VARCHAR(64) NOT NULL, nome VARCHAR(120) NOT NULL, categoria VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, health VARCHAR(16) NOT NULL, endpoint_url VARCHAR(255) DEFAULT NULL, latencia VARCHAR(16) NOT NULL, uptime NUMERIC(5, 2) NOT NULL, eventos24h INT NOT NULL, hubs_alvo JSON NOT NULL, config_notas LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_CON_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE integ_webhook (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, conector_id INT DEFAULT NULL, nome VARCHAR(120) NOT NULL, direcao VARCHAR(8) NOT NULL, evento VARCHAR(80) NOT NULL, url VARCHAR(255) NOT NULL, ativo TINYINT(1) NOT NULL, falhas_consecutivas INT NOT NULL, ultimo_disparo DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_WH_EMPRESA (empresa_id), INDEX IDX_INTEG_WH_CONECTOR (conector_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE integ_log (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, conector_id INT DEFAULT NULL, webhook_id INT DEFAULT NULL, nivel VARCHAR(8) NOT NULL, origem VARCHAR(80) NOT NULL, mensagem LONGTEXT NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_LOG_EMPRESA (empresa_id), INDEX IDX_INTEG_LOG_CONECTOR (conector_id), INDEX IDX_INTEG_LOG_WEBHOOK (webhook_id), INDEX IDX_INTEG_LOG_CRIADO (criado_em), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE integ_api_key (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, nome VARCHAR(80) NOT NULL, prefix VARCHAR(16) NOT NULL, hash VARCHAR(64) NOT NULL, scopes JSON NOT NULL, ambiente VARCHAR(8) NOT NULL, ultimo_uso DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', revogada_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_KEY_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE integ_mapeamento (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, conector_id INT NOT NULL, nome VARCHAR(120) NOT NULL, campo_origem VARCHAR(80) NOT NULL, campo_destino VARCHAR(80) NOT NULL, transformacao VARCHAR(64) DEFAULT NULL, ativo TINYINT(1) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_MAP_EMPRESA (empresa_id), INDEX IDX_INTEG_MAP_CONECTOR (conector_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE integ_conector ADD CONSTRAINT FK_INTEG_CON_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_webhook ADD CONSTRAINT FK_INTEG_WH_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_webhook ADD CONSTRAINT FK_INTEG_WH_CONECTOR FOREIGN KEY (conector_id) REFERENCES integ_conector (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE integ_log ADD CONSTRAINT FK_INTEG_LOG_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_log ADD CONSTRAINT FK_INTEG_LOG_CONECTOR FOREIGN KEY (conector_id) REFERENCES integ_conector (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE integ_log ADD CONSTRAINT FK_INTEG_LOG_WEBHOOK FOREIGN KEY (webhook_id) REFERENCES integ_webhook (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE integ_api_key ADD CONSTRAINT FK_INTEG_KEY_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_mapeamento ADD CONSTRAINT FK_INTEG_MAP_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_mapeamento ADD CONSTRAINT FK_INTEG_MAP_CONECTOR FOREIGN KEY (conector_id) REFERENCES integ_conector (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_mapeamento DROP FOREIGN KEY FK_INTEG_MAP_EMPRESA');
        $this->addSql('ALTER TABLE integ_mapeamento DROP FOREIGN KEY FK_INTEG_MAP_CONECTOR');
        $this->addSql('ALTER TABLE integ_api_key DROP FOREIGN KEY FK_INTEG_KEY_EMPRESA');
        $this->addSql('ALTER TABLE integ_log DROP FOREIGN KEY FK_INTEG_LOG_EMPRESA');
        $this->addSql('ALTER TABLE integ_log DROP FOREIGN KEY FK_INTEG_LOG_CONECTOR');
        $this->addSql('ALTER TABLE integ_log DROP FOREIGN KEY FK_INTEG_LOG_WEBHOOK');
        $this->addSql('ALTER TABLE integ_webhook DROP FOREIGN KEY FK_INTEG_WH_EMPRESA');
        $this->addSql('ALTER TABLE integ_webhook DROP FOREIGN KEY FK_INTEG_WH_CONECTOR');
        $this->addSql('ALTER TABLE integ_conector DROP FOREIGN KEY FK_INTEG_CON_EMPRESA');
        $this->addSql('DROP TABLE integ_mapeamento');
        $this->addSql('DROP TABLE integ_api_key');
        $this->addSql('DROP TABLE integ_log');
        $this->addSql('DROP TABLE integ_webhook');
        $this->addSql('DROP TABLE integ_conector');
    }
}
