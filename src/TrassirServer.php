<?php

namespace dglushakov\trassir;


class TrassirServer
{
    /**
     * @var string $name
     */
    private $name;
    /**
     * @var string|null $ip
     */
    private $ip;

    /**
     * @var string $guid
     */
    private $guid;

    private $stream_context; //тут храним контекст который нужен CURL для работы с неподписанными сертификатами

    public function __construct($ip)
    {
        $this->ip = $ip;
        $this->stream_context = stream_context_create(['ssl' => [  //разрешаем принимать самоподписанные сертификаты от NVR
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'verify_depth' => 0]]);
    }

    /**
     * Get server Name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name= $name;
    }

    /**
     * @return null|string
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * @return string
     */
    public function getGuid(): string
    {
        return $this->guid;
    }

    /**
     * @param string $guid
     */
    public function setGuid(string $guid): void
    {
        $this->guid = $guid;
    }


    /**
     * Checking is NVR online or not to prevent errors
     * @return bool
     */
    protected function check_connection()
    { //проверка доступности сервера.
        $status = false;
        $url = 'http://' . trim($this->ip) . ':80/';
        $curlInit = curl_init($url);
        curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, 2); //третий параметр - время ожидания ответа сервера в секундах
        curl_setopt($curlInit, CURLOPT_HEADER, true);
        curl_setopt($curlInit, CURLOPT_NOBODY, true);
        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlInit);
        curl_close($curlInit);
        if ($response) {
            $status = true;
        }
        return $status;
    }

    /**
     * Get sessionId (sid) using login and password
     *
     * @param string $user
     * @param string $password
     * @return bool|string
     */
    public function authorization(string $user, string $password)
    {
        if (is_null($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
        $sid = false;

        $url = 'https://' . trim($this->ip) . ':8080/login?username=' . trim($user) . '&password=' . trim($password);
        $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
        $server_auth = json_decode($responseJson_str, true); //переводим JSON в массив
        if ($server_auth['success'] == 1) {
            //$conf['sid'] = $server_auth['sid']; //записываем sid массив
            $sid = $server_auth['sid'];
        }
        return $sid;
    }


    /**
     * @param string $sdk_password password for Trassir SDK
     * @return array
     */
    public function getServerObjects(string $sdk_password)
    {
        if (is_null($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
            $url = 'https://' . trim($this->ip) . ':8080/objects/?password=' . trim($sdk_password); //получения объектов сервера
            $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
            $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
            $responseJson_str = substr($responseJson_str, 0, $comment_position);
            $objects = json_decode($responseJson_str, true);
        return $objects;
    }

    public function getServerHealth($sid) // запрос здоровья, возвращает массив
    {
        if (is_null($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
        if (empty($sid)) {
            throw new \InvalidArgumentException('You should make authorization() first to get sid');
        }
        $url = 'https://' . trim($this->ip) . ':8080/health?sid=' . trim($sid); //получение здоровья
        $responseJson_str = file_get_contents($url, null, $this->stream_context); //получаем состояние сервера по адресу
        $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
        $responseJson_str = substr($responseJson_str, 0, $comment_position);
        $server_health = json_decode($responseJson_str, true); //переводим JSON в массив

        return $server_health;
    }

    /**
     * function for get NVR's name (that is configured on NVR locally)
     * @param string $sdk_password
     * @return bool|string
     */
    public function getServerName(string $sdk_password){
        $objects = $this->getServerObjects($sdk_password);
        $result = false;
        if ($objects) {
            foreach ($objects as $obj) {
                if ($obj['class'] == 'Server') {
                    $result = $obj['name'];
                    break;
                }
            }
        }
        return $result;
    }

}
