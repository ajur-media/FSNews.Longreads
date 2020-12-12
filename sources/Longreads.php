<?php

/**
 * Longreads UNIT for Steamboat Engine
 */

namespace SteamboatEngine;

use Exception;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Arris\Helpers\DB;
use Arris\Helpers\FS;
use RuntimeException;
use stdClass;

class Longreads implements LongreadsInterface
{
    /**
     * @var PDO
     */
    private $pdo;
    
    /**
     * Логгер
     *
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * Список запросов к Tilda API
     *
     * @var string[]
     */
    private $api_request_types;
    
    /**
     * Опции подключения к Tilda API
     *
     * @var string[]
     */
    private $api_options;
    
    /**
     * Список проектов тильды
     *
     * @var array|mixed
     */
    private $tilda_projects_list;
    
    /**
     * @var string
     */
    private $path_storage;
    
    /**
     * @var bool|string
     */
    private $path_to_favicon;
    
    /**
     * @var bool|string
     */
    private $path_to_footer_template;
    
    /**
     * SQL-таблица с лонгридами
     *
     * @var string
     */
    private $sql_table;
    
    public function __construct(PDO $pdo, $options = [], LoggerInterface $logger = null)
    {
        $this->api_request_types = [
            'getprojectslist'   => '',          // Список проектов
            'getproject'        => 'projectid', // Информация о проекте
            'getprojectexport'  => 'projectid', // Информация о проекте для экспорта
            'getpageslist'      => 'projectid', // Список страниц в проекте
            'getpage'           => 'pageid',    // Информация о странице (+ body html-code)
            'getpagefull'       => 'pageid',    // Информация о странице (+ fullpage html-code)
            'getpageexport'     => 'pageid',    // Информация о странице для экспорта (+ body html-code)
            'getpagefullexport' => 'pageid',    // Информация о странице для экспорта (+ fullpage html-code)
        ];
        
        $this->pdo = $pdo;
        $this->logger = $logger;
        
        $this->api_options['version'] = $options['api.version'] ?? 'v1';
        $this->api_options['public_key'] = $options['api.public_key'] ?? false;
        $this->api_options['secret_key'] = $options['api.secret_key'] ?? false;
        
        $this->path_storage = $options['path.storage'];
        $this->path_to_favicon = $options['path.favicon'] ?? '';
        $this->path_to_footer_template = $options['path.footer_template'] ?? '';
        
        $this->sql_table = $options['sql.table'] ?? 'longreads';
        
        $this->tilda_projects_list = $options['projects'] ?? [];
    }
    
    public function getStoredAll($order_status = 'DESC', $order_date = 'DESC')
    {
        $order_status = in_array($order_status, [ 'DESC', 'ASC'] ) ? $order_status : 'DESC';
        $order_date = in_array($order_date, [ 'DESC', 'ASC' ] ) ? $order_date : 'DESC';
        
        $sql = "SELECT * FROM {$this->sql_table} ORDER BY status {$order_status}, date {$order_date}";
        
        $sth = $this->pdo->query($sql);
        
        return $sth->fetchAll();
    }
    
    public function getStoredByID($id = null)
    {
        if ($id <= 0) {
            return false;
        }
        
        $sql = "SELECT * FROM {$this->sql_table} WHERE id = :id ";
        
        $sth = $this->pdo->prepare($sql);
        $sth->execute([
            'id'    =>  $id
        ]);
        
        return $sth->fetch();
    }
    
