<?php namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider {

    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // 'event.name' => [
        //     'EventListener',
        // ],
    ];

    // calls the subscribe() method for each of these classes
    protected $subscribe = [
        \App\Handlers\XChain\XChainWebsocketPusherHandler::class,
        \App\Handlers\XChain\XChainTransactionHandler::class,
        \App\Handlers\XChain\XChainBlockHandler::class,
        \App\Listener\EventHandlers\ConsoleLogEventHandler::class,
        \App\Listener\EventHandlers\TransactionEventRebroadcaster::class,
        \App\Listener\EventHandlers\Issuance\AssetInfoCacheListener::class,
        \App\Handlers\Monitoring\MonitoringHandler::class,
    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);
    }

}
