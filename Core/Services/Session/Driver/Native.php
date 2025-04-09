<?php

namespace Core\Services\Session\Driver;

use Core\Services\Session\SessionDriver;
use Core\Services\Session\SessionInterface;


class Native extends SessionDriver implements SessionInterface
{

    /**
     * @var array Flash data to keep for the next request.
     */
    protected array $keep = [];

    /**
     * {@inheritdoc}
     */
    public function initialize(): bool
    {

        // Start the session if the headers haven't already been sent.
        if (!headers_sent()) {
            session_start();
        }

        // Initialize main session array if it hasn't been set.
        if (!isset($_SESSION[$this->key])) {
            $_SESSION[$this->key] = [];
        }

        // Do the session with the flash data key.
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }

        // Successfully initialzed.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function finalize(): bool
    {
        // Remove flash data that is not being kept.
        foreach (array_keys($this->kept()) as $name) {
            if (!in_array($name, $this->keep, true)) {
                unset($_SESSION['flash'][$name]);
            }
        }

        // Successfully finalized.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $name, mixed $data): SessionDriver
    {
        // Insert the session data.
        $_SESSION[$this->key][$name] = $data;

        // Return class instance.
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): mixed
    {
        return $_SESSION[$this->key][$name] ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return isset($_SESSION[$this->key][$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $name): SessionDriver
    {
        if ($this->has($name)) {
            unset($_SESSION[$this->key][$name]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): SessionDriver
    {
        $_SESSION[$this->key] = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $_SESSION[$this->key] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function flash(string $name, array $data = null): mixed
    {
        // If data is null return what is stored.
        if ($data === null) {
            return $_SESSION['flash'][$name] ?? false;
        }

        // Keep this for the next request.
        $this->keep($name);

        // Store data.
        return $_SESSION['flash'][$name] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function keep(string $name): SessionDriver
    {
        // Store in the keep array if it isn't there already.
        if (!in_array($name, $this->keep, true)) {
            $this->keep[] = $name;
        }

        // Return session object.
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function kept(): array
    {
        return $this->keep;
    }

}
