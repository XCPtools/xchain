<?php


/**
*  CounterpartySenderMockBuilder
*/
class CounterpartySenderMockBuilder
{

    function __construct() {

    }

    public function installMockCounterpartySenderDependencies($app, $test_case) {
        $mock_calls = new \ArrayObject(['xcpd' => [], 'btcd' => [], 'insight' => []]);

        $this->installMockXCPDClient($app, $test_case, $mock_calls);
        $this->installMockBitcoindClient($app, $test_case, $mock_calls);

        $insight_mock_calls = $app->make('InsightAPIMockBuilder')->installMockInsightClient($app, $test_case);
        $mock_calls['insight'] = $insight_mock_calls;

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
            return $transaction_hex;
        })); 
        $app->bind('Tokenly\XCPDClient\Client', function() use ($mock) {
            return $mock;
        });
    }

    public function installMockBitcoindClient($app, $test_case, $mock_calls) {
        $mock = $test_case->getMockBuilder('Nbobtc\Bitcoind\Bitcoind')->disableOriginalConstructor()->getMock();
        
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
        
        $mock->method('sendrawtransaction')->will($test_case->returnCallback(function($hex, $allowhighfees=false)  use ($mock_calls) {
            $txid = hash('sha256', json_encode([$hex, $allowhighfees]));
            $mock_calls['btcd'][] = [
                'method'   => 'sendrawtransaction',
                'args'     => [$hex, $allowhighfees],
                'response' => $txid,
            ];
            return $txid;
        })); 

        $app->bind('Nbobtc\Bitcoind\Bitcoind', function() use ($mock) {
            return $mock;
        });
    }


}