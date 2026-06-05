<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub TI — tabela ti_chamado (service desk)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ti_chamado (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            solicitante_id INT NOT NULL,
            responsavel_id INT DEFAULT NULL,
            codigo VARCHAR(16) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            resumo LONGTEXT NOT NULL,
            categoria VARCHAR(32) NOT NULL,
            prioridade VARCHAR(4) NOT NULL,
            status VARCHAR(24) NOT NULL,
            impacto VARCHAR(16) NOT NULL,
            local VARCHAR(32) NOT NULL,
            asset_tag VARCHAR(32) DEFAULT NULL,
            catalog_item VARCHAR(64) DEFAULT NULL,
            usuarios_afetados SMALLINT NOT NULL,
            canal_contato VARCHAR(24) NOT NULL,
            telefone_contato VARCHAR(40) DEFAULT NULL,
            notificar_gestor TINYINT(1) NOT NULL,
            horario_preferido VARCHAR(120) DEFAULT NULL,
            sla_pct SMALLINT NOT NULL,
            helia_confianca SMALLINT DEFAULT NULL,
            helia_analise LONGTEXT DEFAULT NULL,
            helia_kb JSON NOT NULL,
            tags JSON NOT NULL,
            timeline JSON NOT NULL,
            aberto_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resolvido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_CHAMADO_EMPRESA_STATUS (empresa_id, status),
            INDEX IDX_TI_CHAMADO_EMPRESA_ABERTO (empresa_id, aberto_em),
            UNIQUE INDEX UNIQ_TI_CHAMADO_CODIGO (empresa_id, codigo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_chamado ADD CONSTRAINT FK_TI_CHAMADO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_chamado ADD CONSTRAINT FK_TI_CHAMADO_SOLICITANTE FOREIGN KEY (solicitante_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_chamado ADD CONSTRAINT FK_TI_CHAMADO_RESPONSAVEL FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_chamado DROP FOREIGN KEY FK_TI_CHAMADO_EMPRESA');
        $this->addSql('ALTER TABLE ti_chamado DROP FOREIGN KEY FK_TI_CHAMADO_SOLICITANTE');
        $this->addSql('ALTER TABLE ti_chamado DROP FOREIGN KEY FK_TI_CHAMADO_RESPONSAVEL');
        $this->addSql('DROP TABLE ti_chamado');
    }
}
