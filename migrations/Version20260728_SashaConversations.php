<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728_SashaConversations extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabelas para histórico persistente de conversas da Sasha';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sasha_conversation (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            empresa_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            context VARCHAR(50) DEFAULT NULL,
            context_id VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            pinned TINYINT(1) DEFAULT 0 NOT NULL,
            INDEX IDX_SASHA_CONV_USER (user_id),
            INDEX IDX_SASHA_CONV_EMPRESA (empresa_id),
            INDEX IDX_SASHA_CONV_CONTEXT (context, context_id),
            INDEX IDX_SASHA_CONV_UPDATED (updated_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE sasha_message (
            id INT AUTO_INCREMENT NOT NULL,
            conversation_id INT NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            rating INT DEFAULT NULL,
            feedback LONGTEXT DEFAULT NULL,
            INDEX IDX_SASHA_MSG_CONVERSATION (conversation_id),
            INDEX IDX_SASHA_MSG_CREATED (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE sasha_conversation ADD CONSTRAINT FK_SASHA_CONV_USER_FK FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sasha_conversation ADD CONSTRAINT FK_SASHA_CONV_EMPRESA_FK FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sasha_message ADD CONSTRAINT FK_SASHA_MSG_CONVERSATION_FK FOREIGN KEY (conversation_id) REFERENCES sasha_conversation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sasha_message DROP FOREIGN KEY FK_SASHA_MSG_CONVERSATION_FK');
        $this->addSql('ALTER TABLE sasha_conversation DROP FOREIGN KEY FK_SASHA_CONV_USER_FK');
        $this->addSql('ALTER TABLE sasha_conversation DROP FOREIGN KEY FK_SASHA_CONV_EMPRESA_FK');
        $this->addSql('DROP TABLE sasha_message');
        $this->addSql('DROP TABLE sasha_conversation');
    }
}
