<?php

namespace AJUR\FSNews;

class LongreadError
{
    public string $status = 'ERROR';

    public string $result = '';

    public bool $error = true;

    public string $error_message = '';

    /**
     * @var int
     */
    public int $error_code;

    /**
     * @var string
     */
    public string $url;

    public function __construct($error_message = '', $error_code = 0, $url = '')
    {
        $this->error_message = $error_message;
        $this->error_code = $error_code;
        $this->url = $url;
    }
}