    public function import($id, $folder = null, $import_mode = 'update')
    {
        $is_directory_created = false;
        
        $this->logger->debug('Запрошен импорт лонгрида');
        
        try {
            if (!is_dir($this->path_storage))
                throw new Exception('Папка для лонгридов не существует. Обратитесь к нашим админам!');
            
            if (is_null($id))
                throw new Exception('Не передан ID импортируемой страницы');
            
            if (is_null($folder))
                throw new Exception('Не передана папка сохранения лонгрида');
    
            if (!preg_match('/[a-z\d\-\_]+/', $folder))
                throw new Exception('В имени папки допустимы только латинские символы, цифры, дефис и знак подчеркивания');
            
            $import_mode = in_array($import_mode, [ 'insert', 'update' ]) ? $import_mode : 'insert';
    
            $this->logger->debug("ID: {$id}, папка сохранения: `{$folder}`, режим импорта: `{$import_mode}`");
    
            $path_store = $this->path_storage . DIRECTORY_SEPARATOR . $folder;
    
            $this->logger->debug("Путь для сохранения лонгрида", [ $path_store ]);
    
            $export = $this->getPageFullExport($id);
    
            if (!$export || !isset($export->status) || $export->status !== 'FOUND')
                throw new Exception('Ошибка при получении данных Tilda API');
    
            $page = $export->result;
    
            if (!isset($page->published) || empty($page->published))
                throw new Exception('Ошибка импорта: лонгрид не опубликован на тильде');
    
            if (!isset($page->html) || empty($page->html))
                throw new Exception('Ошибка импорта: cтраница не содержит html-контента');
    
            if ($import_mode === 'insert') { // если режим "добавление"
                if (file_exists($path_store)) // но папка уже существует
                    throw new Exception('Папка с указанным именем уже существует. Возможно только обновление лонгрида');
    
                if (!mkdir( $path_store ) && !is_dir( $path_store )) {
                    throw new RuntimeException( sprintf( 'Папку `%s` создать не удалось', $path_store ) );
                }
    
                $is_directory_created = true;
                $this->logger->debug("Папка для лонгрида создана");
            } else {
                if (!file_exists($path_store)) {
                    if (!mkdir( $path_store ) && !is_dir( $path_store )) {
                        throw new RuntimeException( sprintf( 'Папку `%s` создать не удалось', $path_store ) );
                    }
                }
                $is_directory_created = true;
                $this->logger->debug("Папка для обновляемого лонгрида не существовала, но создана");
            }
    
            $page->html = str_replace('<link rel="stylesheet" href="/', '<link rel="stylesheet" href="', $page->html);
            $page->html = str_replace('<script src="/', '<script src="', $page->html);
            $page->html = preg_replace('/<img(.*?)src="\//imu', '<img\\1src="', $page->html);
            $page->html = str_replace("url('/", "url('", $page->html);
            $page->html = str_replace('src="/www.youtube.com', 'src="//www.youtube.com', $page->html);
            $page->html = preg_replace('#<\!--/allrecords-->.*?$#ium', "<!--/allrecords-->\n", $page->html);
            
            if ('' !== $this->path_to_favicon) {
                $page->html = preg_replace('#<link rel="shortcut icon" href=".*?"#ium', '<link rel="shortcut icon" href=' . $this->path_to_favicon, $page->html);
            }
    
            $output = $page->html . "\n";
            
            if ('' !== $this->path_to_footer_template && is_readable($this->path_to_footer_template)) {
                $output = str_replace([ '</body>', '</html>' ], '', $output);
                
                $footer = file_get_contents($this->path_to_footer_template);
                $output .= str_replace('{$smarty.now|date_format:"%Y"}', date('Y'), $footer) . "\n";
            }
    
            if (file_put_contents("{$path_store}/index.html", $output) === false)
                throw new Exception( "Ошибка записи индексного файла лонгрида" );
    
            $this->logger->debug('index.html для лонгрида записан');
    
            // Сохраняем CSS, JS, IMAGES
            $assets_types = ['css', 'js', 'images'];
    
            foreach ($assets_types as $type) {
        
                if (isset($page->{$type})) {
                    $this->logger->debug("Сохраняем ассеты типа {$type}");
            
                    foreach ($page->{$type} as $file) {
                        $content = file_get_contents($file->from);
                
                        if (!$content)
                            throw new Exception("Ошибка получения файла {$file->from}");
                
                        if (!file_put_contents("{$path_store}/{$file->to}", $content))
                            throw new Exception("Ошибка сохранения файла `{$path_store}/{$file->to}`");
                
                        $this->logger->debug("Файл скопирован", [ $file->to , $file->from ]);
                    }
                    $this->logger->debug("Ассеты типа {$type} сохранены.");
                }
            } // foreach
    
            $sql = "UPDATE `{$this->sql_table}` SET `date` = :date, `status` = 1, `folder` = :folder WHERE `id` = :id ";
            $sth = $this->pdo->prepare($sql);
            $sth->execute([
                'date'      =>  $page->date,
                'folder'    =>  $folder,
                'id'        =>  $id
            ]);
    
            $this->logger->debug("Информация по лонгриду сохранена в БД.");
            
        } catch (Exception $e) {
            // очищаем папку от файлов
            // удаляем папку
            if ($is_directory_created) {
                FS::rmdir($path_store);
            }
            $this->logger->debug("Возникла ошибка при импорте лонгрида: ", [ $e->getMessage() ]);
    
            return $e->getMessage();
        } // catch
    
        $this->logger->debug("Лонгрид импортирован");
    
        return 'ok';
    } // import()
    
