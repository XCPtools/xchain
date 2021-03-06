<?php

namespace App\Listener\Job;

use App\Handlers\XChain\Network\Bitcoin\BitcoinBlockEventBuilder;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelEventLog\Facade\EventLog;
use \Exception;

/*
* BTCBlockJob
*/
class BTCBlockJob
{

    const MAX_ATTEMPTS = 10;
    const RETRY_DELAY  = 6;

    public function __construct(BitcoinBlockEventBuilder $event_builder)
    {
        $this->event_builder = $event_builder;
    }

    public function fire($job, $data)
    {

        // build the event data
        $event_data = $this->event_builder->buildBlockEventData($data['hash']);

        // fire an event
        try {
            EventLog::debug('xchain.block.received', [
                'height' => $event_data['height'],
                'hash' => $event_data['hash'],
            ]);
            Event::fire('xchain.block.received', [$event_data]);
            Log::debug("End xchain.block.received {$event_data['height']} ({$event_data['hash']})");

            // check for max error count
            if (app('XChainErrorCounter')->maxErrorCountReached()) {
                EventLog::logError('errorCount.exceeded', ['error' => 'Too many errors received for this process.', 'errorCount' => app('XChainErrorCounter')->getErrorCount()]);
                exit(1);
            }

            // job successfully handled
            $job->delete();
        } catch (Exception $e) {
            EventLog::logError('BTCBlockJob.failed', $e, $data);

            // this block had a problem
            //   but it might be found if we try a few more times
            $attempts = $job->attempts();
            if ($attempts > self::MAX_ATTEMPTS) {
                // we've already tried MAX_ATTEMPTS times - give up
                // Log::debug("Block {$data['hash']} event failed after attempt ".$attempts.". Giving up.");
                EventLog::warning('xchain.block.failedPermanent', [
                    'msg'      => "Block {$data['hash']} event failed after attempt ".$attempts.". Giving up.",
                    'height'   => $event_data['height'],
                    'hash'     => $event_data['hash'],
                    'attempts' => $attempts,
                ]);
                $job->delete();
            } else {
                $release_time = 2;
                // Log::debug("Block {$data['hash']} event failed after attempt ".$attempts.". Trying again in ".self::RETRY_DELAY." seconds.");
                EventLog::debug('xchain.block.failedTemporary', [
                    'msg'      => "Block {$data['hash']} event failed after attempt ".$attempts.". Trying again in ".self::RETRY_DELAY." seconds.",
                    'height'   => $event_data['height'],
                    'hash'     => $event_data['hash'],
                    'attempts' => $attempts,
                ]);
                $job->release(self::RETRY_DELAY);
            }
        }

    }


}
