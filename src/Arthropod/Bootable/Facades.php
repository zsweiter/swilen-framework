<?php

namespace Swilen\Arthropod\Bootable;

use Swilen\Arthropod\Contract\BootableService;
use Swilen\Petiole\Facade;
use Swilen\Shared\Arthropod\Application;

class Facades implements BootableService
{
    /**
     * Boostrap facade application with instances.
     *
     * @param \Swilen\Shared\Arthropod\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app)
    {
        Facade::flushFacadeInstances();

        Facade::setFacadeApplication($app);
    }
}
