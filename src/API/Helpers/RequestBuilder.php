<?php
/**
 * Created by PhpStorm.
 * User: germanlena
 * Date: 4/22/15
 * Time: 3:11 PM
 */

namespace Auth0\SDK\API\Helpers;
use \Auth0\SDK\API\Header\Header;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\RequestException;

class RequestBuilder {

    protected $path = [];
    protected $method = [];
    protected $headers = [];
    protected $params = [];
    protected $form_params = [];
    protected $files = [];
    protected $body;

    public function __construct( $config ) {

        $this->method = $config['method'];
        $this->domain = $config['domain'];
        $this->headers = isset($config['headers']) ? $config['headers'] : array();
        if (array_key_exists('path', $config)) $this->path = $config['path'];

    }

    public function __call($name, $arguments) {

        $argument = null;

        if (count($arguments) > 0) {
            $argument = $arguments[0];
        }
        
        $this->addPath($name, $argument);

        return $this;
    }

    public function addPath($name, $argument = null) {
        $this->path[] = $name;
        if ($argument !== null) {
            $this->path[] = $argument;
        }
        return $this;
    }

    public function addPathVariable($variable) {
        $this->path[] = $variable;
        return $this;
    }

    public function getUrl() {
        return $this->domain . '/' . trim(implode('/',$this->path), '/') . $this->getParams();
    }

    public function getParams() {
        if (empty($this->params)) return '';

        $params = array_map(function($key, $value){
            return "$key=$value";
        }, array_keys($this->params), $this->params);

        return '?' . implode('&',$params);
    }

    public function dump() {
        echo "<pre>";
        echo "METHOD: {$this->method}\n";
        echo "URL: {$this->getUrl()}\n";

        echo "HEADERS:\n\t";
        echo implode("\n\t",array_map(function($k,$v){ return "$k: $v";}, array_keys($this->headers), $this->headers));
        echo "\n";

        echo "BODY: {$this->body}\n";

        echo "</pre>";

        return $this;
    }

    public function addFile($field, $file_path) {
        $this->files[$field] = $file_path;
        return $this;
    }

    public function addFormParam($key, $value) {
        $this->form_params[$key] = $value;
        return $this;
    }

    public function call() {
        $client = new Client();

        try {
            
            $data = [
                'headers' => $this->headers,
                'body' => $this->body,
            ];

            if (!empty($this->files)) {
               $data['multipart'] = $this->buildMultiPart();
            } else if (!empty($this->form_params)) {
                $data['form_params'] = $this->form_params;
            }

            $response = $client->request($this->method, $this->getUrl(), $data);
            $body = (string) $response->getBody();

            return json_decode($body, true);

        } catch (RequestException $e) {
            throw $e;
        }
    }

    public function withHeaders($headers) {

        foreach ($headers as $header) {
            $this->withHeader($header);
        }

        return $this;
    }

    public function withHeader($header, $value = null) {

        if ($header instanceof Header) {
            $this->headers[$header->getHeader()] = $header->getValue();
        }
        else {
            $this->headers[$header] = $value;
        }
        return $this;
    }

    public function withBody($body) {
        $this->body = $body;
        return $this;
    }

    public function withParam($key, $value) {

        $value = ($value === true ? 'true' : $value);
        $value = ($value === false ? 'false' : $value);

        $this->params[$key] = $value;
        return $this;
    }

    public function withParams($params) {
        foreach($params as $param) {
            $this->withParam($param['key'], $param['value']);
        }
        return $this;
    }

    private function buildMultiPart() {
        $multipart = array();

        foreach($this->files as $field => $file) {
            $multipart[] = [
                'name' => $field,
                'contents' => fopen($file, 'r')
            ];
        }
        foreach($this->form_params as $param => $value) {
            $multipart[] = [
                'name' => $param,
                'contents' => $value
            ];
        }
        return $multipart;
    }

}
