<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // HostGator/UTC: agenda e “hoje” no horário do Brasil
        date_default_timezone_set('America/Sao_Paulo');
        parent::boot();
    }
}
