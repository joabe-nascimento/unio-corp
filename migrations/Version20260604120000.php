<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chat Bate Papo — conversas, mensagens, participantes e sinalização de voz';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE chat_conversation (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, type VARCHAR(16) NOT NULL, name VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CHAT_CONV_EMPRESA (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chat_participant (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, user_id INT NOT NULL, joined_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_chat_participant (conversation_id, user_id), INDEX IDX_CHAT_PART_CONV (conversation_id), INDEX IDX_CHAT_PART_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chat_message (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, author_id INT DEFAULT NULL, body LONGTEXT DEFAULT NULL, message_type VARCHAR(16) NOT NULL, voice_path VARCHAR(255) DEFAULT NULL, voice_duration_ms INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CHAT_MSG_CONV (conversation_id), INDEX IDX_CHAT_MSG_AUTHOR (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chat_call_signal (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, from_user_id INT NOT NULL, to_user_id INT DEFAULT NULL, signal_type VARCHAR(24) NOT NULL, payload LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CHAT_CALL_CONV (conversation_id), INDEX IDX_CHAT_CALL_FROM (from_user_id), INDEX IDX_CHAT_CALL_TO (to_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE chat_conversation ADD CONSTRAINT FK_CHAT_CONV_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_participant ADD CONSTRAINT FK_CHAT_PART_CONV FOREIGN KEY (conversation_id) REFERENCES chat_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_participant ADD CONSTRAINT FK_CHAT_PART_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MSG_CONV FOREIGN KEY (conversation_id) REFERENCES chat_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MSG_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE chat_call_signal ADD CONSTRAINT FK_CHAT_CALL_CONV FOREIGN KEY (conversation_id) REFERENCES chat_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_call_signal ADD CONSTRAINT FK_CHAT_CALL_FROM FOREIGN KEY (from_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_call_signal ADD CONSTRAINT FK_CHAT_CALL_TO FOREIGN KEY (to_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_call_signal DROP FOREIGN KEY FK_CHAT_CALL_CONV');
        $this->addSql('ALTER TABLE chat_call_signal DROP FOREIGN KEY FK_CHAT_CALL_FROM');
        $this->addSql('ALTER TABLE chat_call_signal DROP FOREIGN KEY FK_CHAT_CALL_TO');
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MSG_CONV');
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MSG_AUTHOR');
        $this->addSql('ALTER TABLE chat_participant DROP FOREIGN KEY FK_CHAT_PART_CONV');
        $this->addSql('ALTER TABLE chat_participant DROP FOREIGN KEY FK_CHAT_PART_USER');
        $this->addSql('ALTER TABLE chat_conversation DROP FOREIGN KEY FK_CHAT_CONV_EMPRESA');
        $this->addSql('DROP TABLE chat_call_signal');
        $this->addSql('DROP TABLE chat_message');
        $this->addSql('DROP TABLE chat_participant');
        $this->addSql('DROP TABLE chat_conversation');
    }
}
