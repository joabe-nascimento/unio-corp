<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub TI — KB, problemas, playbooks, notificações e extensões em ti_chamado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ti_kb_artigo (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, codigo VARCHAR(16) NOT NULL, titulo VARCHAR(200) NOT NULL, resumo LONGTEXT NOT NULL, conteudo LONGTEXT DEFAULT NULL, categoria VARCHAR(32) NOT NULL, tags JSON NOT NULL, visualizacoes INT NOT NULL DEFAULT 0, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TI_KB_EMPRESA (empresa_id), UNIQUE INDEX UNIQ_TI_KB_CODIGO (empresa_id, codigo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ti_problema (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, codigo VARCHAR(16) NOT NULL, titulo VARCHAR(200) NOT NULL, resumo LONGTEXT NOT NULL, status VARCHAR(24) NOT NULL, causa_raiz LONGTEXT DEFAULT NULL, prioridade VARCHAR(4) NOT NULL, categoria VARCHAR(32) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TI_PROBLEMA_EMPRESA (empresa_id), UNIQUE INDEX UNIQ_TI_PROBLEMA_CODIGO (empresa_id, codigo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ti_playbook (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, titulo VARCHAR(180) NOT NULL, gatilho VARCHAR(120) NOT NULL, categoria VARCHAR(32) DEFAULT NULL, prioridade VARCHAR(4) DEFAULT NULL, passos JSON NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TI_PLAYBOOK_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ti_notificacao (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, user_id INT NOT NULL, tipo VARCHAR(32) NOT NULL, titulo VARCHAR(180) NOT NULL, mensagem LONGTEXT NOT NULL, link VARCHAR(255) DEFAULT NULL, lida TINYINT(1) NOT NULL DEFAULT 0, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TI_NOTIF_EMPRESA (empresa_id), INDEX IDX_TI_NOTIF_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_kb_artigo ADD CONSTRAINT FK_TI_KB_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_problema ADD CONSTRAINT FK_TI_PROBLEMA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_playbook ADD CONSTRAINT FK_TI_PLAYBOOK_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_notificacao ADD CONSTRAINT FK_TI_NOTIF_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_notificacao ADD CONSTRAINT FK_TI_NOTIF_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_chamado ADD problema_id INT DEFAULT NULL, ADD ativo_id INT DEFAULT NULL, ADD sla_pausado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD sla_pausado_motivo VARCHAR(120) DEFAULT NULL, ADD sla_pausado_acumulado_seg INT NOT NULL DEFAULT 0, ADD helia_feedback VARCHAR(16) DEFAULT NULL, ADD helia_feedback_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ti_chamado ADD CONSTRAINT FK_TI_CHAMADO_PROBLEMA FOREIGN KEY (problema_id) REFERENCES ti_problema (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ti_chamado ADD CONSTRAINT FK_TI_CHAMADO_ATIVO FOREIGN KEY (ativo_id) REFERENCES ti_ativo (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_TI_CHAMADO_PROBLEMA ON ti_chamado (problema_id)');
        $this->addSql('CREATE INDEX IDX_TI_CHAMADO_ATIVO ON ti_chamado (ativo_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_chamado DROP FOREIGN KEY FK_TI_CHAMADO_PROBLEMA');
        $this->addSql('ALTER TABLE ti_chamado DROP FOREIGN KEY FK_TI_CHAMADO_ATIVO');
        $this->addSql('DROP INDEX IDX_TI_CHAMADO_PROBLEMA ON ti_chamado');
        $this->addSql('DROP INDEX IDX_TI_CHAMADO_ATIVO ON ti_chamado');
        $this->addSql('ALTER TABLE ti_chamado DROP problema_id, DROP ativo_id, DROP sla_pausado_em, DROP sla_pausado_motivo, DROP sla_pausado_acumulado_seg, DROP helia_feedback, DROP helia_feedback_em');
        $this->addSql('ALTER TABLE ti_notificacao DROP FOREIGN KEY FK_TI_NOTIF_EMPRESA');
        $this->addSql('ALTER TABLE ti_notificacao DROP FOREIGN KEY FK_TI_NOTIF_USER');
        $this->addSql('ALTER TABLE ti_playbook DROP FOREIGN KEY FK_TI_PLAYBOOK_EMPRESA');
        $this->addSql('ALTER TABLE ti_problema DROP FOREIGN KEY FK_TI_PROBLEMA_EMPRESA');
        $this->addSql('ALTER TABLE ti_kb_artigo DROP FOREIGN KEY FK_TI_KB_EMPRESA');
        $this->addSql('DROP TABLE ti_notificacao');
        $this->addSql('DROP TABLE ti_playbook');
        $this->addSql('DROP TABLE ti_problema');
        $this->addSql('DROP TABLE ti_kb_artigo');
    }
}
