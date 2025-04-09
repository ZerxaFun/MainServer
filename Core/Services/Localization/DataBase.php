<?php

namespace Core\Services\Localization;

use Core\Services\Config\Config;
use RuntimeException;

class DataBase
{
    /**
     * Список всех секций выбранной таблицы
     *
     * @var array
     */
    protected array $colum;

    /**
     * Массив допустимых языков для проверки
     *
     * @var array
     */
    protected array $languages;


    public object $result;

    /**
     * @var bool
     */
    public bool $validation = true;

    public function __construct(
        object $query,
        public string $languageTag,
        string $subject
    )
    {
        /**
         * Список разрешенных языков системы
         */
        $this->languages = Config::group('language');
        $this->languageTag = $this->languageTag($this->languageTag);

        foreach ($query as $key => $value) {
            $this->colum[$key] = $value;
            foreach ($this->languages as $language => $name) {
                $language = '_' . $language;

                if (stripos($key, $language) !== false) {
                    if($language === $this->languageTag) {
                        continue;
                    }

                    unset($this->colum[$key]);
                }
            }
        }


        $this->result();
        $this->validation($query, $subject);
    }

    private function languageTag(string $languageTag): string
    {
        if($languageTag === '') {
            $languageTag = Config::item('defaultLanguage');
        }

        if(!array_key_exists($languageTag, $this->languages)) {
            throw new RuntimeException(
                sprintf(
                    'Выбранный язык <strong>%s</strong> не найден в индексе языков приложения.',
                    $languageTag
                )
            );
        }

        if($this->languages[$languageTag] === false) {
            throw new RuntimeException(
                sprintf(
                    'Выбранный язык <strong>%s</strong> запрещен в индексе языков приложения.',
                    $languageTag
                )
            );
        }

        return '_' . $languageTag;
    }


    /**
     * Возвращает результат в виде массива с выбранным языком и без префикса языка в таблице базы данных
     *
     */
    public function result(): object
    {
        $result = [];
        /**
         * Удаление ключа из языка.
         */
        foreach($this->colum as $key => $value)
        {
            $result[str_replace($this->languageTag, '', $key)] = $value;
        }

        $this->result = (object) $result;

        return $this->result;
    }

    /**
     * Валидация секций с нужным языком, если они пусты, то возвращаем false
     */
    public function validation(object $query, string $subject): void
    {
        $subject .= $this->languageTag;
        /**
         * Удаление ключа из языка.
         */

        if($query->$subject === '') {
            $this->validation = false;
        }
    }
}