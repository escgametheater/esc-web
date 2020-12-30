<?php

abstract class SCGIServer
{
     private $socket = null;
     private $has_gc = true;

     public function __construct($socket_url = 'tcp://127.0.0.1:9999')
     {
          if (PHP_SAPI !== 'cli')
                throw new LogicException("SCGI Application should be run using CLI SAPI");

          // Checking for GarbageCollection patch
          if (false === function_exists('gc_enabled')) {
                $this->has_gc = false;
                echo "WARNING: This version of PHP is compiled without GC-support. Memory-leaks are possible!\n";
          } elseif (false === gc_enabled()) {
                gc_enable();
          }

          $errno = 0;
          $errstr = "";
          $this->socket = stream_socket_server($socket_url, $errno, $errstr);

          if (false === $this->socket)
                throw new RuntimeException('Failed creating socket-server (URL: "'.$socket_url.'"): '.$errstr, $errno);

          echo 'Initialized SCGI Application: '.get_class($this).' @ ['.$socket_url."]\n";
     }

     public function __destruct()
     {
          fclose($this->socket);
          echo "DeInitialized SCGI Application: ".get_class($this)."\n";
     }

    public function runLoop()
    {
        echo "Entering runloop…\n";

        try {
            while ($conn = stream_socket_accept($this->socket, -1)) {
                list($headers, $body) = $this->parseRequest($conn);

                $response = $this->requestHandler($headers, $body);
                $response->send($conn);

                unset($request);
                unset($response);

                fclose($conn);
            }
        } catch (Exception $e) {
            fclose($conn);
            echo '[Exception] '.get_class($e).': '.$e->getMessage()."\n";
        }

        echo "Left runloop…\n";
     }

     private function parseRequest($conn)
     {
          $len = stream_get_line($conn, 20, ':');

          if (false === $len)
                throw new Exception('error reading data');

          if (!is_numeric($len))
                throw new Exception('invalid protocol ('.$len.')');

          $_headers = explode("\0", stream_get_contents($conn, $len)); // getting headers
          $divider = stream_get_contents($conn, 1); // ","

          $headers = [];
          $first = null;
          foreach ($_headers as $element) {
                if (null === $first) {
                     $first = $element;
                } else {
                     $headers[$first] = $element;
                     $first = null;
                }

                if (true === $this->has_gc)
                     gc_collect_cycles();
          }
          unset($_headers, $first);

          if (!isset($headers['SCGI']) || $headers['SCGI'] != '1')
                throw new SCGI_Exception("Request is not SCGI/1 Compliant");

          if (!isset($headers['CONTENT_LENGTH']))
                throw new SCGI_Exception("CONTENT_LENGTH header not present");

          if ($headers['CONTENT_LENGTH'] > 0)
             $body = stream_get_contents($conn, $headers['CONTENT_LENGTH']);
          else
             $body = null;

          unset($headers['SCGI'], $headers['CONTENT_LENGTH']);

          return [$headers, $body];
     }

     protected function requestHandler($request)
     {
          $this->response->addHeader('Status', '500 Internal Server Error');
          $this->response->addHeader('Content-type', 'text/html; charset=UTF-8');
          $this->response->write("<h1>500 — Internal Server Error</h1><p>Application doesn't implement requestHandler() method :-P</p>");
     }
}
