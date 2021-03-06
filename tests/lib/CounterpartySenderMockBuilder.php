<?php

use Tokenly\CurrencyLib\CurrencyUtil;


/**
*  CounterpartySenderMockBuilder
*/
class CounterpartySenderMockBuilder
{

    function __construct() {

    }

    public function installMockCounterpartySenderDependencies($app, $test_case, $override_functions=null) {
        $mock_calls = new \ArrayObject(['xcpd' => [], 'btcd' => []]);

        $this->installMockXCPDClient($app, $test_case, $mock_calls);
        $this->installMockBitcoindClient($app, $test_case, $mock_calls, $override_functions);

        return $mock_calls;
    }

    public function installMockXCPDClient($app, $test_case, $mock_calls) {
        $mock = $test_case->getMockBuilder('Tokenly\XCPDClient\Client')->disableOriginalConstructor()->getMock();

        $mock->method('__call')->will($test_case->returnCallback(function($name, $arguments) use ($mock_calls) {
            $vars = $arguments[0];
            $transaction_hex = '7777777777777'.hash('sha256', json_encode($vars));
            $mock_calls['xcpd'][] = [
                'method'   => $name,
                'args'     => $arguments,
                'response' => $transaction_hex,
            ];

            // return a mock get_balances response
            if ($name == 'get_balances') {
                return [
                    [
                        'asset'    => 'FOOCOIN',
                        'quantity' => CurrencyUtil::valueToSatoshis(100),
                    ],
                    [
                        'asset'    => 'BARCOIN',
                        'quantity' => CurrencyUtil::valueToSatoshis(200),
                    ],
                ];
            }
            // return a mock get_asset_info response
            //   will accept any asset, but returns the details below
            if ($name == 'get_asset_info') {
                $asset = $arguments[0]['assets'][0];

                $filepath = base_path()."/tests/fixtures/get_asset_info/".implode('_', $arguments[0]['assets']).".json";
                if (file_exists($filepath)) {
                    $asset_infos = json_decode(file_get_contents($filepath), true);
                    return $asset_infos;
                }

                return [
                    0 => [
                        'issuer'      => "1Hso4cqKAyx9bsan8b5nbPqMTNNce8ZDto",
                        'description' => "Crypto-Rewards Program http://ltbcoin.com",
                        'owner'       => "1Hso4cqKAyx9bsan8b5nbPqMTNNce8ZDto",
                        'locked'      => false,
                        'asset'       => "$asset",
                        'supply'      => 29833802457990000,
                        'divisible'   => true
                    ],
                ];
            }

            if ($name == 'get_sends') {
                $txid = $arguments[0]['filters']['value'];
                $filepath = base_path()."/tests/fixtures/sends/{$txid}.json";
                if (!file_exists($filepath)) { throw new Exception("Send fixture not found for $txid", 1); }
                $send = json_decode(file_get_contents($filepath), true);
                return [$send];
            }

            if ($name == 'get_issuances') {
                if (isset($arguments[0]['filters']['value'])) {
                    $txid = $arguments[0]['filters']['value'];
                } else {
                    $txid = '0000000000000000000000000000000000000000000000000000000022222222';
                    Log::debug("Warning: using default get_issuances fixture ".$txid);
                }
                $filepath = base_path()."/tests/fixtures/issuances/{$txid}.json";
                if (!file_exists($filepath)) { throw new Exception("Issuance fixture not found for $txid", 1); }
                $issuances = json_decode(file_get_contents($filepath), true);
                return $issuances;
            }

            if ($name == 'get_broadcasts') {
                $txid = $arguments[0]['filters']['value'];
                $filepath = base_path()."/tests/fixtures/broadcasts/{$txid}.json";
                if (!file_exists($filepath)) { throw new Exception("Broadcast fixture not found for $txid", 1); }
                $broadcasts = json_decode(file_get_contents($filepath), true);
                return $broadcasts;
            }

            if ($name == 'get_credits' OR $name == 'get_debits') {
                $argstring = implode(
                    "-",
                    collect($arguments[0]['filters'])->map(function($arg) { return str_replace(' ', '_', is_array($arg['value']) ? implode('_', $arg['value']) : $arg['value']); })->toArray()
                );
                $filepath = base_path()."/tests/fixtures/{$name}/{$argstring}.json";
                if (!file_exists($filepath)) {
                    Log::debug("NOTE: $name fixture not found at $filepath");
                    return [];
                }
                return json_decode(file_get_contents($filepath), true);
            }

            return $transaction_hex;
        })); 
        $app->bind('Tokenly\XCPDClient\Client', function() use ($mock) {
            return $mock;
        });
    }

