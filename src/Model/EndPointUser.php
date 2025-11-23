<?php
declare(strict_types=1);

namespace Echron\Satis\UAC\Model;

class EndPointUser
{
    public string $username;
    public string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }
}

