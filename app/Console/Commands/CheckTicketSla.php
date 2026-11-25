<?php

namespace App\Console\Commands;

use App\Enums\TicketLogType;
use App\Mail\TicketOverdueMail;
use App\Models\Ticket;
use App\Services\TicketLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Flags requests whose due date passed while their clock was running.
 * The clock itself needs no job: elapsed time is computed from status changes.
 */
class CheckTicketSla extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Mark running requests past their due date as overdue and alert the technician';

    public function handle(): int
    {
        $count = 0;

        Ticket::query()
            ->whereNotNull('clock_running_since')
            ->where('overdue', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->with('technician:id,name,email')
            ->chunkById(200, function ($tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    $ticket->update(['overdue' => true]);
                    TicketLogger::log($ticket, TicketLogType::SlaBreached, meta: ['due' => $ticket->due_date->format('Y-m-d H:i')]);

                    if ($ticket->technician?->email) {
                        Mail::to($ticket->technician)->queue(new TicketOverdueMail($ticket));
                    }
                    $count++;
                }
            });

        $this->info("{$count} request(s) became overdue.");

        return self::SUCCESS;
    }
}
