<?php

namespace Core\Services\Http;

use Core\Routing\APIControllers;

/**
 * Расширенный HTTP-запрос с валидацией.
 * Поддерживает доступ к данным через "dot notation" (например, filters.startDate),
 * и автоматически преобразует плоские ключи в многомерный массив.
 */
class ValidatedRequest extends Request
{
    /**
     * Валидированные данные (после успешной валидации).
     *
     * @var array
     */
    protected array $validated = [];

    /**
     * Ошибки, обнаруженные при валидации.
     *
     * @var array
     */
    protected array $errors = [];

    /**
     * Конструктор. Преобразует валидационные данные из плоского массива с точками
     * в многомерную структуру.
     *
     * @param array $validated
     * @param array $errors
     */
    public function __construct(array $validated = [], array $errors = [])
    {
        $unflattened = $this->unflattenKeys($validated);

        parent::__construct($unflattened);
        $this->validated = $unflattened;
        $this->errors = $errors;
    }

    /**
     * Возвращает валидированные данные или конкретное поле.
     * Поддерживает вложенный доступ через точку: input('filters.startDate').
     *
     * @param string|null $key
     * @return mixed
     */
    public function input(?string $key = null): mixed
    {
        return $key ? $this->getValueByPath($this->validated, $key) : $this->validated;
    }

    /**
     * Возвращает значение из оригинального (невалидированного) запроса.
     * Также поддерживает "dot notation".
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->getValueByPath($this->data, $key);
        return $value !== null ? $value : $default;
    }

    /**
     * Возвращает массив ошибок валидации.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Проверяет, прошла ли валидация без ошибок.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Прерывает выполнение и возвращает ошибку 422, если валидация не прошла.
     *
     * @return true
     */
    public function check(): bool
    {
        if (!$this->isValid()) {
            APIControllers::setData([
                'message' => 'Данные не прошли валидацию',
                'errors'  => $this->errors,
            ], 422);
        }

        return true;
    }

    /**
     * Получает значение из массива по ключу с точками (filters.startDate → ['filters']['startDate']).
     *
     * @param array $data
     * @param string $path
     * @return mixed|null
     */
    protected function getValueByPath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Преобразует массив с "dot notation" ключами в многомерный массив.
     *
     * @param array $flat
     * @return array
     */
    protected function unflattenKeys(array $flat): array
    {
        $result = [];

        foreach ($flat as $key => $value) {
            $segments = explode('.', $key);
            $ref = &$result;

            foreach ($segments as $segment) {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }

            $ref = $value;
        }

        return $result;
    }
}
