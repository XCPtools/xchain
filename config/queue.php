<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Default Queue Driver
	|--------------------------------------------------------------------------
	|
	| The Laravel queue API supports a variety of back-ends via an unified
	| API, giving you convenient access to each back-end using the same
	| syntax for each one. Here you may set the default queue driver.
	|
	| Supported: "sync", "beanstalkd", "sqs", "iron", "redis"
	|
	*/

	'default' => 'beanstalkd',

	/*
	|--------------------------------------------------------------------------
	| Queue Connections
	|--------------------------------------------------------------------------
	|
	| Here you may configure the connection information for each server that
	| is used by your application. A default configuration has been added
	| for each back-end shipped with Laravel. You are free to add more.
	|
	*/

	'connections' => [

		'btctx' => [
			'driver' => 'blockingbeanstalkd',
			'host'   => getenv('BEANSTALK_HOST') ?: '127.0.0.1',
			'queue'  => 'btctx',
			'port'   => getenv('BEANSTALK_PORT') ?: 11300,
			'ttr'    => 60,
		],

		'sync' => [
			'driver' => 'sync',
		],

		'beanstalkd' => [
			'driver' => 'beanstalkd',
			'host'   => getenv('BEANSTALK_HOST') ?: '127.0.0.1',
			'port'   => getenv('BEANSTALK_PORT') ?: 11300,
			'ttr'    => 60,
		],

		'sqs' => [
			'driver' => 'sqs',
			'key'    => 'your-public-key',
			'secret' => 'your-secret-key',
			'queue'  => 'your-queue-url',
			'region' => 'us-east-1',
		],

		'iron' => [
			'driver'  => 'iron',
			'host'    => 'mq-aws-us-east-1.iron.io',
			'token'   => 'your-token',
			'project' => 'your-project-id',
			'queue'   => 'your-queue-name',
			'encrypt' => true,
		],

		'redis' => [
			'driver' => 'redis',
			'queue'  => 'default',
		],

	],

	/*
	|--------------------------------------------------------------------------
	| Failed Queue Jobs
	|--------------------------------------------------------------------------
	|
	| These options configure the behavior of failed queue job logging so you
	| can control which database and table are used to store the jobs that
	| have failed. You may change them to any database / table you wish.
	|
	*/

	'failed' => [
		'database' => 'mysql', 'table' => 'failed_jobs',
	],

];
