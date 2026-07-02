<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Checklist de onboarding persistente por usuário (onboarding_completed_steps)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD onboarding_completed_steps JSON DEFAULT NULL');
        $this->addSql("UPDATE `user` SET onboarding_completed_steps = '[]' WHERE onboarding_completed_steps IS NULL");
        $this->addSql('ALTER TABLE `user` MODIFY onboarding_completed_steps JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP onboarding_completed_steps');
    }
}
