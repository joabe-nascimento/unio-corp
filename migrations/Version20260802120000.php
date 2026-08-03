<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prazo alertas, portal cliente, RAG GED, cobrança jurídica';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_prazo_config (
            id INT AUTO_INCREMENT NOT NULL,
            alerta_whatsapp TINYINT(1) NOT NULL,
            alerta_email TINYINT(1) NOT NULL,
            telefone_alerta VARCHAR(20) DEFAULT NULL,
            email_alerta VARCHAR(160) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            UNIQUE INDEX UNIQ_JUR_PRAZO_CFG_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE juridico_prazo_config ADD CONSTRAINT FK_JUR_PRAZO_CFG_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE juridico_prazo_alerta_log (
            id INT AUTO_INCREMENT NOT NULL,
            nivel VARCHAR(16) NOT NULL,
            canal VARCHAR(16) NOT NULL,
            enviado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            prazo_id INT NOT NULL,
            INDEX IDX_JUR_PRAZO_ALERTA_PRAZO (prazo_id),
            UNIQUE INDEX UNIQ_JUR_PRAZO_ALERTA (prazo_id, nivel, canal),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE juridico_prazo_alerta_log ADD CONSTRAINT FK_JUR_PRAZO_ALERTA_PRAZO FOREIGN KEY (prazo_id) REFERENCES juridico_prazo (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE juridico_cliente
            ADD portal_invite_token VARCHAR(64) DEFAULT NULL,
            ADD portal_invite_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD portal_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE juridico_cliente ADD CONSTRAINT FK_JUR_CLIENTE_PORTAL_USER FOREIGN KEY (portal_user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_JUR_CLIENTE_PORTAL_USER ON juridico_cliente (portal_user_id)');

        $this->addSql('ALTER TABLE juridico_documento
            ADD rag_sincronizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD rag_hash VARCHAR(64) DEFAULT NULL,
            ADD visivel_portal TINYINT(1) NOT NULL DEFAULT 0');

        $this->addSql('CREATE TABLE juridico_cobranca (
            id INT AUTO_INCREMENT NOT NULL,
            descricao VARCHAR(200) NOT NULL,
            valor NUMERIC(12, 2) NOT NULL,
            vencimento DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            status VARCHAR(16) NOT NULL,
            pago_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ultima_cobranca_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            cliente_id INT DEFAULT NULL,
            processo_id INT DEFAULT NULL,
            lancamento_id INT DEFAULT NULL,
            INDEX IDX_JUR_COBR_EMPRESA_STATUS (empresa_id, status),
            INDEX IDX_JUR_COBR_VENCIMENTO (vencimento),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE juridico_cobranca ADD CONSTRAINT FK_JUR_COBR_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_cobranca ADD CONSTRAINT FK_JUR_COBR_CLIENTE FOREIGN KEY (cliente_id) REFERENCES juridico_cliente (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_cobranca ADD CONSTRAINT FK_JUR_COBR_PROCESSO FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_cobranca ADD CONSTRAINT FK_JUR_COBR_LANCAMENTO FOREIGN KEY (lancamento_id) REFERENCES juridico_honorario_lancamento (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_cobranca DROP FOREIGN KEY FK_JUR_COBR_EMPRESA');
        $this->addSql('ALTER TABLE juridico_cobranca DROP FOREIGN KEY FK_JUR_COBR_CLIENTE');
        $this->addSql('ALTER TABLE juridico_cobranca DROP FOREIGN KEY FK_JUR_COBR_PROCESSO');
        $this->addSql('ALTER TABLE juridico_cobranca DROP FOREIGN KEY FK_JUR_COBR_LANCAMENTO');
        $this->addSql('DROP TABLE juridico_cobranca');

        $this->addSql('ALTER TABLE juridico_documento DROP rag_sincronizado_em, DROP rag_hash, DROP visivel_portal');

        $this->addSql('ALTER TABLE juridico_cliente DROP FOREIGN KEY FK_JUR_CLIENTE_PORTAL_USER');
        $this->addSql('DROP INDEX IDX_JUR_CLIENTE_PORTAL_USER ON juridico_cliente');
        $this->addSql('ALTER TABLE juridico_cliente DROP portal_invite_token, DROP portal_invite_expires_at, DROP portal_user_id');

        $this->addSql('ALTER TABLE juridico_prazo_alerta_log DROP FOREIGN KEY FK_JUR_PRAZO_ALERTA_PRAZO');
        $this->addSql('DROP TABLE juridico_prazo_alerta_log');

        $this->addSql('ALTER TABLE juridico_prazo_config DROP FOREIGN KEY FK_JUR_PRAZO_CFG_EMPRESA');
        $this->addSql('DROP TABLE juridico_prazo_config');
    }
}
