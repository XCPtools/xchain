<?php

namespace App\Console\Commands\Blocks;

use App\Providers\EventLog\Facade\EventLog;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class LoadMissingBlocksCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'xchain:load-missing-blocks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfills missing blocks';


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('maximum-blocks', 'm', InputOption::VALUE_OPTIONAL, 'Maximum blocks to backfill', 10)
            ->setHelp(<<<EOF
Backfills any missing blocks
EOF
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $backfill_max = $this->input->getOption('maximum-blocks');
        $blockchain_repository = $this->laravel->make('App\Blockchain\Block\BlockChainStore');
        $block_handler = $this->laravel->make('App\Handlers\XChain\XChainBlockHandler');
        $insight_client = $this->laravel->make('Tokenly\Insight\Client');
        
        // load the current block from insight
        $data = $insight_client->getBestBlockHash();
        $first_missing_hash = $blockchain_repository->findFirstMissingHash($data['bestblockhash'], $backfill_max);
        if ($first_missing_hash) {
            // backfill any missing blocks
            $missing_block_events = $blockchain_repository->loadMissingBlockEventsFromInsight($first_missing_hash, $backfill_max);

            // process missing blocks
            foreach($missing_block_events as $missing_block_event) {
                EventLog::log('block.missing.cli', $missing_block_event, ['height', 'hash']);
                $block_handler->processBlock($missing_block_event);
            }
        }
    }

}
