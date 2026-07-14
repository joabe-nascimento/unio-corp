<?php



namespace App\Service\PosOperatorio;



use App\Entity\Empresa;

use App\Entity\PosOperatorioPaciente;

use App\Entity\PosOperatorioProtocolo;

use App\PosOperatorio\ClinicProtocolLibrary;

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



    /** Garante todos os modelos da biblioteca e devolve lista sem duplicatas para o formulário. */

    /** @return list<PosOperatorioProtocolo> */

    public function listForPacienteForm(Empresa $empresa, ?PosOperatorioPaciente $paciente = null): array

    {

        $this->ensureLibraryProtocols($empresa);



        return $this->dedupeForForm($this->listByEmpresa($empresa), $paciente?->getProtocolo()?->getId());

    }



    public function ensureLibraryProtocols(Empresa $empresa): void

    {

        foreach (ClinicProtocolLibrary::templates() as $template) {

            if ($this->findMatchingTemplate($empresa, $template) !== null) {

                continue;

            }

            $this->importFromTemplate($empresa, $template);

        }

    }



    /** @param array{slug?: string, nome?: string, tipo?: string} $template */

    public function findMatchingTemplate(Empresa $empresa, array $template): ?PosOperatorioProtocolo

    {

        $slug = mb_strtolower(trim((string) ($template['slug'] ?? '')));

        $tipo = mb_strtolower(trim((string) ($template['tipo'] ?? '')));

        $nome = mb_strtolower(trim((string) ($template['nome'] ?? '')));



        foreach ($this->listByEmpresa($empresa) as $protocolo) {

            $protocoloNome = mb_strtolower(trim($protocolo->getNome()));

            $protocoloTipo = mb_strtolower(trim((string) ($protocolo->getTipoProcedimento() ?? '')));



            if ($nome !== '' && $protocoloNome === $nome) {

                return $protocolo;

            }

            if ($tipo !== '' && $protocoloTipo === $tipo) {

                return $protocolo;

            }

            if ($slug !== '' && $protocoloTipo === $slug) {

                return $protocolo;

            }

            // Compatibilidade com seeds antigos (tipo curto, ex.: "apendicectomia")

            if ($slug === 'apendicectomia-lap' && $protocoloTipo === 'apendicectomia' && str_contains($protocoloNome, 'apendicectomia')) {

                return $protocolo;

            }

        }



        return null;

    }



    /** Protocolo existente ou recém-importado. */
    public function importFromTemplateIfMissing(Empresa $empresa, array $template): PosOperatorioProtocolo

    {

        $existing = $this->findMatchingTemplate($empresa, $template);

        if ($existing instanceof PosOperatorioProtocolo) {

            return $existing;

        }



        return $this->importFromTemplate($empresa, $template);

    }



    /** @return list<PosOperatorioProtocolo> */

    private function dedupeForForm(array $protocolos, ?int $selectedId): array

    {

        $byKey = [];

        foreach ($protocolos as $protocolo) {

            if (!$protocolo instanceof PosOperatorioProtocolo) {

                continue;

            }

            if (!$protocolo->isAtivo() && $protocolo->getId() !== $selectedId) {

                continue;

            }

            $key = mb_strtolower(trim($protocolo->getNome()));

            if ($key === '') {

                continue;

            }

            if (!isset($byKey[$key])) {

                $byKey[$key] = $protocolo;

                continue;

            }

            $existing = $byKey[$key];

            if ($protocolo->getId() === $selectedId) {

                $byKey[$key] = $protocolo;

            } elseif ($existing->getId() !== $selectedId && (int) $protocolo->getId() < (int) $existing->getId()) {

                $byKey[$key] = $protocolo;

            }

        }



        $result = array_values($byKey);

        usort($result, static fn (PosOperatorioProtocolo $a, PosOperatorioProtocolo $b): int => strcasecmp($a->getNome(), $b->getNome()));



        return $result;

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

            ->setPerguntas(
                \is_array($data['perguntas'] ?? null) && $data['perguntas'] !== []
                    ? $data['perguntas']
                    : PosOperatorioProtocoloDefaults::perguntas()
            )

            ->setRegrasAlerta($this->parseRegras($data))

            ->setAtivo(true);



        if ($protocolo->getChecklist() === []) {

            $protocolo->setChecklist(PosOperatorioProtocoloDefaults::checklistBasico());

        }



        $this->em->persist($protocolo);

        $this->em->flush();



        return $protocolo;

    }



    /** @param array<string, mixed> $template */
    public function importFromTemplate(Empresa $empresa, array $template): PosOperatorioProtocolo
    {
        $checklistText = implode("\n", array_map(
            static fn (array $i): string => ($i['dia'] ?? '') . ': ' . ($i['item'] ?? ''),
            $template['checklist'] ?? [],
        ));

        return $this->create($empresa, [
            'nome' => $template['nome'] ?? 'Protocolo',
            'tipo' => $template['tipo'] ?? '',
            'duracao_dias' => $template['duracao_dias'] ?? 14,
            'checklist_text' => $checklistText,
            'perguntas' => $template['perguntas'] ?? PosOperatorioProtocoloDefaults::perguntas(),
            'regras_dor_p1' => $template['regras']['dor_p1_min'] ?? 8,
            'regras_febre_p2' => $template['regras']['febre_p2_min'] ?? 38.5,
        ]);
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

            if (preg_match('/^(-?\d+)\s*[:.\-]\s*(.+)$/u', $line, $m)) {

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

