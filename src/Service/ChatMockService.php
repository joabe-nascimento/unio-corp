<?php

namespace App\Service;

/**
 * Conversas mock — substituir por WebSocket/API quando o backend estiver pronto.
 */
class ChatMockService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getConversations(): array
    {
        return [
            $this->conv('c1', 'Equipe Comercial', 'group', 'EC', false, 2, 'Ana: Fechamos o contrato!', '12 min', [
                ['id' => 'm1', 'role' => 'other', 'sender' => 'Ana Lima', 'text' => 'Bom dia, pessoal!', 'at' => '2026-05-23T09:00:00'],
                ['id' => 'm2', 'role' => 'other', 'sender' => 'Carlos Mendes', 'text' => 'Reunião às 14h confirmada.', 'at' => '2026-05-23T09:15:00'],
                ['id' => 'm3', 'role' => 'other', 'sender' => 'Ana Lima', 'text' => 'Fechamos o contrato!', 'at' => '2026-05-23T09:42:00'],
            ]),
            $this->conv('c2', 'Maria Costa', 'direct', 'MC', true, 1, 'Você: Envio o relatório ainda hoje', '28 min', [
                ['id' => 'm4', 'role' => 'other', 'sender' => 'Maria Costa', 'text' => 'Oi! Consegue revisar a planilha de membros?', 'at' => '2026-05-23T08:50:00'],
                ['id' => 'm5', 'role' => 'user', 'sender' => null, 'text' => 'Claro, olho até o meio-dia.', 'at' => '2026-05-23T09:05:00'],
                ['id' => 'm6', 'role' => 'other', 'sender' => 'Maria Costa', 'text' => 'Perfeito, obrigada!', 'at' => '2026-05-23T09:06:00'],
            ]),
            $this->conv('c3', 'João Silva', 'direct', 'JS', false, 0, 'Atualizei o cadastro no RH', '1 h', [
                ['id' => 'm7', 'role' => 'other', 'sender' => 'João Silva', 'text' => 'Atualizei o cadastro no RH.', 'at' => '2026-05-23T08:30:00'],
            ]),
            $this->conv('c4', 'Projeto Alpha', 'group', 'PA', false, 0, 'Pedro: Cronograma aprovado', '3 h', [
                ['id' => 'm8', 'role' => 'other', 'sender' => 'Pedro Alves', 'text' => 'Cronograma aprovado pelo gestor.', 'at' => '2026-05-23T07:00:00'],
                ['id' => 'm9', 'role' => 'other', 'sender' => 'Lucia Ferreira', 'text' => 'Ótimo! Iniciamos na segunda.', 'at' => '2026-05-23T07:05:00'],
            ]),
            $this->conv('c5', 'RH Interno', 'group', 'RH', false, 0, 'Sistema: Lembrete de férias', 'Ontem', [
                ['id' => 'm10', 'role' => 'system', 'sender' => null, 'text' => 'Lembrete: 3 solicitações de férias aguardando aprovação.', 'at' => '2026-05-22T16:00:00'],
            ]),
        ];
    }

    public function getUnreadCount(): int
    {
        $total = 0;
        foreach ($this->getConversations() as $conv) {
            $total += (int) ($conv['unread'] ?? 0);
        }

        return $total;
    }

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return array<string, mixed>
     */
    private function conv(
        string $id,
        string $name,
        string $type,
        string $initials,
        bool $online,
        int $unread,
        string $preview,
        string $timeLabel,
        array $messages,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'initials' => $initials,
            'online' => $online,
            'unread' => $unread,
            'preview' => $preview,
            'time_label' => $timeLabel,
            'messages' => $messages,
        ];
    }
}
