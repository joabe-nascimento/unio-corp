<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de aceite dos Termos/Privacidade no cadastro do usuário';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD termos_aceitos_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD termos_versao VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP termos_aceitos_em, DROP termos_versao');
    }
}
