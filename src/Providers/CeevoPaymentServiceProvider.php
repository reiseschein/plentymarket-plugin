<?php

namespace CeevoPayment\Providers;

use CeevoPayment\Extensions\CeevoPaymentTwigServiceProvider;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use CeevoPayment\Methods\CeevoPaymentMethod;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Plugin\Templates\Twig;

/**
 * Class CeevoPaymentServiceProvider
 * @package CeevoPayment\Providers
 */
class CeevoPaymentServiceProvider extends ServiceProvider
{
    /**
     * Register the route service provider
     */
    public function register()
    {
        $this->getApplication()->register(CeevoPaymentRouteServiceProvider::class);
    }

    /**
     * Boot additional services for the payment method
     *
     * @param Twig $twig
     * @param PaymentMethodContainer $payContainer
     */
    public function boot(   Twig $twig,
                            PaymentMethodContainer $payContainer)
    {
        $twig->addExtension(CeevoPaymentTwigServiceProvider::class);

        //Register the CeevoPayment Plugin
        $payContainer->register('plenty::CASH', CeevoPaymentMethod::class,
            [ AfterBasketChanged::class, AfterBasketItemAdd::class, AfterBasketCreate::class]   );
    }
}