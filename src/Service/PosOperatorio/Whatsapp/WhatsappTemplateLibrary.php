<?php

namespace App\Service\PosOperatorio\Whatsapp;

/**
 * Biblioteca de templates de mensagens WhatsApp para diferentes eventos clínicos.
 * Centraliza a criação de mensagens padronizadas e personalizáveis.
 */
final class WhatsappTemplateLibrary
{
    /**
     * Confirmação de agendamento (D-1)
     */
    public static function agendaConfirmacao(
        string $pacienteNome,
        \DateTimeInterface $dataHora,
        string $procedimento,
        string $profissional,
        ?string $localEndereco = null,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        $quando = $dataHora->format('d/m/Y \à\s H:i');
        
        $msg = "🏥 *Lembrete de Consulta*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Confirme sua consulta amanhã:\n";
        $msg .= "📅 {$quando}\n";
        $msg .= "👨‍⚕️ {$profissional}\n";
        $msg .= "🩺 {$procedimento}\n";
        
        if ($localEndereco) {
            $msg .= "\n📍 Local: {$localEndereco}\n";
        }
        
        $msg .= "\n💬 Responda SIM para confirmar ou ligue para reagendar.\n";
        $msg .= "\n_Mensagem automática • Unio Saúde_";
        
        return $msg;
    }

    /**
     * Marco da Trilha pré-operatória (D-7, D-3, etc)
     */
    public static function trilhaMarco(
        string $pacienteNome,
        int $diaRelativo,
        string $itemChecklist,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        $diaLabel = self::formatDiaLabel($diaRelativo);
        
        $msg = "🛡️ *Trilha Unio • {$diaLabel}*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Lembrete importante para sua preparação cirúrgica:\n\n";
        $msg .= "✓ {$itemChecklist}\n\n";
        $msg .= "Qualquer dúvida, entre em contato com sua equipe médica.\n";
        $msg .= "\n_Saúde que acompanha • Unio Saúde_";
        
        return $msg;
    }

    /**
     * Resultado de exame disponível
     */
    public static function resultadoExame(
        string $pacienteNome,
        string $tipoExame,
        string $urlPortal,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        
        $msg = "🔬 *Resultado Disponível*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "O resultado do seu exame já está disponível:\n";
        $msg .= "📋 {$tipoExame}\n\n";
        $msg .= "🔗 Acesse: {$urlPortal}\n\n";
        $msg .= "Em caso de dúvidas, consulte seu médico.\n";
        $msg .= "\n_Unio Saúde_";
        
        return $msg;
    }

    /**
     * Lembrete de medicação pós-operatória
     */
    public static function lembreteMedicacao(
        string $pacienteNome,
        string $medicamento,
        string $posologia,
        string $horario,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        
        $msg = "💊 *Lembrete de Medicação*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Hora de tomar sua medicação:\n\n";
        $msg .= "🔹 {$medicamento}\n";
        $msg .= "🔹 {$posologia}\n";
        $msg .= "🕐 Horário: {$horario}\n\n";
        $msg .= "Não esqueça de marcar no seu aplicativo!\n";
        $msg .= "\n_Cuidado contínuo • Unio Saúde_";
        
        return $msg;
    }

    /**
     * Cobrança de conta em aberto
     */
    public static function cobrancaConta(
        string $pacienteNome,
        float $valorReais,
        \DateTimeInterface $vencimento,
        ?string $urlPagamento = null,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        $valorFormatado = number_format($valorReais, 2, ',', '.');
        $vencimentoFormatado = $vencimento->format('d/m/Y');
        
        $msg = "💳 *Lembrete Financeiro*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Identificamos uma conta em aberto:\n\n";
        $msg .= "💰 Valor: R$ {$valorFormatado}\n";
        $msg .= "📅 Vencimento: {$vencimentoFormatado}\n";
        
        if ($urlPagamento) {
            $msg .= "\n🔗 Pagar agora: {$urlPagamento}\n";
        }
        
        $msg .= "\nDúvidas? Entre em contato conosco.\n";
        $msg .= "\n_Unio Saúde_";
        
        return $msg;
    }

