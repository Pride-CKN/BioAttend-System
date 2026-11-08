<?php

namespace Tests\Support;

class FakeZkDevice
{
    public bool $connectable = true;

    /** @var list<array<string,mixed>> */
    public array $rows = [];

    public string $firmware = 'Ver 6.60 Apr 14 2020';

    /** @var list<string> */
    public array $calls = [];

    public function connect(): bool
    {
        $this->calls[] = 'connect';

        return $this->connectable;
    }

    public function version(): string
    {
        return $this->firmware;
    }

    public function disableDevice(): bool
    {
        $this->calls[] = 'disableDevice';

        return true;
    }

    public function enableDevice(): bool
    {
        $this->calls[] = 'enableDevice';

        return true;
    }

    public function getAttendance(): array
    {
        $this->calls[] = 'getAttendance';

        return $this->rows;
    }

    /**
     * @return list<array{uid:int,userid:string}>
     */
    public function getUser(): array
    {
        $this->calls[] = 'getUser';

        return array_map(
            fn (array $row): array => [
                'uid' => (int) $row['uid'],
                'userid' => (string) $row['id'],
            ],
            $this->rows,
        );
    }

    public function disconnect(): bool
    {
        $this->calls[] = 'disconnect';

        return true;
    }

    public static function punch(int $uid, string $pin, string $timestamp, int $type = 1): array
    {
        return ['uid' => $uid, 'id' => $pin, 'state' => 1, 'timestamp' => $timestamp, 'type' => $type];
    }
}
