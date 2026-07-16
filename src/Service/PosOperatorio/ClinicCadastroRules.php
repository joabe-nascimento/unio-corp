<?php

namespace App\Service\PosOperatorio;

final class ClinicCadastroRules
{
    public const ORIGENS_CLINICAS = [
        'indicacao_medica' => 'Indicação médica',
        'espontaneo' => 'Espontâneo',
        'convenio' => 'Convênio',
        'instagram' => 'Instagram',
        'google' => 'Google',
        'indicacao_paciente' => 'Indicação de paciente',
        'empresa' => 'Empresa',
        'outro' => 'Outro',
    ];

    public const PARENTESCOS = [
        'conjuge' => 'Cônjuge',
        'filho' => 'Filho(a)',
        'pai' => 'Pai',
        'mae' => 'Mãe',
        'irmao' => 'Irmão(ã)',
        'outro' => 'Outro',
    ];

    public const CONSELHOS = [
        'CRM' => 'CRM — Medicina',
        'CRO' => 'CRO — Odontologia',
        'COREN' => 'COREN — Enfermagem',
        'CREFITO' => 'CREFITO — Fisioterapia',
        'CRFa' => 'CRFa — Fonoaudiologia',
        'CRP' => 'CRP — Psicologia',
        'CRN' => 'CRN — Nutrição',
        'OUTRO' => 'Outro conselho',
    ];

    /** Especialidades clínicas comuns (valor = rótulo). */
    public const ESPECIALIDADES = [
        'Cirurgia geral' => 'Cirurgia geral',
        'Cirurgia plástica' => 'Cirurgia plástica',
        'Cirurgia bariátrica' => 'Cirurgia bariátrica',
        'Ortopedia' => 'Ortopedia',
        'Oftalmologia' => 'Oftalmologia',
        'Otorrinolaringologia' => 'Otorrinolaringologia',
        'Ginecologia e obstetrícia' => 'Ginecologia e obstetrícia',
        'Urologia' => 'Urologia',
        'Dermatologia' => 'Dermatologia',
        'Cardiologia' => 'Cardiologia',
        'Clínica médica' => 'Clínica médica',
        'Anestesiologia' => 'Anestesiologia',
        'Pediatria' => 'Pediatria',
        'Neurologia' => 'Neurologia',
        'Endocrinologia' => 'Endocrinologia',
        'Gastroenterologia' => 'Gastroenterologia',
        'Odontologia' => 'Odontologia',
        'Ortodontia' => 'Ortodontia',
        'Implantodontia' => 'Implantodontia',
        'Fisioterapia' => 'Fisioterapia',
        'Nutrição' => 'Nutrição',
        'Psicologia' => 'Psicologia',
        'Enfermagem' => 'Enfermagem',
        'Outra' => 'Outra',
    ];

    public const TIPOS_SALA = [
        'consultorio' => 'Consultório',
        'centro_cirurgico' => 'Centro cirúrgico',
        'exame' => 'Exame',
        'outro' => 'Outro',
    ];

    public const UNIDADES_MEDIDA_ESTOQUE = [
        'un' => 'Unidade (un)',
        'cx' => 'Caixa (cx)',
        'pct' => 'Pacote (pct)',
        'ml' => 'Mililitro (ml)',
        'l' => 'Litro (l)',
        'g' => 'Grama (g)',
        'kg' => 'Quilograma (kg)',
        'par' => 'Par',
        'kit' => 'Kit',
    ];

    public const ORCAMENTO_STATUSES = [
        'rascunho' => 'Rascunho',
        'enviado' => 'Enviado',
        'aprovado' => 'Aprovado',
        'recusado' => 'Recusado',
        'convertido' => 'Convertido',
    ];

    public const UFS_BRASIL = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public const VERSOES_TISS = ['3.05.00', '4.00.00', '4.01.00', '4.02.00'];

    /** Tipos frequentes para modelos SOAP (valor = rótulo). */
    public const TIPOS_PROCEDIMENTO_SOAP = [
        'Consulta' => 'Consulta',
        'Retorno pós-operatório' => 'Retorno pós-operatório',
        'Pré-operatório' => 'Pré-operatório',
        'Herniorrafia' => 'Herniorrafia',
        'Cirurgia plástica' => 'Cirurgia plástica',
        'Bariátrica' => 'Bariátrica',
        'Endoscopia' => 'Endoscopia',
        'Procedimento ambulatorial' => 'Procedimento ambulatorial',
        'Urgência' => 'Urgência',
        'Outro' => 'Outro',
    ];

    /** @return list<array{value: string, label: string}> */
    public static function ufSelectOptions(bool $withEmpty = true): array
    {
        $opts = [];
        if ($withEmpty) {
            $opts[] = ['value' => '', 'label' => '— Selecione a UF —'];
        }
        foreach (self::UFS_BRASIL as $uf) {
            $opts[] = ['value' => $uf, 'label' => $uf];
        }

        return $opts;
    }

