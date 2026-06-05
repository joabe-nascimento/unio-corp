<?php

namespace App\Security;

/**
 * Níveis mínimos por ação no Núcleo TI (escopo hub_ti).
 *
 * Hierarquia: MEMBRO(1) < SUPERVISOR_EQUIPE(2) < SUPERVISOR(3) < GESTOR_EQUIPE(4) < GESTOR(5)
 * Tenant: bypass em TiGrantService.
 */
final class TiGrantPolicy
{
    public const SCOPE = 'hub_ti';

    /** Ver módulo (qualquer grant no produto). */
    public const VIEW = 'MEMBRO';

    /** Portal solicitante — abrir/acompanhar próprios chamados. */
    public const PORTAL_CHAMADO = 'MEMBRO';

    /** Fila service desk — triagem, status, notas, atribuir, Helia operacional. */
    public const OPERATE_CHAMADOS = 'SUPERVISOR_EQUIPE';

    /** Excluir chamado, escalar, pausar SLA. */
    public const MANAGE_CHAMADOS = 'SUPERVISOR';

    /** Exclusão definitiva e ações destrutivas na fila. */
    public const DELETE_CHAMADOS = 'GESTOR_EQUIPE';

    /** KB — leitura. */
    public const VIEW_KB = 'SUPERVISOR_EQUIPE';

    /** KB — CRUD. */
    public const MANAGE_KB = 'GESTOR_EQUIPE';

    /** Problemas — leitura. */
    public const VIEW_PROBLEMAS = 'SUPERVISOR';

    /** Problemas — CRUD e vínculo. */
    public const MANAGE_PROBLEMAS = 'GESTOR_EQUIPE';

    /** SLA / manutenções — leitura. */
    public const VIEW_OPS = 'SUPERVISOR_EQUIPE';

    /** Manutenções — CRUD. */
    public const MANAGE_MANUTENCOES = 'GESTOR_EQUIPE';

    /** Infra (ativos, licenças, integrações) — leitura. */
    public const VIEW_INFRA = 'SUPERVISOR';

    /** Infra — CRUD. */
    public const MANAGE_INFRA = 'GESTOR_EQUIPE';

    /** Cortex / analytics — leitura. */
    public const VIEW_INTEL = 'SUPERVISOR_EQUIPE';

    /** Analytics export, integrações sensíveis. */
    public const MANAGE_INTEL = 'GESTOR_EQUIPE';

    /** Novidades — publicar/editar. */
    public const MANAGE_NOVIDADES = 'GESTOR_EQUIPE';

    /** Configuração plena (ex.: excluir integração). */
    public const CONFIG = 'GESTOR';
}
