<?php
namespace Ceevo\Migrations;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Ceevo\Helper\PaymentHelper;

class CreatePaymentMethod
{
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepositoryContract;
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    /**
     * CreatePaymentMethod constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepositoryContract
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(    PaymentMethodRepositoryContract $paymentMethodRepositoryContract,
                                    PaymentHelper $paymentHelper)
    {
        $this->paymentMethodRepositoryContract = $paymentMethodRepositoryContract;
        $this->paymentHelper = $paymentHelper;
    }

    public function run()
    {
        if($this->paymentHelper->getPaymentMethod(PaymentHelper::PAYMENTKEY_CEEVO) == 'no_paymentmethod_found')
        {
            $paymentMethodData = array( 'pluginKey'   => 'plentyCeevo',
                                        'paymentKey'  => 'CEEVO_PAYMENT',
                                        'name'        => 'Ceevo Payment');                                        
            $this->paymentMethodRepositoryContract->createPaymentMethod($paymentMethodData);
        }
        
    }
}
?>