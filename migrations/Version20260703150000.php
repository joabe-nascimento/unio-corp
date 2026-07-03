<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trilha de auditoria da plataforma (platform_audit_log) para /admin/operacoes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE platform_audit_log (
                id INT AUTO_INCREMENT NOT NULL,
                actor_id INT DEFAULT NULL,
                actor_email VARCHAR(180) DEFAULT NULL,
                actor_nome VARCHAR(120) DEFAULT NULL,
                categoria VARCHAR(24) NOT NULL,
                acao VARCHAR(32) NOT NULL,
                resultado VARCHAR(16) NOT NULL,
                alvo_tipo VARCHAR(64) DEFAULT NULL,
                alvo_id INT DEFAULT NULL,
                alvo_rotulo VARCHAR(255) DEFAULT NULL,
                rota VARCHAR(120) DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                mensagem LONGTEXT NOT NULL,
                payload JSON DEFAULT NULL,
                criado_em DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_platform_audit_criado (criado_em),
                INDEX idx_platform_audit_category (categoria, criado_em),
                INDEX IDX_PLATFORM_AUDIT_ACTOR (actor_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE platform_audit_log ADD CONSTRAINT FK_PLATFORM_AUDIT_ACTOR FOREIGN KEY (actor_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_audit_log DROP FOREIGN KEY FK_PLATFORM_AUDIT_ACTOR');
        $this->addSql('DROP TABLE platform_audit_log');
    }
}
