<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publicações & Intimações — captura DJEN, triagem IA e vínculo com processos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_publicacao_captura (
            id INT AUTO_INCREMENT NOT NULL,
            numero_oab VARCHAR(12) NOT NULL,
            uf_oab VARCHAR(2) NOT NULL,
            ativo TINYINT(1) NOT NULL,
            ultima_captura_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            INDEX IDX_JUR_PUB_CAP_EMPRESA (empresa_id),
            UNIQUE INDEX UNIQ_JUR_PUB_CAP_OAB (empresa_id, numero_oab, uf_oab),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE juridico_publicacao (
            id INT AUTO_INCREMENT NOT NULL,
            djen_id BIGINT DEFAULT NULL,
            hash VARCHAR(64) DEFAULT NULL,
            fonte VARCHAR(16) NOT NULL,
            numero_processo VARCHAR(40) DEFAULT NULL,
            numero_processo_norm VARCHAR(20) DEFAULT NULL,
            tipo_comunicacao VARCHAR(80) DEFAULT NULL,
            tipo_documento VARCHAR(80) DEFAULT NULL,
            tribunal VARCHAR(16) DEFAULT NULL,
            orgao VARCHAR(180) DEFAULT NULL,
            classe VARCHAR(120) DEFAULT NULL,
            data_disponibilizacao DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\',
            texto LONGTEXT DEFAULT NULL,
            link VARCHAR(500) DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            prioridade VARCHAR(16) NOT NULL,
            ia_classificacao VARCHAR(80) DEFAULT NULL,
            ia_resumo LONGTEXT DEFAULT NULL,
            ia_sugestao_acao LONGTEXT DEFAULT NULL,
            ia_sugestao_prazo_dias SMALLINT DEFAULT NULL,
            ia_sugestao_tipo_prazo VARCHAR(80) DEFAULT NULL,
            motivo_cancelamento VARCHAR(255) DEFAULT NULL,
            lida_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            triada_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            prazo_criado TINYINT(1) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            empresa_id INT NOT NULL,
            processo_id INT DEFAULT NULL,
            cliente_id INT DEFAULT NULL,
            triada_por_id INT DEFAULT NULL,
            INDEX IDX_JUR_PUB_EMPRESA_STATUS (empresa_id, status),
            INDEX IDX_JUR_PUB_EMPRESA_PRIOR (empresa_id, prioridade),
            INDEX IDX_JUR_PUB_NUMERO_NORM (empresa_id, numero_processo_norm),
            UNIQUE INDEX UNIQ_JUR_PUB_DJEN (empresa_id, djen_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE juridico_publicacao_captura ADD CONSTRAINT FK_JUR_PUB_CAP_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_publicacao ADD CONSTRAINT FK_JUR_PUB_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_publicacao ADD CONSTRAINT FK_JUR_PUB_PROCESSO FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_publicacao ADD CONSTRAINT FK_JUR_PUB_CLIENTE FOREIGN KEY (cliente_id) REFERENCES juridico_cliente (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_publicacao ADD CONSTRAINT FK_JUR_PUB_TRIADA FOREIGN KEY (triada_por_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_publicacao DROP FOREIGN KEY FK_JUR_PUB_EMPRESA');
        $this->addSql('ALTER TABLE juridico_publicacao DROP FOREIGN KEY FK_JUR_PUB_PROCESSO');
        $this->addSql('ALTER TABLE juridico_publicacao DROP FOREIGN KEY FK_JUR_PUB_CLIENTE');
        $this->addSql('ALTER TABLE juridico_publicacao DROP FOREIGN KEY FK_JUR_PUB_TRIADA');
        $this->addSql('ALTER TABLE juridico_publicacao_captura DROP FOREIGN KEY FK_JUR_PUB_CAP_EMPRESA');
        $this->addSql('DROP TABLE juridico_publicacao');
        $this->addSql('DROP TABLE juridico_publicacao_captura');
    }
}
