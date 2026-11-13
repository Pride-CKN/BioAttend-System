<?php

namespace Tests\Support;

class FakeZkDevice
{
    public bool $connectable = true;

    /** @var list<array<string,mixed>> */
    public array $attendanceRows = [];

    /** @var array<string, array{uid:int,userid:string,name:string,role:int,password:string,cardno:string}> */
    public array $users = [];

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

        return $this->attendanceRows;
    }

    /**
     * @return array<string, array{uid:int,userid:string,name:string,role:int,password:string,cardno:string}>
     */
    public function getUser(): array
    {
        $this->calls[] = 'getUser';

        return $this->users;
    }

    public function setUser(
        int $uid,
        string $userid,
        string $name,
        string $password,
        int $role = 0,
        string|int $cardno = 0,
    ): bool {
        $this->calls[] = 'setUser';
        $this->users[$userid] = [
            'uid' => $uid,
            'userid' => $userid,
            'name' => $name,
            'role' => $role,
            'password' => $password,
            'cardno' => (string) $cardno,
        ];

        return true;
    }

    public function removeUser(int $uid): bool
    {
        $this->calls[] = 'removeUser';

        foreach ($this->users as $identifier => $user) {
            if ($user['uid'] === $uid) {
                unset($this->users[$identifier]);

                return true;
            }
        }

        return false;
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
