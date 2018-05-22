<?php

include_once 'bitexthai.php';
define('BTC', 1);

/**
 * @property  limit
 */
class FinancialState
{
    protected $limit = 4;

    protected $margin = 0.002;

    protected $reserve = 0.02;

    const BTC = 1;

    protected $api = null;

    protected $balance = null, $orders = null, $marketData = null;

    protected $average = ['BTC' => 0.00, 'ETH' => 0.00];

    protected $minQuantity = 0.1;

    protected $maxQuantity = 0.2;

    protected $minOrder = 0.0005; //min order qty

    protected $sellLimit = 0.01;  //limit to sell at a time 1/100 (%)

    public function __construct()
    {

        $this->api = new bitexthai('463626bedba5', 'f5e3ad1f07a4', '', false);

        $this->coins = [0, 1 => 'BTC'];

        $this->balance = $this->api->balance();

        file_put_contents("balance.json", json_encode($this->balance));

        if ($this->balance->BTC->total * $this->sellLimit < $this->minOrder) {
            $this->sellLimit = $this->minOrder / $this->balance->BTC->total; // / $this->minOrder; #$this->minOrder;
            printf("sell limit adusted to %s \n", $this->sellLimit);
        }

        $this->marketData = $this->api->marketData(['BTC']);

        $this->tradehistory = $this->api->tradehistory(1);

        $this->trade = $this->api->trade(1);
    }


    function haveCash()
    {
        return $this->balance->THB->total > 0;
    }

    function haveBitcoin()
    {
        return $this->balance->BTC->total > 0;
    }

    function haveEtherium()
    {
        return $this->balance->ETH->total > 0;
    }

    function haveCoin($coin)
    {
        switch ($coin) {
            case 'BTC':
                return $this->haveBitcoin();
                break;
            case 'ETH':
                return $this->haveEtherium();
                break;
            case 'THB':
                return $this->haveCash();
                break;
        }
    }

    function sale()
    {
        $thb = $this->balance->THB->total;
        $assets = $this->balance->BTC->total * $this->marketData[0]['last_price'];
        printf("Cash %s, Assets %s, Total %s\n", $thb, $assets, $thb + $assets);
        return ($this->marketData[0]['last_price'] > $this->tradehistory->avg) && ($assets > $this->balance->THB->total);
    }

    function buy()
    {
        $assets = $this->balance->BTC->total * $this->marketData[0]['last_price'];
        if ($this->marketData[0]['last_price'] < $this->tradehistory->avg && $assets < $this->balance->THB->total) {
            $buyAmount = ($this->balance->THB->total / $this->limit);
            $potential = ($buyAmount / $this->marketData[0]['last_price']) * $this->tradehistory->avg;
            $profit = $potential - $buyAmount;
            $margin = $profit / $buyAmount;
            $toBuy = $margin > $this->margin;
            printf("Buy amount %s Potential Sale at %s, Profit %s, Margin %s  [%s] %s\n", $buyAmount, $potential, $profit, $margin, $this->margin, ($margin > $this->margin));
            return ($margin > $this->margin && ($assets < $this->balance->THB->total));
        }
        printf("Current price is above daily avg\n");
        return false;
    }

    function run()
    {
        if ($this->buy()) {
            $order_id = $this->api->order(1, 'buy', $this->balance->THB->total / $this->limit, $this->marketData[0]['last_price'] - 1);
            printf("order id %d \n", $order_id);
            $executed = true;
            if ($order_id != 0) {
                $retry = 0;
                while (count($this->api->getorders(["type" => "buy"])) != 0 && ++$retry < 2) {
                    printf("waiting for order execution (60 sec) \n");
                    sleep(10);
                }
		$orders = $this->api->getorders(["type" => "buy"]);
                $order = $orders[0]; 
		if ($this->api->cancel(1, $order->order_id)) {
                    $executed = false;
                    printf("Order cancelled \n");
                }
            }

            if ($executed === true) $this->api->order(1, 'sell', (($this->balance->THB->total / $this->limit) / ($this->marketData[0]['last_price'] - 1)), $this->tradehistory->avg);
            return; //$order;
        }

        if ($this->sale()) {
            printf("Sell amount %s Potential Sale at %s \n", $this->balance->BTC->total * $this->sellLimit, $this->balance->BTC->total * $this->sellLimit * $this->marketData[0]['last_price']);
            $this->api->order(1, 'sell', $this->balance->BTC->total * $this->sellLimit, $this->marketData[0]['last_price']);
            return;
        }

        printf("No buy or sell options\n");
    }
}


$f = new FinancialState();
$f->run();
