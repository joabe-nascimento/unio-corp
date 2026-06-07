<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gestão de Pessoas: equipes enriquecidas, catálogo de cargos e avaliações';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE departamento ADD descricao LONGTEXT DEFAULT NULL, ADD area VARCHAR(80) DEFAULT NULL, ADD lider_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE departamento ADD CONSTRAINT FK_DEPARTAMENTO_LIDER FOREIGN KEY (lider_id) REFERENCES funcionario (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DEPARTAMENTO_LIDER ON departamento (lider_id)');

        $this->addSql('CREATE TABLE pessoas_cargo (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, titulo VARCHAR(120) NOT NULL, descricao LONGTEXT DEFAULT NULL, area VARCHAR(80) DEFAULT NULL, nivel VARCHAR(32) DEFAULT NULL, ativo TINYINT(1) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PESSOAS_CARGO_EMPRESA (empresa_id), UNIQUE INDEX UNIQ_PESSOAS_CARGO_EMPRESA_TITULO (empresa_id, titulo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pessoas_cargo ADD CONSTRAINT FK_PESSOAS_CARGO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE pessoas_avaliacao (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, funcionario_id INT NOT NULL, avaliador_id INT DEFAULT NULL, nota NUMERIC(3, 1) NOT NULL, periodo VARCHAR(32) NOT NULL, comentario LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PESSOAS_AVAL_EMPRESA (empresa_id), INDEX IDX_PESSOAS_AVAL_FUNC (funcionario_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pessoas_avaliacao ADD CONSTRAINT FK_PESSOAS_AVAL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pessoas_avaliacao ADD CONSTRAINT FK_PESSOAS_AVAL_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pessoas_avaliacao ADD CONSTRAINT FK_PESSOAS_AVAL_AVALIADOR FOREIGN KEY (avaliador_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pessoas_avaliacao DROP FOREIGN KEY FK_PESSOAS_AVAL_EMPRESA');
        $this->addSql('ALTER TABLE pessoas_avaliacao DROP FOREIGN KEY FK_PESSOAS_AVAL_FUNC');
        $this->addSql('ALTER TABLE pessoas_avaliacao DROP FOREIGN KEY FK_PESSOAS_AVAL_AVALIADOR');
        $this->addSql('DROP TABLE pessoas_avaliacao');

        $this->addSql('ALTER TABLE pessoas_cargo DROP FOREIGN KEY FK_PESSOAS_CARGO_EMPRESA');
        $this->addSql('DROP TABLE pessoas_cargo');

        $this->addSql('ALTER TABLE departamento DROP FOREIGN KEY FK_DEPARTAMENTO_LIDER');
        $this->addSql('DROP INDEX IDX_DEPARTAMENTO_LIDER ON departamento');
        $this->addSql('ALTER TABLE departamento DROP descricao, DROP area, DROP lider_id');
    }
}
