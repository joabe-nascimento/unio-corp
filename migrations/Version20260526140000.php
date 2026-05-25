<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projetos e metas de desenvolvimento (Core) — quadro pessoal da plataforma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS eng_tarefa');
        $this->addSql('DROP TABLE IF EXISTS eng_meta');
        $this->addSql('DROP TABLE IF EXISTS eng_projeto');

        $this->addSql('CREATE TABLE dev_projeto (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            nome VARCHAR(150) NOT NULL,
            codigo VARCHAR(32) DEFAULT NULL,
            descricao LONGTEXT DEFAULT NULL,
            area VARCHAR(64) DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            cor VARCHAR(7) DEFAULT NULL,
            progresso SMALLINT NOT NULL DEFAULT 0,
            data_alvo DATE DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_DEV_PROJETO_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dev_projeto ADD CONSTRAINT FK_DEV_PROJETO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE dev_meta (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            projeto_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao LONGTEXT DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            prioridade VARCHAR(16) NOT NULL DEFAULT \'MEDIA\',
            progresso_percent SMALLINT NOT NULL DEFAULT 0,
            data_alvo DATE DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_DEV_META_EMPRESA (empresa_id),
            INDEX IDX_DEV_META_PROJETO (projeto_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dev_meta ADD CONSTRAINT FK_DEV_META_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dev_meta ADD CONSTRAINT FK_DEV_META_PROJETO FOREIGN KEY (projeto_id) REFERENCES dev_projeto (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE dev_tarefa (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            projeto_id INT NOT NULL,
            meta_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao LONGTEXT DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            prioridade VARCHAR(16) NOT NULL DEFAULT \'MEDIA\',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_DEV_TAREFA_EMPRESA (empresa_id),
            INDEX IDX_DEV_TAREFA_PROJETO (projeto_id),
            INDEX IDX_DEV_TAREFA_META (meta_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dev_tarefa ADD CONSTRAINT FK_DEV_TAREFA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dev_tarefa ADD CONSTRAINT FK_DEV_TAREFA_PROJETO FOREIGN KEY (projeto_id) REFERENCES dev_projeto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dev_tarefa ADD CONSTRAINT FK_DEV_TAREFA_META FOREIGN KEY (meta_id) REFERENCES dev_meta (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dev_tarefa DROP FOREIGN KEY FK_DEV_TAREFA_EMPRESA');
        $this->addSql('ALTER TABLE dev_tarefa DROP FOREIGN KEY FK_DEV_TAREFA_PROJETO');
        $this->addSql('ALTER TABLE dev_tarefa DROP FOREIGN KEY FK_DEV_TAREFA_META');
        $this->addSql('DROP TABLE dev_tarefa');
        $this->addSql('ALTER TABLE dev_meta DROP FOREIGN KEY FK_DEV_META_EMPRESA');
        $this->addSql('ALTER TABLE dev_meta DROP FOREIGN KEY FK_DEV_META_PROJETO');
        $this->addSql('DROP TABLE dev_meta');
        $this->addSql('ALTER TABLE dev_projeto DROP FOREIGN KEY FK_DEV_PROJETO_EMPRESA');
        $this->addSql('DROP TABLE dev_projeto');
    }
}
