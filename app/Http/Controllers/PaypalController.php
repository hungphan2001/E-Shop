<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use Helper;
use Srmklive\PayPal\Services\PayPal as PayPalClient;


class PaypalController extends Controller
{
    public function createpaypal($request)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $paypalToken = $provider->getAccessToken();

        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('processSuccess'),
                "cancel_url" => route('processCancel'),
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => "VND",
                        "value" => $request->address2
                    ]
                ]
            ]
        ]);

        if (isset($response['id']) && $response['id'] != null) {

            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            return redirect()
                ->route('createpaypal')
                ->with('error', 'Something went wrong.');

        } else {
            return redirect()
                ->route('createpaypal')
                ->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }


    public function processPaypal(Request $request)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $paypalToken = $provider->getAccessToken();

        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('processSuccess'),
                "cancel_url" => route('processCancel'),
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => "USD",
                        "value" => 10
                    ]
                ]
            ]
        ]);
        if (isset($response['id']) && $response['id'] != null) {

            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            return redirect()
                ->back()
                ->with('error', 'Something went wrong.');

        } else {
            return redirect()
                ->back()
                ->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }


    public function processSuccess(Request $request)
    {

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);

        if (isset($response['status']) && $response['status'] == 'COMPLETED') {
            $order=new Order();
            $order_data = [];
            $order_data['order_number']='ORD-'.strtoupper(Str::random(10));
            $order_data['user_id']=$request->user()->id;
            $order_data['sub_total']=Helper::totalCartPrice();
            $order_data['total_amount']=Helper::totalCartPrice();
            $order_data['quantity']=Helper::cartCount();
            $order_data['first_name']=$response['payment_source']['paypal']['name']['given_name'];
            $order_data['last_name']=$response['payment_source']['paypal']['name']['surname'];
            $order_data['email']=$response['payment_source']['paypal']['email_address'];
            $order_data['phone']='0123123123';
            $order_data['country']='Nhận tại cửa hàng';
            $order_data['address1']='Nhận tại cửa hàng';
            $order_data['status']="delivered";
            $order->fill($order_data);
            $status=$order->save();
            request()->session()->flush();
            return redirect()->route('home')
                ->with('success', 'Transaction complete.');
        } else {
            return redirect()
                ->back()
                ->with('error', $response['message'] ?? 'Something went wrong.');
        }

    }

    public function processCancel(Request $request)
    {
        return redirect()
            ->back()
            ->with('error', $response['message'] ?? 'You have canceled the transaction.');
    }

}

