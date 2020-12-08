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
     *
     * @param string $order_status
     * @param string $order_date
     * @return array
     */
    public function getStoredAll($order_status = 'DESC', $order_date = 'DESC');
    
    /**
     * Получить конкретный лонгрид из БД по ID
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
     * Добавить лонгрид (?)
     *
     * @param null $page
     * @return mixed
     */
    public function add($page = null);
    
    /**
     * Удалить импортированный лонгрид
     *
     * @param $id
     * @return mixed
     */
    public function deleteStored($id);
    
    /**
     * Изменить настройки видимости лонгрида
     *
     * @param $id
     * @param string $new_state
     * @return string
     */
    public function itemToggleVisibility($id, $new_state = 'hide');
    
}