    /** @return list<array{value: string, label: string}> */
    public static function especialidadeSelectOptions(bool $withEmpty = true): array
    {
        $opts = [];
        if ($withEmpty) {
            $opts[] = ['value' => '', 'label' => '— Selecione —'];
        }
        foreach (self::ESPECIALIDADES as $value => $label) {
            $opts[] = ['value' => $value, 'label' => $label];
        }

        return $opts;
    }

    /** @return list<array{value: string, label: string}> */
    public static function versaoTissSelectOptions(bool $withEmpty = true): array
    {
        $opts = [];
        if ($withEmpty) {
            $opts[] = ['value' => '', 'label' => '— Selecione —'];
        }
        foreach (self::VERSOES_TISS as $v) {
            $opts[] = ['value' => $v, 'label' => $v];
        }

        return $opts;
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function requireNome(string $nome, int $max = 180): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new \InvalidArgumentException('Nome é obrigatório.');
        }

        return mb_substr($nome, 0, $max);
    }

    public static function normalizeCpf(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = self::digitsOnly($value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) !== 11) {
            throw new \InvalidArgumentException('CPF deve ter 11 dígitos.');
        }
        if (!self::isValidCpfDigits($digits)) {
            throw new \InvalidArgumentException('CPF inválido.');
        }

        return $digits;
    }

    public static function normalizeCnpj(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = self::digitsOnly($value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) !== 14) {
            throw new \InvalidArgumentException('CNPJ deve ter 14 dígitos.');
        }
        if (!self::isValidCnpjDigits($digits)) {
            throw new \InvalidArgumentException('CNPJ inválido.');
        }

        return $digits;
    }

    public static function normalizeCep(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = self::digitsOnly($value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) !== 8) {
            throw new \InvalidArgumentException('CEP deve ter 8 dígitos.');
        }

        return $digits;
    }

    public static function normalizeCns(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = self::digitsOnly($value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) !== 15) {
            throw new \InvalidArgumentException('CNS deve ter 15 dígitos.');
        }

        return $digits;
    }

    public static function normalizeUf(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $uf = mb_strtoupper(trim($value));
        if (!preg_match('/^[A-Z]{2}$/', $uf)) {
            throw new \InvalidArgumentException('UF deve ter 2 letras.');
        }
        if (!\in_array($uf, self::UFS_BRASIL, true)) {
            throw new \InvalidArgumentException('UF inválida.');
        }

        return $uf;
    }

    public static function normalizeEmail(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $email = mb_strtolower(trim($value));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail inválido.');
        }

        return mb_substr($email, 0, 120);
    }

    public static function normalizeVersaoTiss(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $v = trim($value);
        if (\in_array($v, self::VERSOES_TISS, true)) {
            return $v;
        }
        // Aceita versões legadas no formato N.NN.NN (ex.: 3.03.01)
        if (preg_match('/^\d+\.\d{2}\.\d{2}$/', $v) === 1) {
            return mb_substr($v, 0, 16);
        }

        throw new \InvalidArgumentException('Versão TISS inválida. Use: '.implode(', ', self::VERSOES_TISS).'.');
    }

    public static function normalizeConselho(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            throw new \InvalidArgumentException('Conselho inválido.');
        }
        foreach (self::CONSELHOS as $key => $_label) {
            if (strcasecmp($key, $raw) === 0) {
                return $key;
            }
        }

        throw new \InvalidArgumentException('Conselho inválido. Use: '.implode(', ', array_keys(self::CONSELHOS)).'.');
    }

    public static function assertCarteirinhaConvenio(?int $convenioId, ?string $numero): void
    {
        if (($convenioId ?? 0) > 0 && trim((string) $numero) === '') {
            throw new \InvalidArgumentException('Informe o nº da carteirinha do convênio.');
        }
    }

    public static function assertEnderecoCoerente(?string $cep, ?string $cidade, ?string $uf): void
    {
        $hasAny = ($cep !== null && $cep !== '') || ($cidade !== null && $cidade !== '') || ($uf !== null && $uf !== '');
        if (!$hasAny) {
            return;
        }
        if ($cidade === null || $cidade === '' || $uf === null || $uf === '') {
            throw new \InvalidArgumentException('Endereço incompleto: informe cidade e UF.');
        }
    }

    /** Paciente com cirurgia no futuro além de 365 dias — bloqueia erro de digitação. */
    public static function assertDataCirurgiaCoerente(?\DateTimeImmutable $data): void
    {
        if ($data === null) {
            return;
        }
        $hoje = new \DateTimeImmutable('today');
        $limiteFuturo = $hoje->modify('+365 days');
        $limitePassado = $hoje->modify('-20 years');
        if ($data > $limiteFuturo) {
            throw new \InvalidArgumentException('Data da cirurgia não pode ser mais de 1 ano no futuro.');
        }
        if ($data < $limitePassado) {
            throw new \InvalidArgumentException('Data da cirurgia parece inválida (mais de 20 anos no passado).');
        }
    }

    public static function assertIdadeMinimaCirurgia(?\DateTimeImmutable $nascimento, ?\DateTimeImmutable $cirurgia): void
    {
        if ($nascimento === null || $cirurgia === null) {
            return;
        }
        $idade = $nascimento->diff($cirurgia)->y;
        if ($idade < 0) {
            throw new \InvalidArgumentException('Data de nascimento posterior à cirurgia.');
        }
        if ($idade < 12) {
            throw new \InvalidArgumentException('Paciente com menos de 12 anos na data da cirurgia — revise titular/responsável.');
        }
    }

    public static function normalizeSexo(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $sexo = mb_strtoupper(trim($value));
        if (!\in_array($sexo, ['M', 'F', 'O'], true)) {
            throw new \InvalidArgumentException('Sexo inválido (use M, F ou O).');
        }

        return $sexo;
    }

    public static function normalizePhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = self::digitsOnly($value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) < 10 || strlen($digits) > 13) {
            throw new \InvalidArgumentException('Telefone inválido.');
        }

        return $digits;
    }

    public static function parseMoneyToCentavos(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        // Remove símbolo, espaços ASCII e NBSP (toLocaleString pt-BR usa U+00A0).
        $raw = preg_replace('/[R$\s\x{00A0}]+/u', '', trim($value)) ?? '';
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw;
        }

        if (str_contains($raw, ',')) {
            $normalized = str_replace('.', '', $raw);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = $raw;
        }

        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException('Valor monetário inválido.');
        }

        return (int) round(((float) $normalized) * 100);
    }

    public static function centavosToMoney(?int $centavos): string
    {
        if ($centavos === null) {
            return '';
        }

        return number_format($centavos / 100, 2, ',', '.');
    }

    public static function assertPrazoGlosa(int $dias): int
    {
        if ($dias < 1 || $dias > 180) {
            throw new \InvalidArgumentException('Prazo de glosa deve ser entre 1 e 180 dias.');
        }

        return $dias;
    }

    public static function assertDuracaoMinutos(int $minutos): int
    {
        if ($minutos < 5 || $minutos > 480) {
            throw new \InvalidArgumentException('Duração deve ser entre 5 e 480 minutos.');
        }

        return $minutos;
    }

    public static function assertQuantidadeNaoNegativa(int $quantidade): int
    {
        if ($quantidade < 0) {
            throw new \InvalidArgumentException('Quantidade não pode ser negativa.');
        }

        return $quantidade;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     alergias: string,
     *     medicamentos_uso: string,
     *     comorbidades: string,
     *     cirurgias_previas: string,
     *     habitos: string,
     *     observacoes: string
     * }
     */
    public static function validateAnamnese(array $data): array
    {
        $keys = [
            'alergias',
            'medicamentos_uso',
            'comorbidades',
            'cirurgias_previas',
            'habitos',
            'observacoes',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = trim((string) ($data[$key] ?? ''));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{id: string, tipo: string, label: string, opcoes?: list<string>}
     */
    public static function validatePerguntaQuestionario(array $data): array
    {
        $id = trim((string) ($data['id'] ?? ''));
        $tipo = trim((string) ($data['tipo'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));

        if ($id === '') {
            throw new \InvalidArgumentException('Pergunta: id é obrigatório.');
        }
        if (!\in_array($tipo, ['escala', 'numero', 'select', 'texto'], true)) {
            throw new \InvalidArgumentException('Pergunta: tipo inválido.');
        }
        if ($label === '') {
            throw new \InvalidArgumentException('Pergunta: label é obrigatório.');
        }

        $out = [
            'id' => $id,
            'tipo' => $tipo,
            'label' => $label,
        ];

        if ($tipo === 'select') {
            $opcoes = $data['opcoes'] ?? [];
            if (!\is_array($opcoes) || $opcoes === []) {
                throw new \InvalidArgumentException('Pergunta select exige opções.');
            }
            $normalized = [];
            foreach ($opcoes as $opcao) {
                $text = trim((string) $opcao);
                if ($text !== '') {
                    $normalized[] = $text;
                }
            }
            if ($normalized === []) {
                throw new \InvalidArgumentException('Pergunta select exige opções.');
            }
            $out['opcoes'] = array_values($normalized);
        }

        return $out;
    }

    private static function isValidCpfDigits(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; ++$t) {
            $sum = 0;
            for ($i = 0; $i < $t; ++$i) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpjDigits(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; ++$i) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }
        $d1 = $sum % 11;
        $d1 = $d1 < 2 ? 0 : 11 - $d1;
        if ((int) $cnpj[12] !== $d1) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 13; ++$i) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }
        $d2 = $sum % 11;
        $d2 = $d2 < 2 ? 0 : 11 - $d2;

        return (int) $cnpj[13] === $d2;
    }
}
