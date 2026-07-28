<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unio Jurídico Enterprise: API pública (tokens), integração DataJud/tribunais e multi-escritório (grupo matriz/filial)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_token (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(120) NOT NULL, token_hash VARCHAR(64) NOT NULL, token_prefix VARCHAR(16) NOT NULL, scopes JSON NOT NULL, ativo TINYINT NOT NULL, criado_em DATETIME NOT NULL, ultimo_uso_em DATETIME DEFAULT NULL, revogado_em DATETIME DEFAULT NULL, total_requisicoes INT NOT NULL DEFAULT 0, empresa_id INT NOT NULL, criado_por_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_API_TOKEN_HASH (token_hash), INDEX IDX_API_TOKEN_EMPRESA (empresa_id), INDEX IDX_API_TOKEN_CRIADO_POR (criado_por_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_API_TOKEN_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_API_TOKEN_CRIADO_POR FOREIGN KEY (criado_por_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE juridico_tribunal_config (id INT AUTO_INCREMENT NOT NULL, datajud_api_key VARCHAR(120) DEFAULT NULL, ativo TINYINT NOT NULL, ultimo_teste_em DATETIME DEFAULT NULL, ultimo_teste_ok TINYINT NOT NULL, ultimo_teste_mensagem LONGTEXT DEFAULT NULL, total_consultas INT NOT NULL DEFAULT 0, criado_em DATETIME NOT NULL, atualizado_em DATETIME DEFAULT NULL, empresa_id INT NOT NULL, UNIQUE INDEX UNIQ_JUR_TRIB_EMPRESA (empresa_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE juridico_tribunal_config ADD CONSTRAINT FK_JUR_TRIB_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE empresa ADD codigo_grupo VARCHAR(12) DEFAULT NULL, ADD empresa_matriz_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EMPRESA_CODIGO_GRUPO ON empresa (codigo_grupo)');
        $this->addSql('CREATE INDEX IDX_EMPRESA_MATRIZ ON empresa (empresa_matriz_id)');
        $this->addSql('ALTER TABLE empresa ADD CONSTRAINT FK_EMPRESA_MATRIZ FOREIGN KEY (empresa_matriz_id) REFERENCES empresa (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_API_TOKEN_EMPRESA');
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_API_TOKEN_CRIADO_POR');
        $this->addSql('DROP TABLE api_token');

        $this->addSql('ALTER TABLE juridico_tribunal_config DROP FOREIGN KEY FK_JUR_TRIB_EMPRESA');
        $this->addSql('DROP TABLE juridico_tribunal_config');

        $this->addSql('ALTER TABLE empresa DROP FOREIGN KEY FK_EMPRESA_MATRIZ');
        $this->addSql('DROP INDEX UNIQ_EMPRESA_CODIGO_GRUPO ON empresa');
        $this->addSql('DROP INDEX IDX_EMPRESA_MATRIZ ON empresa');
        $this->addSql('ALTER TABLE empresa DROP codigo_grupo, DROP empresa_matriz_id');
    }
}
