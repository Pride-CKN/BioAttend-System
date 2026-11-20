<?php

namespace Tests\Support;

class FakeZkDevice
{
    public bool $connectable = true;

    /** @var list<array<string,mixed>> */
    public array $attendanceRows = [];

    /** @var array<string, array{uid:int,user_id:string,name:string,role:int,password:string,card_no:int}> */
    public array $users = [];

    public string $firmware = 'Ver 6.60 Apr 14 2020';

    /** @var list<string> */
    public array $calls = [];

    public function connect(): bool
    {
        $this->calls[] = 'connect';

        return $this->connectable;
    }

    public function getVersion(): string
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

    public function getAttendanceStructured(): array|false
    {
        $this->calls[] = 'getAttendanceStructured';

        return $this->attendanceRows;
    }

    /** @return array<string, array{uid:int,user_id:string,name:string,role:int,password:string,card_no:int}> */
    public function getUsersStructured(): array|false
    {
        $this->calls[] = 'getUsersStructured';

        return $this->users;
    }

    public function setUser(
        int $uid,
        string $userid,
        string $name,
        string $password,
        int $role = 0,
        int $cardNo = 0,
    ): string|false {
        $this->calls[] = 'setUser';
        $this->users[$userid] = [
            'uid' => $uid,
            'user_id' => $userid,
            'name' => $name,
            'role' => $role,
            'password' => $password,
            'card_no' => $cardNo,
        ];

        return '';
    }

    public function deleteUser(int $uid): string|false
    {
        $this->calls[] = 'deleteUser';

        foreach ($this->users as $identifier => $user) {
            if ($user['uid'] === $uid) {
                unset($this->users[$identifier]);

                return '';
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
        return ['uid' => $uid, 'user_id' => $pin, 'state' => 1, 'timestamp' => $timestamp, 'verify_type' => $type];
    }
}
