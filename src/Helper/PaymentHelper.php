<?php //strict

namespace Ceevo\Helper;

use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Method\Models\PaymentMethod;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;

use Ceevo\Services\SessionStorageService;

/**
 * Class PaymentHelper
 * @package Ceevo\Helper
 */
class PaymentHelper
{
  use Loggable;
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var SessionStorageService
     */
    private $sessionService;

    /**
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepo;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepo;

    /**
     * @var array
     */
    private $statusMap = array();

    const PAYMENTKEY_CEEVO = 'CV';
    const PAYMENTKEY_CEEVO_NAME = 'CEEVO';
    /**
     * PaymentHelper constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentRepositoryContract $paymentRepo
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
     * @param ConfigRepository $config
     * @param SessionStorageService $sessionService
     * @param OrderRepositoryContract $orderRepo
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                PaymentRepositoryContract $paymentRepo,
                                PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo,
                                ConfigRepository $config,
                                SessionStorageService $sessionService,
                                OrderRepositoryContract $orderRepo)
    {
        $this->config                                   = $config;
        $this->sessionService                           = $sessionService;
        $this->paymentMethodRepository                  = $paymentMethodRepository;
        $this->paymentOrderRelationRepo                 = $paymentOrderRelationRepo;
        $this->paymentRepository                        = $paymentRepo;
        $this->orderRepo                                = $orderRepo;
        $this->statusMap                                = array();
    }

    /**
     * Load the ID of the payment method for the given plugin key
     * Return the ID for the payment method
     *
     * @param string $paymethod Paymethod Shortcut i.e. CC
     * @return string|int
     */
    public function getPaymentMethod($paymethod)
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('ceevo');
 
        if( !is_null($paymentMethods) )
        {
            foreach($paymentMethods as $paymentMethod)
            {
                if($paymentMethod->paymentKey == 'CEEVO')
                {
                    return $paymentMethod->id;
                }
            }
        }

        return 'no_paymentmethod_found';
    }

    public function getPaymentKeyByMop($mop)
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('ceevo');
        if(!is_null($paymentMethods))
        {
            foreach($paymentMethods as $paymentMethod)
            {
                if($paymentMethod->id == $mop)
                {
                    return $paymentMethod->paymentKey;
                }
            }
        }
        return false;
    }
    
    public function createPlentyPayment($data, $trxid, $uniqueid)
    {
      $this
            ->getLogger(__CLASS__ . '_' . __METHOD__)
            ->info('Ceevo::Logger.infoCaption', [
              'data' => $data,
              'trxid' => $trxid,
              'uniqueid' => $uniqueid,
            ]);
      
        /** @var Payment $payment */
        $payment = pluginApp( \Plenty\Modules\Payment\Models\Payment::class );

        $payment->mopId             = (int)$data['REQUEST']['CRITERION.MOPID'];
        $payment->transactionType   = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status            = $this->mapStatus((STRING)$data['STATUS']); // 1=wait approve 2=approve 3=CP 4=PartCP 5=cancel 6=refused 9=RF 10=PartRF
        $payment->currency          = $data['REQUEST']['CURRENCY'];
        $payment->amount            = $data['REQUEST']['AMOUNT'];
        $payment->receivedAt        = date('YmdHis');
        //$payment->method            = $this->paymentMethodRepository->findByPaymentMethodId((int)$data['REQUEST']['CRITERION.MOPID']);
      
        $this
            ->getLogger(__CLASS__ . '_' . __METHOD__)
            ->info('Ceevo::Logger.infoCaption', [
              'payment' => $payment,
            ]);
        
        //try {
          $paymentProperty = [];
          $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, 'TransactionID: '.(string)$trxid);
          //$paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_REFERENCE_ID, (string)$uniqueid);
          $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $uniqueid);
          $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_ORIGIN, Payment::ORIGIN_PLUGIN);

          $this
            ->getLogger(__CLASS__ . '_' . __METHOD__)
            ->info('Ceevo::Logger.infoCaption', [
              'props' => $paymentProperty,
            ]);
          
          $payment->properties = $paymentProperty;

          $payment = $this->paymentRepository->createPayment($payment);
          
          $this
            ->getLogger(__CLASS__ . '_' . __METHOD__)
            ->info('Ceevo::Logger.infoCaption', [
              'payment' => $payment,
            ]);
        /*
        } catch (Exception $e) {
          $this
           ->getLogger('PaymentHelper::createPlentyPayment')
           ->info('Ceevo::Logger.infoCaption', [
            $e->getMessage()
          ]);
        }
        */
        
        return $payment;
    }
    
    /**
     * Assign the payment to an order in plentymarkets
     *
     * @param Payment $payment
     * @param int $orderId
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
      
      $this
            ->getLogger('PaymentHandler::assignPlentyPaymentToPlentyOrder')
            ->info('Ceevo::Logger.infoCaption', [
              'payment' => $payment,
              'orderId' => $orderId,
            ]);
      
      //try {
        // Get the order by the given order ID
        $order = $this->orderRepo->findOrderById($orderId);
        
        $this
            ->getLogger('PaymentHandler::assignPlentyPaymentToPlentyOrder')
            ->info('Ceevo::Logger.infoCaption', [
              'order' => $order,
            ]);

        // Check whether the order truly exists in plentymarkets
        if(!is_null($order) && $order instanceof Order)
        {
            // Assign the given payment to the given order
            $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);
        }
      /*
      } catch (Exception $e) {
          $this
           ->getLogger('PaymentHelper::assignPlentyPaymentToPlentyOrder')
           ->info('Ceevo::Logger.infoCaption', [
            $e->getMessage()
          ]);
      }
      */
    }

    public function mapStatus(string $status)
    {
        if(!is_array($this->statusMap) || count($this->statusMap) <= 0)
        {
            $statusConstants = $this->paymentRepository->getStatusConstants();
            if(!is_null($statusConstants) && is_array($statusConstants))
            {
                $this->statusMap['SUCCEEDED']             = $statusConstants['captured'];
                $this->statusMap['FAILED']                = $statusConstants['refused'];
                $this->statusMap['PENDING']               = $statusConstants['awaiting_approval'];
                $this->statusMap['ERROR']                 = $statusConstants['refused'];
                $this->statusMap['CANCEL']                = $statusConstants['refused'];
            }
        }
        return strlen($status)?(int)$this->statusMap[$status]:2;
    }
    
    /**
     * Returns a PaymentProperty with the given params
     *
     * @param Payment $payment
     * @param array $data
     * @return PaymentProperty
     */
    private function getPaymentProperty($typeId, $value)
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp( \Plenty\Modules\Payment\Models\PaymentProperty::class );

        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = (string)$value;

        return $paymentProperty;
    }
    
    public function log($class, $method, $msg, $arg, $error = false)
    {
        $logger = $this->getLogger($class . '_' . $method);
        if ($error) {
            $logger->error($msg, $arg);
        } else {
            if (!is_array($arg)) {
                $arg = [$arg];
            }
            $arg[] = $msg;
            $logger->info('Ceevo::Logger.infoCaption', $arg);
        }
    }
}