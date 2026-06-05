<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub TI — ativos, licenças, integrações, logs e manutenções';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ti_ativo (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            codigo VARCHAR(32) NOT NULL,
            tipo VARCHAR(48) NOT NULL,
            modelo VARCHAR(120) NOT NULL,
            responsavel VARCHAR(120) DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            ciclo_pct SMALLINT NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_TI_ATIVO_CODIGO (empresa_id, codigo),
            INDEX IDX_TI_ATIVO_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_ativo ADD CONSTRAINT FK_TI_ATIVO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE ti_licenca (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            nome VARCHAR(120) NOT NULL,
            seats SMALLINT NOT NULL,
            used SMALLINT NOT NULL,
            custo_mensal NUMERIC(12, 2) NOT NULL,
            renovacao_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_LICENCA_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_licenca ADD CONSTRAINT FK_TI_LICENCA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE ti_integracao (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            nome VARCHAR(120) NOT NULL,
            status VARCHAR(16) NOT NULL,
            latencia VARCHAR(16) NOT NULL,
            uptime NUMERIC(5, 2) NOT NULL,
            eventos24h INT NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_INTEGRACAO_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_integracao ADD CONSTRAINT FK_TI_INTEGRACAO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE ti_integracao_log (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            integracao_id INT DEFAULT NULL,
            conector VARCHAR(64) NOT NULL,
            nivel VARCHAR(16) NOT NULL,
            mensagem LONGTEXT NOT NULL,
            registrado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_INT_LOG_EMPRESA (empresa_id, registrado_em),
            INDEX IDX_TI_INT_LOG_INTEGRACAO (integracao_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_integracao_log ADD CONSTRAINT FK_TI_INT_LOG_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_integracao_log ADD CONSTRAINT FK_TI_INT_LOG_INTEGRACAO FOREIGN KEY (integracao_id) REFERENCES ti_integracao (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE ti_manutencao (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            janela VARCHAR(120) NOT NULL,
            impacto VARCHAR(180) NOT NULL,
            status VARCHAR(24) NOT NULL,
            owner VARCHAR(64) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_MANUTENCAO_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_manutencao ADD CONSTRAINT FK_TI_MANUTENCAO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_integracao_log DROP FOREIGN KEY FK_TI_INT_LOG_EMPRESA');
        $this->addSql('ALTER TABLE ti_integracao_log DROP FOREIGN KEY FK_TI_INT_LOG_INTEGRACAO');
        $this->addSql('DROP TABLE ti_integracao_log');
        $this->addSql('ALTER TABLE ti_integracao DROP FOREIGN KEY FK_TI_INTEGRACAO_EMPRESA');
        $this->addSql('DROP TABLE ti_integracao');
        $this->addSql('ALTER TABLE ti_manutencao DROP FOREIGN KEY FK_TI_MANUTENCAO_EMPRESA');
        $this->addSql('DROP TABLE ti_manutencao');
        $this->addSql('ALTER TABLE ti_licenca DROP FOREIGN KEY FK_TI_LICENCA_EMPRESA');
        $this->addSql('DROP TABLE ti_licenca');
        $this->addSql('ALTER TABLE ti_ativo DROP FOREIGN KEY FK_TI_ATIVO_EMPRESA');
        $this->addSql('DROP TABLE ti_ativo');
    }
}
