<?php

namespace App\Service\Marketing;

/**
 * Demonstração pública dos produtos Carteirinha digital e Guia médico.
 */
final class ClinicPatientProductService
{
    /** @return list<array<string, mixed>> */
    public function plans(): array
    {
        $base = $this->baseDemo();

        return [
            array_merge($base, [
                'id' => 'essencial',
                'label' => 'Plano Essencial',
                'tagline' => 'Identidade digital para recepção e retornos',
                'price_hint' => 'Incluso no plano base',
                'theme' => 'essencial',
                'ribbon' => null,
                'perks' => [
                    'Frente e verso com dados clínicos',
                    'Código de verificação na recepção',
                    'Emissão manual pela clínica',
                ],
                'verificacao' => 'A7F2C91B',
                'nome' => 'João Pereira',
                'iniciais' => 'JP',
                'foto' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=224&h=224&q=80',
                'role' => 'Paciente pós-operatório',
                'plano_label' => 'Plano Essencial',
                'codigo' => 'PO-0018',
                'cpf_masked' => '418.762.390-87',
                'procedimento' => 'Artroscopia de joelho',
                'dia_pos' => 7,
                'medico' => 'Dr. Carlos Mendes',
                'protocolo' => 'Artroscopia · 21 dias',
                'cirurgia' => '01/07/2026',
                'valido_ate' => '29/07/2026',
                'emitido_em' => '01/07/2026',
            ]),
            array_merge($base, [
                'id' => 'profissional',
                'label' => 'Plano Profissional',
                'tagline' => 'Carteirinha com validação e histórico de emissões',
                'price_hint' => 'Clínicas em crescimento',
                'theme' => 'profissional',
                'ribbon' => 'Profissional',
                'perks' => [
                    'Tudo do Essencial',
                    'QR de validação em tempo real',
                    'Reemissão com trilha de auditoria',
                    'Compartilhamento seguro com o paciente',
                ],
                'verificacao' => 'PR0-8F2C91',
                'nome' => 'Maria Silva',
                'iniciais' => 'MS',
                'foto' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=224&h=224&q=80',
                'role' => 'Paciente em acompanhamento',
                'codigo' => 'PO-0031',
                'cpf_masked' => '742.193.580-04',
                'procedimento' => 'Colecistectomia laparoscópica',
                'dia_pos' => 5,
                'medico' => 'Dra. Fernanda Rocha',
                'protocolo' => 'Colecistectomia · 14 dias',
                'cirurgia' => '05/07/2026',
                'valido_ate' => '27/07/2026',
                'emitido_em' => '05/07/2026',
                'plano_label' => 'Plano Profissional',
            ]),
            array_merge($base, [
                'id' => 'premium',
                'label' => 'Plano Premium',
                'tagline' => 'Experiência premium com suporte e personalização',
                'price_hint' => 'Redes e centros de excelência',
                'theme' => 'premium',
                'ribbon' => 'Premium',
                'perks' => [
                    'Tudo do Profissional',
                    'Visual personalizado com logo da clínica',
                    'Suporte prioritário na validação',
                    'Integração com agenda de retornos',
                ],
                'verificacao' => 'PM-24K9X7Q1',
                'nome' => 'Ana Costa',
                'iniciais' => 'AC',
                'foto' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=224&h=224&q=80',
                'role' => 'Paciente VIP · acompanhamento',
                'codigo' => 'PO-0042',
                'cpf_masked' => '529.982.247-25',
                'procedimento' => 'Herniorrafia inguinal',
                'dia_pos' => 3,
                'medico' => 'Dra. Renata Oliveira',
                'protocolo' => 'Herniorrafia · 14 dias',
                'cirurgia' => '08/07/2026',
                'valido_ate' => '22/07/2026',
                'emitido_em' => '08/07/2026',
                'plano_label' => 'Plano Premium',
                'suporte' => 'Suporte clínico 24h',
            ]),
        ];
    }

