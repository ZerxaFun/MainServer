<?php

namespace Core\Services\Session;


interface SessionInterface
{

    /**
     * Initializes the session.
     *
     * @return bool
     */
    public function initialize(): bool;

    /**
     * Finalizes the session.
     *
     * @return bool
     */
    public function finalize(): bool;

    /**
     * Inserts data into a session.
     *
     * @param  string  $name  The name of the session.
     * @param  mixed   $data  The data to add into the session.
     */
    public function put(string $name, mixed $data);

    /**
     * Gets an item from the session.
     *
     * @param  string  $name  The name of the session.
     * @return mixed
     */
    public function get(string $name): mixed;

    /**
     * Checks if an item exists in session.
     *
     * @param  string  $name  The name of the session.
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Deletes an item from session.
     *
     * @param  string  $name  The name of the session.
     */
    public function forget(string $name);

    /**
     * Deletes all items from the session.
     *
     */
    public function flush();

    /**
     * Returns all items in the session.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Sets flash data that only lives for one request, if no data was passed
     * it will attempt to find the stored data.
     *
     * @param  string  $name  The name of the flash data.
     * @param array|null $data  The data to store in the session.
     * @return mixed
     */
    public function flash(string $name, array $data = null): mixed;

    /**
     * Keep flash data for another request.
     *
     * @param  string  $name  The name of the data to keep.
     */
    public function keep(string $name);

    /**
     * Returns the data kept for the next request.
     *
     * @return array
     */
    public function kept(): array;

}
