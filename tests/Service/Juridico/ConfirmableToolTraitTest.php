<?php

namespace App\Tests\Service\Juridico;

use App\Service\Sasha\Tool\Juridico\ConfirmableToolTrait;
use PHPUnit\Framework\TestCase;

final class ConfirmableToolTraitTest extends TestCase
{
    private object $fixture;

    protected function setUp(): void
    {
        $this->fixture = new class {
            use ConfirmableToolTrait;

            /** @param array<string, mixed> $params */
            public function isConfirmado(array $params): bool
            {
                return $this->confirmado($params);
            }

            /**
             * @param array<string, mixed> $params
             * @param list<array{label: string, value: string}> $preview
             *
             * @return array<string, mixed>
             */
            public function confirmar(string $tool, array $params, string $titulo, array $preview): array
            {
                return $this->pedirConfirmacao($tool, $params, $titulo, $preview);
            }
        };
    }

    /** @dataProvider valoresConfirmados */
    public function testReconheceValoresConfirmados(mixed $valor): void
    {
        self::assertTrue($this->fixture->isConfirmado(['confirmado' => $valor]));
    }

    /** @return iterable<string, array{0: mixed}> */
    public static function valoresConfirmados(): iterable
    {
        yield 'bool true' => [true];
        yield 'int 1' => [1];
        yield 'string 1' => ['1'];
        yield 'string true' => ['true'];
    }

    public function testNaoConfirmadoQuandoAusenteOuFalsy(): void
    {
        self::assertFalse($this->fixture->isConfirmado([]));
        self::assertFalse($this->fixture->isConfirmado(['confirmado' => false]));
        self::assertFalse($this->fixture->isConfirmado(['confirmado' => '0']));
        self::assertFalse($this->fixture->isConfirmado(['confirmado' => 0]));
    }

    public function testPedirConfirmacaoMontaEstruturaComParamsConfirmados(): void
    {
        $card = $this->fixture->confirmar(
            'criar_tarefa',
            ['processo_id' => 12, 'titulo' => 'Apelação'],
            'Criar tarefa no processo',
            [['label' => 'Processo', 'value' => '123']],
        );

        self::assertSame('confirm', $card['type']);
        self::assertSame('criar_tarefa', $card['tool']);
        self::assertSame('Criar tarefa no processo', $card['title']);
        self::assertTrue($card['params']['confirmado']);
        self::assertSame(12, $card['params']['processo_id']);
        self::assertSame('Sim, pode confirmar', $card['confirm_label']);
        self::assertSame('Agora não', $card['cancel_label']);
    }
}
