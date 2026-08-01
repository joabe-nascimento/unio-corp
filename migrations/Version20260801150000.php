<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Central de Atendimento — tickets omni-canal, mensagens e templates Sasha';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_atendimento_template (
            id INT AUTO_INCREMENT NOT NULL,
            titulo VARCHAR(120) NOT NULL,
            area VARCHAR(80) DEFAULT NULL,
            corpo LONGTEXT NOT NULL,
            ativo TINYINT(1) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            INDEX IDX_JUR_ATEND_TPL_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_atendimento_ticket (
            id INT AUTO_INCREMENT NOT NULL,
            assunto VARCHAR(200) NOT NULL,
            status VARCHAR(24) NOT NULL,
            canal VARCHAR(16) NOT NULL,
            prioridade VARCHAR(16) NOT NULL,
            sla_horas SMALLINT NOT NULL,
            sla_limite_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            primeira_resposta_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resolvido_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            cliente_id INT DEFAULT NULL,
            processo_id INT DEFAULT NULL,
            responsavel_id INT DEFAULT NULL,
            INDEX IDX_JUR_ATEND_TKT_EMPRESA_STATUS (empresa_id, status),
            INDEX IDX_JUR_ATEND_TKT_SLA (empresa_id, sla_limite_em),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_atendimento_mensagem (
            id INT AUTO_INCREMENT NOT NULL,
            direcao VARCHAR(12) NOT NULL,
            canal VARCHAR(16) NOT NULL,
            corpo LONGTEXT NOT NULL,
            remetente_nome VARCHAR(120) DEFAULT NULL,
            whatsapp_enviado TINYINT(1) NOT NULL,
            whatsapp_status VARCHAR(32) DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ticket_id INT NOT NULL,
            autor_id INT DEFAULT NULL,
            INDEX IDX_JUR_ATEND_MSG_TICKET (ticket_id, criado_em),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE juridico_atendimento_template ADD CONSTRAINT FK_JUR_ATEND_TPL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket ADD CONSTRAINT FK_JUR_ATEND_TKT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket ADD CONSTRAINT FK_JUR_ATEND_TKT_CLIENTE FOREIGN KEY (cliente_id) REFERENCES juridico_cliente (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket ADD CONSTRAINT FK_JUR_ATEND_TKT_PROCESSO FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket ADD CONSTRAINT FK_JUR_ATEND_TKT_RESP FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_atendimento_mensagem ADD CONSTRAINT FK_JUR_ATEND_MSG_TICKET FOREIGN KEY (ticket_id) REFERENCES juridico_atendimento_ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_atendimento_mensagem ADD CONSTRAINT FK_JUR_ATEND_MSG_AUTOR FOREIGN KEY (autor_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_atendimento_mensagem DROP FOREIGN KEY FK_JUR_ATEND_MSG_TICKET');
        $this->addSql('ALTER TABLE juridico_atendimento_mensagem DROP FOREIGN KEY FK_JUR_ATEND_MSG_AUTOR');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket DROP FOREIGN KEY FK_JUR_ATEND_TKT_EMPRESA');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket DROP FOREIGN KEY FK_JUR_ATEND_TKT_CLIENTE');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket DROP FOREIGN KEY FK_JUR_ATEND_TKT_PROCESSO');
        $this->addSql('ALTER TABLE juridico_atendimento_ticket DROP FOREIGN KEY FK_JUR_ATEND_TKT_RESP');
        $this->addSql('ALTER TABLE juridico_atendimento_template DROP FOREIGN KEY FK_JUR_ATEND_TPL_EMPRESA');
        $this->addSql('DROP TABLE juridico_atendimento_mensagem');
        $this->addSql('DROP TABLE juridico_atendimento_ticket');
        $this->addSql('DROP TABLE juridico_atendimento_template');
    }
}
