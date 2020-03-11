<?php
namespace Ceevo\Helper;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PayCore
 * @package Ceevo\Helper
 */
class PayCore
{
  use Loggable;
  /**
   * ContactService constructor.
   */
  public function __construct()
  {
  }

  public $response   = '';
  public $error      = '';

  public $live_url       = 'https://api.ceevo.com';
  public $test_url       = 'https://api.dev.ceevo.com';
  public $live_token_url   = 'https://auth.ceevo.com/auth/realms/ceevo-realm/protocol/openid-connect/token';
  public $test_token_url   = 'https://auth.dev.transact24.com/auth/realms/ceevo-realm/protocol/openid-connect/token';
  public $live_sdk_url = 'https://sdk.ceevo.com';
  public $test_sdk_url = 'https://sdk-beta.ceevo.com';

  public $access_token = '';

  function getCustomerByEmail($param){
    $url = $param['API.URL'];
    $userData = $param['userData'];
    $customer_id = false;

    if($userData && array_key_exists('email', $userData)) {
      $url = $url . '/payment/customer/'.$userData['email'];
    
      // $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', [ 'url' => $url, ]);
      $customer_id = $this->callAPI('GET', $url, $param, null, __METHOD__);
    }
    return $customer_id;
  }

  function createCustomer($param){
    $url = $param['API.URL'];
    $userData = $param['userData'];

    $customer_id = $this->getCustomerByEmail($param);
    if(!$customer_id){
      $data = array("billing_address" => array("city" => $userData['city'], "country" => $userData['country'],"state" => $userData['state'],
                    "street" => $userData['street'],"zip_or_postal"=> $userData['zip']),"email" => $userData['email'],"first_name" => $userData['firstname'],
                    "last_name" => $userData['lastname'],"mobile" => $userData['phone'],"phone" => $userData['phone']);  
      $data_string = json_encode($data);
      // $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $data);
      $customer_id = $this->callAPI('POST', $url . '/payment/customer', $param, $data_string, __METHOD__);
    }
   
    return $customer_id;
  }

  function genCardTokenWidget($twig, $param) {
    $content = '<button type="button" data-dismiss="modal" aria-label="Close" class="close" onclick="location.href=\'/checkout\'"><span aria-hidden="true">×</span></button>
    <center><iframe src="payment/ceevo/token_frame" frameborder="0" width="100%" height="800px" id="widget_frame"></iframe></center>';
    return $content;
    // return $twig->render('Ceevo::content.tokenise', ['apiKey' => $param['API.KEY'], 'mode' => $param['ENV.MODE'], 'price' => $param['PRICE'], 
    //                       'currency' => $param['CURRENCY'], 'apiUrl' => $apiUrl, 'cardTokenUrl' => $param['cardTokenUrl']]);
  }

  function registerAccountToken($conf, $customer_registered_id){
      $url = $conf['API.URL'];
      $token_array = array("account_token" => $conf['tokenise']['card_token'],"is_default" => true);
      // $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $token_array);
      $token_string = json_encode($token_array);
      $this->callAPI('POST', $url . '/payment/customer/'.$customer_registered_id, $conf, $token_string, __METHOD__);
  }

