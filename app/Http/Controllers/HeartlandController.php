<?php

namespace App\Http\Controllers;

use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\ServicesConfig;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\RestaurantsController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HeartlandController extends Controller
{
    function tokenProcess (Request $request)
    {
        $requestValidation = array(
            'tokenPayment', 
            'chargeAmount', 
            'postalCode', 
            'urlPortico', 
            'secretApiKey', 
            'cardNumber', 
            'cardExpMonth',
            'cardExpYear',
            'cardCvn' 
        );
        foreach ($request->all() as $key => $val) {
            if (!in_array($key, $requestValidation)) {
                return response()->json(["error" => "Invalid parameter: " . $key]);
            }
        }

        if(!isset($request->urlPortico)){
            $request->urlPortico = $urlPortico;
        }
        if(!isset($request->secretApiKey)){
            $request->secretApiKey = $secretApiKey;
        }

        $config = new PorticoConfig();
        $config->secretApiKey = $request->secretApiKey;
        $config->serviceUrl = $request->urlPortico;
        ServicesContainer::configureService($config);

        // $card = new CreditCardData();
        // $card->token = $request->tokenPayment;

        $card = new CreditCardData();
        $card->number = $request->cardNumber;
        $card->expMonth = $request->cardExpMonth;
        $card->expYear = $request->cardExpYear;
        $card->cvn = $request->cardCvn;

        $address = new Address();
        $address->postalCode = preg_replace('/[^0-9]/', '', $request->postalCode);

        $response = $card->authorize(round($request->chargeAmount, 2))
        ->withCurrency("USD")
        ->withAddress($address)
        ->execute();

        try {
            $chargeAmount = round($request->chargeAmount, 2);
            $response = $card->charge($chargeAmount)
                ->withCurrency('USD')
                ->withAddress($address)
                ->withAllowDuplicates(true)
                ->execute();
            
            return response()->json(["Process transactionId" => $response->transactionId], 200);    
        } 
        catch (GatewayException $e)
        {
            // handle error
            echo 'Failure: ' . $e->getMessage();
            exit;
        }
        catch (Exception $e) {
            echo 'Failure: ' . $e->getMessage();
            exit;
        }

    }

    function voidProcess (Request $request, $orderId)
    {
        $requestValidation = array(
            'tokenPayment', 
            'chargeAmount', 
            'postalCode', 
            'urlPortico', 
            'secretApiKey', 
            'cardNumber', 
            'cardExpMonth',
            'cardExpYear',
            'cardCvn',
            'transactionId',
        );
        foreach ($request->all() as $key => $val) {
            if (!in_array($key, $requestValidation)) {
                return response()->json(["error" => "Invalid parameter: " . $key]);
            }
        }
        
        // $order = app(OrdersController::class)->orderVoidData($orderId);
        // $restaurant = app(RestaurantsController::class)->restaurantVoidData($order['restaurantId']);
        $chargeAmount = round($request->chargeAmount, 2);

        $config = new PorticoConfig();
        $config->secretApiKey = $request->secretApiKey;
        $config->serviceUrl = $request->urlPortico;
        ServicesContainer::configureService($config);
           
        try {

            Transaction::fromId($request->transactionId, $orderId, PaymentMethodType::CREDIT)
                ->void()
                ->execute();
            return response()->json(["message" => $request->transactionId. " Voided"], 200); 
        }
        catch (Exception $e) {
            echo 'Failure: ' . $e->getMessage();
            exit;
        }
    }

    function refundProcess (Request $request, $orderId){

        $requestValidation = array(
            'tokenPayment', 
            'chargeAmount', 
            'postalCode', 
            'urlPortico', 
            'secretApiKey', 
            'cardNumber', 
            'cardExpMonth',
            'cardExpYear',
            'cardCvn',
            'refundAmount',
            'transactionId',

        );
        foreach ($request->all() as $key => $val) {
            if (!in_array($key, $requestValidation)) {
                return response()->json(["error" => "Invalid parameter: " . $key]);
            }
        }

        if(!isset($request->urlPortico)){
            $request->urlPortico = $urlPortico;
        }
        if(!isset($request->secretApiKey)){
            $request->secretApiKey = $secretApiKey;
        }

        $refundAmount = round($request->refundAmount, 2);

        $config = new PorticoConfig();
        $config->secretApiKey = $request->secretApiKey;
        $config->serviceUrl = $request->urlPortico;
        ServicesContainer::configureService($config);

        try {

            Transaction::fromId($request->transactionId)
                ->refund($refundAmount)
                ->withCurrency("USD")
                ->execute();

            return response()->json(["message" => $request->transactionId." Refunded"], 200); 
        }
        catch (Exception $e) {
            echo 'Failure: ' . $e->getMessage();
            exit;
        }

    }
}
