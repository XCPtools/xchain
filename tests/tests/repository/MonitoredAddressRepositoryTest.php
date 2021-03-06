<?php

use Ramsey\Uuid\Uuid;
use \PHPUnit_Framework_Assert as PHPUnit;

class MonitoredAddressRepositoryTest extends TestCase {

    protected $useDatabase = true;

    public function testAddAddress()
    {
        // insert
        $monitored_address_repo = $this->app->make('App\Repositories\MonitoredAddressRepository');
        $created_address_model = $monitored_address_repo->create($this->app->make('\MonitoredAddressHelper')->sampleDBVars());

        // load from repo
        $loaded_address_models = $monitored_address_repo->findByAddress('1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD');
        $loaded_address_model = $loaded_address_models->first();
        PHPUnit::assertNotEmpty($loaded_address_model);
        PHPUnit::assertEquals(1, $loaded_address_models->count());
        PHPUnit::assertEquals($created_address_model['id'], $loaded_address_model['id']);
        PHPUnit::assertEquals($created_address_model['address'], $loaded_address_model['address']);
    }


    public function testFindByAddresses()
    {
        // insert
        $monitored_address_helper = $this->app->make('\MonitoredAddressHelper');
        $monitored_address_repo = $this->app->make('App\Repositories\MonitoredAddressRepository');
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient111111111111111111111111']));
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient222222222222222222222222', 'active' => false,]));
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient333333333333333333333333']));
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient444444444444444444444444']));

        // load from repo
        $loaded_address_models = $monitored_address_repo->findByAddresses(['1recipient222222222222222222222222','1recipient333333333333333333333333']);
        PHPUnit::assertEquals(2, $loaded_address_models->count());

        // load from repo (active only)
        $loaded_address_models = $monitored_address_repo->findByAddresses(['1recipient222222222222222222222222','1recipient333333333333333333333333'], true);
        PHPUnit::assertEquals(1, $loaded_address_models->count());
        PHPUnit::assertEquals('1recipient333333333333333333333333', $loaded_address_models->get()[0]['address']);

        // load from repo (inactive only)
        $loaded_address_models = $monitored_address_repo->findByAddresses(['1recipient222222222222222222222222','1recipient333333333333333333333333'], false);
        PHPUnit::assertEquals(1, $loaded_address_models->count());
        PHPUnit::assertEquals('1recipient222222222222222222222222', $loaded_address_models->get()[0]['address']);

        // load from repo (with user_id)
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient555555555555555555555555', 'user_id' => 10001]));
        $loaded_address_models = $monitored_address_repo->findByAddressAndUserId('1recipient222222222222222222222222', 999999);
        PHPUnit::assertEquals(0, $loaded_address_models->count());
        $loaded_address_models = $monitored_address_repo->findByAddressAndUserId('1recipient222222222222222222222222', app('UserHelper')->getSampleUser()['id']);
        PHPUnit::assertEquals(1, $loaded_address_models->count());
        PHPUnit::assertEquals('1recipient222222222222222222222222', $loaded_address_models->get()[0]['address']);
        $loaded_address_models = $monitored_address_repo->findByAddressAndUserId('1recipient555555555555555555555555', 10001);
        PHPUnit::assertEquals(1, $loaded_address_models->count());
        PHPUnit::assertEquals('1recipient555555555555555555555555', $loaded_address_models->get()[0]['address']);

    }

    public function testFindAddressMonitorByAddressId()
    {
        // insert
        $monitored_address_helper = $this->app->make('\MonitoredAddressHelper');
        $monitored_address_repo = $this->app->make('App\Repositories\MonitoredAddressRepository');
        $payment_address_helper = app('PaymentAddressHelper');

        $addresses = [
            $payment_address_helper->createSamplePaymentAddress(null, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']),
            $payment_address_helper->createSamplePaymentAddress(null, ['address' => '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU']),
            $payment_address_helper->createSamplePaymentAddress(null, ['address' => '1AAAA3333xxxxxxxxxxxxxxxxxxxsTtS6v']),
        ];

        $monitors = [
            $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '', 'payment_address_id' => $addresses[0]['id']])),
            $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '', 'payment_address_id' => $addresses[1]['id']])),
            $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '', 'payment_address_id' => $addresses[2]['id']])),
        ];

        // load from repo
        $monitors = $monitored_address_repo->findByAddressId($addresses[0]['id']);
        PHPUnit::assertEquals(1, $monitors->count());
        PHPUnit::assertEquals('', $monitors[0]['address']);
        PHPUnit::assertEquals($addresses[0]['id'], $monitors[0]['payment_address_id']);

    }

    public function testFindMonitoredAddressesOnly()
    {
        // insert
        $monitored_address_helper = $this->app->make('\MonitoredAddressHelper');
        $monitored_address_repo = $this->app->make('App\Repositories\MonitoredAddressRepository');
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient111111111111111111111111']));
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient222222222222222222222222', 'active' => false,]));
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient333333333333333333333333']));
        $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient444444444444444444444444']));

        // load from repo
        $addresses = $monitored_address_repo->findAllAddresses();
        PHPUnit::assertEquals(['1recipient111111111111111111111111','1recipient222222222222222222222222','1recipient333333333333333333333333','1recipient444444444444444444444444',], $addresses);
    }

    public function testFindByUUID()
    {
        // insert
        $monitored_address_repo = $this->app->make('App\Repositories\MonitoredAddressRepository');
        $monitored_address_helper = $this->app->make('\MonitoredAddressHelper');
        $created_address = $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient111111111111111111111111']));

        // load from repo
        $loaded_address_model = $monitored_address_repo->findByUuid($created_address['uuid']);
        PHPUnit::assertNotEmpty($loaded_address_model);
        PHPUnit::assertEquals($created_address['id'], $loaded_address_model['id']);
        PHPUnit::assertEquals($created_address['address'], $loaded_address_model['address']);
    }

    public function testDeleteByUUID()
    {
        // insert
        $monitored_address_repo = $this->app->make('App\Repositories\MonitoredAddressRepository');
        $monitored_address_helper = $this->app->make('\MonitoredAddressHelper');
        $created_address = $monitored_address_repo->create($monitored_address_helper->sampleDBVars(['address' => '1recipient111111111111111111111111']));

        // delete
        PHPUnit::assertTrue($monitored_address_repo->deleteByUuid($created_address['uuid']));

        // load from repo
        $loaded_address_model = $monitored_address_repo->findByUuid($created_address['uuid']);
        PHPUnit::assertEmpty($loaded_address_model);
    }


}
