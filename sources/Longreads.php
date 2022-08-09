<?php

/**
 * Longreads UNIT for Steamboat Engine
 */

namespace AJUR\FSNews;

use Curl\Curl;
use PDOException;
use Psr\Log\NullLogger;
use RuntimeException;
use PDO;
use Psr\Log\LoggerInterface;

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
    private LoggerInterface $logger;

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

    /**
     * Опция "отрезать ли футер чтобы вставить на его место счетчики из шаблона?" (true)
     *
     * @var bool|mixed
     */
    private $option_cutoff_footer;

    /**
     * Опция "локализовывать ли медиа" (убирать в начале имени /) (true)
     *
     * @var bool|mixed
     */
    private $option_localize_media;

    /**
     * Клиент для загрузки файлов: NATIVE | CURL
     *
     * @var mixed|string
     */
    private $option_download_client;

    /**
     * @var bool
     */
    private $debug_write_raw_html;

    /**
     * @var bool
     */
    private bool $throw_on_error;

    public function __construct(PDO $pdo, array $options = [], $logger = null)
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
        $this->logger = is_null($logger) ? new NullLogger() : $logger;

        $this->api_options['version'] = $options['api.version'] ?? 'v1';
        $this->api_options['public_key'] = $options['api.public_key'] ?? false;
        $this->api_options['secret_key'] = $options['api.secret_key'] ?? false;

        $this->path_storage = $options['path.storage'];
        $this->path_to_favicon = $options['path.favicon'] ?? '';
        $this->path_to_footer_template = $options['path.footer_template'] ?? '';

        $this->option_cutoff_footer = (bool)($options['options.option_cutoff_footer'] ?? true);
        $this->option_localize_media = (bool)($options['options.option_localize_media'] ?? true);
        $this->option_download_client = $options['options.download_client'] ?? 'native';

        $this->throw_on_error = (bool)($options['throw.on.error'] ?? false);

        $this->debug_write_raw_html = (bool)($options['debug.write_raw_html'] ?? false);

        $this->sql_table = $options['sql.table'] ?? 'longreads';

        if (is_array($options['projects'])) {
            $this->tilda_projects_list = $options['projects'];
        } elseif (is_string($options['projects'])) {
            $this->tilda_projects_list = array_map(static function ($i) { return (int)$i; }, explode(' ', $options['projects']));
        }
    }

    public function __get($name)
    {
        if ($this->{$name}) return $this->{$name};

        return null;
    }

    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    public function __isset($name)
    {
        return property_exists($this, $name);
    }

    public function getProjectsList()
    {
        return $this->tilda_projects_list;
    }

    public function getStoredAll(string $order_status = 'DESC', string $order_date = 'DESC'):array
    {
        $order_status = in_array($order_status, [ 'DESC', 'ASC'] ) ? $order_status : 'DESC';
        $order_date = in_array($order_date, [ 'DESC', 'ASC' ] ) ? $order_date : 'DESC';

        $sql = vsprintf("SELECT * FROM %s ORDER BY status %s, date %s", [ $this->sql_table, $order_status, $order_date ]);

        $sth = $this->pdo->query($sql);

        $dataset = $sth->fetchAll();

        return $dataset ?: [];
    }

    public function getStoredByID($id = null):array
    {
        if ($id <= 0) {
            return [];
        }

        $sql = "SELECT * FROM {$this->sql_table} WHERE id = :id ";

        $sth = $this->pdo->prepare($sql);
        $sth->execute([
            'id'    =>  $id
        ]);

        $dataset = $sth->fetch();

        return $dataset ?: [];
    }

    public function import($id, string $folder = '', string $import_mode = 'update')
    {
        $import_mode = in_array($import_mode, [ 'insert', 'update' ]) ? $import_mode : 'insert';

        $is_directory_created = false;
        $path_store = '';

        if ($import_mode == 'update') {
            $this->logger->debug('Запрошено обновление лонгрида', [ $id, $folder ]);
        } else {
            $this->logger->debug('Запрошен импорт лонгрида', [ $id ]);
        }

        try {
            if (!is_dir($this->path_storage)) {
                throw new RuntimeException('Папка для лонгридов не существует. Обратитесь к нашим админам!');
            }

            if (is_null($id)) {
                throw new RuntimeException('Не передан ID импортируемой страницы');
            }

            if (empty($folder)) {
                throw new RuntimeException('Не передана папка сохранения лонгрида');
            }

            if (!preg_match('/[a-z\d\-\_]+/', $folder)) {
                throw new RuntimeException('В имени папки допустимы только латинские символы, цифры, дефис и знак подчеркивания');
            }

            $this->logger->debug("ID: {$id}, папка сохранения: `{$folder}`, режим импорта: `{$import_mode}`");

            $path_store = rtrim($this->path_storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($folder, DIRECTORY_SEPARATOR);

            $this->logger->debug("Путь для сохранения лонгрида", [ $path_store ]);

            $export = $this->getPageFullExport($id);

            if (!$export || !isset($export->status) || $export->status !== 'FOUND'){
                throw new RuntimeException('Ошибка при получении данных Tilda API');
            }

            $page = $export->result;

            if (empty($page->published)) {
                throw new RuntimeException('Ошибка импорта: лонгрид не опубликован на тильде');
            }

            if (empty($page->html)) {
                throw new RuntimeException('Ошибка импорта: лонгрид не содержит html-контента');
            }

            if ($import_mode === 'insert') { // если режим "добавление"
                // но папка уже существует
                if (file_exists($path_store)) {
                    throw new RuntimeException('Папка с указанным именем уже существует. Возможно только обновление лонгрида (или удаление и добавление)');
                }

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

            $html = $page->html;

            if ($this->debug_write_raw_html) {
                file_put_contents("{$path_store}/index_raw.html", $html);
            }

            // локализация favicon
            if ('' !== $this->path_to_favicon) {
                $html = preg_replace('#<link rel="shortcut icon" href=".*?"#ium', '<link rel="shortcut icon" href=' . $this->path_to_favicon, $html);
            }

            // локализация подключенных CSS (href="/..." -> href="...")
            if ($this->option_localize_media) {
                $html = str_replace('<link rel="stylesheet" href="/', '<link rel="stylesheet" href="', $html);

                // локализация подключенных скриптов (src="/..." -> src="...")
                $html = str_replace('<script src="/', '<script src="', $html);

                // локализация подключенных картинок (src="/..." -> src="...")
                $html = preg_replace('/<img(.*?)src="\//imu', '<img\\1src="', $html);

                // локализация url()
                $html = str_replace("url('/", "url('", $html);
            }

            // замена / на // для ютуба
            $html = str_replace('src="/www.youtube.com', 'src="//www.youtube.com', $html);

            if ($this->option_cutoff_footer) {
                $html = substr($html, 0, strpos($html, '<!--/allrecords-->'));
                $html .= '<!--/allrecords-->';
            }

            if ('' !== $this->path_to_footer_template) {
                $html = str_replace([ '</body>', '</html>' ], '', $html);
                
                if (!is_readable($this->path_to_footer_template)) {
                    throw new RuntimeException("Файл шаблона-футера нечитаем или отсутствует");
                }

                $footer = file_get_contents($this->path_to_footer_template);

                if (false === $footer) {
                    throw new RuntimeException("Ошибка чтения файла шаблона {$this->path_to_footer_template}");
                }

                $html .= str_replace('{$smarty.now|date_format:"%Y"}', date('Y'), $footer) . "\n";
            }

            if (file_put_contents("{$path_store}/index.html", $html) === false) {
                throw new RuntimeException( "Ошибка записи индексного файла лонгрида" );
            }

            $this->logger->debug('index.html для лонгрида записан');

            // Сохраняем CSS, JS, IMAGES
            $assets_types = ['css', 'js', 'images'];

            foreach ($assets_types as $type) {

                if (isset($page->{$type})) {
                    $this->logger->debug("Сохраняем ассеты типа {$type}");

                    foreach ($page->{$type} as $file) {
                        $this->logger->debug("Загружаем {$type} ", [ $file->from ]);

                        $this->downloadFile($file->from, "{$path_store}/{$file->to}");

                        $this->logger->debug("Файл {$type} скопирован", [ $file->to , $file->from ]);
                    }

                    $this->logger->debug("Ресурсы типа {$type} сохранены.");
                }
            } // foreach

            // теперь проверяем, существует ли в базе импортированных лонгридов такая запись
            $sql = "SELECT COUNT(*) AS cnt FROM `{$this->sql_table}` WHERE `id` = :id";
            $sth = $this->pdo->prepare($sql);
            $sth->execute(['id'        =>  $id]);

            if ($sth->fetchColumn() > 0) {

                $this->logger->debug('Обновляем информацию о лонгриде в БД... ');

                $dataset = [
                    'date'      =>  $page->date,
                    'status'    =>  1,
                    'folder'    =>  $folder
                ];

                $sql = self::makeUpdateQuery($this->sql_table, $dataset, "`id` = {$id}");

            } else {

                $this->logger->debug('Сохраняем информацию о лонгриде в БД... ');

                $dataset = [
                    'id'            =>  $id,
                    'projectid'     =>  $page->projectid,
                    'title'         =>  $page->title,
                    'descr'         =>  $page->descr,
                    'img'           =>  $page->img,
                    'featureimg'    =>  $page->featureimg,
                    'alias'         =>  $page->alias,
                    'date'          =>  $page->date,
                    'sort'          =>  $page->sort,
                    'published'     =>  $page->published,
                    'fb_title'      =>  $page->fb_title,
                    'status'        =>  1,
                    // fb_descr, fb_img, meta_title, meta_descr, meta_keywords
                    'filename'      =>  $page->filename
                ];

                $sql = self::makeInsertQuery($this->sql_table, $dataset);
            }

            $this->logger->debug('PDO SQL Query: ', [ str_replace("\r\n", "", $sql) ]);
            $this->logger->debug('PDO SQL Dataset: ', [ $dataset ]);

            $sth = $this->pdo->prepare($sql);

            if (!$sth->execute($dataset)) {
                throw new RuntimeException('Ошибка сохранения/обновления информации о лонгриде в БД');
            }

            $this->logger->debug("Информация по лонгриду сохранена в БД.");

        } catch (RuntimeException $e) {
            // очищаем папку от файлов
            // удаляем папку
            if ($is_directory_created) {
                self::rmdir($path_store);
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
            if (!is_dir($this->path_storage)) {
                throw new RuntimeException('Папка для лонгридов не существует. Обратитесь к нашим админам!');
            }

            if (is_null($page)) {
                throw new RuntimeException('Нет данных для добавления');
            }

            // mapping POST data to DATASET
            foreach ($page as $key => $value) {
                if (!in_array($key, $valid_fields)) {
                    continue 1;
                }
                $dataset[ $key ] = $value;
            }

            if (empty($dataset['published'])) {
                $dataset['published'] = 0;
            }

            // проверим существование
            $sth = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->sql_table} WHERE id = :id ");
            $sth->execute(['id' => $dataset['id']]);
            $count = $sth->fetchColumn();

            if ($count > 0) {
                $state = 'update';
                $this->logger->debug('Обновляем информацию о лонгриде в БД', [ $dataset['id'] ]);
                $sql = self::makeReplaceQuery($this->sql_table, $dataset);
            } else {
                $state = 'ok';
                $this->logger->debug('Добавляем информацию о лонгриде в БД', [ $dataset['id'] ]);
                $sql = self::makeInsertQuery($this->sql_table, $dataset);
            }

            $this->logger->debug('PDO SQL Query: ', [ str_replace("\r\n", "", $sql) ]);
            $this->logger->debug('PDO SQL Dataset: ', [ $dataset ]);

            $sth = $this->pdo->prepare($sql);

            $sql_status = $sth->execute($dataset);

            $this->logger->debug('Статус обновления/добавления лонгрида: ', [ $sql_status ]);

        } catch (PDOException $e) {
            $this->logger->debug('PDO Error, bad SQL request', [ $e->getMessage(), $sql, $dataset ]);
            return $e->getMessage();

        } catch (RuntimeException $e) {
            $this->logger->debug('Ошибка', [$e->getMessage()]);
            return $e->getMessage();
        }

        return $state;
    }

    public function deleteStored($id)
    {
        try {
            if (!is_dir($this->path_storage)) {
                throw new RuntimeException('Папка для лонгридов не существует. Обратитесь к нашим админам!');
            }

            if (empty($id)) {
                throw new RuntimeException("Не указан ID удаляемого лонгрида");
            }

            $this->logger->debug("Начинаем удаление лонгрида с ID: ", [ $id ]);

            $sth = $this->pdo->prepare("SELECT * FROM {$this->sql_table} WHERE id = :id");
            $sth->execute([
                'id'    =>  $id
            ]);
            $longread = $sth->fetch();

            if (!$longread) {
                throw new RuntimeException("Лонгрид с указанным идентификатором не найден в базе данных");
            }

            $lr_folder = rtrim($this->path_storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($longread['folder'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (!is_dir($lr_folder)) {
                throw new RuntimeException('Директории с лонгридом не существует. Обратитесь к администратору!');
            }

            $lr_files = scandir($lr_folder);

            if ($lr_files === false) {
                throw new RuntimeException('Не удалось прочитать оглавление директории с лонгридом. Обратитесь к администратору');
            }

            $lr_files = array_diff($lr_files, [ '.', '..']);

            $this->logger->debug("Удаляем файлы из директории: ", [ $lr_folder ]);

            foreach ($lr_files as $file) {
                $this->logger->debug("Удаляем файл {$file}");
                @unlink($lr_folder . $file);
            }

            if (!rmdir($lr_folder)) {
                throw new RuntimeException("Не получилось удалить директорию {$lr_folder}");
            }

            // удаляем запись из базы
            $sth = $this->pdo->prepare("DELETE FROM {$this->sql_table} WHERE id = :id");

            if (false === $sth->execute([ 'id' => $id])) {
                throw new RuntimeException("Не удалось удалить лонгрид из базы данных, код лонгрида: {$id}");
            }

        } catch (RuntimeException $e) {
            $this->logger->debug("Возникла ошибка при удалении лонгрида: ", [ $e->getMessage() ]);

            return $e->getMessage();
        }

        $this->logger->debug("Лонгрид удалён");

        return 'ok';
    } // delete()

    public function itemToggleVisibility($id, string $new_state = 'hide')
    {
        try {
            if (is_null($id) || $id <= 0) {
                throw new RuntimeException('Не передан ID изменяемого лонгрида');
            }

            $this->logger->debug("Запрошено изменение видимости лонгрида (id), (новый статус)", [ $id, $new_state ]);

            $new_state = in_array($new_state, [ 'hide', 'show' ]) ? $new_state : 'hide';
            $new_state = ($new_state === 'hide') ? -1 : 0;

            $sth = $this->pdo->prepare("UPDATE `longreads` SET `status` = :status WHERE `id` = :id ");
            $sth->execute([
                'status'    =>  $new_state,
                'id'        =>  $id
            ]);

            return 'ok';

        } catch (RuntimeException $e) {
            $this->logger->debug($e->getMessage());

            return $e->getMessage();
        }
    }

    /**
     * Возвращает список опубликованных лонгридов на Тильде
     *
     * возвращается структура
     * [
     *  "status" => FOUND|ERROR
     *  "count" => N
     *  "data" => [ [] ]
     * ]
     *
     * При преобразовании в JSON требуются опции
     * `JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE`
     *
     * @return array JSON Decoded array
     */
    public function fetchPagesList($projects = []):array
    {
        $pages_list = [
            "status"    =>  "FOUND",
            "count"     =>  0,
            "result"    =>  []
        ];

        $projects_list = !empty($projects) ? $projects : $this->tilda_projects_list;

        foreach ($projects_list as $pid) {
            $pid_loaded_count = 0;

            $http_request_query = [
                'publickey' =>  $this->api_options['public_key'],
                'secretkey' =>  $this->api_options['secret_key'],
                'projectid' =>  $pid
            ];
            $req_url = $this->makeApiURI('getpageslist', $http_request_query);

            $this->logger->debug("Запрашиваем список лонгридов в проекте ", [ $pid ]);
            $this->logger->debug("URL запроса: ", [ $req_url ]);

            $curl = new Curl();
            $curl->get($req_url);

            $response = json_decode($curl->response); // JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE (работает для ассоциативного массива)

            $curl->close();

            if ($curl->error) {
                $this->logger->debug("[Tilda.API] Ошибка получения данных", [ $response->message, $curl->error_message, $curl->error_code ]);
                // throw new RuntimeException("[Tilda.API] Ошибка получения данных для проекта {$pid} : " . $response->message);
            }

            $this->logger->debug('Статус ответа: ', [ $response->status ]);

            $pages_list['pages'][ $pid ] = [];
            if ($response->status === "FOUND") {
                foreach ($response->result as $page_info) {
                    $pages_list['result'][] = $page_info;
                    $pages_list['pages'][ $pid ][] = $page_info->id;
                    $pid_loaded_count++;
                }
            }

            $this->logger->debug("Получено информации о лонгридах: ", [ $pid_loaded_count ]);
        }

        $pages_list['count'] = count($pages_list['result']);

        $this->logger->debug("Всего получено информации о лонгридах: ", [ $pages_list['count'] ]);

        if ($pages_list['count'] == 0) {
            $pages_list['status'] = "NOT FOUND";
        }

        return $pages_list;
    }

    public function getPageFullExport($id, $associative = null)
    {
        $http_request_query = [
            'publickey' =>  $this->api_options['public_key'],
            'secretkey' =>  $this->api_options['secret_key'],
            'pageid'    =>  $id
        ];

        $url = $this->makeApiURI('getpagefullexport', $http_request_query);

        $this->logger->debug('[getPageFullExport] URL запроса к тильде:', [ $url ]);

        $curl = new Curl();

        try {
            $curl->get($url);

            $response = $curl->response;
            $response = json_decode($response, $associative);

            if (false === $response) {
                throw new RuntimeException( "[getPageFullExport] ERROR: Не удалось json-декодировать данные с Tilda API" );
            }

            if ($curl->error) {
                throw new RuntimeException( "[getPageFullExport] ERROR: Не удалось получить данные с Tilda API:  " . ($associative ? $response['message'] : $response->message) );
            }

            $curl->close();

            return $response;

        } catch (RuntimeException $e) {
            $this->logger->debug($e->getMessage(), [ $e->getCode(), $url ]);

            return $associative ? [
                'status'    =>  'ERROR',
                'message'   =>  $e->getMessage(),
                'errorside' =>  'info'
            ] : new LongreadError($e->getMessage(), $curl->error_code, $url);
        }
    }

    /* =============================================================================== */
    /* ================================== PRIVATE METHODS ============================ */
    /* =============================================================================== */

    /**
     * Скачивает CURL-ом файл с URL по указанному пути
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    private function downloadFile(string $from, string $to): bool
    {
        $file_handle = fopen($to, 'w+');

        if ($file_handle === false) {
            throw new RuntimeException("Ошибка создания файла `{$to}`");
        }

        $curl = new Curl();

        $curl->setOpt(CURLOPT_FILE, $file_handle);
        $curl->get($from);
        $curl->setOpt(CURLOPT_FILE, null);

        fclose($file_handle);

        if ($curl->error) {
            throw new RuntimeException("Ошибка скачивания файла {$from} " . $curl->error_message);
        }

        $curl->close();

        return !($curl->error);
    }

    /**
     * Создает URI для API-запроса
     *
     * @param string $command
     * @param array $http_request_query
     * @param bool $is_https
     * @return string
     */
    private function makeApiURI(string $command, array $http_request_query, bool $is_https = false): string
    {
        $scheme = $is_https ? 'https://' : 'http://';

        return empty($http_request_query)
            ? "{$scheme}api.tildacdn.info/{$this->api_options['version']}/{$command}/"
            : "{$scheme}api.tildacdn.info/{$this->api_options['version']}/{$command}/?" . http_build_query( $http_request_query );
    }

    /**
     * Recursive rmdir
     *
     * @param $directory
     * @return bool
     */
    private static function rmdir($directory): bool
    {
        if (!\is_dir( $directory )) {
            return false;
        }

        $files = \array_diff( \scandir( $directory ), [ '.', '..' ] );

        foreach ($files as $file) {
            $target = "{$directory}/{$file}";
            (\is_dir( $target ))
                ? self::rmdir( $target )
                : \unlink( $target );
        }
        return \rmdir( $directory );
    }

    /**
     * Строит запрос REPLACE <table> SET ...
     *
     * @param string $table
     * @param array $dataset
     * @param string $where
     * @return false|string
     */
    private static function makeReplaceQuery(string $table, array &$dataset, string $where = '')
    {
        $fields = [];

        if (empty($dataset)) {
            return false;
        }

        $query = "REPLACE `{$table}` SET ";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $fields[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            $fields[] = "`{$index}` = :{$index}";
        }

        $query .= \implode(', ', $fields);

        $query .= " {$where}; ";

        return $query;
    }

    /**
     * Строит запрос INSERT INTO table
     *
     * @param string $table
     * @param $dataset
     * @return string
     */
    private static function makeInsertQuery(string $table, &$dataset):string
    {
        if (empty($dataset)) {
            return "INSERT INTO {$table} () VALUES (); ";
        }

        $set = [];

        $query = "INSERT INTO `{$table}` SET ";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $set[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            $set[] = "`{$index}` = :{$index}";
        }

        $query .= \implode(', ', $set) . ' ;';

        return $query;
    }

    /**
     * Строит запрос UPDATE table SET
     *
     * @param string $table
     * @param $dataset
     * @param $where_condition
     * @return string
     */
    private static function makeUpdateQuery(string $table, &$dataset, $where_condition):string
    {
        $set = [];

        if (empty($dataset)) {
            return false;
        }

        $query = "UPDATE `{$table}` SET";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $set[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            $set[] = "`{$index}` = :{$index}";
        }

        $query .= \implode(', ', $set);

        if (\is_array($where_condition)) {
            $where_condition = \key($where_condition) . ' = ' . \current($where_condition);
        }

        if ( \is_string($where_condition ) && !\strpos($where_condition, 'WHERE')) {
            $where_condition = " WHERE {$where_condition}";
        }

        if (\is_null($where_condition)) {
            $where_condition = '';
        }

        $query .= " $where_condition ;";

        return $query;
    }

}

# -eof-
