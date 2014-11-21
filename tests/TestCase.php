<?php

class TestCase extends Illuminate\Foundation\Testing\TestCase {

    protected $useDatabase = false;

	/**
	 * Creates the application.
	 *
	 * @return \Illuminate\Foundation\Application
	 */
	public function createApplication()
	{
		$app = require __DIR__.'/../bootstrap/app.php';

		$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

		return $app;
	}

    public function setUp()
    {
        parent::setUp();

        if($this->useDatabase)
        {
            $this->setUpDb();
        }
    }

    public function setUpDb()
    {
        $this->app['Illuminate\Contracts\Console\Kernel']->call('migrate');
    }

    public function teardownDb()
    {
        $this->app['Illuminate\Contracts\Console\Kernel']->call('migrate:reset');
    }

}