    public function add($page = null)
    {
        $state = 'insert';
        $valid_fields = ['id', 'projectid', 'title', 'fb_title', 'descr', 'img', 'featureimg', 'alias', 'date', 'sort', 'published', 'filename'];
    
        $this->logger->debug('Добавляем инфо о новом лонгриде в базу...');
    
        $dataset = [];
        
        try {
            if (!is_dir($this->path_storage))
                throw new Exception('Папка для лонгридов не существует. Обратитесь к нашим админам!');
            
            if (is_null($page))
                throw new Exception('Нет данных для добавления');
            
            // mapping POST data to DATASET
            foreach ($page as $key => $value) {
                if (!in_array($key, $valid_fields)) {
                    continue 1;
                }
                $dataset[ $key ] = $value;
            }
    
            if (empty($dataset['published'])) $dataset['published'] = 0;
    
            // проверим существование
            $sth = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->sql_table} WHERE id = :id ");
            $sth->execute(['id' => $dataset['id']]);
            $count = $sth->fetchColumn();
    
            if ($count > 0) {
                $state = 'update';
                $this->logger->debug('Обновляем информацию о лонгриде в БД', [ $dataset['id'] ]);
                $sql = DB::makeReplaceQuery('longreads', $dataset);
            } else {
                $state = 'ok';
                $this->logger->debug('Добавляем информацию о лонгриде в БД', [ $dataset['id'] ]);
                $sql = DB::makeInsertQuery('longreads', $dataset);
            }
    
            $this->logger->debug('PDO SQL Query: ', [ $sql ]);
            $this->logger->debug('PDO SQL Dataset: ', [ $dataset ]);
    
            $sth = $this->pdo->prepare($sql);
            
            $sql_status = $sth->execute($dataset);
            
            $this->logger->debug('Статус обновления/добавления лонгрида: ', [ $sql_status ]);
    
        } catch (PDOException $e) {
            $this->logger->debug('PDO Error, bad SQL request', [ $e->getMessage(), $sql, $dataset ]);
            return $e->getMessage();
    
        } catch (Exception $e) {
            $this->logger->debug('Ошибка', [$e->getMessage()]);
            return $e->getMessage();
        }
        
