<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela user_product_grant para permissões granulares por produto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_product_grant (id INT AUTO_INCREMENT NOT NULL, scope VARCHAR(64) NOT NULL, product_id VARCHAR(64) NOT NULL, perfil_grant VARCHAR(32) NOT NULL, atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_id INT NOT NULL, INDEX IDX_86D7B040A76ED395 (user_id), UNIQUE INDEX uniq_user_scope_product (user_id, scope, product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_product_grant ADD CONSTRAINT FK_86D7B040A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_product_grant DROP FOREIGN KEY FK_86D7B040A76ED395');
        $this->addSql('DROP TABLE user_product_grant');
    }
}
