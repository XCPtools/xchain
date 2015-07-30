<?php

namespace App\Handlers\XChain\Network\Counterparty;

use App\Handlers\XChain\Network\Bitcoin\BitcoinTransactionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class CounterpartyTransactionHandler extends BitcoinTransactionHandler {


    protected function buildNotification($event_type, $parsed_tx, $quantity, $sources, $destinations, $confirmations, $block, $block_seq, $monitored_address) {
        $notification = parent::buildNotification($event_type, $parsed_tx, $quantity, $sources, $destinations, $confirmations, $block, $block_seq, $monitored_address);

        // add the counterparty Tx details
        $notification['counterpartyTx'] = $parsed_tx['counterpartyTx'];

        return $notification;
    }


    protected function willNeedToPreprocessSendNotification($parsed_tx, $confirmations) {
        // for counterparty, we need to validate all confirmed transactions with counterpartyd
        if ($confirmations == 0) {
            // unconfirmed transactions are forwarded ahead.  They will be validated when they confirm.
            return false;
        }

        // if the parsed transaction has not been verified with counterpartyd, then push it through it into the counterparty verification queue
        $is_validated = (isset($parsed_tx['counterpartyTx']['validated']) AND $parsed_tx['counterpartyTx']['validated']);

        if ($is_validated) {
            // if this transactions was already validated
            return false;
        }


        // all other confirmed counterparty transactions must be validated
        return true;
    }


    protected function preprocessSendNotification($parsed_tx, $confirmations, $block_seq, $block, $matched_monitored_address_ids) {
        // throw this transaction into the counterpartyd verification queue
        $data = [
            'tx'            => $parsed_tx,
            'confirmations' => $confirmations,
            'block_seq'     => $block_seq,
            'block_id'      => $block['id'],
        ];
        // Log::debug("pushing ValidateConfirmedCounterpartydTxJob ".json_encode($data['block_id'], 192));
        Queue::connection('blockingbeanstalkd')
            ->push('App\Jobs\XChain\ValidateConfirmedCounterpartydTxJob', $data, 'validate_counterpartytx');
    }

}