    public function installMockBitcoindClient($app, $test_case, $mock_calls, $override_functions=null) {
        $mock = $test_case->getMockBuilder('Nbobtc\Bitcoind\Bitcoind')->disableOriginalConstructor()->disableOriginalClone()->getMock();
        
        $mock->method('createrawtransaction')->will($test_case->returnCallback(function($inputs, $destinations)  use ($mock_calls) {
            $transaction_hex = '5555555555555'.hash('sha256', json_encode([$inputs, $destinations]));
            $mock_calls['btcd'][] = [
                'method'   => 'createrawtransaction',
                'args'     => [$inputs, $destinations],
                'response' => $transaction_hex,
            ];
            return $transaction_hex;
        })); 
        
        $mock->method('signrawtransaction')->will($test_case->returnCallback(function($hex, $txinfo=[], $keys=[], $sighashtype='ALL')  use ($mock_calls) {
            $transaction_hex = '5555555555555'.hash('sha256', json_encode([$hex, $txinfo, $keys, $sighashtype]));
            $out = (object) [
                'hex'      => $transaction_hex,
                'complete' => 1,
            ];
            $mock_calls['btcd'][] = [
                'method'   => 'signrawtransaction',
                'args'     => [$hex, $txinfo, $keys, $sighashtype],
                'response' => $out,
            ];
            return $out;
        })); 
        
        $mock->method('sendrawtransaction')->will($test_case->returnCallback(function($hex, $allowhighfees=false)  use ($mock_calls, $override_functions) {
            if ($override_functions !== null AND isset($override_functions['sendrawtransaction'])) {
                $txid = call_user_func($override_functions['sendrawtransaction'], $hex, $allowhighfees);
            } else {
                $txid = '99999999'.substr(hash('sha256', json_encode([$hex, $allowhighfees])), 8);
            }

            $mock_calls['btcd'][] = [
                'method'   => 'sendrawtransaction',
                'args'     => [$hex, $allowhighfees],
                'response' => $txid,
            ];
            return $txid;
        })); 

        $mock->method('getrawtransaction')->will($test_case->returnCallback(function($txid, $verbose=false)  use ($mock_calls, $override_functions) {
            $response = null;

            if ($verbose == true) {
                if ($override_functions !== null AND isset($override_functions['getrawtransaction'])) {
                    $data = call_user_func($override_functions['getrawtransaction'], $txid, $verbose);
                    $response = $data;
                } else {
                    if ($this->apiFixtureExists('_getrawtransaction_'.$txid.'.json')) {
                        $data = $this->loadAPIFixture('_getrawtransaction_'.$txid.'.json');
                    } else {
                        Log::debug("NOTE: using _getrawtransaction_sample.json for $txid");
                        $data = $this->loadAPIFixture('_getrawtransaction_sample.json');

                    }
                    $response = $data;
                }
            } else {
                // return a fake hex value
                $response = '000000001';
                
            }

            $mock_calls['btcd'][] = [
                'method'   => 'getrawtransaction',
                'args'     => [$txid, $verbose],
                'response' => $response,
            ];

            return $response;
        })); 

        $mock->method('getblock')->will($test_case->returnCallback(function($blockhash)  use ($mock_calls, $override_functions) {
            $response = null;

            if ($override_functions !== null AND isset($override_functions['getblock'])) {
                $data = call_user_func($override_functions['getblock'], $blockhash, $verbose);
                $response = $data;
            } else {
                if ($this->apiFixtureExists('_block_'.$blockhash.'.json')) {
                    $data = $this->loadAPIFixture('_block_'.$blockhash.'.json');
                    $response = $data;
            } else {
                    throw new Exception("Block not found: $blockhash", 1);
                    // $data = $this->loadAPIFixture('_block_sample.json');

                }
            }

            $mock_calls['btcd'][] = [
                'method'   => 'getblock',
                'args'     => [$blockhash],
                'response' => $response,
            ];

            return $response;
        })); 

        $app->bind('Nbobtc\Bitcoind\Bitcoind', function() use ($mock) {
            return $mock;
        });


        // also catch calls direction to the rpc client
        $mock_client = $test_case->getMockBuilder('Nbobtc\Bitcoind\Client')->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $mock_client->method('execute')->will($test_case->returnCallback(function($method, $args) use ($mock_calls, $override_functions) {
            if ($method == 'searchrawtransactions') {
                $address = $args[0];
                if ($override_functions !== null AND isset($override_functions['searchrawtransactions'])) {
                    $data = call_user_func($override_functions['searchrawtransactions'], $address);
                } else {
                    if ($this->apiFixtureExists('_searchrawtransactions_'.$address.'.json')) {
                        $data = $this->loadAPIFixture('_searchrawtransactions_'.$address.'.json');
                    } else {
                        $data = $this->loadAPIFixture('_searchrawtransactions_sample.json');
                    }

                    // replace the data with the correct address
                    $filtered_data = $data;
                    foreach($data as $offset => $utxo) {
                        foreach($utxo['vout'] as $vout_offset => $vout) {
                            $vout['scriptPubKey']['addresses'] = [$address];
                            $filtered_data[$offset]['vout'][$vout_offset] = $vout;
                        }

                    }
                    $data = $filtered_data;
                }

                $mock_calls['btcd'][] = [
                    'method'   => 'searchrawtransactions',
                    'args'     => $args,
                    'response' => $data,
                ];

                // return as an object
                return (object) ['result' => $data];
            }

            throw new Exception("Unknown method: $method", 1);
        })); 

        $app->bind('Nbobtc\Bitcoind\Client', function() use ($mock_client) {
            return $mock_client;
        });

    }


    public function loadAPIFixture($filename) {
        $filepath = base_path().'/tests/fixtures/api/'.$filename;
        return json_decode(file_get_contents($filepath), true);
    }

    public function apiFixtureExists($filename) {
        $filepath = base_path().'/tests/fixtures/api/'.$filename;
        return file_exists($filepath);
    }


}