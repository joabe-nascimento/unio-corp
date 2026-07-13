<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catálogo TUSS, lotes/remessa TISS e vínculo guia→lote';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE clinic_tuss_codigo (id INT AUTO_INCREMENT NOT NULL, codigo VARCHAR(20) NOT NULL, descricao VARCHAR(255) NOT NULL, tabela VARCHAR(8) DEFAULT \'22\', valor_sugerido_centavos INT DEFAULT NULL, ativo TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_CLINIC_TUSS_CODIGO (codigo), INDEX IDX_CLINIC_TUSS_ATIVO (ativo), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE clinic_lote_tiss (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, convenio_id INT NOT NULL, numero VARCHAR(40) NOT NULL, competencia VARCHAR(7) NOT NULL, status VARCHAR(16) NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLINIC_LOTE_EMPRESA (empresa_id), INDEX IDX_CLINIC_LOTE_CONVENIO (convenio_id), INDEX IDX_CLINIC_LOTE_EMPRESA_STATUS (empresa_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clinic_lote_tiss ADD CONSTRAINT FK_CLINIC_LOTE_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clinic_lote_tiss ADD CONSTRAINT FK_CLINIC_LOTE_CONVENIO FOREIGN KEY (convenio_id) REFERENCES clinic_convenio (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD lote_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE clinic_guia_tiss ADD CONSTRAINT FK_CLINIC_GUIA_LOTE FOREIGN KEY (lote_id) REFERENCES clinic_lote_tiss (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CLINIC_GUIA_LOTE ON clinic_guia_tiss (lote_id)');

        $seed = [
            ['10101012', 'Consulta em consultório (clínica médica / especialidades)', 15000],
            ['10101039', 'Consulta em pronto socorro', 18000],
            ['10102019', 'Visita hospitalar (paciente internado)', 12000],
            ['20101015', 'Curativo simples', 4500],
            ['20101074', 'Retirada de pontos de sutura', 6000],
            ['20102011', 'Injeção intramuscular', 2500],
            ['30715017', 'Herniorrafia inguinal unilateral', 280000],
            ['30715025', 'Herniorrafia inguinal bilateral', 420000],
            ['30715106', 'Herniorrafia umbilical', 220000],
            ['30723010', 'Colecistectomia videolaparoscópica', 520000],
            ['30711011', 'Apendicectomia', 350000],
            ['30725012', 'Artroscopia de joelho (diagnóstica)', 380000],
            ['30725047', 'Artroscopia de joelho com meniscectomia', 480000],
            ['30731012', 'Osteossíntese de fratura (membro)', 410000],
            ['31309012', 'Cesariana', 450000],
            ['31301012', 'Parto normal', 380000],
            ['40101010', 'Eletrocardiograma', 8000],
            ['40304361', 'Ultrassonografia de abdome total', 18000],
            ['40805018', 'Radiografia de tórax (PA)', 9000],
            ['50000560', 'Taxa de sala (porte médio)', 35000],
            ['60000123', 'Material de consumo cirúrgico (porte)', 25000],
            ['80000100', 'Diária de apartamento', 45000],
        ];

        foreach ($seed as [$codigo, $descricao, $valor]) {
            $this->addSql(
                'INSERT INTO clinic_tuss_codigo (codigo, descricao, tabela, valor_sugerido_centavos, ativo) VALUES (?, ?, \'22\', ?, 1)',
                [$codigo, $descricao, $valor]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP FOREIGN KEY FK_CLINIC_GUIA_LOTE');
        $this->addSql('DROP INDEX IDX_CLINIC_GUIA_LOTE ON clinic_guia_tiss');
        $this->addSql('ALTER TABLE clinic_guia_tiss DROP lote_id');
        $this->addSql('ALTER TABLE clinic_lote_tiss DROP FOREIGN KEY FK_CLINIC_LOTE_EMPRESA');
        $this->addSql('ALTER TABLE clinic_lote_tiss DROP FOREIGN KEY FK_CLINIC_LOTE_CONVENIO');
        $this->addSql('DROP TABLE clinic_lote_tiss');
        $this->addSql('DROP TABLE clinic_tuss_codigo');
    }
}
