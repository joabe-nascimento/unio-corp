<?php

namespace App\PosOperatorio;

use App\Entity\PosOperatorioEvento;
use App\Rh\RhProcessDisplay;

/** Formata eventos da linha do tempo clínica para exibição humana. */
final class PosOperatorioTimelineFormatter
{
    /** @return array{time: string, label: string, detail: string, icon: string} */
    public static function format(PosOperatorioEvento $ev): array
    {
        $tipo = $ev->getTipo();
        $criadoEm = $ev->getCriadoEm();

        return [
            'time' => $criadoEm->format('H:i'),
            'label' => self::label($tipo),
            'detail' => self::detail($ev),
            'icon' => self::icon($tipo),
        ];
    }

    private static function label(string $tipo): string
    {
        return match ($tipo) {
            PosOperatorioEvento::TIPO_CADASTRO => 'Cadastro',
            PosOperatorioEvento::TIPO_ALERTA => 'Alerta clínico',
            PosOperatorioEvento::TIPO_QUESTIONARIO => 'Questionário',
            PosOperatorioEvento::TIPO_CONSENTIMENTO => 'Consentimento LGPD',
            PosOperatorioEvento::TIPO_LEMBRETE => 'Lembrete',
            PosOperatorioEvento::TIPO_EVOLUCAO => 'Evolução clínica',
            PosOperatorioEvento::TIPO_RETORNO => 'Retorno confirmado',
            PosOperatorioEvento::TIPO_VITORIA => 'Assistente',
            PosOperatorioEvento::TIPO_ACESSO_FICHA => 'Acesso à ficha',
            PosOperatorioEvento::TIPO_CHAT => 'Chat clínico',
            default => 'Registro',
        };
    }

    private static function icon(string $tipo): string
    {
        return match ($tipo) {
            PosOperatorioEvento::TIPO_CADASTRO => 'fa-user-plus',
            PosOperatorioEvento::TIPO_ALERTA => 'fa-triangle-exclamation',
            PosOperatorioEvento::TIPO_QUESTIONARIO => 'fa-file-medical',
            PosOperatorioEvento::TIPO_CONSENTIMENTO => 'fa-shield-halved',
            PosOperatorioEvento::TIPO_LEMBRETE => 'fa-bell',
            PosOperatorioEvento::TIPO_EVOLUCAO => 'fa-notes-medical',
            PosOperatorioEvento::TIPO_RETORNO => 'fa-calendar-check',
            PosOperatorioEvento::TIPO_VITORIA => 'fa-sparkles',
            PosOperatorioEvento::TIPO_ACESSO_FICHA => 'fa-eye',
            PosOperatorioEvento::TIPO_CHAT => 'fa-comments',
            default => 'fa-circle-dot',
        };
    }

    private static function detail(PosOperatorioEvento $ev): string
    {
        $desc = trim($ev->getDescricao());
        $tipo = $ev->getTipo();

        if ($tipo === PosOperatorioEvento::TIPO_ACESSO_FICHA) {
            if (str_starts_with($desc, 'Ficha visualizada por ')) {
                return self::stripIp($desc);
            }

            if (preg_match('/Acesso à ficha \([^)]+\) por (.+?)( · IP|$)/u', $desc, $matches) === 1) {
                return 'Ficha visualizada por ' . self::personName(trim($matches[1]), $ev);
            }

            if ($desc !== '') {
                return self::stripIp($desc);
            }

            $autor = $ev->getAutor();
            if ($autor !== null) {
                return 'Ficha visualizada por ' . self::personName($autor->getNome() ?? '', $ev, $autor->getEmail());
            }

            return 'Ficha visualizada';
        }

        if ($tipo === PosOperatorioEvento::TIPO_CADASTRO) {
            $normalized = str_replace(
                ['Paciente cadastrado no núcleo', 'Paciente demo cadastrado'],
                ['Paciente cadastrado', 'Paciente cadastrado'],
                $desc,
            );

            return $normalized !== '' ? $normalized : 'Paciente incluído no acompanhamento';
        }

        if ($desc === '') {
            return '';
        }

        return self::stripIp($desc);
    }

    private static function personName(string $nome, PosOperatorioEvento $ev, ?string $email = null): string
    {
        $normalized = mb_strtolower(trim($nome));
        if (
            str_starts_with($normalized, 'tenant ')
            || \in_array($normalized, ['tenant master', 'gestor unio', 'membro santos'], true)
            || RhProcessDisplay::isGenericHubName($nome)
        ) {
            return 'Equipe clínica';
        }

        $email ??= $ev->getAutor()?->getEmail();

        return RhProcessDisplay::colaboradorNome($nome, $email);
    }

    private static function stripIp(string $text): string
    {
        return trim((string) preg_replace('/ · IP [\d.:a-f]+$/i', '', $text));
    }
}
