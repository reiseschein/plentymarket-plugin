<?php
/**
 * Created by IntelliJ IDEA.
 * User: ckunze
 * Date: 23/2/17
 * Time: 15:48
 */

namespace CeevoPayment\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;
use Plenty\Plugin\Routing\ApiRouter;

class CeevoPaymentRouteServiceProvider extends RouteServiceProvider
{

    /**
     * @param Router $router
     */
    public function map(Router $router , ApiRouter $apiRouter)
    {
       $apiRouter->version(['v1'], ['middleware' => ['oauth']],
            function ($routerApi)
            {
                /** @var ApiRouter $routerApi */
                $routerApi->get('payment/ceevopayment/settings/{plentyId}/{lang}'  , ['uses' => 'CeevoPayment\Controllers\SettingsController@loadSettings']);
                $routerApi->put('payment/ceevopayment/settings'                    , ['uses' => 'CeevoPayment\Controllers\SettingsController@saveSettings']);
            });
    }

}