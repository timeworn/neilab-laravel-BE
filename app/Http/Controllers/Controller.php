<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\InternalWallet;



class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function exchange($param){
        $n_id = $param['ex_name'];
        $exchange_id = '\\ccxt\\' . $n_id;
        $exchange = new $exchange_id(array(
            'enableRateLimit' => true,
            'apiKey' => $param['api_key'],
            'secret' => $param['api_secret'],
            'options' => array(
                'defaultType' => 'future',
            ),
        ));
        // $exchange = new $exchange_id(array(
        //     'enableRateLimit' => true,
        //     'apiKey' => 'WuwQjNckG59iMabbJDuZb1nhHwcUIlwERJnKoxaI8JbBGE7YMUuFVlG6TskjcEOv',
        //     'secret' => 'lalUvZN7JwGCTLLkR8A23XzbLXh0nMK0I4aukKYFMz1zA3QQmbjDuLAyzLKXELjL',
        //     'options' => array(
        //         'defaultType' => 'future',
        //     ),
        // ));
        // $exchange->set_sandbox_mode(True);
        return $exchange;
    }
    function getBalance($address) {
        return file_get_contents('https://blockchain.info/q/addressbalance/'. $address);
    }

    public function sendUSDT($from, $to, $amount){

        $wallet_info = InternalWallet::where('wallet_address', $from)->get()->toArray();
        if($wallet_info > 0){
            $private_key = $wallet_info[0]['private_key'];
            $result = exec('node USDTSendServer/sendUSDT.js '.$private_key.','.$to.','.$amount);
            echo($result);
        }
    }

    public function createMarketBuyOrder($symbol, $amount){
        echo $symbol;
        echo $amount;
        exit;
    }
}
