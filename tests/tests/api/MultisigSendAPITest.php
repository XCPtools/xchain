<?php

use App\Models\TXO;
use BitWasp\Bitcoin\Key\PrivateKeyFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use Illuminate\Support\Facades\Queue;
use \PHPUnit_Framework_Assert as PHPUnit;

class MultisigSendAPITest extends TestCase {

    protected $useDatabase = true;
    protected $mock_fee_priorities = true;

    public function testAPIErrorsAddMultisigSend()
    {
        // create a multisig payment address
        $payment_address = $this->paymentAddressHelper()->createSampleMultisigPaymentAddress();

        $api_tester = $this->getAPITester();
        $api_tester->testAddErrors([
            [
                'postVars' => [
                    // 'destination' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
                    'quantity'    => 5,
                    'feePerKB'    => 0.00002500,
                    'asset'       => 'SOUP',
                ],
                'expectedErrorString' => 'destination field is required',
            ],
            [
                'postVars' => [
                    'destination' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
                    'quantity'    => 0,
                    'feePerKB'    => 0.00002500,
                    'asset'       => 'SOUP',
                ],
                'expectedErrorString' => 'quantity is invalid',
            ],
            [
                'postVars' => [
                    'destination' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
                    'quantity'    => 5,
                    'feePerKB'    => 0,
                    'asset'       => 'SOUP',
                ],
                'expectedErrorString' => 'fee per k b is invalid',
            ],
            [
                'postVars' => [
                    'destination' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
                    'quantity'    => 5,
                    'feePerKB'    => 0.00002500,
                    'asset'       => '',
                ],
                'expectedErrorString' => 'asset field is required',
            ],
            [
                'postVars' => [
                    'destination' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
                    'quantity'    => 5,
                    'feePerKB'    => 0.0001,
                    'feeRate'     => 'medium',
                    'asset'       => 'SOUP',
                ],
                'expectedErrorString' => 'feePerKB and feeRate cannot both be specified',
            ],
        ], '/'.$payment_address['uuid']);
    }

    public function testAPIAddMultisigSend() {
        // install mocks
        app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);
        $mock = app('CopayClientMockHelper')->mockCopayClient()->shouldIgnoreMissing();
        $copay_client_sign_args = [];
        $mock->shouldReceive('proposePublishAndSignTransaction')->once()
            ->andReturnUsing(function() use (&$copay_client_sign_args) {
                // save passed args
                $args = func_get_args();
                foreach($args as $_k => $_v) { $copay_client_sign_args[$_k] = $_v; }

                return ['id' => '11111111-2222-3333-4444-a94fbfbddfbd','bar'=>'baz'];
            });

        // create a multisig payment address
        $payment_address = $this->paymentAddressHelper()->createSampleMultisigPaymentAddress();

        $api_tester = $this->getAPITester();
        $posted_vars = app('SampleSendsHelper')->sampleMultisigPostVars();
        $expected_created_resource = [
            'id'               => '{{response.id}}',
            'destination'      => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'quantity'         => 25.0,
            'asset'            => 'TOKENLY',
            'requestId'        => '{{response.requestId}}',
            'txProposalId'     => '11111111-2222-3333-4444-a94fbfbddfbd',
            'copayTransaction' => '{{response.copayTransaction}}',
        ];
        $expected_loaded_resource = ['sweep' => false] + $expected_created_resource;
        unset($expected_loaded_resource['txProposalId']);
        unset($expected_loaded_resource['copayTransaction']);
        $loaded_send = $api_tester->testAddResource($posted_vars, $expected_created_resource, '/'.$payment_address['uuid'], $expected_loaded_resource);

