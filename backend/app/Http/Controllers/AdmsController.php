<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Device\AdmsAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdmsController extends Controller
{
    public function __construct(private readonly AdmsAttendanceService $attendance) {}

    public function handshake(Request $request): Response
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber === null) {
            return $this->text('ERROR: no SN', 400);
        }

        if ($this->registeredDevice($serialNumber) === null) {
            return $this->text('ERROR: unknown device', 403);
        }

        return $this->text(implode("\n", [
            "GET OPTION FROM:{$serialNumber}",
            'ErrorDelay=60',
            'Delay=30',
            'TransTimes=00:00;12:00',
            'TransInterval=1',
            'TransFlag=TransData AttLog',
            'TimeZone=7',
            'Realtime=1',
            'Encrypt=0',
            '',
        ]));
    }

    public function receive(Request $request): Response
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber === null) {
            return $this->text('ERROR: no SN', 400);
        }

        $device = $this->registeredDevice($serialNumber);

        if ($device === null) {
            return $this->text('ERROR: unknown device', 403);
        }

        if (strtoupper((string) $request->query('table')) !== 'ATTLOG') {
            return $this->text('OK');
        }

        $result = $this->attendance->ingest($device, $serialNumber, $request->getContent());

        return $this->text('OK:'.$result['received']);
    }

    public function getRequest(Request $request): Response
    {
        return $this->registeredResponse($request);
    }

    public function deviceCommand(Request $request): Response
    {
        return $this->registeredResponse($request);
    }

    public function ping(): Response
    {
        return $this->text('OK');
    }

    private function registeredResponse(Request $request): Response
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber === null) {
            return $this->text('ERROR: no SN', 400);
        }

        if ($this->registeredDevice($serialNumber) === null) {
            return $this->text('ERROR: unknown device', 403);
        }

        return $this->text('OK');
    }

    private function serialNumber(Request $request): ?string
    {
        $serialNumber = trim((string) $request->query('SN'));

        return $serialNumber === '' || strlen($serialNumber) > 100 ? null : $serialNumber;
    }

    private function registeredDevice(string $serialNumber): ?Device
    {
        return Device::query()
            ->where('serial_number', $serialNumber)
            ->where('is_active', true)
            ->first();
    }

    private function text(string $content, int $status = 200): Response
    {
        return response($content, $status)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
