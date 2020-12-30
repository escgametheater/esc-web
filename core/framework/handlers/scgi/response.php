<?php

class SCGIResponse
{
    private $headers = [];
    private $content_type = null;
    private $status = '200 Ok';
    private $body = null;

    public function __construct()
    {
        $this->content_type = ini_get('default_mimetype');

        if ($charset = ini_get('default_charset'))
            $this->content_type .= '; charset='.$charset;
    }

    public function set_status($code)
    {
        static $status = [
          401 => '401 Unauthorized',
          404 => '404 Not Found',
          500 => '500 Internal Server Error'
        ];
        if (!in_array($status, $code))
            $code = 500;

        $this->status = $status[$code];
    }

    public function set_body($body)
    {
        $this->body = $body;
    }

    public function add_header($name, $value)
    {
        if ($name == 'Status')
            $this->status = $value;
        elseif ($name == 'Content-type')
            $this->content_type = $value;
        else
            $this->headers[] = $name.': '.$value;
    }

    public function send($conn)
    {
        // Headers
        fwrite($conn, 'Status: '.$this->status."\r\n");
        fwrite($conn, 'Content-type: '.$this->content_type."\r\n");
        fwrite($conn, implode("\r\n", $this->headers));
        fwrite($conn, "\r\n\r\n");
        // Body
        if ($this->body !== null)
            fwrite($conn, $this->body);
    }
}