        return $state;
    }
    
    public function deleteStored($id)
    {
        try {
            if (!is_dir($this->path_storage))
                throw new Exception('Папка для лонгридов не существует. Обратитесь к нашим админам!');
    
            if (empty($id))
                throw new Exception("Не указан ID удаляемого лонгрида");
    
            $this->logger->debug("Начинаем удаление лонгрида с ID: ", [ $id ]);
    
            $sth = $this->pdo->prepare("SELECT * FROM {$this->sql_table} WHERE id = :id");
            $sth->execute([
                'id'    =>  $id
            ]);
            $longread = $sth->fetch();
    
            if ($longread == false)
                throw new Exception("Лонгрид с указанным идентификатором не найден в базе данных");
    
            $lr_folder = $this->path_storage . DIRECTORY_SEPARATOR . $longread['folder'] . DIRECTORY_SEPARATOR;
            $lr_files = array_diff(scandir($lr_folder), [ '.', '..']);
    
            $this->logger->debug("Удаляем файлы...");
            foreach ($lr_files as $file) {
                $this->logger->debug("Удаляем файл {$file}");
                @unlink($lr_folder . $file);
            }
    
            if (!rmdir($lr_folder))
                throw new Exception("Не получилось удалить каталог {$lr_folder}");
    
            // удаляем запись из базы
            $sth = $this->pdo->prepare("DELETE FROM {$this->sql_table} WHERE id = :id");
            $sth->execute([ 'id' => $id]);
    
    
    
        } catch (Exception $e) {
            $this->logger->debug("Возникла ошибка при удалении лонгрида: ", [ $e->getMessage() ]);
    
            return $e->getMessage();
        }
    
        $this->logger->debug("Лонгрид удалён");
        return 'ok';
    } // delete()
    
    public function itemToggleVisibility($id, $new_state = 'hide')
    {
        try {
            if (is_null($id) || $id <= 0)
                throw new Exception('Не передан ID изменяемого лонгрида');
            
            $new_state = in_array($new_state, [ 'hide', 'show' ]) ? $new_state : 'hide';
            $new_state = ($new_state === 'hide') ? -1 : 0;
    
            $sth = $this->pdo->prepare("UPDATE `longreads` SET `status` = :status WHERE `id` = :id ");
            $sth->execute([
                'status'    =>  $new_state,
                'id'        =>  $id
            ]);
            
            return 'ok';
            
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            
            return $e->getMessage();
        }
    }
    
    /**
     * Возвращает список опубликованных лонгридов на Тильде
     * @todo: rename
     *
     * @return array
     */
    public function fetchPagesList()
    {
        $request = 'getpageslist';
        $pages_list = [
            "status"    =>  "FOUND",
            "count"     =>  0,
            "result"    =>  []
        ];
        $url = "http://api.tildacdn.info/{$this->api_options['version']}/getpageslist/"; // http://api.tildacdn.info/v1/getpageslist/
        
        foreach ($this->tilda_projects_list as $pid) {
            $http_request_query = [
                'publickey' =>  $this->api_options['public_key'],
                'secretkey' =>  $this->api_options['secret_key'],
                'projectid' =>  $pid
            ];
            $req_url = $url . '?' . http_build_query($http_request_query);
    
            $this->logger->debug('Loading new longreads from ', [ $req_url ]);
            
            $response = json_decode(file_get_contents($req_url));
    
            $this->logger->debug('Response status is:', [ $response->status ]);
    
            if ($response->status === "FOUND") {
                foreach ($response->result as $page_info) {
                    $pages_list['result'][] = $page_info;
                }
            }
        }
        $pages_list['count'] = count($pages_list['result']);
        if ($pages_list['count'] == 0) {
            $pages_list['status'] = "ERROR";
        }
        
        return $pages_list;
    }
    
    /* ================================== PRIVATE METHODS ============================ */
    
    /**
     * @param $id
     * @return stdClass
     */
    private function getPageFullExport($id)
    {
        $http_request_query = [
            'publickey' =>  $this->api_options['public_key'],
            'secretkey' =>  $this->api_options['secret_key'],
            'pageid'    =>  $id
        ];
        
        $url  = "http://api.tildacdn.info/{$this->api_options['version']}/getpagefullexport/" . '?' . http_build_query( $http_request_query ) ;
    
        $this->logger->debug('[getPageFullExport] URL запроса к тильде:', [ $url ]);
        
        try {
            $response = file_get_contents($url);
            
            if (false === $response) {
                throw new Exception( "[getPageFullExport] ERROR: Не удалось получить данные с Tilda API" );
            }
            
            $response = json_decode($response);
            
            if (false === $response) {
                throw new Exception( "[getPageFullExport] ERROR: Не удалось json-декодировать данные с Tilda API" );
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage(), [ $e->getCode(), $url ]);
            
            return new stdClass();
        }
    }
    
}

# -eof-
