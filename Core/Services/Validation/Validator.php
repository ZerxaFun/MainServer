<?php

namespace Core\Services\Validation;

use Core\Services\Validation\Exceptions\ValidationException;
use Core\Services\Validation\Rules;
use Core\Services\Validation\Contracts\RuleInterface;
use InvalidArgumentException;

/**
 * Валидатор — проверяет входные данные по набору правил.
 * Работает с декларативным массивом вида:
 * [
 *   'email' => ['required' => true, 'email' => true],
 *   'age'   => ['type' => 'int', 'min' => 18],
 * ]
 */
class Validator
{
    /**
     * Массив правил валидации, заданных пользователем.
     * Формат: [поле => [правило => параметр]]
     */
    protected array $rules;

    /**
     * Ошибки, накопленные в процессе валидации.
     * Формат: [поле => [сообщения]]
     */
    protected array $errors = [];

    /**
     * Поля, успешно прошедшие валидацию.
     */
    protected array $validated = [];

    /**
     * Конструктор принимает массив правил.
     *
     * @param array $rules
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Выполняет валидацию данных по заданным правилам.
     *
     * @param array $data — входящие данные (например, из запроса)
     * @return bool — true, если всё прошло успешно
     * @throws ValidationException — если есть хотя бы одна ошибка
     */
    public function validate(array $data): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;

            foreach ($ruleSet as $key => $param) {
                // Получаем объект правила
                $rule = $this->resolveRule($key, $param);

                // Применяем правило — если не прошло, добавляем сообщение об ошибке
                if (!$rule->validate($field, $value, $data)) {
                    $this->errors[$field][] = $rule->message($field);
                }
            }

            // Если нет ошибок по этому полю — добавляем его в "валидированные"
            if (empty($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        // Если есть ошибки — выбрасываем исключение с деталями
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return true;
    }

    /**
     * Возвращает только валидированные поля.
     * Используется после успешной валидации.
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Возвращает объект правила по его названию.
     *
     * @param string $key Название правила (например, "required", "min", "type")
     * @param mixed $param Параметр правила (если требуется)
     *
     * @return RuleInterface
     *
     * @throws \InvalidArgumentException Если правило не поддерживается
     *
     * required  — Поле обязательно к заполнению (true/false)
     * type      — Тип значения: string, int, bool, array, object, null
     * min       — Минимальная длина строки или значение числа (int/float)
     * max       — Максимальная длина строки или значение числа (int/float)
     * uuid      — Значение должно быть валидным UUID v4
     * email     — Должен быть корректным email-адресом
     * enum      — Должен входить в список значений (array)
     * ip        — Валидный IPv4 или IPv6 адрес
     * url       — Валидный URL
     * phone     — Номер телефона (цифры, от 7 до 15 символов, можно с "+")
     * date      — Строка с валидной датой (распознаётся strtotime())
     */
    protected function resolveRule(string $key, mixed $param): RuleInterface
    {
        return match ($key) {
            'required' => new Rules\RequiredRule(),
            'type'     => new Rules\TypeRule($param),
            'min'      => new Rules\MinRule($param),
            'max'      => new Rules\MaxRule($param),
            'uuid'     => new Rules\UuidRule(),
            'email'    => new Rules\EmailRule(),
            'enum'     => new Rules\EnumRule($param),
            'ip'       => new Rules\IpRule(),
            'url'      => new Rules\UrlRule(),
            'phone'    => new Rules\PhoneRule(),
            'date'     => new Rules\DateRule(),
            default    => throw new InvalidArgumentException("Неизвестное правило валидации. Ключ: {$key}, знаение {$param}")
        };
    }
}
