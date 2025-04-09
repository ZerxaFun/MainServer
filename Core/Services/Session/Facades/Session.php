<?php

namespace Core\Services\Session\Facades;


use Core\Services\Session\Session as Factory;
use Core\Services\Session\SessionDriver;


class Session
{

    /**
     * @var SessionDriver The session class instance.
     */
    protected static $session;

    /**
     * Initializes the session.
     *
     */
    public static function initialize(): \Core\Services\Session\Session
    {
        return static::make();
    }

    /**
     * Finalizes the session.
     *
     * @return bool
     */
    public static function finalize(): bool
    {
        return static::make()->driver()->finalize();
    }

    /**
     * Inserts data into a session.
     *
     * @param  string  $name  The name of the session.
     * @param  mixed   $data  The data to add into the session.
     */
    public static function put(string $name, mixed $data): SessionDriver
    {
        return static::make()->driver()->put($name, $data);
    }

    /**
     * Gets data from a session.
     *
     * @param  string  $name  The name of the session.
     * @return mixed
     */
    public static function get(string $name): mixed
    {
        return static::make()->driver()->get($name);
    }

    /**
     * Checks if an item exists in session.
     *
     * @param  string  $name  The name of the session.
     * @return bool
     */
    public static function has(string $name): bool
    {
        return static::make()->driver()->has($name);
    }

    /**
     * Deletes an item from session.
     *
     * @param  string  $name  The name of the session.
     */
    public static function forget(string $name): SessionDriver
    {
        return static::make()->driver()->forget($name);
    }

    /**
     * Deletes all items from the session.
     *
     */
    public function flush(): SessionDriver
    {
        return static::make()->driver()->flush();
    }

    /**
     * Returns all items in the session.
     *
     * @return array
     */
    public function all(): array
    {
        return static::make()->driver()->all();
    }

    /**
     * Sets flash data that only lives for one request, if no data was passed
     * it will attempt to find the stored data.
     *
     * @param  string  $name  The name of the flash data.
     * @param array|null $data  The data to store in the session.
     * @return mixed
     */
    public function flash(string $name, array $data = null): mixed
    {
        return static::make()->driver()->flash($name, $data);
    }

    /**
     * Keep flash data for another request.
     *
     * @param  string  $name  The name of the data to keep.
     */
    public function keep(string $name): SessionDriver
    {
        return static::make()->driver()->keep($name);
    }

    /**
     * Returns the data kept for the next request.
     *
     * @return array
     */
    public function kept(): array
    {
        return static::make()->driver()->kept();
    }

    /**
     * Instantiates a new instance of the session class.
     *
     */
    public static function make(): \Core\Services\Session\Session
    {
        return static::$session ?? new Factory;
    }

}
