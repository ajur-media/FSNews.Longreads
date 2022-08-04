<?php

/**
 * Longreads Unit Interface for Steamboat Engine
 */

namespace AJUR\FSNews;

use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException as RuntimeExceptionAlias;
use stdClass;

interface LongreadsInterface
{
    
    /**
     * Longreads constructor
     *
     * @param PDO $pdo
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct(PDO $pdo, array $options = [], $logger = null);
    
    /**
     * Getter
     * Используется, например, для возврата ассоциированного логгера
     *
     * @param $name
     * @return mixed
     */
    public function __get($name);
    
    /**
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __set($name, $value);
    
    /**
     * @param $name
     * @return mixed
     */
    public function __isset($name);
    
    /**
     * Возвращает список проектов (project_id) как массив целочисленных значений
     *
     * @return int[]
     */
    public function getProjectsList();
    
    /**
     * Получить список всех сохраненных лонгридов из БД
     * @param string $order_status
     * @param string $order_date
     * @return array
     * @todo: rename
     *
     */
    public function getStoredAll(string $order_status = 'DESC', string $order_date = 'DESC'): array;
    
    /**
     * Получить конкретный лонгрид из БД по ID
     * @todo: rename
     *
     * @param $id
     * @return bool|mixed
     */
    public function getStoredByID($id = null);
    
    /**
     * Импортировать лонгрид по идентификатору
     *
     * @param $id
     * @param null $folder
     * @param string $import_mode
     * @return mixed
     * @throws RuntimeExceptionAlias
     *
     */
    public function import($id, $folder = null, string $import_mode = 'update');
    
    /**
     * Добавляем информацию о лонгриде в БД
     * @todo: rename
     *
     * @param null $page
     * @return mixed
     */
    public function add($page = null);
    
    /**
     * Удалить импортированный лонгрид
     * @todo: rename
     *
     * @param $id
     * @return mixed
     */
    public function deleteStored($id);
    
    /**
     * Изменить настройки видимости лонгрида
     * @todo: rename
     *
     * @param $id
     * @param string $new_state
     * @return string
     */
    public function itemToggleVisibility($id, $new_state = 'hide');

    /**
     * Возвращает список опубликованных лонгридов на Тильде
     * @param null $associative
     * @return array
     *
     * @todo: rename
     *
     */
    public function fetchPagesList(): array;

    /**
     * Возвращает Json-decoded информацию о лонгриде
     *
     * @param $id
     * @param null $associative (null|true)
     * @return stdClass|array
     */
    public function getPageFullExport($id, $associative = null);
}

# -eof-
