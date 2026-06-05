<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chat — exclusão lógica, anexos e respostas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD file_path VARCHAR(255) DEFAULT NULL, ADD file_name VARCHAR(255) DEFAULT NULL, ADD file_mime VARCHAR(120) DEFAULT NULL, ADD reply_to_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MSG_REPLY FOREIGN KEY (reply_to_id) REFERENCES chat_message (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CHAT_MSG_REPLY ON chat_message (reply_to_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MSG_REPLY');
        $this->addSql('DROP INDEX IDX_CHAT_MSG_REPLY ON chat_message');
        $this->addSql('ALTER TABLE chat_message DROP deleted_at, DROP file_path, DROP file_name, DROP file_mime, DROP reply_to_id');
    }
}
