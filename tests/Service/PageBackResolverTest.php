<?php

namespace App\Tests\Service;

use App\Service\Organismo\OrganismoFeature;
use App\Service\Organismo\OrganismoRedirectService;
use App\Service\PageBackResolver;
use PHPUnit\Framework\TestCase;

class PageBackResolverTest extends TestCase
{
    private PageBackResolver $resolver;

    protected function setUp(): void
    {
        $redirects = new OrganismoRedirectService(new OrganismoFeature(false, false));
        $this->resolver = new PageBackResolver($redirects);
    }

    public function testFeriasListBackToRhHub(): void
    {
        $back = $this->resolver->resolve('app_rh_ferias');
        $this->assertNotNull($back);
        $this->assertSame('app_rh', $back['route']);
    }

    public function testFeriasShowBackToList(): void
    {
        $back = $this->resolver->resolve('app_rh_ferias_show');
        $this->assertNotNull($back);
        $this->assertSame('app_rh_ferias', $back['route']);
    }

    public function testRhHubBackToOperacoes(): void
    {
        $back = $this->resolver->resolve('app_rh');
        $this->assertNotNull($back);
        $this->assertSame('app_hub_operacoes', $back['route']);
    }

    public function testPortalSubpageBackToPortal(): void
    {
        $back = $this->resolver->resolve('app_rh_portal_ferias');
        $this->assertNotNull($back);
        $this->assertSame('app_rh_portal', $back['route']);
    }

    public function testRecrutamentoCarreirasBackToHub(): void
    {
        $back = $this->resolver->resolve('app_recrutamento_carreiras');
        $this->assertNotNull($back);
        $this->assertSame('app_recrutamento', $back['route']);
    }

    public function testRecrutamentoAnalyticsBackToHub(): void
    {
        $back = $this->resolver->resolve('app_recrutamento_analytics');
        $this->assertNotNull($back);
        $this->assertSame('app_recrutamento', $back['route']);
    }

    public function testRhRecrutamentoPipelineBackToHubPipeline(): void
    {
        $back = $this->resolver->resolve('app_rh_recrutamento_pipeline');
        $this->assertNotNull($back);
        $this->assertSame('app_recrutamento_pipeline', $back['route']);
    }

    public function testRecrutamentoUnknownSubrouteBackToHub(): void
    {
        $back = $this->resolver->resolve('app_recrutamento_candidatos_export');
        $this->assertNotNull($back);
        $this->assertSame('app_recrutamento', $back['route']);
    }

    public function testPosOperatorioPacienteEditarBackToPacientesList(): void
    {
        $back = $this->resolver->resolve('app_pos_operatorio_paciente_editar', ['id' => 2]);
        $this->assertNotNull($back);
        $this->assertSame('app_pos_operatorio_pacientes', $back['route']);
        $this->assertSame([], $back['params']);
    }

    public function testTiKbBackToTiHub(): void
    {
        $back = $this->resolver->resolve('app_ti_kb');
        $this->assertNotNull($back);
        $this->assertSame('app_ti', $back['route']);
    }

    public function testAdminConfiguracoesBackToAdmin(): void
    {
        $back = $this->resolver->resolve('app_admin_configuracoes');
        $this->assertNotNull($back);
        $this->assertSame('app_admin', $back['route']);
    }

    public function testPessoasEquipeDetalheBackToEquipesList(): void
    {
        $back = $this->resolver->resolve('app_pessoas_equipe_detalhe', ['id' => 5]);
        $this->assertNotNull($back);
        $this->assertSame('app_pessoas_equipes', $back['route']);
    }

    public function testCoreProjetosShowBackToLista(): void
    {
        $back = $this->resolver->resolve('app_core_projetos_show', ['id' => 1]);
        $this->assertNotNull($back);
        $this->assertSame('app_core_projetos', $back['route']);
        $this->assertSame(['view' => 'lista'], $back['params']);
    }

    public function testPessoasCargoEditarBackToCargosList(): void
    {
        $back = $this->resolver->resolve('app_pessoas_cargo_editar', ['id' => 3]);
        $this->assertNotNull($back);
        $this->assertSame('app_pessoas_cargos', $back['route']);
    }

    public function testPessoasEquipeEditarBackToDetalhe(): void
    {
        $back = $this->resolver->resolve('app_pessoas_equipe_editar', ['id' => 5]);
        $this->assertNotNull($back);
        $this->assertSame('app_pessoas_equipe_detalhe', $back['route']);
        $this->assertSame(['id' => 5], $back['params']);
    }

    public function testRhAdmissaoShowBackToList(): void
    {
        $back = $this->resolver->resolve('app_rh_admissao_show', ['id' => 1]);
        $this->assertNotNull($back);
        $this->assertSame('app_rh_admissoes', $back['route']);
    }
}
