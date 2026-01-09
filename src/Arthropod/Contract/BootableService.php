<?php

namespace Swilen\Arthropod\Contract;

use Swilen\Shared\Arthropod\Application;

interface BootableService
{
    /**
     * Bootstrap this service.
     *
     * @param \Swilen\Shared\Arthropod\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app);
}
