<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RH eSocial: tentativas e ultimo_erro na fila de lotes';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['rh_esocial_lote'])) {
            return;
        }

        $cols = $sm->listTableColumns('rh_esocial_lote');
        if (!isset($cols['tentativas'])) {
            $this->addSql('ALTER TABLE rh_esocial_lote ADD tentativas INT NOT NULL DEFAULT 0');
        }
        if (!isset($cols['ultimo_erro'])) {
            $this->addSql('ALTER TABLE rh_esocial_lote ADD ultimo_erro VARCHAR(500) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['rh_esocial_lote'])) {
            return;
        }

        $cols = $sm->listTableColumns('rh_esocial_lote');
        if (isset($cols['ultimo_erro'])) {
            $this->addSql('ALTER TABLE rh_esocial_lote DROP ultimo_erro');
        }
        if (isset($cols['tentativas'])) {
            $this->addSql('ALTER TABLE rh_esocial_lote DROP tentativas');
        }
    }
}
