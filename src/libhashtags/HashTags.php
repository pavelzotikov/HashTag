<?php

namespace libhashtags;

use Exception;

class HashTags {

    /** @var \Predis\Client $this->redis */
    private $redis;

    /** @var HashTags $this->_instance */
    private static $_instance;

    /** @var array $this->errors */
    private $_errors = array();

    /** @var string Паттерн для парсинга хэштегов в тексте */
    private $_parse_pattern = '/(?:[^\w]?|^|\s)(#\w+(?!(?>[^<]*(?:<(?!\/?a\b)[^<]*)*)<\/a>))/ui';

    /** @var string Ключ хранилища в формате функции sprintf. Хранит какие хэштеги указаны в записи */
    private $_storage_entry_key_format = 'hashtags:entry:%d';

    /** @var string Ключ хранилища в формате функции sprintf. Хранит в каких записях указан хэштег */
    private $_storage_hashtag_key_format = 'hashtag:%s';

    /**
     * Singleton метод для создания одного экземпляра класса с подключенными дополнительными параметрами
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
            self::$_instance->redis = new \Predis\Client();
        }

        return self::$_instance;
    }

    /**
     * @param int $entry_id - id записи
     * @return bool - return $this->save()
     */
    public function remove($entry_id)
    {
        return $this->save($entry_id, "");
    }

    /**
     * @param $entry_id - id записи
     * @param $text - текст в котором будут искаться хэштеги
     * @return bool - true: сохранение прошлом успешно | false: возникли проблемы при сохранении хэштегов,
     * требуется получить массив ошибок getErrors и сделать логирование
     */
    public function save($entry_id, $text)
    {

        /** @var array $hashtags - нахолим все хэштеги в тексте */
        $hashtags = $this->parse($text, "");

        try {

            /** @var string $storage_entry_key - получаем ключ хранилища */
            $storage_entry_key = $this->getStorageEntryKey($entry_id);

            /** @var array $current_hashtags - получаем ранее сохраненные хэштеги дял записи */
            $current_hashtags = $this->getHashtagsByEntryId($entry_id, $storage_entry_key);

            /** @var array $new_hashtags - сравниваем ранее сохраненные хэштеги с новыми и находим какие требуется добавить */
            $new_hashtags = array_diff($hashtags, $current_hashtags);

            /** Пробегаем по новым хэштегам и добавляем их к записи и запись к каждому кэштегу */
            if ($new_hashtags) foreach ($new_hashtags as $new_hashtag) {

                /** @var string $storage_hashtag_key - получаем ключ хранилища для хранения id записей по хэштегу */
                $storage_hashtag_key = $this->getStorageHashtagKey($new_hashtag);

                /** Добавляем новый хэштег к записи */
                $this->redis->sadd($storage_entry_key, $new_hashtag);

                /** Добавляем запись к хэштегу */
                $this->redis->sadd($storage_hashtag_key, $entry_id);

            }

            /** @var array $removed_hashtags - сравниваем ранее сохраненные хэштеги с новыми и находим какие требуется удалить */
            $removed_hashtags = array_diff($current_hashtags, $hashtags);

            /** Пробегаем по новым хэштегам и удаляем их из записи и удаляем id записи у кэштега */
            if ($removed_hashtags) foreach ($removed_hashtags as $removed_hashtag) {

                /** @var string $storage_hashtag_key - получаем ключ хранилища для хранения id записей по хэштегу */
                $storage_hashtag_key = $this->getStorageHashtagKey($removed_hashtag);

                /** Удаляем хэштеги которые не найдены в записи */
                $this->redis->srem($storage_entry_key, $removed_hashtag);

                /** Удаляем id записи из хёштега */
                $this->redis->srem($storage_hashtag_key, $entry_id);

            }

        } catch (Exception $exception) {

            $this->_errors[] = $exception;
            return false;

        }

        return true;

    }

    /**
     * Функция получения ранее сохраненных хэщтегов по id записи
     * @param int $entry_id - id записи
     * @param null|string $storage_entry_key - ключ хранилища
     * @return array - возвращаем массив ранее сохраненных хэштегов
     */
    public function getHashtagsByEntryId($entry_id, $storage_entry_key = null)
    {
        /** Если ключ хранилища не передан - формируем его из id записи */
        if (!$storage_entry_key) $storage_entry_key = $this->getStorageEntryKey($entry_id);

        /** Фозвращаем массив ранее сохраненнных хэштегов */
        return $this->redis->smembers($storage_entry_key) ?: array();
    }

    /**
     * Функция получения массива id записей по хэштегу
     * @param string $hashtag - имя хэштега
     * @param null|string $storage_hashtag_key - ключ хранилища
     * @return array - возвращаем массив ранее сохраненных хэштегов
     */
    public function getEntriesByHashtag($hashtag, $storage_hashtag_key = null)
    {
        /** Если ключ хранилища не передан - формируем его из id записи */
        if (!$storage_hashtag_key) $storage_hashtag_key = $this->getStorageHashtagKey($hashtag);

        /** Фозвращаем массив id записей по хэштегу */
        return $this->redis->smembers($storage_hashtag_key) ?: array();
    }

    /**
     * Функция извлекает хэштеги из текста
     * @param string $text - текст в котором будет искать хэштеги
     * @return array - возвращается массив найденных хэштегов
     */
    private function parse($text)
    {
        $hashtags = array();

        /** Извлекаем хэштеги из текста по паттерну */
        if (preg_match_all($this->_parse_pattern, $text, $matches)) {

            /** @var array $hashtags - пробегаемся по хэштегам и приводим к нижнему регистру плюс убираем дублирующие */
            $hashtags = array_unique(array_map('mb_strtolower', $matches[1]));

        }

        return $hashtags;
    }

    /**
     * Функция формируем ключ для хранилища
     * @param int $entry_id - id записи
     * @return string - возвращает ключ для хранилища
     */
    private function getStorageEntryKey($entry_id)
    {
        return sprintf($this->_storage_entry_key_format, $entry_id);
    }

    /**
     * Функция формируем ключ для хранилища
     * @param string $hashtag - имя хэштега
     * @return string - возвращает ключ для хранилища
     */
    private function getStorageHashtagKey($hashtag)
    {
        return sprintf($this->_storage_hashtag_key_format, $hashtag);
    }

    /**
     * Функция возврашает ошибки возникшие при сохранении хэштегов
     * @return array [Exception]
     */
    public function getErrors()
    {
        return $this->_errors;
    }

}