    public function planById(string $id): ?array
    {
        foreach ($this->plans() as $plan) {
            if ($plan['id'] === $id) {
                return $plan;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function demoAccess(): array
    {
        return [
            'cpf' => '52998224725',
            'cpf_masked' => '529.982.247-25',
            'codigo' => 'PO-0042',
            'verificacao' => 'PM-24K9X7Q1',
        ];
    }

    /** @return array<string, mixed> */
    public function demoPatient(): array
    {
        return $this->baseDemo();
    }

    /** @return array<string, mixed> */
    public function demoGuia(): array
    {
        return [
            'titulo' => 'Guia médico',
            'subtitulo' => 'Você está no D+3 — retirada de curativo e mobilização leve',
            'fase_label' => 'Fase intermediária',
            'fase_descricao' => 'Recuperação funcional gradual. Mantenha o curativo limpo, movimente-se conforme orientação e observe sinais de infecção no sítio cirúrgico.',
            'procedimento' => 'Herniorrafia inguinal',
            'marcos' => [
                ['dia' => 1, 'item' => 'Repouso relativo e analgesia prescrita', 'state' => 'done'],
                ['dia' => 3, 'item' => 'Retirada de curativo e início da mobilização', 'state' => 'current'],
                ['dia' => 7, 'item' => 'Retorno ambulatorial com o médico', 'state' => 'future'],
                ['dia' => 14, 'item' => 'Alta do acompanhamento pós-operatório', 'state' => 'future'],
            ],
            'marco_steps' => [
                ['state' => 'is-done', 'day' => 'D+1', 'text' => 'Repouso relativo e analgesia prescrita'],
                ['state' => 'is-current', 'day' => 'D+3', 'text' => 'Retirada de curativo e mobilização'],
                ['state' => '', 'day' => 'D+7', 'text' => 'Retorno ambulatorial'],
            ],
            'orientacoes' => [
                'Caminhe pequenas distâncias, se liberado pelo médico.',
                'Mantenha o curativo limpo e seco até a próxima orientação.',
                'Evite esforço abdominal e levantamento de peso.',
                'Tome os medicamentos nos horários indicados na receita.',
                'Registre febre, dor intensa ou alteração no curativo.',
            ],
            'sinais_alerta' => [
                'Dor intensa que não melhora com a medicação prescrita',
                'Febre acima de 38 °C ou calafrios',
                'Vermelhidão, calor ou secreção com odor no curativo',
                'Sangramento intenso ou falta de ar',
            ],
            'contato_rapido' => 'Apresente sua carteirinha digital na recepção ou ligue para a clínica em horário comercial.',
            'lembretes' => [
                ['icon' => 'fa-pills', 'titulo' => 'Medicação', 'texto' => 'Analgésicos conforme prescrição — não interrompa sem orientação.'],
                ['icon' => 'fa-calendar-check', 'titulo' => 'Retorno D+7', 'texto' => 'Agende ou confirme consulta ambulatorial com a equipe.'],
                ['icon' => 'fa-id-card', 'titulo' => 'Carteirinha', 'texto' => 'Tenha a carteirinha digital pronta para validação na recepção.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function comprovanteDemoCard(): array
    {
        $base = $this->baseDemo();
        $access = $this->demoAccess();
        unset($base['cpf_masked']);

        return array_merge($base, [
            'theme' => 'profissional',
            'doc_type_label' => 'Comprovante',
            'ribbon' => 'Comprovante',
            'role' => 'Documento do procedimento',
            'plano_label' => null,
            'verificacao' => $access['verificacao'],
            'suporte' => 'Apresente na recepção ou valide pelo QR',
            'emitido_em' => $base['emitido_em'] ?? '08/07/2026',
        ]);
    }

    /** @return array<string, string> */
    public function secoes(): array
    {
        return [
            'carterinha' => 'Carteirinha digital',
            'comprovante' => 'Comprovante de procedimento',
            'guia' => 'Guia médico',
        ];
    }

    /** @return array<string, mixed> */
    private function baseDemo(): array
    {
        return [
            'clinica' => 'UNIO SAÚDE',
            'iniciais' => 'AC',
            'foto' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=224&h=224&q=80',
            'nome' => 'Ana Costa',
            'role' => 'Paciente pós-operatório',
            'codigo' => 'PO-0042',
            'cpf_masked' => '529.982.247-25',
            'procedimento' => 'Herniorrafia inguinal',
            'dia_pos' => 3,
            'medico' => 'Dra. Renata Oliveira',
            'cirurgia' => '08/07/2026',
            'protocolo' => 'Herniorrafia · 14 dias',
            'valido_ate' => '22/07/2026',
            'telefone' => '(11) 98765-4321',
            'emergencia' => 'João Costa · (11) 91234-5678',
            'emitido_em' => '08/07/2026',
            'plano_label' => 'Plano Essencial',
        ];
    }
}
