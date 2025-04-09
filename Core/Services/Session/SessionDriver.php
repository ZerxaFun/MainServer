<?php

namespace Core\Services\Session;


class SessionDriver
{

    /**
     * @var string  The session key name.
     */
    protected string $key = 'Majestic';

    /**
     * Returns the array key used to store session data.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

}
