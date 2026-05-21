<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessZkPunchJob;
use App\Models\ZkMachine;
use App\Models\ZkRawLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ZkPushController extends Controller
{
    /**
     * Initial handshake: machine registers itself and receives sync options.
     * GET /iclock/cdata?SN=<serial>&options=all&pushver=...
     */
    public function handshake(Request $request): Response
    {
        $serialNumber = $request->query('SN');

        if (! $serialNumber) {
            return response('ERROR', 400);
        }

        $machine = ZkMachine::where('serial_number', $serialNumber)->first();

        if (! $machine) {
            return response('ERROR', 403);
        }

        $machine->update([
            'firmware_version' => $request->query('pushver'),
            'last_sync_at' => now(),
        ]);

        $body = implode("\r\n", [
            "GET OPTION FROM: {$serialNumber}",
            "ATTLOGStamp={$machine->last_attlog_stamp}",
            'OPERLOGStamp=9999',
            'ATTPHOTOStamp=None',
            'ErrorDelay=30',
            'Delay=10',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=1000000000',
            'Realtime=1',
            'Encrypt=None',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Machine pushes attendance log records.
     * POST /iclock/cdata?SN=<serial>&table=ATTLOG&Stamp=<timestamp>
     */
    public function push(Request $request): Response
    {
        $serialNumber = $request->query('SN');
        $table = $request->query('table');

        if (! $serialNumber || $table !== 'ATTLOG') {
            return response('OK', 200);
        }

        $machine = ZkMachine::where('serial_number', $serialNumber)->first();

        if (! $machine) {
            return response('ERROR', 403);
        }

        $body = $request->getContent();
        $lines = array_filter(explode("\n", trim($body)));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Format: EMPLOYEE_CODE\tDATETIME\tSTATUS\tVERIFY\tWORK_CODE\tRECORD_ID
            $parts = explode("\t", $line);

            if (count($parts) < 3) {
                continue;
            }

            [$employeeCode, $datetime, $punchStatus] = $parts;

            $log = ZkRawLog::create([
                'zk_machine_id' => $machine->id,
                'employee_code' => trim($employeeCode),
                'punched_at' => trim($datetime),
                'punch_status' => (int) trim($punchStatus),
                'verify_type' => isset($parts[3]) ? (int) trim($parts[3]) : 0,
                'raw_line' => $line,
            ]);

            ProcessZkPunchJob::dispatch($log->id);
        }

        $newStamp = $request->query('Stamp', $machine->last_attlog_stamp);
        $machine->update([
            'last_sync_at' => now(),
            'last_attlog_stamp' => (int) $newStamp,
        ]);

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Machine polls for pending commands (user enrollment, deletions, etc.).
     * GET /iclock/getrequest?SN=<serial>
     */
    public function getRequest(): Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Machine sends back the result of an executed command.
     * POST /iclock/devicecmd?SN=<serial>
     */
    public function deviceCmd(): Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}
