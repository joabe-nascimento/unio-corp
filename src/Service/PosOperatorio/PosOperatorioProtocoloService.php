<?php



namespace App\Service\PosOperatorio;



use App\Entity\Empresa;

use App\Entity\PosOperatorioProtocolo;

use Doctrine\ORM\EntityManagerInterface;



final class PosOperatorioProtocoloService

{

    public function __construct(

        private EntityManagerInterface $em,

        private \App\Repository\PosOperatorioProtocoloRepository $repository,

    ) {}



    /** @return list<PosOperatorioProtocolo> */

    public function listByEmpresa(Empresa $empresa): array

    {

        return $this->repository->findBy(['empresa' => $empresa], ['nome' => 'ASC']);

    }



    public function findForEmpresa(Empresa $empresa, int $id): ?PosOperatorioProtocolo

    {

        $p = $this->repository->find($id);



        return ($p instanceof PosOperatorioProtocolo && $p->getEmpresa()->getId() === $empresa->getId()) ? $p : null;

    }



    public function create(Empresa $empresa, array $data): PosOperatorioProtocolo

    {

        $protocolo = (new PosOperatorioProtocolo())

            ->setEmpresa($empresa)

            ->setNome(trim((string) ($data['nome'] ?? '')))

            ->setTipoProcedimento(trim((string) ($data['tipo'] ?? '')) ?: null)

            ->setDuracaoDias(max(1, (int) ($data['duracao_dias'] ?? 14)))

            ->setChecklist($this->parseChecklistText((string) ($data['checklist_text'] ?? '')))

            ->setPerguntas(PosOperatorioProtocoloDefaults::perguntas())

            ->setRegrasAlerta($this->parseRegras($data))

            ->setAtivo(true);



        if ($protocolo->getChecklist() === []) {

            $protocolo->setChecklist(PosOperatorioProtocoloDefaults::checklistBasico());

        }



        $this->em->persist($protocolo);

        $this->em->flush();



        return $protocolo;

    }



    public function update(PosOperatorioProtocolo $protocolo, array $data): void

    {

        if (($nome = trim((string) ($data['nome'] ?? ''))) !== '') {

            $protocolo->setNome($nome);

        }

        $protocolo->setTipoProcedimento(trim((string) ($data['tipo'] ?? '')) ?: null);

        $protocolo->setDuracaoDias(max(1, (int) ($data['duracao_dias'] ?? $protocolo->getDuracaoDias())));

        $protocolo->setAtivo($data['ativo'] ?? $protocolo->isAtivo());



        if (array_key_exists('checklist_text', $data)) {

            $checklist = $this->parseChecklistText((string) $data['checklist_text']);

            if ($checklist !== []) {

                $protocolo->setChecklist($checklist);

            }

        }



        if (array_key_exists('regras_dor_p1', $data) || array_key_exists('regras_febre_p2', $data)) {

            $protocolo->setRegrasAlerta($this->parseRegras($data));

        }



        $this->em->flush();

    }



    public function formatChecklistText(PosOperatorioProtocolo $protocolo): string

    {

        $lines = [];

        foreach ($protocolo->getChecklist() as $item) {

            $dia = (int) ($item['dia'] ?? 0);

            $text = trim((string) ($item['item'] ?? ''));

            if ($text !== '') {

                $lines[] = $dia . ': ' . $text;

            }

        }



        return implode("\n", $lines);

    }



    /** @return list<array{dia: int, item: string}> */

    public function parseChecklistText(string $text): array

    {

        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {

            $line = trim($line);

            if ($line === '') {

                continue;

            }

            if (preg_match('/^(\d+)\s*[:.\-]\s*(.+)$/', $line, $m)) {

                $out[] = ['dia' => (int) $m[1], 'item' => trim($m[2])];

            }

        }



        return $out;

    }



    /** @return array<string, mixed> */

    private function parseRegras(array $data): array

    {

        $regras = PosOperatorioProtocoloDefaults::regrasAlerta();

        if (isset($data['regras_dor_p1']) && $data['regras_dor_p1'] !== '') {

            $regras['dor_p1_min'] = max(0, min(10, (int) $data['regras_dor_p1']));

        }

        if (isset($data['regras_febre_p2']) && $data['regras_febre_p2'] !== '') {

            $regras['febre_p2_min'] = max(35.0, (float) str_replace(',', '.', (string) $data['regras_febre_p2']));

        }



        return $regras;

    }

}

