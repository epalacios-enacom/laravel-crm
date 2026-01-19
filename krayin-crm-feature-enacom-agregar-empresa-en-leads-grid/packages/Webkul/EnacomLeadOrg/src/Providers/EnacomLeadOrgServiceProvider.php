<?php

namespace Webkul\EnacomLeadOrg\Providers;

use Illuminate\Support\ServiceProvider;

class EnacomLeadOrgServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'enacomleadorg');
    }

    public function register()
    {
    }
}
