<?php

/**
 * Longreads Unit Interface for Steamboat Engine
 */

namespace SteamboatEngine;

use PDO;
use Psr\Log\LoggerInterface;

interface LongreadsInterface
{
    
    /**
     * Longreads constructor
     *
     * @param PDO $pdo
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct(PDO $pdo, $options = [], LoggerInterface $logger = null);
    
    /**
     * Получить список всех сохраненных лонгридов из БД
     * @todo: rename
     *
     * @param string $order_status
     * @param string $order_date
     * @return array
     */
    public function getStoredAll($order_status = 'DESC', $order_date = 'DESC');
    
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
     *
     * @return mixed
     */
    public function import($id, $folder = null, $import_mode = 'update');
    
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
     * @todo: rename
     *
     * @return array
     */
    public function fetchPagesList();
    
}

# -eof-
