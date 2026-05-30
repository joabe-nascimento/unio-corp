<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub Inovação — ideias, decisões, conexões, impacto, tendências e novidades';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inov_ideia (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            autor_id INT DEFAULT NULL,
            codigo VARCHAR(16) NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            resumo LONGTEXT DEFAULT NULL,
            problema LONGTEXT DEFAULT NULL,
            hipotese LONGTEXT DEFAULT NULL,
            metrica_sucesso LONGTEXT DEFAULT NULL,
            metodo_teste LONGTEXT DEFAULT NULL,
            estagio VARCHAR(24) NOT NULL,
            impacto SMALLINT NOT NULL,
            esforco SMALLINT NOT NULL,
            votos SMALLINT NOT NULL,
            progresso SMALLINT NOT NULL,
            rigor SMALLINT DEFAULT NULL,
            tags JSON NOT NULL,
            hub_relacionado VARCHAR(64) DEFAULT NULL,
            owner_nome VARCHAR(120) DEFAULT NULL,
            metrica VARCHAR(180) DEFAULT NULL,
            categoria VARCHAR(32) DEFAULT NULL,
            urgencia VARCHAR(16) DEFAULT NULL,
            arquivado TINYINT(1) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_INOV_IDEIA_EMPRESA (empresa_id),
            INDEX IDX_INOV_IDEIA_AUTOR (autor_id),
            INDEX IDX_INOV_IDEIA_ESTAGIO (empresa_id, estagio),
            INDEX IDX_INOV_IDEIA_ARQUIVADO (empresa_id, arquivado),
            UNIQUE INDEX UNIQ_INOV_IDEIA_CODIGO (empresa_id, codigo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inov_ideia ADD CONSTRAINT FK_INOV_IDEIA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inov_ideia ADD CONSTRAINT FK_INOV_IDEIA_AUTOR FOREIGN KEY (autor_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE inov_decisao (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            ideia_id INT DEFAULT NULL,
            autor_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            tipo VARCHAR(16) NOT NULL,
            motivo LONGTEXT NOT NULL,
            owner_nome VARCHAR(120) DEFAULT NULL,
            decidido_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_INOV_DECISAO_EMPRESA (empresa_id),
            INDEX IDX_INOV_DECISAO_IDEIA (ideia_id),
            INDEX IDX_INOV_DECISAO_AUTOR (autor_id),
            INDEX IDX_INOV_DECISAO_TIPO (empresa_id, tipo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inov_decisao ADD CONSTRAINT FK_INOV_DECISAO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inov_decisao ADD CONSTRAINT FK_INOV_DECISAO_IDEIA FOREIGN KEY (ideia_id) REFERENCES inov_ideia (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inov_decisao ADD CONSTRAINT FK_INOV_DECISAO_AUTOR FOREIGN KEY (autor_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE inov_conexao (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            autor_id INT DEFAULT NULL,
            hub VARCHAR(64) NOT NULL,
            icon VARCHAR(32) NOT NULL,
            sinergia SMALLINT NOT NULL,
            status VARCHAR(16) NOT NULL,
            oportunidade LONGTEXT NOT NULL,
            acao VARCHAR(180) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_INOV_CONEXAO_EMPRESA (empresa_id),
            INDEX IDX_INOV_CONEXAO_AUTOR (autor_id),
            INDEX IDX_INOV_CONEXAO_STATUS (empresa_id, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inov_conexao ADD CONSTRAINT FK_INOV_CONEXAO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inov_conexao ADD CONSTRAINT FK_INOV_CONEXAO_AUTOR FOREIGN KEY (autor_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE inov_impact_entry (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            ideia_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            estagio_label VARCHAR(32) NOT NULL,
            valor_capturado VARCHAR(32) DEFAULT NULL,
            roi VARCHAR(16) DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_INOV_IMPACT_EMPRESA (empresa_id),
            INDEX IDX_INOV_IMPACT_IDEIA (ideia_id),
            INDEX IDX_INOV_IMPACT_STATUS (empresa_id, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inov_impact_entry ADD CONSTRAINT FK_INOV_IMPACT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inov_impact_entry ADD CONSTRAINT FK_INOV_IMPACT_IDEIA FOREIGN KEY (ideia_id) REFERENCES inov_ideia (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE inov_tendencia (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            label VARCHAR(120) NOT NULL,
            valor SMALLINT NOT NULL,
            hint LONGTEXT DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            ordem SMALLINT NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_INOV_TENDENCIA_EMPRESA (empresa_id),
            INDEX IDX_INOV_TENDENCIA_ORDEM (empresa_id, ordem),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inov_tendencia ADD CONSTRAINT FK_INOV_TENDENCIA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE inov_novidade (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            autor_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            resumo LONGTEXT NOT NULL,
            icon VARCHAR(32) NOT NULL,
            route_name VARCHAR(64) DEFAULT NULL,
            badge VARCHAR(32) DEFAULT NULL,
            variant VARCHAR(16) NOT NULL,
            publicado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_INOV_NOVIDADE_EMPRESA (empresa_id),
            INDEX IDX_INOV_NOVIDADE_AUTOR (autor_id),
            INDEX IDX_INOV_NOVIDADE_PUBLICADO (empresa_id, publicado_em),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inov_novidade ADD CONSTRAINT FK_INOV_NOVIDADE_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inov_novidade ADD CONSTRAINT FK_INOV_NOVIDADE_AUTOR FOREIGN KEY (autor_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inov_novidade DROP FOREIGN KEY FK_INOV_NOVIDADE_EMPRESA');
        $this->addSql('ALTER TABLE inov_novidade DROP FOREIGN KEY FK_INOV_NOVIDADE_AUTOR');
        $this->addSql('DROP TABLE inov_novidade');

        $this->addSql('ALTER TABLE inov_tendencia DROP FOREIGN KEY FK_INOV_TENDENCIA_EMPRESA');
        $this->addSql('DROP TABLE inov_tendencia');

        $this->addSql('ALTER TABLE inov_impact_entry DROP FOREIGN KEY FK_INOV_IMPACT_EMPRESA');
        $this->addSql('ALTER TABLE inov_impact_entry DROP FOREIGN KEY FK_INOV_IMPACT_IDEIA');
        $this->addSql('DROP TABLE inov_impact_entry');

        $this->addSql('ALTER TABLE inov_conexao DROP FOREIGN KEY FK_INOV_CONEXAO_EMPRESA');
        $this->addSql('ALTER TABLE inov_conexao DROP FOREIGN KEY FK_INOV_CONEXAO_AUTOR');
        $this->addSql('DROP TABLE inov_conexao');

        $this->addSql('ALTER TABLE inov_decisao DROP FOREIGN KEY FK_INOV_DECISAO_EMPRESA');
        $this->addSql('ALTER TABLE inov_decisao DROP FOREIGN KEY FK_INOV_DECISAO_IDEIA');
        $this->addSql('ALTER TABLE inov_decisao DROP FOREIGN KEY FK_INOV_DECISAO_AUTOR');
        $this->addSql('DROP TABLE inov_decisao');

        $this->addSql('ALTER TABLE inov_ideia DROP FOREIGN KEY FK_INOV_IDEIA_EMPRESA');
        $this->addSql('ALTER TABLE inov_ideia DROP FOREIGN KEY FK_INOV_IDEIA_AUTOR');
        $this->addSql('DROP TABLE inov_ideia');
    }
}
