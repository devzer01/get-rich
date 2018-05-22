<?php

include_once 'bitexthai.php';
define('BTC', 1);

	
$api = new bitexthai('463626bedba5','f5e3ad1f07a4','', false);
$balance = $api->balance();
$market = $api->marketData(['BTC']);

$networth = $balance->THB->total + $balance->BTC->total * $market[0]['last_price'];

printf("%d", $networth);

