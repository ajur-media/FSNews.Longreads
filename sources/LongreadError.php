<?php

namespace AJUR\FSNews;

class LongreadError
{
    public string $status = 'ERROR';

    public $result;

    public bool $error = true;

    public string $error_message = '';

    /**
     * @var int
     */
    public int $error_code;

    /**
     * @param int $error_code
     * @param string $url
     */
    public string $url;

    public function __construct($error_message = '', $error_code = 0, $url = '', $id = 0)
    {
        $this->error_message = $error_message;
        $this->error_code = $error_code;
        $this->url = $url;
        $this->result = new \stdClass();
        $this->result->id = $id;
    }
}