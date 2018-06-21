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

    /**
     * array for handle list of channels from NMR
     * @var array $channels
     */
    private $channels;

    /**
     * Variable for storage sessidon id
     * @var string $sid
     */
    private $sid;
    private $user;
    private $password;
    private $sdk_password;

    private $stream_context; //тут храним контекст который нужен CURL для работы с неподписанными сертификатами

    public function __construct(string $ip, string $user, string $password, string $sdk_password)
    {
        $this->ip = $ip;
        $this->user = $user;
        $this->password = $password;
        $this->sdk_password = $sdk_password;
        $this->stream_context = stream_context_create(['ssl' => [  //разрешаем принимать самоподписанные сертификаты от NVR
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'verify_depth' => 0]]);
    }

    /**
     * @return array
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
    private function auth()
    {
        if (is_null($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
        $this->sid = false;

        $url = 'https://' . trim($this->ip) . ':8080/login?username=' . trim($this->user) . '&password=' . trim($this->password);
        $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
        $server_auth = json_decode($responseJson_str, true); //переводим JSON в массив
        if ($server_auth['success'] == 1) {
            $this->sid = $server_auth['sid'];
        } else {
            return false;
        }
        return true;
    }

    /**
     * function to get all server objects (channels, IP-devices etc.) Also it set up servers Name and Guid
     * also fills $this->channels array
     *
     * @param string $sdk_password password for Trassir SDK
     * @return array
     */
    public function getServerObjects()
    {
        if (is_null($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
            $url = 'https://' . trim($this->ip) . ':8080/objects/?password=' . trim($this->sdk_password); //получения объектов сервера
            $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
            $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
            $responseJson_str = substr($responseJson_str, 0, $comment_position);
            $objects = json_decode($responseJson_str, true);

            foreach ($objects as $obj) {
                if ($obj['class']=='Server') {
                    $this->name = $obj['name'];
                    $this->guid = $obj['guid'];
                }
                if ($obj['class']=='Channel') {
                    $this->channels[] = [
                        'name' => $obj['name'],
                        'guid' =>$obj ['guid'],
                        'parent' =>$obj ['parent'],
                    ];
                }
            }
        return $objects;
    }

    public function getHealth() // запрос здоровья, возвращает массив
    {
        if (is_null($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
        $this->auth();
        echo $this->sid;
        if (!$this->sid) {
            throw new \InvalidArgumentException('You should make authorization() first to get sid');
        }
        $url = 'https://' . trim($this->ip) . ':8080/health?sid=' . trim($this->sid); //получение здоровья
        $responseJson_str = file_get_contents($url, null, $this->stream_context); //получаем состояние сервера по адресу
        $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
        $responseJson_str = substr($responseJson_str, 0, $comment_position);
        $server_health = json_decode($responseJson_str, true); //переводим JSON в массив

        $result = $server_health;
        if(!empty($this->channels)) {
            foreach ($this->channels as $channel) {
                $url = 'https://' . trim($this->ip) . ':8080/settings/channels/' . $channel['guid'] . '/flags/signal?sid=' . trim($this->sid); //получения статуса канала
                $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
                $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
                $responseJson_str = substr($responseJson_str, 0, $comment_position);
                $channelHealth = json_decode($responseJson_str, true);

                $channelsHealth[]=[
                    'guid' => $channel['guid'],
                    'signal' => $channelHealth['value']
                ];
            }
        $result = array_merge($server_health, $channelsHealth);
        }

        return $result;
    }
}
