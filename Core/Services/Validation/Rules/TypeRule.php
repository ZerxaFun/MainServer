<?php

namespace Core\Services\Validation\Rules;

use Core\Services\Validation\Contracts\RuleInterface;
use InvalidArgumentException;

class TypeRule implements RuleInterface
{
    protected string $type;

    protected ?RuleInterface $subRule = null;

    protected const ALLOWED_TYPES = [
        'string',
        'int', 'integer',
        'float',
        'numeric',
        'bool', 'boolean',
        'array',
        'object',
        'null',
        // вложенные через подправила:
        'uuid',
        'email',
        'enum',
        'ip',
        'url',
        'phone',
        'date',
        'equals',
    ];

    protected const DELEGATED_RULES = [
        'uuid'  => UuidRule::class,
        'email' => EmailRule::class,
        'enum'  => EnumRule::class,
        'ip'    => IpRule::class,
        'url'   => UrlRule::class,
        'phone' => PhoneRule::class,
        'date'  => DateRule::class,
        'equals'=> EqualsRule::class,
    ];

    public function __construct(string $type)
    {
        $type = strtolower($type);

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("Неизвестный тип данных: {$type}");
        }

        $this->type = $type;

        if (isset(self::DELEGATED_RULES[$type])) {
            $ruleClass = self::DELEGATED_RULES[$type];

            // Если rule требует параметр, он не подойдёт для type — нужно будет прокинуть позже
            $this->subRule = new $ruleClass();
        }
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($this->subRule !== null) {
            return $this->subRule->validate($field, $value, $data);
        }

        return match ($this->type) {
            'int' => is_int($value) || (is_numeric($value) && !str_contains((string)$value, '.')),
            'float' => is_float($value) || (is_numeric($value) && str_contains((string)$value, '.')),
            'numeric' => is_numeric($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value)
        };
    }

    public function message(string $field): string
    {
        if ($this->subRule !== null) {
            return $this->subRule->message($field);
        }

        return "Поле {$field} должно быть типа {$this->type}.";
    }
}
