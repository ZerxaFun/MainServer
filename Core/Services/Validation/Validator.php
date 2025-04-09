<?php

namespace Core\Services\Validation;

use Core\Services\Validation\Exceptions\ValidationException;
use Core\Services\Validation\Rules;
use Core\Services\Validation\Contracts\RuleInterface;

class Validator
{
    protected array $rules;
    protected array $errors = [];

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function validate(array $data): bool
    {
        foreach ($this->rules as $field => $ruleSet) {
            foreach ($ruleSet as $key => $param) {
                $rule = $this->resolveRule($key, $param);

                if (!$rule->validate($field, $data[$field] ?? null, $data)) {
                    $this->errors[$field][] = $rule->message($field);
                }
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return true;
    }

    protected function resolveRule(string $key, mixed $param): RuleInterface
    {
        return match ($key) {
            'required' => new Rules\RequiredRule(),
            'type'     => new Rules\TypeRule($param),
            'min'      => new Rules\MinRule($param),
            'max'      => new Rules\MaxRule($param),
            default    => throw new \InvalidArgumentException("Неизвестное правило валидации: {$key}")
        };
    }
}
