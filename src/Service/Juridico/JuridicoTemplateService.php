<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoTemplatePeca;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoTemplatePecaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JuridicoTemplateService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoTemplatePecaRepository $repo,
    ) {
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoTemplatePeca
    {
        $tpl = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$tpl) {
            throw new JuridicoProcessException('Template não encontrado.');
        }

        return $tpl;
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoTemplatePeca
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $corpo = (string) ($data['corpo'] ?? '');
        if ($nome === '' || trim($corpo) === '') {
            throw new JuridicoProcessException('Informe nome e corpo do template.');
        }

        $tpl = (new JuridicoTemplatePeca())
            ->setEmpresa($empresa)
            ->setNome($nome)
            ->setTipo(trim((string) ($data['tipo'] ?? 'peticao')) ?: 'peticao')
            ->setArea($this->nullIfEmpty($data['area'] ?? null))
            ->setCorpo($corpo)
            ->setVariaveis($this->extrairVariaveis($corpo));

        $this->em->persist($tpl);
        $this->em->flush();

        return $tpl;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoTemplatePeca $tpl, array $data): void
    {
        $tpl->setNome(trim((string) ($data['nome'] ?? $tpl->getNome())));
        $tpl->setTipo(trim((string) ($data['tipo'] ?? $tpl->getTipo())));
        $tpl->setArea($this->nullIfEmpty($data['area'] ?? $tpl->getArea()));
        $tpl->setCorpo((string) ($data['corpo'] ?? $tpl->getCorpo()));
        $tpl->setVariaveis($this->extrairVariaveis($tpl->getCorpo()));
        $tpl->setVersao($tpl->getVersao() + 1);
        $tpl->touch();
        $this->em->flush();
    }

    public function aprovar(JuridicoTemplatePeca $tpl, User $user): void
    {
        $tpl->setStatus(JuridicoTemplatePeca::STATUS_APROVADO)->setAprovadoPor($user)->touch();
        $this->em->flush();
    }

    public function render(JuridicoTemplatePeca $tpl, ?JuridicoProcesso $processo = null, array $extra = []): string
    {
        $map = [
            'cliente' => $processo?->getCliente()?->getNome() ?? ($extra['cliente'] ?? ''),
            'processo.numero' => $processo?->getNumero() ?? ($extra['numero'] ?? ''),
            'processo.tribunal' => $processo?->getTribunal() ?? '',
            'processo.area' => $processo?->getArea() ?? '',
            'responsavel' => $processo?->getResponsavel()?->getNome() ?? '',
            'hoje' => (new \DateTimeImmutable())->format('d/m/Y'),
        ];
        foreach ($extra as $k => $v) {
            $map[(string) $k] = (string) $v;
        }

        $corpo = $tpl->getCorpo();
        foreach ($map as $chave => $valor) {
            $corpo = str_replace(['{{'.$chave.'}}', '{{ '.$chave.' }}'], $valor, $corpo);
        }

        return $corpo;
    }

    /** @return list<string> */
    private function extrairVariaveis(string $corpo): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $corpo, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
