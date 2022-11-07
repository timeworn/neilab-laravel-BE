<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\InternalWallet;
use SWeb3\Sweb3;
use SWeb3\Sweb3_contract;

use App\Models\SuperLoad;
use App\Models\SubLoad;
use App\Models\Withdraw;

use App\Models\ExchangeInfo;
use App\Models\InternalTradeBuyList;
use App\Models\InternalTradeSellList;
use App\Models\User;
use App\Models\MarketingCampain;
use App\Models\MarketingFeeWallet;
use App\Models\SendFeeTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;





class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        $this->RPCusername = config('app.RPCusername');
        $this->RPCpassword = config('app.RPCpassword');
        $this->withdraw_limit = config('app.withdraw_limit');
        $this->binance_withdraw_daily_total_amount = 0;
        $this->ftx_withdraw_daily_total_amount = 0;

    }

    // Redirect to Required Marketing page and coming soon page

    public function requiredMarketingCampain(){
        $page_title = 'required marketing campaign';
        $page_description = 'Some description for the page';
        $action = __FUNCTION__;
        $theme_mode = $this->getThemeMode();

        return view('zenix.page.requiredMarketingCampain', compact('page_title', 'page_description', 'action', 'theme_mode'));
    }

    public function coming_soon(){
        $page_title = 'Coming Soon...';
        $page_description = 'Some description for the page';
        $action = __FUNCTION__;
        $theme_mode = $this->getThemeMode();

        return view('zenix.page.coming_soon', compact('page_title', 'page_description', 'action', 'theme_mode'));
    }


    public function exchange($param=null){
        $n_id = $param['ex_name'];
        $exchange_id = '\\ccxt\\' . $n_id;
        $exchange = new $exchange_id(array(
            'enableRateLimit' => true,
            'apiKey' => $param['api_key'],
            'secret' => $param['api_secret'],
        ));
        return $exchange;
    }

    function getBalance($address) {
        return file_get_contents('https://blockchain.info/q/addressbalance/'. $address);
    }

    public function getBTCMarketPrice($exchange_info, $amount){
        # code...
        $bitcoin_ticker = $exchange_info->fetch_ticker('BTC/USDT');
        $btc_amount = round($amount/$bitcoin_ticker['bid'], 6);
        return $btc_amount;
    }

    public function getUSDTPrice($exchange_info, $amount){
        # code...
        $bitcoin_ticker = $exchange_info->fetch_ticker('BTC/USDT');
        $usdt_amount = round($amount*$bitcoin_ticker['bid'], 6);
        return $usdt_amount;
    }

    public function createMarketBuyOrder($symbol, $amount, $exchange){
        $type = 'market';
        $side = 'buy';
        $order = $exchange->createOrder($symbol, $type, $side, $amount);
        \Log::info("Create Market Buy Order which amount is".$amount);
        return $order;
    }

    public function createMarketSellOrder($symbol, $amount, $exchange){
        $type = 'market';
        $side = 'sell';
        $order = $exchange->createOrder($symbol, $type, $side, $amount);
        \Log::info("Create Market Sell Order which amount is".$amount);
        return $order;
    }

    public function marketBuyOrder($exchange, $amount, $superload_id, $ex_name){
        $symbol = "BTC/USDT";
        $market_amount = round($this->getBTCMarketPrice($exchange, $amount)*0.999, 6);
        $order = $this->createMarketBuyOrder($symbol, $market_amount, $exchange);

        $superload_info = SuperLoad::where('id', $superload_id)->get()->toArray();
        if($order['amount'] > 0){
            /* update result amount of marketing sale */
            $total_sold_amount = $superload_info[0]['result_amount'] + $order['amount'];
            $update_superload_result = SuperLoad::where('id', $superload_id)->update(['result_amount' => $total_sold_amount]);
            \Log::info("New marketing buy has been request. amount = ".$order['amount']);
        }
        /* If all deposited money has been saled, withdraw the total result amount. */
        if($superload_info[0]['status'] == 1 && $superload_info[0]['manual_withdraw_flag'] == 0){
            $withdraw_result = $this->withdraw($exchange, $superload_id, $ex_name);

            if($withdraw_result){
                $update_superload_result = SuperLoad::where('id', $superload_id)->update(['status' => 2]);
            }else{
                $update_superload_result = SuperLoad::where('id', $superload_id)->update(['status' => 2]);
                $update_superload_result = SuperLoad::where('id', $superload_id)->update(['manual_withdraw_flag' => 1]);
            }
        }
    }

    public function marketSellOrder($exchange, $amount, $superload_id, $ex_name){
        $symbol = "BTC/USDT";
        $market_amount = round($amount*0.999, 6);
        $order = $this->createMarketSellOrder($symbol, $market_amount, $exchange);

        $order['amount'] = $this->getUSDTPrice($exchange, $order['amount']);

        $superload_info = SuperLoad::where('id', $superload_id)->get()->toArray();
        if($order['amount'] > 0){
            $total_sold_amount = $superload_info[0]['result_amount'] + $order['amount'];
            $update_superload_result = SuperLoad::where('id', $superload_id)->update(['result_amount' => $total_sold_amount]);
            \Log::info("New marketing sell has been request. amount = ".$order['amount']);
        }
        if($superload_info[0]['status'] == 1 && $superload_info[0]['manual_withdraw_flag'] == 0){

            sleep(13);
            $this->withdraw($exchange, $superload_id, $ex_name);
            $withdraw_result = $this->withdraw($exchange, $superload_id, $ex_name);
            if($withdraw_result){
                $update_superload_result = SuperLoad::where('id', $superload_id)->update(['status' => 2]);
            }else{
                $update_superload_result = SuperLoad::where('id', $superload_id)->update(['status' => 2]);
                $update_superload_result = SuperLoad::where('id', $superload_id)->update(['manual_withdraw_flag' => 1]);
            }

        }
    }

    public function checkTransaction($from, $to, $amount, $tx_id){
        exec('node C:\NeilLab\app\Http\Controllers\Admin\USDTSendServer\checkTransaction.js ' .$from.' '.$to. ' '.$amount.' '.$tx_id, $output);
        return $output;
    }


    public function withdraw($exchange, $superload_id, $ex_name){

        $superload_info = SuperLoad::where('id', $superload_id)->get()->toArray();
        $withdraw_info = array();

        if(isset($superload_info[0]['trade_type']) && $superload_info[0]['trade_type'] == 1){
            try {
                //code...
                $code = "BTC";
                $amount = round($superload_info[0]['result_amount'] * 0.985, 6);
                $internal_wallets = InternalWallet::where('chain_stack', 1)->where('wallet_type', 1)->get()->toArray();
                $address = $internal_wallets[0]['wallet_address'];
                $withdraw_detail = $exchange->withdraw($code, $amount, $address);

                $withdraw_amount = $this->getUSDTPrice($exchange, $amount);

                if($ex_name == 'Binance'){
                    $this->binance_withdraw_daily_total_amount += $withdraw_amount;

                }else{

                    $this->ftx_withdraw_daily_total_amount += $withdraw_amount;
                }

                $withdraw_info['trade_type'] = 1;
                $withdraw_info['trade_id'] = $superload_info[0]['trade_id'];
                $withdraw_info['superload_id'] = $superload_id;
                $withdraw_info['exchange_id'] = $superload_info[0]['exchange_id'];
                $withdraw_info['withdraw_order_id'] = $withdraw_detail['id'];
                $withdraw_info['manual_flag'] = 0;
                $withdraw_info['status'] = 0;
                $result = Withdraw::create($withdraw_info);
                \Log::info("Withdraw request has been ordered. amount = ".$amount." to ".$address);
                return true;
            } catch (\Throwable $th) {
                //throw $th;
                \Log::info($th->getMessage());
                return false;
            }


        }else if(isset($superload_info[0]['trade_type']) && $superload_info[0]['trade_type'] == 2){
            try {
                //code...
                $code = "USDT";
                $amount = $superload_info[0]['result_amount'];
                $real_amount = round($amount * 0.985, 6);

                $internal_wallets = InternalWallet::where('chain_stack', 2)->where('wallet_type', 1)->get()->toArray();

                $address = $internal_wallets[0]['wallet_address'];
                $withdraw_detail = $exchange->withdraw($code, $real_amount, $address);

                if($ex_name == 'Binance'){
                    $this->binance_withdraw_daily_total_amount += $real_amount;
                }else{
                    $this->ftx_withdraw_daily_total_amount += $real_amount;
                }

                $withdraw_info['trade_type'] = 2;
                $withdraw_info['trade_id'] = $superload_info[0]['trade_id'];
                $withdraw_info['superload_id'] = $superload_id;
                $withdraw_info['exchange_id'] = $superload_info[0]['exchange_id'];
                $withdraw_info['withdraw_order_id'] = $withdraw_detail['id'];
                $withdraw_info['manual_flag'] = 0;
                $withdraw_info['status'] = 0;
                $result = Withdraw::create($withdraw_info);
                \Log::info("Withdraw request has been ordered. amount = ".$real_amount." to ".$address);
                return true;
            } catch (\Throwable $th) {
                //throw $th;
                \Log::info($th->getMessage());
                return false;
            }

        }
    }
    public function cronInit(){
        $this->binance_withdraw_daily_total_amount = 0;
        $this->ftx_withdraw_daily_total_amount = 0;
    }
    /* This function works every 3 minutes */
    public function cronHandleFunction(){
        /*
        order_size_limit_btc => This is the order size limit that system can order at once.
        order_size_limit_usdt => This is the order size limit that system can order at once.
        */
        $order_size_limit_btc = 0.001;
        $order_size_limit_usdt = 20;

        $result = ExchangeInfo::orderBy('id', 'asc')->get()->toArray();

            /* retrieve exchanges whether deposit transaction has been completed or not */
            foreach ($result as $key => $value) {
            $exchange = $this->exchange($value);
            $usdt_deposit_history = $exchange->fetchDeposits("USDT");
            foreach ($usdt_deposit_history as $key => $deposit_value) {
                # code...
                /* If deposit transaction has been completed, take a place next logic. */
                if($deposit_value['status'] == 'ok'){
                    if(isset($deposit_value['txid'])){
                        $database_status_of_superload = SuperLoad::where('tx_id', $deposit_value['txid'])->get()->toArray();
                        if (count($database_status_of_superload) > 0) {
                            # code...
                            $withdraw_available = $this->checkWithdrawAvailable($exchange, $value['ex_name'], $database_status_of_superload[0]['amount'], 0);

                            if($withdraw_available){

                                /* If there remains unordered amount, request order till left amount is zero */
                                if(count($database_status_of_superload) != 0 && $database_status_of_superload[0]['left_amount'] > 0 && $database_status_of_superload[0]['status'] == 0){
                                    /* If remains amount is less than 15 usdt. */
                                    if($database_status_of_superload[0]['left_amount'] - $order_size_limit_usdt < 15){
                                        $update_superload_result = SuperLoad::where('id', $database_status_of_superload[0]['id'])->update(['left_amount' => 0,'status' => 1]);
                                        $this->marketBuyOrder($exchange, $database_status_of_superload[0]['left_amount'], $database_status_of_superload[0]['id'], $value['ex_name']);
                                        \Log::info("Deposit transaction of ".$deposit_value['txid']." has been confirmed from ".$value['ex_name']);
                                    }else if($database_status_of_superload[0]['left_amount'] > $order_size_limit_usdt){
                                        $this->marketBuyOrder($exchange, $order_size_limit_usdt, $database_status_of_superload[0]['id'], $value['ex_name']);
                                        $remain_amount = $database_status_of_superload[0]['left_amount'] - $order_size_limit_usdt;
                                        SuperLoad::where('id', $database_status_of_superload[0]['id'])->update(['left_amount' => $remain_amount]);
                                    }else{
                                        /* If all money has been ordered, update status. */
                                        $update_superload_result = SuperLoad::where('id', $database_status_of_superload[0]['id'])->update(['left_amount' => 0,'status' => 1]);
                                        $this->marketBuyOrder($exchange, $database_status_of_superload[0]['left_amount'], $database_status_of_superload[0]['id'], $value['ex_name']);
                                        \Log::info("Deposit transaction of ".$deposit_value['txid']." has been confirmed from ".$value['ex_name']);
                                    }
                                }
                            }
                        }

                    }
                }
            }
            $btc_deposit_history = $exchange->fetchDeposits("BTC");
            foreach ($btc_deposit_history as $key => $deposit_value) {
                # code...
                /* If deposit transaction has been completed, take a place next logic. */
                if($deposit_value['status'] == 'ok'){
                    if(isset($deposit_value['txid'])){
                        $database_status_of_superload = SuperLoad::where('tx_id', $deposit_value['txid'])->get()->toArray();
                        if(count($database_status_of_superload) > 0){

                            $withdraw_available = $this->checkWithdrawAvailable($exchange, $value['ex_name'], $database_status_of_superload[0]['amount'], 1);
                            if($withdraw_available){

                                /* If there remains unordered amount, request order till left amount is zero */
                                if(count($database_status_of_superload) != 0 && $database_status_of_superload[0]['left_amount'] > 0 && $database_status_of_superload[0]['status'] == 0){
                                    /* If remains amount is less than 0.001 btc. */
                                    if($database_status_of_superload[0]['left_amount'] - $order_size_limit_btc < 0.001){
                                        $update_superload_result = SuperLoad::where('id', $database_status_of_superload[0]['id'])->update(['left_amount' => 0,'status' => 1]);
                                        $this->marketSellOrder($exchange, $database_status_of_superload[0]['left_amount'], $database_status_of_superload[0]['id'], $value['ex_name']);
                                        \Log::info("Deposit transaction of ".$deposit_value['txid']." has been confirmed from ".$value['ex_name']);

                                    }else if($database_status_of_superload[0]['left_amount'] > $order_size_limit_btc){
                                        $this->marketSellOrder($exchange, $order_size_limit_btc, $database_status_of_superload[0]['id'], $value['ex_name']);
                                        $remain_amount = $database_status_of_superload[0]['left_amount'] - $order_size_limit_btc;
                                        SuperLoad::where('id', $database_status_of_superload[0]['id'])->update(['left_amount' => $remain_amount]);
                                    }else{
                                        /* If all money has been ordered, update status. */
                                        $update_superload_result = SuperLoad::where('id', $database_status_of_superload[0]['id'])->update(['left_amount' => 0,'status' => 1]);
                                        $this->marketSellOrder($exchange, $database_status_of_superload[0]['left_amount'], $database_status_of_superload[0]['id'], $value['ex_name']);
                                        \Log::info("Deposit transaction of ".$deposit_value['txid']." has been confirmed from ".$value['ex_name']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function checkWithdrawAvailable($exchange, $ex_name, $amount, $type){
        if($type == 1){
            $withdraw_usdt_amount = $this->getUSDTPrice($exchange, $amount);
        }else{
            $withdraw_usdt_amount = $amount;
        }
        if($ex_name == 'Binance'){
            if($this->binance_withdraw_daily_total_amount + $withdraw_usdt_amount < 8000000){
                return true;
            }else{
                return false;
            }
        }else if($ex_name == 'FTX'){
            if($this->ftx_withdraw_daily_total_amount + $withdraw_usdt_amount < 2000000){
                return true;
            }else{
                return false;
            }
        }
        return false;
    }

    public function cronWithdrawHandleFunction(){
        $withdraw_order_info = Withdraw::where('status', 0)->get()->toArray();
        foreach ($withdraw_order_info as $key => $value) {
            # code...
            if($value['trade_type'] == 1){
                $asset = "BTC";
                $confirm_result = $this->confirmWithdrawTransaction($asset, $value);
                if($confirm_result['success']){
                    \Log::info("DEBUG-----------------------------------------------");
                    $this->lastStep($asset, $value, $confirm_result['withdraw_transaction']);
                }
            }else{
                $asset = "USDT";
                $confirm_result = $this->confirmWithdrawTransaction($asset, $value);
                if($confirm_result['success']){
                    \Log::info("DEBUG-----------------------------------------------");
                    $this->lastStep($asset, $value, $confirm_result['withdraw_transaction']);
                }
            }
        }
    }
    public function handleSendFee($trade_info, $amount, $trade_type){
        $user_info = User::where('id', $trade_info['user_id'])->get()->toArray();
        $marketing_info = MarketingCampain::where('id', $user_info[0]['marketing_campain_id'])->get()->toArray();
        if(count($marketing_info) > 0){

            $fee_amount = round($amount/100*$marketing_info[0]['total_fee'],6);
            $remain_amount = $amount - $fee_amount;


            if($trade_type == 1){
                $marketing_fee_wallets = MarketingFeeWallet::where('fee_type', 1)->where('chain_net', 1)->get()->toArray();
                $send_result = $this->sendBTC($marketing_fee_wallets[0]['wallet_address'], $fee_amount);

                $tx_id =  $send_result['txid'];
                \Log::info("Total Fee (".$fee_amount."BTC)has been sent to " . $marketing_fee_wallets[0]['wallet_address']);

                $chain_net = 1;
                $send_fee_result = true;
            }else{
                $marketing_fee_wallets = MarketingFeeWallet::where('fee_type', 1)->where('chain_net', 2)->get()->toArray();

                $internal_wallet_info = InternalWallet::where('wallet_type',1)->where('chain_stack',2)->get()->toArray();
                $private_key = base64_decode($internal_wallet_info[0]['private_key']);
                $address = $internal_wallet_info[0]['wallet_address'];

                $send_usdt_result = $this->sendUSDT($address, $private_key , $marketing_fee_wallets[0]['wallet_address'], $fee_amount);
                \Log::info($send_usdt_result);
                $tx_id = $send_usdt_result[1];
                \Log::info("Total Fee (".$fee_amount."USDT)has been sent to " . $marketing_fee_wallets[0]['wallet_address']);

                $chain_net = 2;
            }
            $transaction_history = array();
            $transaction_history['fee_type'] = 1;
            $transaction_history['chain_net'] = $chain_net;
            $transaction_history['amount'] = $fee_amount;
            $transaction_history['tx_id'] = $tx_id;
            $transaction_history['user_id'] = $trade_info['user_id'];

            $transaction_create_result = SendFeeTransaction::create($transaction_history);

            if($transaction_create_result->id > 0){
                $return_status = true;
            }else{
                $return_status = false;
            }

            return (['status' => $return_status, 'remain_amount' => $remain_amount]);
        }else{
            return (['status' => false]);
        }

    }
    public function lastStep($asset, $withdraw_tbl, $withdraw_transaction){

        if($asset == 'BTC'){
            $trade_info = InternalTradeBuyList::where('id', $withdraw_tbl['trade_id'])->get()->toArray();
            $sending_fee_result = $this->handleSendFee($trade_info[0], $withdraw_transaction['amount'] - $withdraw_transaction['fee']['cost'], 1);
            if($sending_fee_result['status']){
                sleep(25);
                $send_client_amount = round($sending_fee_result['remain_amount'] * 0.998, 6);
                $send_result = $this->sendBTC($trade_info[0]['delivered_address'], $send_client_amount);

                $subload_info = array();
                $subload_info['tx_id'] = $send_result['txid'];
                $subload_info['trade_type']         = $withdraw_tbl['trade_type'];
                $subload_info['trade_id']           = $withdraw_tbl['trade_id'];
                $subload_info['superload_id']       = $withdraw_tbl['superload_id'];
                $subload_info['exchange_id']        = $withdraw_tbl['exchange_id'];
                $subload_info['receive_address']    = $withdraw_transaction['addressTo'];
                $subload_info['amount']             = $withdraw_transaction['amount'] - $withdraw_transaction['fee']['cost'];
                $subload_info['withdraw_order_id']  = $withdraw_transaction['id'];
                $subload_info['status']             = 1;

                $subload_create_result = SubLoad::create($subload_info);

                $update_withdraw_tbl_result = Withdraw::where('id', $withdraw_tbl['id'])->update(['status' => 1]);
                $this->updateOrderStatus($withdraw_tbl['superload_id'], 0);
                sleep(20);
                \Log::info("Complete one subload of buy transaction");
            }
        }else if($asset == 'USDT'){
            $trade_info = InternalTradeSellList::where('id', $withdraw_tbl['trade_id'])->get()->toArray();
            $sending_fee_result = $this->handleSendFee($trade_info[0], $withdraw_transaction['amount'] - $withdraw_transaction['fee']['cost'], 2);
            if($sending_fee_result['status']){
                sleep(25);
                $internal_wallet_info = InternalWallet::where('wallet_type',1)->where('chain_stack',2)->get()->toArray();
                $private_key = base64_decode($internal_wallet_info[0]['private_key']);
                $address = $internal_wallet_info[0]['wallet_address'];

                $send_client_amount = round($sending_fee_result['remain_amount'] * 0.998, 6);

                $send_usdt_result = $this->sendUSDT($address, $private_key, $trade_info[0]['delivered_address'],  $send_client_amount);

                $subload_info = array();
                $subload_info['tx_id'] = $send_usdt_result[1];
                $subload_info['trade_type']         = $withdraw_tbl['trade_type'];
                $subload_info['trade_id']           = $withdraw_tbl['trade_id'];
                $subload_info['superload_id']       = $withdraw_tbl['superload_id'];
                $subload_info['exchange_id']        = $withdraw_tbl['exchange_id'];
                $subload_info['receive_address']    = $withdraw_transaction['addressTo'];
                $subload_info['amount']             = $withdraw_transaction['amount'] - $withdraw_transaction['fee']['cost'];
                $subload_info['withdraw_order_id']  = $withdraw_transaction['id'];
                $subload_info['status']             = 1;
                $subload_create_result = SubLoad::create($subload_info);

                $update_withdraw_tbl_result = Withdraw::where('id', $withdraw_tbl['id'])->update(['status' => 1]);
                $this->updateOrderStatus($withdraw_tbl['superload_id'], 1);
                sleep(20);
                \Log::info("Complete one subload of sell transaction");
            }
        }
    }

    public function updateOrderStatus($superload_id, $type){
        $superload_info = SuperLoad::where($superload_id)->get()->toArray();
        $complete_status = false;
        if(count($superload_info) > 0){
            $order_info = SuperLoad::where('masterload_id', $superload_info[0]['masterload_id'])->get()->toArray();
            if(count($order_info) > 0){
                $withdraw_info = Withdraw::where('trade_type', $order_info[0]['trade_type'])->where('trade_id', $order_info[0]['trade_id'])->where('status', 1)->get()->toArray();
                if(count($order_info) == count($withdraw_info)){
                    $complete_status = true;
                }
            }
        }
        if($complete_status == true){
            if($type == 0){
                InternalTradeBuyList::where('id', $superload_info[0]['trade_id'])->update(['state' => 3]);
            }else if($type ==1){
                InternalTradeSellList::where('id', $superload_info[0]['trade_id'])->update(['state' => 3]);
            }
        }
    }

    public function confirmWithdrawTransaction($asset, $value){
        $exchange_info = ExchangeInfo::where('id', $value['exchange_id'])->get()->toArray();
        $exchange = $this->exchange($exchange_info[0]);
        $withdraw_transaction_history = $exchange->fetchWithdrawals($asset);

        $return = false;
        $transaction = array();

        foreach ($withdraw_transaction_history as $key => $history_value) {
            # code...
            if($value['manual_flag'] == 0){
                if($history_value['id'] == $value['withdraw_order_id'] && $history_value['status'] == 'ok'){

                    \Log::info("Withdarw request has been confirmed from ".$exchange_info[0]['ex_name']."!");
                    $return = true;
                    $transaction = $history_value;
                    break;
                }
            }else if($value['manual_flag'] == 1){
                if($history_value['txid'] == $value['withdraw_order_id'] && $history_value['status'] == 'ok'){

                    \Log::info("Withdarw request has been confirmed from ".$exchange_info[0]['ex_name']."!");
                    $return = true;
                    $transaction = $history_value;
                    break;
                }
            }
        }
        return ['success' => $return, 'withdraw_transaction' => $transaction];
    }

    public function confirm_btc_payment ($amount, $txid) {

        $curl = curl_init();
        $year = date('Y');

        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:7890",
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->RPCusername.':'.$this->RPCpassword,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => '{ "id": "curltext", "method":"onchain_history", "params": {"year": '.$year.' } }',
            CURLOPT_POST => 1,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return ['status'=>'error', 'message'=>$err];
        } else {
            $result = json_decode($response);
            if(isset($result->result)){
                $transactions = $result->result->transactions;
                foreach($transactions as $tx) {
                    if(floatval($tx->bc_value) === floatval($amount) && $tx->txid === $txid && $tx->confirmations >= 6) {
                        return ['status'=>'success', 'result'=>'true'];
                    }
                }
                return ['status'=>'success', 'result'=>'false'];
            }else{
                return ['status'=>'error', 'message'=>'Some error occured!'];
            }
        }
    }

    public function sendBTC($to, $amount){

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:7890",
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->RPCusername.':'.$this->RPCpassword,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => '{"id":"curltext","method":"payto","params": {"destination" : "'.$to.'", "amount" : '.$amount.', "password" : "Arman11223344#"}}',
            CURLOPT_POST => 1,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            return ['status'=>'error', 'message'=>$err];
        } else {
            $result = json_decode($response);
            if(isset($result->result)){

                curl_setopt_array($curl, [
                    CURLOPT_URL => "http://localhost:7890",
                    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => $this->RPCusername.':'.$this->RPCpassword,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_POSTFIELDS => '{"id":"curltext","method":"broadcast","params": ["'.$result->result.'"]}',
                    CURLOPT_POST => 1,
                ]);

                $response1 = curl_exec($curl);
                $err1 = curl_error($curl);
                curl_close($curl);

                if($err1){
                    return ['status'=>'error', 'message'=>$err1];
                }else{
                    $result1 = json_decode($response1);
                    if(isset($result1->result)){
                        return ['status'=>'success', 'txid'=>$result1->result];
                    }else{
                        return ['status'=>'error', 'message'=>'An error occured!'];
                    }
                }
            }else{
                return ['status'=>'error', 'message'=>$result->error->message];
            }
        }
    }

    public function sendUSDT($from, $from_pk, $to, $amount){
        $amount_big = $amount*1000000;
        exec('node C:\server\NeilLab\app\Http\Controllers\Admin\USDTSendServer\sendUSDT.js ' .$from.' '.$from_pk. ' '.$to.' '.$amount_big, $output);
        return $output;
    }

    public function updateThemeMode(Request $request){
        $themeMode = $request['mode'];
        $update_theme_mode_result = User::where('id', auth()->user()->id)->update(['theme_mode' => $themeMode]);
        return response()->json(["success" => $update_theme_mode_result]);
    }

    public function getThemeMode(){
        return auth()->user()->theme_mode;
    }
    public function getAmountBinanceFTX($amonut){

        $result = ExchangeInfo::orderBy('id', 'asc')->get()->toArray();
        $binance_account = array();
        $ftx_account = array();
        $exchange_available_accounts = array();
        $binance_deposite_amount = 0;
        $ftx_deposite_amount = 0;

        foreach ($result as $key => $value) {
         # code...
            $exchange = $this->exchange($value);
            try {
                //code...
                $btc_wallet = $exchange->fetchDepositAddress("BTC");
                if($value['ex_name'] == 'Binance'){
                    array_push($binance_account, $value['id']);
                }else{
                    array_push($ftx_account, $value['id']);
                }
                array_push($exchange_available_accounts, $value['id']);
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        $binance_available_number = count($binance_account);
        $ftx_available_number = count($ftx_account);
        if($binance_available_number != 0 || $ftx_available_number != 0){
            $binance_account_rate = 8 * $binance_available_number / (8 * $binance_available_number + 2 * $ftx_available_number);
            $ftx_account_rate = 2 * $ftx_available_number / (8 * $binance_available_number + 2 * $ftx_available_number);

            if($binance_available_number != 0){
                $binance_deposite_amount = round($amonut * round($binance_account_rate, 7, PHP_ROUND_HALF_DOWN ) / $binance_available_number, 6, PHP_ROUND_HALF_DOWN );
            }
            if($ftx_available_number != 0){
                $ftx_deposite_amount = round($amonut * round($ftx_account_rate, 7, PHP_ROUND_HALF_DOWN ) / $ftx_available_number, 6, PHP_ROUND_HALF_DOWN );
            }
        }

        $return_value = array();
        $return_value['binance_account'] = $binance_account;
        $return_value['ftx_account'] = $ftx_account;
        $return_value['binance_deposite_amount'] = $binance_deposite_amount;
        $return_value['ftx_deposite_amount'] = $ftx_deposite_amount;
        $return_value['exchange_available_accounts'] = $exchange_available_accounts;
        return $return_value;
    }
}
