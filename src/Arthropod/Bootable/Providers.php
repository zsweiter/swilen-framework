<?php

namespace Swilen\Arthropod\Bootable;

use Swilen\Arthropod\Contract\BootableService;
use Swilen\Shared\Arthropod\Application;

class Providers implements BootableService
{
    /**
     * Register and boostrap application service providers.
     *
     * @param \Swilen\Shared\Arthropod\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $app->registerProviders();

        $app->boot();
    }
}
