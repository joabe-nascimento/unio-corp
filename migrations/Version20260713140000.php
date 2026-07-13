<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CRM pack: leads, contas, oportunidades e atividades (hub_comercial)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE crm_lead (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            responsavel_id INT DEFAULT NULL,
            nome VARCHAR(160) NOT NULL,
            email VARCHAR(180) DEFAULT NULL,
            telefone VARCHAR(40) DEFAULT NULL,
            empresa_nome VARCHAR(160) DEFAULT NULL,
            cargo VARCHAR(120) DEFAULT NULL,
            origem VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL,
            notas LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_crm_lead_empresa_status (empresa_id, status),
            INDEX IDX_CRM_LEAD_EMP (empresa_id),
            INDEX IDX_CRM_LEAD_RESP (responsavel_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE crm_conta (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            owner_id INT DEFAULT NULL,
            nome VARCHAR(180) NOT NULL,
            documento VARCHAR(18) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            telefone VARCHAR(40) DEFAULT NULL,
            site VARCHAR(180) DEFAULT NULL,
            segmento VARCHAR(120) DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            notas LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_crm_conta_empresa_status (empresa_id, status),
            INDEX IDX_CRM_CONTA_EMP (empresa_id),
            INDEX IDX_CRM_CONTA_OWNER (owner_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE crm_oportunidade (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            conta_id INT DEFAULT NULL,
            lead_id INT DEFAULT NULL,
            owner_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            estagio VARCHAR(24) NOT NULL,
            valor NUMERIC(14, 2) DEFAULT NULL,
            probabilidade SMALLINT NOT NULL,
            fecha_prevista DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            notas LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_crm_oportunidade_empresa_estagio (empresa_id, estagio),
            INDEX IDX_CRM_OP_EMP (empresa_id),
            INDEX IDX_CRM_OP_CONTA (conta_id),
            INDEX IDX_CRM_OP_LEAD (lead_id),
            INDEX IDX_CRM_OP_OWNER (owner_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE crm_atividade (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            lead_id INT DEFAULT NULL,
            conta_id INT DEFAULT NULL,
            oportunidade_id INT DEFAULT NULL,
            responsavel_id INT DEFAULT NULL,
            tipo VARCHAR(24) NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao LONGTEXT DEFAULT NULL,
            vence_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            concluida TINYINT(1) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_crm_atividade_empresa_done (empresa_id, concluida),
            INDEX IDX_CRM_AT_EMP (empresa_id),
            INDEX IDX_CRM_AT_LEAD (lead_id),
            INDEX IDX_CRM_AT_CONTA (conta_id),
            INDEX IDX_CRM_AT_OP (oportunidade_id),
            INDEX IDX_CRM_AT_RESP (responsavel_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE crm_lead ADD CONSTRAINT FK_CRM_LEAD_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_lead ADD CONSTRAINT FK_CRM_LEAD_RESP FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_conta ADD CONSTRAINT FK_CRM_CONTA_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_conta ADD CONSTRAINT FK_CRM_CONTA_OWNER FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_oportunidade ADD CONSTRAINT FK_CRM_OP_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_oportunidade ADD CONSTRAINT FK_CRM_OP_CONTA FOREIGN KEY (conta_id) REFERENCES crm_conta (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_oportunidade ADD CONSTRAINT FK_CRM_OP_LEAD FOREIGN KEY (lead_id) REFERENCES crm_lead (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_oportunidade ADD CONSTRAINT FK_CRM_OP_OWNER FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_atividade ADD CONSTRAINT FK_CRM_AT_EMP FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_atividade ADD CONSTRAINT FK_CRM_AT_LEAD FOREIGN KEY (lead_id) REFERENCES crm_lead (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_atividade ADD CONSTRAINT FK_CRM_AT_CONTA FOREIGN KEY (conta_id) REFERENCES crm_conta (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_atividade ADD CONSTRAINT FK_CRM_AT_OP FOREIGN KEY (oportunidade_id) REFERENCES crm_oportunidade (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_atividade ADD CONSTRAINT FK_CRM_AT_RESP FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_atividade DROP FOREIGN KEY FK_CRM_AT_EMP');
        $this->addSql('ALTER TABLE crm_atividade DROP FOREIGN KEY FK_CRM_AT_LEAD');
        $this->addSql('ALTER TABLE crm_atividade DROP FOREIGN KEY FK_CRM_AT_CONTA');
        $this->addSql('ALTER TABLE crm_atividade DROP FOREIGN KEY FK_CRM_AT_OP');
        $this->addSql('ALTER TABLE crm_atividade DROP FOREIGN KEY FK_CRM_AT_RESP');
        $this->addSql('ALTER TABLE crm_oportunidade DROP FOREIGN KEY FK_CRM_OP_EMP');
        $this->addSql('ALTER TABLE crm_oportunidade DROP FOREIGN KEY FK_CRM_OP_CONTA');
        $this->addSql('ALTER TABLE crm_oportunidade DROP FOREIGN KEY FK_CRM_OP_LEAD');
        $this->addSql('ALTER TABLE crm_oportunidade DROP FOREIGN KEY FK_CRM_OP_OWNER');
        $this->addSql('ALTER TABLE crm_conta DROP FOREIGN KEY FK_CRM_CONTA_EMP');
        $this->addSql('ALTER TABLE crm_conta DROP FOREIGN KEY FK_CRM_CONTA_OWNER');
        $this->addSql('ALTER TABLE crm_lead DROP FOREIGN KEY FK_CRM_LEAD_EMP');
        $this->addSql('ALTER TABLE crm_lead DROP FOREIGN KEY FK_CRM_LEAD_RESP');
        $this->addSql('DROP TABLE crm_atividade');
        $this->addSql('DROP TABLE crm_oportunidade');
        $this->addSql('DROP TABLE crm_conta');
        $this->addSql('DROP TABLE crm_lead');
    }
}
