<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unio Jurídico — pipeline, timeline, templates, audiências, webhooks, conflito, compliance e GED estruturado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_publicacao ADD pipeline_status VARCHAR(32) DEFAULT \'pendente\' NOT NULL, ADD prazo_gerado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE juridico_documento ADD texto_extraido LONGTEXT DEFAULT NULL, ADD ocr_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD metadata_json JSON DEFAULT NULL, ADD precedente TINYINT(1) DEFAULT 0 NOT NULL, ADD resultado_precedente VARCHAR(24) DEFAULT NULL, ADD assinatura_status VARCHAR(24) DEFAULT NULL, ADD assinatura_provider VARCHAR(40) DEFAULT NULL, ADD assinatura_ref VARCHAR(120) DEFAULT NULL');

        $this->addSql('CREATE TABLE juridico_publicacao_evento (
            id INT AUTO_INCREMENT NOT NULL,
            tipo VARCHAR(32) NOT NULL,
            payload JSON DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            publicacao_id INT NOT NULL,
            INDEX IDX_JUR_PUB_EVT_PUB (publicacao_id),
            INDEX IDX_JUR_PUB_EVT_TIPO (tipo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_processo_evento (
            id INT AUTO_INCREMENT NOT NULL,
            tipo VARCHAR(32) NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            resumo LONGTEXT DEFAULT NULL,
            referencia_tipo VARCHAR(40) DEFAULT NULL,
            referencia_id INT DEFAULT NULL,
            ocorreu_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            visivel_portal TINYINT(1) DEFAULT 1 NOT NULL,
            metadata_json JSON DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            processo_id INT NOT NULL,
            INDEX IDX_JUR_PROC_EVT_EMP (empresa_id),
            INDEX IDX_JUR_PROC_EVT_PROC (processo_id),
            INDEX IDX_JUR_PROC_EVT_OCO (ocorreu_em),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_template_peca (
            id INT AUTO_INCREMENT NOT NULL,
            nome VARCHAR(160) NOT NULL,
            tipo VARCHAR(80) NOT NULL,
            area VARCHAR(80) DEFAULT NULL,
            corpo LONGTEXT NOT NULL,
            variaveis JSON DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            versao SMALLINT NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            aprovado_por_id INT DEFAULT NULL,
            INDEX IDX_JUR_TPL_EMP (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_audiencia (
            id INT AUTO_INCREMENT NOT NULL,
            tipo VARCHAR(80) NOT NULL,
            local VARCHAR(180) DEFAULT NULL,
            link_virtual VARCHAR(500) DEFAULT NULL,
            data_hora DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            checklist JSON DEFAULT NULL,
            roteiro LONGTEXT DEFAULT NULL,
            ata LONGTEXT DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            processo_id INT DEFAULT NULL,
            responsavel_id INT DEFAULT NULL,
            INDEX IDX_JUR_AUD_EMP (empresa_id),
            INDEX IDX_JUR_AUD_PROC (processo_id),
            INDEX IDX_JUR_AUD_DATA (data_hora),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_webhook_subscription (
            id INT AUTO_INCREMENT NOT NULL,
            url VARCHAR(500) NOT NULL,
            secret VARCHAR(120) NOT NULL,
            eventos JSON NOT NULL,
            ativo TINYINT(1) DEFAULT 1 NOT NULL,
            falhas_consecutivas SMALLINT DEFAULT 0 NOT NULL,
            ultimo_envio_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            INDEX IDX_JUR_WH_EMP (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_webhook_entrega (
            id INT AUTO_INCREMENT NOT NULL,
            evento VARCHAR(64) NOT NULL,
            payload JSON DEFAULT NULL,
            status_http SMALLINT DEFAULT NULL,
            sucesso TINYINT(1) NOT NULL,
            resposta LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            subscription_id INT NOT NULL,
            INDEX IDX_JUR_WHD_SUB (subscription_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_conflito_check (
            id INT AUTO_INCREMENT NOT NULL,
            nome_consultado VARCHAR(180) NOT NULL,
            resultado VARCHAR(24) NOT NULL,
            detalhes JSON DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            processo_id INT DEFAULT NULL,
            cliente_id INT DEFAULT NULL,
            INDEX IDX_JUR_CONF_EMP (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_compliance_incidente (
            id INT AUTO_INCREMENT NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao LONGTEXT DEFAULT NULL,
            categoria VARCHAR(40) NOT NULL,
            status VARCHAR(24) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resolvido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            INDEX IDX_JUR_CMP_EMP (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_portal_aprovacao (
            id INT AUTO_INCREMENT NOT NULL,
            aceito TINYINT(1) NOT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            cliente_id INT NOT NULL,
            documento_id INT DEFAULT NULL,
            processo_id INT DEFAULT NULL,
            INDEX IDX_JUR_PAP_CLI (cliente_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE juridico_publicacao_evento ADD CONSTRAINT FK_JUR_PUB_EVT_PUB FOREIGN KEY (publicacao_id) REFERENCES juridico_publicacao (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_processo_evento ADD CONSTRAINT FK_JUR_PROC_EVT_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_processo_evento ADD CONSTRAINT FK_JUR_PROC_EVT_PROC FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_template_peca ADD CONSTRAINT FK_JUR_TPL_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_template_peca ADD CONSTRAINT FK_JUR_TPL_APROV FOREIGN KEY (aprovado_por_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_audiencia ADD CONSTRAINT FK_JUR_AUD_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_audiencia ADD CONSTRAINT FK_JUR_AUD_PROC FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_audiencia ADD CONSTRAINT FK_JUR_AUD_USER FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_webhook_subscription ADD CONSTRAINT FK_JUR_WH_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_webhook_entrega ADD CONSTRAINT FK_JUR_WHD_SUB FOREIGN KEY (subscription_id) REFERENCES juridico_webhook_subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_conflito_check ADD CONSTRAINT FK_JUR_CONF_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_conflito_check ADD CONSTRAINT FK_JUR_CONF_PROC FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_conflito_check ADD CONSTRAINT FK_JUR_CONF_CLI FOREIGN KEY (cliente_id) REFERENCES juridico_cliente (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_compliance_incidente ADD CONSTRAINT FK_JUR_CMP_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_portal_aprovacao ADD CONSTRAINT FK_JUR_PAP_CLI FOREIGN KEY (cliente_id) REFERENCES juridico_cliente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_portal_aprovacao ADD CONSTRAINT FK_JUR_PAP_DOC FOREIGN KEY (documento_id) REFERENCES juridico_documento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_portal_aprovacao ADD CONSTRAINT FK_JUR_PAP_PROC FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_portal_aprovacao DROP FOREIGN KEY FK_JUR_PAP_CLI');
        $this->addSql('ALTER TABLE juridico_portal_aprovacao DROP FOREIGN KEY FK_JUR_PAP_DOC');
        $this->addSql('ALTER TABLE juridico_portal_aprovacao DROP FOREIGN KEY FK_JUR_PAP_PROC');
        $this->addSql('ALTER TABLE juridico_compliance_incidente DROP FOREIGN KEY FK_JUR_CMP_EMP');
        $this->addSql('ALTER TABLE juridico_conflito_check DROP FOREIGN KEY FK_JUR_CONF_EMP');
        $this->addSql('ALTER TABLE juridico_conflito_check DROP FOREIGN KEY FK_JUR_CONF_PROC');
        $this->addSql('ALTER TABLE juridico_conflito_check DROP FOREIGN KEY FK_JUR_CONF_CLI');
        $this->addSql('ALTER TABLE juridico_webhook_entrega DROP FOREIGN KEY FK_JUR_WHD_SUB');
        $this->addSql('ALTER TABLE juridico_webhook_subscription DROP FOREIGN KEY FK_JUR_WH_EMP');
        $this->addSql('ALTER TABLE juridico_audiencia DROP FOREIGN KEY FK_JUR_AUD_EMP');
        $this->addSql('ALTER TABLE juridico_audiencia DROP FOREIGN KEY FK_JUR_AUD_PROC');
        $this->addSql('ALTER TABLE juridico_audiencia DROP FOREIGN KEY FK_JUR_AUD_USER');
        $this->addSql('ALTER TABLE juridico_template_peca DROP FOREIGN KEY FK_JUR_TPL_EMP');
        $this->addSql('ALTER TABLE juridico_template_peca DROP FOREIGN KEY FK_JUR_TPL_APROV');
        $this->addSql('ALTER TABLE juridico_processo_evento DROP FOREIGN KEY FK_JUR_PROC_EVT_EMP');
        $this->addSql('ALTER TABLE juridico_processo_evento DROP FOREIGN KEY FK_JUR_PROC_EVT_PROC');
        $this->addSql('ALTER TABLE juridico_publicacao_evento DROP FOREIGN KEY FK_JUR_PUB_EVT_PUB');
        $this->addSql('DROP TABLE juridico_portal_aprovacao');
        $this->addSql('DROP TABLE juridico_compliance_incidente');
        $this->addSql('DROP TABLE juridico_conflito_check');
        $this->addSql('DROP TABLE juridico_webhook_entrega');
        $this->addSql('DROP TABLE juridico_webhook_subscription');
        $this->addSql('DROP TABLE juridico_audiencia');
        $this->addSql('DROP TABLE juridico_template_peca');
        $this->addSql('DROP TABLE juridico_processo_evento');
        $this->addSql('DROP TABLE juridico_publicacao_evento');
        $this->addSql('ALTER TABLE juridico_publicacao DROP pipeline_status, DROP prazo_gerado_em');
        $this->addSql('ALTER TABLE juridico_documento DROP texto_extraido, DROP ocr_em, DROP metadata_json, DROP precedente, DROP resultado_precedente, DROP assinatura_status, DROP assinatura_provider, DROP assinatura_ref');
    }
}
