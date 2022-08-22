<?php
namespace ccxt;

// ----------------------------------------------------------------------------

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

// -----------------------------------------------------------------------------

function test_margin_modification($exchange, $marginModification) {
    $format = array(
        info => array(),
        type => 'add',
        amount => 0.1,
        total => 0.29934828,
        code => 'USDT',
        symbol => 'ADA/USDT:USDT',
        status => 'ok',
    );
    $keys = is_array($format) ? array_keys($format) : array();
    for ($i = 0; $i < count($keys); $i++) {
        assert (is_array($marginModification) && array_key_exists($keys[$i], $marginModification));
    }
    assert (gettype($marginModification['info']) === 'array');
    if ($marginModification['type'] !== null) {
        assert ($marginModification['type'] === 'add' || $marginModification['type'] === 'reduce' || $marginModification['type'] === 'set');
    }
    if ($marginModification['amount'] !== null) {
        assert ((is_float($marginModification['amount']) || is_int($marginModification['amount'])));
    }
    if ($marginModification['total'] !== null) {
        assert ((is_float($marginModification['total']) || is_int($marginModification['total'])));
    }
    if ($marginModification['code'] !== null) {
        assert (gettype($marginModification['code']) === 'string');
    }
    if ($marginModification['symbol'] !== null) {
        assert (gettype($marginModification['symbol']) === 'string');
    }
    if ($marginModification['status'] !== null) {
        assert ($exchange->in_array($marginModification['status'], array( 'ok', 'pending', 'canceled', 'failed' )));
    }
}