    /**
     * Confirmação de pagamento recebido
     */
    public static function confirmacaoPagamento(
        string $pacienteNome,
        float $valorReais,
        string $metodoPagamento,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        $valorFormatado = number_format($valorReais, 2, ',', '.');
        
        $msg = "✅ *Pagamento Confirmado*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Recebemos seu pagamento:\n\n";
        $msg .= "💰 Valor: R$ {$valorFormatado}\n";
        $msg .= "💳 Forma: {$metodoPagamento}\n\n";
        $msg .= "Obrigado pela confiança!\n";
        $msg .= "\n_Unio Saúde_";
        
        return $msg;
    }

    /**
     * Pesquisa de satisfação pós-atendimento
     */
    public static function pesquisaSatisfacao(
        string $pacienteNome,
        string $urlPesquisa,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        
        $msg = "⭐ *Sua opinião importa!*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Como foi sua experiência conosco?\n\n";
        $msg .= "Responda nossa breve pesquisa (2 min):\n";
        $msg .= "🔗 {$urlPesquisa}\n\n";
        $msg .= "Seu feedback nos ajuda a melhorar!\n";
        $msg .= "\n_Obrigado • Unio Saúde_";
        
        return $msg;
    }

    /**
     * Aniversário do paciente
     */
    public static function aniversario(string $pacienteNome): string
    {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        
        $msg = "🎉 *Feliz Aniversário!*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "A equipe Unio Saúde deseja um dia especial repleto de saúde e alegria!\n\n";
        $msg .= "🎂🎈🎁\n\n";
        $msg .= "Conte sempre conosco para cuidar da sua saúde.\n";
        $msg .= "\n_Com carinho • Unio Saúde_";
        
        return $msg;
    }

    /**
     * Alerta de alta hospitalar
     */
    public static function altaHospitalar(
        string $pacienteNome,
        \DateTimeInterface $dataAlta,
        string $orientacoes,
        ?string $retornoData = null,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        $dataFormatada = $dataAlta->format('d/m/Y');
        
        $msg = "🏠 *Alta Hospitalar*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Sua alta foi liberada em {$dataFormatada}.\n\n";
        $msg .= "*Orientações importantes:*\n";
        $msg .= "{$orientacoes}\n";
        
        if ($retornoData) {
            $msg .= "\n📅 Retorno agendado: {$retornoData}\n";
        }
        
        $msg .= "\nQualquer emergência, entre em contato!\n";
        $msg .= "\n_Cuidado contínuo • Unio Saúde_";
        
        return $msg;
    }

    /**
     * Solicitação de documentos
     */
    public static function solicitacaoDocumentos(
        string $pacienteNome,
        array $documentos,
        ?string $prazo = null,
    ): string {
        $primeiroNome = explode(' ', trim($pacienteNome))[0] ?: 'paciente';
        
        $msg = "📄 *Documentos Necessários*\n\n";
        $msg .= "Olá, {$primeiroNome}!\n\n";
        $msg .= "Para continuarmos seu atendimento, precisamos dos seguintes documentos:\n\n";
        
        foreach ($documentos as $doc) {
            $msg .= "• {$doc}\n";
        }
        
        if ($prazo) {
            $msg .= "\n⏰ Prazo: {$prazo}\n";
        }
        
        $msg .= "\nEnvie via WhatsApp ou portal do paciente.\n";
        $msg .= "\n_Unio Saúde_";
        
        return $msg;
    }

    private static function formatDiaLabel(int $dia): string
    {
        if ($dia === 0) {
            return 'Dia da cirurgia';
        }
        if ($dia < 0) {
            return abs($dia) . ' dias antes';
        }
        return $dia . ' dias depois';
    }
}
