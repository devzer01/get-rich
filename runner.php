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

    protected $coin = null;

    protected $balance = null, $orders = null, $marketData = null;

    protected $average = ['BTC' => 0.00, 'ETH' => 0.00];

    protected $minQuantity = 0.1;

    protected $maxQuantity = 0.2;

    protected $minOrder = 0.0005; //min order qty

    protected $sellLimit = 0.01;  //limit to sell at a time 1/100 (%)

    protected $coinLimit = 0.4; //investing per coin

    protected $cash = null;

    protected $pair = 1;

    protected $tradehistory = null;

    protected $trade = null;

    protected $buycieling = 261366 * 1.002;

    public function __construct($coin = 'BTC', $pair = 1)
    {

        $this->coin = $coin;

        $this->pair = $pair;

        $this->api = new bitexthai('463626bedba5', 'f5e3ad1f07a4', '', false);

        $balanceRaw = $this->api->balance();

        file_put_contents("balance.json", json_encode($balanceRaw));

        $this->balance = $balanceRaw->{$this->coin};

        $this->cash = $balanceRaw->THB;

        if ($this->balance->total * $this->sellLimit < $this->minOrder) {
            $this->sellLimit = $this->minOrder / $this->balance->total; // / $this->minOrder; #$this->minOrder;
            printf("sell limit adusted to %s \n", $this->sellLimit);
        }

        $this->marketData = $this->api->marketData([$this->coin]);

        $this->tradehistory = $this->api->tradehistory($this->pair);

        $this->trade = $this->api->trade($this->pair);
    }


    function sale()
    {
        $thb = $this->cash->total;
        $assets = $this->balance->total * $this->marketData[0]['last_price'];
        printf("Cash %s, Assets %s, Total %s\n", $thb, $assets, $thb + $assets);
        return ($this->marketData[0]['last_price'] > $this->tradehistory->avg) && ($assets > $this->cash->total);
    }

    function buy()
    {
        $assets = $this->balance->total * $this->marketData[0]['last_price'];
        if ($this->marketData[0]['last_price'] < $this->tradehistory->avg && $assets < $this->cash->total) {
            $buyAmount = ($this->cash->total / $this->limit) / 2;
	    $sellAmount = $buyAmount * 1.01;
            $profit = $sellAmount - $buyAmount;
            $margin = $profit / $buyAmount;
            $toBuy = $margin > $this->margin;
            printf("Buy amount %s Potential Sale at %s, Profit %s, Margin %s  [%s] %s\n", $buyAmount, $sellAmount, $profit, $margin, $this->margin, ($margin > $this->margin));
            return ($margin > $this->margin && ($assets < $this->cash->total));
        }
        printf("Current price is above daily avg\n");
        return false;
    }

    function run()
    {
        if ($this->buy()) {
            $order_id = $this->api->order($this->pair, 'buy', $this->cash->total / $this->limit / 2, $this->marketData[0]['last_price']);
            printf("order id %d \n", $order_id);
            $executed = true;
            if ($order_id != 0) {
                $retry = 0;
                while (count($this->api->getorders(["type" => "buy"])) != 0 && ++$retry < 2) {
                    printf("waiting for order execution (60 sec) \n");
                    sleep(60);
                }
                if ($this->api->cancel($this->pair, $order_id)) {
                    $executed = false;
                    printf("Order cancelled \n");
                }
            }

            if ($executed === true) $this->api->order($this->pair, 'sell', (($this->cash->total / $this->limit / 2) / ($this->marketData[0]['last_price'])), $this->marketData[0]['last_price'] * 1.003);
            return; //$order;
        }

        if ($this->sale()) {
            printf("Sell amount %s Potential Sale at %s \n", $this->balance->total * $this->sellLimit, $this->balance->total * $this->sellLimit * $this->marketData[0]['last_price']);
            $this->api->order($this->pair, 'sell', $this->balance->total * $this->sellLimit, $this->marketData[0]['last_price']);
            return;
        }

        printf("No buy or sell options\n");
    }
}


$f = new FinancialState('BTC', 1);
$f->run();
