<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de leitura de notícias da tela de boas-vindas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE welcome_news_read (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            empresa_id INT DEFAULT NULL,
            article_key VARCHAR(120) NOT NULL,
            read_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_WELCOME_NEWS_READ_USER (user_id),
            INDEX IDX_WELCOME_NEWS_READ_EMPRESA (empresa_id),
            UNIQUE INDEX UNIQ_WELCOME_NEWS_READ (user_id, article_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE welcome_news_read ADD CONSTRAINT FK_WELCOME_NEWS_READ_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE welcome_news_read ADD CONSTRAINT FK_WELCOME_NEWS_READ_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE welcome_news_read DROP FOREIGN KEY FK_WELCOME_NEWS_READ_USER');
        $this->addSql('ALTER TABLE welcome_news_read DROP FOREIGN KEY FK_WELCOME_NEWS_READ_EMPRESA');
        $this->addSql('DROP TABLE welcome_news_read');
    }
}