        PHPUnit::assertEquals(50000, $copay_client_sign_args[1]['feePerKBSat']);
        PHPUnit::assertEquals('My test message', $copay_client_sign_args[1]['message']);
        Mockery::close();
    }

    public function testAPIAddMultisigSendWithFeeRate() {
        // install mocks
        app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);
        $mock = app('CopayClientMockHelper')->mockCopayClient()->shouldIgnoreMissing();
        $copay_client_sign_args = [];
        $mock->shouldReceive('proposePublishAndSignTransaction')->once()
            ->andReturnUsing(function() use (&$copay_client_sign_args) {
                // save passed args
                $args = func_get_args();
                foreach($args as $_k => $_v) { $copay_client_sign_args[$_k] = $_v; }

                return ['id' => '11111111-2222-3333-4444-a94fbfbddfbd','bar'=>'baz'];
            });

        // create a multisig payment address
        $payment_address = $this->paymentAddressHelper()->createSampleMultisigPaymentAddress();

        $api_tester = $this->getAPITester();
        $posted_vars = app('SampleSendsHelper')->sampleMultisigPostVars(['feeRate' => 'medium']);
        unset($posted_vars['feePerKB']);
        $expected_created_resource = [
            'id'               => '{{response.id}}',
            'destination'      => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'quantity'         => 25.0,
            'asset'            => 'TOKENLY',
            'requestId'        => '{{response.requestId}}',
            'txProposalId'     => '11111111-2222-3333-4444-a94fbfbddfbd',
            'copayTransaction' => '{{response.copayTransaction}}',
        ];
        $expected_loaded_resource = ['sweep' => false] + $expected_created_resource;
        unset($expected_loaded_resource['txProposalId']);
        unset($expected_loaded_resource['copayTransaction']);
        $loaded_send = $api_tester->testAddResource($posted_vars, $expected_created_resource, '/'.$payment_address['uuid'], $expected_loaded_resource);

        PHPUnit::assertEquals(120832, $copay_client_sign_args[1]['feePerKBSat']);
        PHPUnit::assertEquals('My test message', $copay_client_sign_args[1]['message']);
        Mockery::close();
    }

    public function testAPIRemoveMultisigSend() {
        // install mocks
        app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);
        $mock = app('CopayClientMockHelper')->mockCopayClient()->shouldIgnoreMissing();
        $copay_client_sign_args = [];
        $copay_client_delete_args = [];
        $mock->shouldReceive('proposePublishAndSignTransaction')->once()
            ->andReturnUsing(function() use (&$copay_client_sign_args) {
                // save passed args
                $args = func_get_args();
                foreach($args as $_k => $_v) { $copay_client_sign_args[$_k] = $_v; }
                return ['id' => '11111111-2222-3333-4444-a94fbfbddfbd','bar'=>'baz'];
            });
        $mock->shouldReceive('deleteTransactionProposal')->once()
            ->andReturnUsing(function() use (&$copay_client_delete_args) {
                // save passed args
                $args = func_get_args();
                foreach($args as $_k => $_v) { $copay_client_delete_args[$_k] = $_v; }
                return [];
            });

        // create a multisig payment address
        $payment_address = $this->paymentAddressHelper()->createSampleMultisigPaymentAddress();

        $api_tester = $this->getAPITester();
        $posted_vars = app('SampleSendsHelper')->sampleMultisigPostVars();
        $expected_created_resource = [
            'id'               => '{{response.id}}',
            'destination'      => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'quantity'         => 25.0,
            'asset'            => 'TOKENLY',
            'requestId'        => '{{response.requestId}}',
            'txProposalId'     => '11111111-2222-3333-4444-a94fbfbddfbd',
            'copayTransaction' => '{{response.copayTransaction}}',
        ];
        $expected_loaded_resource = ['sweep' => false] + $expected_created_resource;
        unset($expected_loaded_resource['txProposalId']);
        unset($expected_loaded_resource['copayTransaction']);
        $loaded_send = $api_tester->testAddResource($posted_vars, $expected_created_resource, '/'.$payment_address['uuid'], $expected_loaded_resource);
        PHPUnit::assertEquals('My test message', $copay_client_sign_args[1]['message']);

        // now destroy it
        $api_tester->callAPIWithAuthenticationAndReturnJSONContent('DELETE', '/api/v1/multisig/sends/'.$loaded_send['uuid'], [], 204);

        // check that it is gone
        $loaded_resource_model = app('App\Repositories\SendRepository')->findByUuid($loaded_send['uuid']);
        PHPUnit::assertEmpty($loaded_resource_model);

        PHPUnit::assertEquals('11111111-2222-3333-4444-a94fbfbddfbd', $copay_client_delete_args[1]);

        Mockery::close();
    }

    public function testAPIGetMultisigSend() {
        // install mocks
        app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);
        $mock = app('CopayClientMockHelper')->mockCopayClient()->shouldIgnoreMissing();
        $copay_client_sign_args = [];
        $copay_get_delete_args = [];
        $mock->shouldReceive('proposePublishAndSignTransaction')->once()
            ->andReturnUsing(function() use (&$copay_client_sign_args) {
                // save passed args
                $args = func_get_args();
                foreach($args as $_k => $_v) { $copay_client_sign_args[$_k] = $_v; }
                return ['id' => '11111111-2222-3333-4444-a94fbfbddfbd','bar'=>'baz'];
            });
        $mock->shouldReceive('getTransactionProposal')->once()
            ->andReturnUsing(function() use (&$copay_get_delete_args) {
                // save passed args
                $args = func_get_args();
                foreach($args as $_k => $_v) { $copay_get_delete_args[$_k] = $_v; }
                return ['state' => 'rejected'];
            });

        // create a multisig payment address
        $payment_address = $this->paymentAddressHelper()->createSampleMultisigPaymentAddress();

        $api_tester = $this->getAPITester();
        $posted_vars = app('SampleSendsHelper')->sampleMultisigPostVars();
        $expected_created_resource = [
            'id'               => '{{response.id}}',
            'destination'      => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j',
            'quantity'         => 25.0,
            'asset'            => 'TOKENLY',
            'requestId'        => '{{response.requestId}}',
            'txProposalId'     => '11111111-2222-3333-4444-a94fbfbddfbd',
            'copayTransaction' => '{{response.copayTransaction}}',
        ];
        $expected_loaded_resource = ['sweep' => false] + $expected_created_resource;
        unset($expected_loaded_resource['txProposalId']);
        unset($expected_loaded_resource['copayTransaction']);
        $loaded_send = $api_tester->testAddResource($posted_vars, $expected_created_resource, '/'.$payment_address['uuid'], $expected_loaded_resource);
        PHPUnit::assertEquals('My test message', $copay_client_sign_args[1]['message']);

        // now get it
        $get_send_api_response = $api_tester->callAPIWithAuthenticationAndReturnJSONContent('GET', '/api/v1/multisig/sends/'.$loaded_send['uuid'], [], 200);

        // check that it is gone
        PHPUnit::assertNotEmpty($get_send_api_response);
        PHPUnit::assertEquals('rejected', $get_send_api_response['copayTransaction']['state']);

        PHPUnit::assertEquals('11111111-2222-3333-4444-a94fbfbddfbd', $copay_get_delete_args[1]);

        Mockery::close();
    }


    ////////////////////////////////////////////////////////////////////////
    
    protected function getAPITester() {
        $api_tester = app('SimpleAPITester', [$this->app, '/api/v1/multisig/sends', app('App\Repositories\SendRepository')]);
        $api_tester->ensureAuthenticatedUser();
        return $api_tester;
    }

    protected function paymentAddressHelper() {
        if (!isset($this->payment_address_helper)) { $this->payment_address_helper = app('PaymentAddressHelper'); }
        return $this->payment_address_helper;
    }


}
