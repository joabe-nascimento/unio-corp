<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notificações da plataforma (core) com persistência por workspace';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_notificacao (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, user_id INT NOT NULL, modulo VARCHAR(32) NOT NULL, tipo VARCHAR(48) NOT NULL, titulo VARCHAR(180) NOT NULL, mensagem LONGTEXT NOT NULL, route_name VARCHAR(64) DEFAULT NULL, route_params JSON DEFAULT NULL, icon VARCHAR(48) NOT NULL, severidade VARCHAR(16) NOT NULL, lida TINYINT(1) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PLATFORM_NOTIF_USER (empresa_id, user_id, lida, criado_em), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE platform_notificacao ADD CONSTRAINT FK_PLATFORM_NOTIF_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE platform_notificacao ADD CONSTRAINT FK_PLATFORM_NOTIF_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_notificacao DROP FOREIGN KEY FK_PLATFORM_NOTIF_EMPRESA');
        $this->addSql('ALTER TABLE platform_notificacao DROP FOREIGN KEY FK_PLATFORM_NOTIF_USER');
        $this->addSql('DROP TABLE platform_notificacao');
    }
}