  function getToken($conf){
    $api = $conf['TOKEN.URL'];
    $param['grant_type'] = "client_credentials"; 
    $param['client_id'] = $conf['CLIENT.ID']; 
    $param['client_secret'] = $conf['CLIENT.SECRET']; 
    $mode = $conf['ENV.MODE'];

    $curl = curl_init(); 
    curl_setopt($curl, CURLOPT_URL,$api); 
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,1); 
    //curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , 120);
    curl_setopt($curl, CURLOPT_TIMEOUT, 120);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($param));
    $res = curl_exec($curl); 
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $res);
    $jres = json_decode($res, true);

    $this->access_token  = $jres['access_token'];
    return $this->access_token;
  } 

  function chargeApi($param){
    $url = $param['API.URL'];

    $userData = $param['userData'];   
    $orderId =  $param['REQUEST']['ORDER.ID'];
    $apiKey =  $param['API.KEY'];
    $mode = $param['ENV.MODE'];
    $threed = $param['3D.SECURE'];
    $isCapture = $param['TRANSACTION.TYPE'];

    $items_array = array();
    foreach($param['basketItems'] as $item){      
      $item_json = array("item" => $item['name'],"itemValue" =>(string) ($item['price'] * 100));
      array_push($items_array, json_encode($item_json));
    }
    $itemString = implode(',',$items_array);

    $access_token = $this->access_token;    
    $authorization = "Authorization: Bearer $access_token";   
    $charge_api = $url . "/payment/charge";        
    
    $successURL = $param['REQUEST']['CRITERION.SUCCESSURL'];
    $failURL = $param['REQUEST']['CRITERION.FAILURL'];
    $cparam = '{"amount": '.( $param['REQUEST']['AMOUNT'] * 100 ).',
            "3dsecure": '.$threed.',
            "capture": '.$isCapture.',
            "mode" : "'.$mode.'",
            "method_code":  "'.$param['tokenise']['method_code'].'",
            "currency": "'.$param['REQUEST']['CURRENCY'].'",
            "customer_id": "'.$param['customer_id'].'", 
            "account_token": "'.$param['tokenise']['card_token'].'",
            "session_id": "'.$param['tokenise']['session_id'].'",
            "redirect_urls": {
                "failure_url": "'.$failURL.'",
                "success_url": "'.$successURL.'"
            },
            "cart_items": ['.$itemString.'],
            "reference_id": "'.$orderId.'",
            "shipping_address": {
                "city": "'.$userData['city'].'",
                "country": "'.$userData['country'].'",
                "state": "'.$userData['state'].'",
                "street": "'.$userData['street'].'",
                "zip_or_postal": "'.$userData['zip'].'"
            },
            "user_email": "'.$userData['email'].'"}';
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $cparam);
    $curl = curl_init(); 
    curl_setopt($curl, CURLOPT_URL,$charge_api); 
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,1); 
    //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , 120);
    curl_setopt($curl, CURLOPT_TIMEOUT, 120);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $cparam);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($cparam),
            $authorization
        )
    );
    $cres = curl_exec($curl);
    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $cres);

    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $locationUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
    $headers = substr($cres, 0, $header_size);
    $body = substr($cres, $header_size); 
    $jbody = json_decode($body, true);
    curl_close($curl);

    $transactionHeaders = $this->http_parse_headers($headers);
    if(empty($locationUrl)) {
      $locationUrl   = $transactionHeaders['Location'] ? $transactionHeaders['Location'] : $transactionHeaders['location'];                
    }

    $jbody['3d_url']   = $locationUrl;   

    return $jbody;
  }

  function callAPI($method, $url, $conf, $data, $func){
    $apiKey =  $conf['API.KEY'];
    $access_token = $this->access_token;
    $authorization = "Authorization: Bearer $access_token";

    $curl = curl_init();
    $this->getLogger(__CLASS__ . '_' . $func)->info('Ceevo::Logger.infoCaption', ($data)?$data:['url' => $url]);
    switch ($method){
        case "POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data)
              curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
          break;
        case "PUT":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
          if ($data)
              curl_setopt($curl, CURLOPT_POSTFIELDS, $data);                                 
          break;
        default:
          if ($data)
              $url = sprintf("%s?%s", $url, $data);
    }
    
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data),
        $authorization
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , 120);
    curl_setopt($curl, CURLOPT_TIMEOUT, 120);

    // EXECUTE:
    $response = curl_exec($curl);
    $hCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
    // Retudn headers seperatly from the Response Body
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $locationUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $this->getLogger(__CLASS__ . '_' . $func)->info('Ceevo::Logger.infoCaption', ['response'=>$body]);

    curl_close($curl);
    header("Content-Type:text/plain; charset=UTF-8");
    $transactionHeaders = $this->http_parse_headers($headers);
    if(empty($locationUrl)) {
      $locationUrl   = $transactionHeaders['Location'] ? $transactionHeaders['Location'] : $transactionHeaders['location'];                
    }

    $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', $transactionHeaders);

    $returnId = '';
    $bodyDecode = json_decode($body);
    switch ($callType) {
      case 'getCustomerByEmail':                 
          if($hCode == '404') {
              $returnId = false;
          } else {
              $returnId = end($bodyDecode)->id;
              $this->getLogger(__CLASS__ . '_' . __METHOD__)->info('Ceevo::Logger.infoCaption', [ 'is_exist_customer' => $returnId, ]);
          }
      break;
      case 'createCustomer':              
          // $path = parse_url($locationUrl, PHP_URL_PATH);
          $returnId = basename($locationUrl);
      break;
      case 'registerAccountToken':
          $returnId  =  $bodyDecode->status;
      break;
    }
    return $returnId;
  }

  function http_parse_headers($raw_headers)
  {
      $headers = array();
      $key = ''; // [+]
      foreach(explode("\n", $raw_headers) as $i => $h)
      {
          $h = explode(':', $h, 2);
          if (isset($h[1]))
          {
              if (!isset($headers[$h[0]]))
                  $headers[$h[0]] = trim($h[1]);
              elseif (is_array($headers[$h[0]]))
              {
                  // $tmp = array_merge($headers[$h[0]], array(trim($h[1]))); // [-]
                  // $headers[$h[0]] = $tmp; // [-]
                  $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1]))); // [+]
              }
              else
              {
                  // $tmp = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [-]
                  // $headers[$h[0]] = $tmp; // [-]
                  $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [+]
              }
              $key = $h[0]; // [+]
          }
          else // [+]
          { // [+]
              if (substr($h[0], 0, 1) == "\t") // [+]
                  $headers[$key] .= "\r\n\t".trim($h[0]); // [+]
              elseif (!$key) // [+]
                  $headers[0] = trim($h[0]);trim($h[0]); // [+]
          } // [+]
      }
      return $headers;
  }

} // end of class
?>
