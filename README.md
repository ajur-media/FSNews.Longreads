# SteamBoatEngine Module -- Longreads

## Требуемая структура таблицы 

```
CREATE TABLE `longreads` (
  `id`          int(11)         NOT NULL,
  `projectid`   int(11)         NOT NULL DEFAULT '0',
  `title`       varchar(1024)   NOT NULL DEFAULT '' ,
  `fb_title`    varchar(1024)   NOT NULL DEFAULT '' ,
  `descr`       varchar(2048)   NOT NULL DEFAULT '' ,
  `img`         varchar(1024)   NOT NULL DEFAULT '' ,
  `featureimg`  varchar(1024)   NOT NULL DEFAULT '' ,
  `alias`       varchar(1024)   NOT NULL DEFAULT '' ,
  `date`        datetime        NOT NULL,
  `sort`        int(11)         NOT NULL DEFAULT '0',
  `published`   int(11)         DEFAULT NULL,
  `filename`    varchar(255)    NOT NULL DEFAULT '' ,
  `status`      tinyint(4)      NOT NULL DEFAULT '0',
  `folder`      varchar(255)    NOT NULL DEFAULT '' ,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='информация о лонгридах';
```

## Методы

### `__construct(PDO $pdo, $options = [], LoggerInterface $logger = null)`

Конструктор класса. Принимает аргументы `PDO $pdo`, `array $options`, `\psr\log\LoggerInterface $logger` 

Значения массива опций:

- `version` - версия Tilda API, необязательный, по умолчанию `v1`
- `public_key` - публичный ключ доступа к Tilda API, **обязательный**
- `secret_key` - секретный ключ доступа к Tilda API, **обязательный**
- `projects` - массив "проектов" лонгридов на Tilda, **обязательный**. Может быть передан как массив или как строка чисел, разделенных пробелами. 

- `path.storage` - путь к директории лонгридов, **обязательный**
- `path.favicon` - путь к FavIcon, который будет подставлен в html-файл лонгрида, не обязательный, по умолчанию favicon тильды
- `path.footer_template` - путь к файлу шаблона футера лонгрида, который будет прикреплен после текста, необязательный (но желательный)

- `sql.table` - SQL таблица с лонгридами, необязательный, по умолчанию `longreads`

- `options.option_cutoff_footer` - обрезать ли футер для вставки своих счетчиков из шаблона (true)
- `options.option_localize_media` - локализовывать путь к медиа (в некоторых случаях картинки могут ссылаться на корень, их нужно запрашивать из текущей папки), (true)
- `options.download_client` - клиент для скачивания. По умолчанию native, допустимо значение curl, требует пакет `curl/curl`


### `getStoredAll($order_status = 'DESC', $order_date = 'DESC')`
    
Получить список всех сохраненных лонгридов из БД

### `getStoredByID($id = null)`;

Получить конкретный лонгрид из БД по ID
    
### `import($id, $folder = null, $import_mode = 'update')`;

Импортировать лонгрид по идентификатору    
    
### `add($page = null)`

Добавить лонгрид (?)
    
### `deleteStored($id)`

Удалить импортированный лонгрид

### `fetchPagesList()`

Возвращает список опубликованных лонгридов на Тильде
 
---
  
   