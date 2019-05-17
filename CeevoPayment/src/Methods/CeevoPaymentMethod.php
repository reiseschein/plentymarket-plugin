<?php

namespace CeevoPayment\Methods;

use IO\Services\SessionStorageService;
use CeevoPayment\Services\SettingsService;
use Plenty\Modules\Category\Contracts\CategoryRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Application;

/**
 * Class CeevoPaymentMethod
 * @package CeevoPayment\Methods
 */
class CeevoPaymentMethod extends PaymentMethodService
{
    /** @var BasketRepositoryContract */
    private $basketRepo;

    /** @var  SettingsService */
    private $settings;

    /** @var  Checkout */
    private $checkout;

    /**
    * CeevoPaymentMethod constructor.
    * @param BasketRepositoryContract $basketRepo
    * @param SettingsService          $settingsService
    * @param Checkout                 $checkout
    */
    public function __construct(BasketRepositoryContract    $basketRepo,
                                SettingsService             $settingsService,
                                Checkout $checkout)
    {
        $this->basketRepo     = $basketRepo;
        $this->settings     = $settingsService;
        $this->checkout       = $checkout;
    }

    /**
    * Check whether CeevoPayment is active or not
    *
    * @return bool
    */
    public function isActive()
    {
        if(!in_array($this->checkout->getShippingCountryId(), $this->settings->getShippingCountries()))
        {
            return false;
        }

        return true;
    }

    /**
     * Get shown name
     *
     * @param $lang
     * @return string
     */
    public function getName($lang = 'de')
    {
        $name = $this->settings->getSetting('name', $lang);
        if(!strlen($name) > 0)
        {
            return 'Barzahlung';
        }
        return $name;
    }

    /**
    * Get CeevoPayment Fee
    *
    * @return float
    */
    public function getFee()
    {

        return 0.00;
        $basket = $this->basketRepo->load();

        // Shipping Country ID with ID = 1 belongs to Germany
        if($basket->shippingCountryId == 1)
        {
              return (float)$this->settings->getSetting('feeDomestic');
        }
        else
        {
              return (float)$this->settings->getSetting('feeForeign');
        }
    }

    /**
    * Get CeevoPayment Icon
    *
    * @return string
    */
    public function getIcon( ConfigRepository $config )
    {
        if( $this->settings->getSetting('logo') == 1)
        {
            return $this->settings->getSetting('logoUrl');
        }
        elseif($this->settings->getSetting('logo') == 2)
        {
            $app = pluginApp(Application::class);
                $icon = $app->getUrlPath('ceevopayment').'/images/icon.png';

                return $icon;
        }

        return '';
    }

    /**
    * Get CeevoPayment Description
    *
    * @param ConfigRepository $config
    * @return string
    */
    public function getDescription( ConfigRepository $config )
    {
      /** @var FrontendSessionStorageFactoryContract $session */
        $session = pluginApp(FrontendSessionStorageFactoryContract::class);
        $lang = $session->getLocaleSettings()->language;
        return $this->settings->getSetting('description', $lang);
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @return bool
     */
    public function isSwitchableTo()
    {
        return true;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @return bool
     */
    public function isSwitchableFrom()
    {
        return true;
    }

    /**
     * Get PrepaymentSourceUrl
     *
     * @return string
     */
    public function getSourceUrl()
    {
        /** @var FrontendSessionStorageFactoryContract $session */
        $session = pluginApp(FrontendSessionStorageFactoryContract::class);
        $lang = $session->getLocaleSettings()->language;

        $infoPageType = $this->settings->getSetting('infoPageType', $lang);

        switch ($infoPageType)
        {
            case 1:
                // internal
                $categoryId = (int) $this->settings->getSetting('infoPageIntern', $lang);
                if($categoryId  > 0)
                {
                    /** @var CategoryRepositoryContract $categoryContract */
                    $categoryContract = pluginApp(CategoryRepositoryContract::class);
                    return $categoryContract->getUrl($categoryId, $lang);
                }
                return '';
            case 2:
                // external
                return $this->settings->getSetting('infoPageExtern', $lang);
            default:
                return '';
        }
    }
}
