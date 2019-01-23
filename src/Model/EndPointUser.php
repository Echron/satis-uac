<?php
declare(strict_types=1);

namespace Echron\Satis\UAC\Model;

class EndPointUser
{
    public $username;
    public $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }
}

