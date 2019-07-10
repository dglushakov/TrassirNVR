<?php

namespace Dglushakov\Trassir;


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
    private $channels = [];

    /**
     * Variable for storage session id
     * @var string|false $sid
     */
    private $sid;

    private $userName;
    private $password;
    private $sdkPassword;

    private $stream_context; //тут храним контекст который нужен CURL для работы с неподписанными сертификатами

    public function __construct(string $ip, string $userName=null, string $password=null, string $sdkPassword=null)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->ip = $ip;
            $this->userName = $userName;
            $this->password = $password;
            $this->sdkPassword = $sdkPassword;
            $this->stream_context = stream_context_create(['ssl' => [  //разрешаем принимать самоподписанные сертификаты от NVR
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'verify_depth' => 0]]);

        } else {
            throw new \InvalidArgumentException('Please enter valid IP address.');
        }
    }


    public function setUserName($userName){
        $this->userName = $userName;
    }

    public function getUserName(){
        return $this->userName;
    }

    public function setPassword($password){
        $this->password = $password;
    }

    public function getPassword(){
        return $this->password;
    }

    public function setSdkPassword($sdkpassword){
        $this->sdkPassword = $sdkpassword;
    }

    public function getSdkPassword(){
        return $this->sdkPassword;
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
    public function checkConnection()
    { //проверка доступности сервера.
        $connectionStatus = false;
        $url = 'http://' . trim($this->ip) . ':80/';
        $content = @file_get_contents($url, false, $this->stream_context);
        if ($content) {
            $connectionStatus = true;
        }
        return $connectionStatus;
    }

    /**
     * Get sessionId (sid) using login and password
     * @return bool|string
     */
    public function login()
    {
        $url = 'https://' . trim($this->ip) . ':8080/login?username=' . trim($this->userName) . '&password=' . trim($this->password);
        $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
        $server_auth = json_decode($responseJson_str, true); //переводим JSON в массив
        if ($server_auth['success'] == 1) {
            $this->sid = $server_auth['sid'];
        } else {
            $this->sid = false;
        }
        return $this->sid;
    }


    /**
     * function to get all server objects (channels, IP-devices etc.) Also it set up servers Name and Guid
     * also fills $this->channels array
     *
     * @return array
     */
    public function getServerObjects()
    {
        $url = 'https://' . trim($this->ip) . ':8080/objects/?password=' . trim($this->sdkPassword); //получения объектов сервера
        $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
        $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
        $responseJson_str = substr($responseJson_str, 0, $comment_position);
        $objects = json_decode($responseJson_str, true);

        foreach ($objects as $obj) {
            if ($obj['class'] == 'Server') {
                $this->name = $obj['name'];
                $this->guid = $obj['guid'];
            }
            if ($obj['class'] == 'Channel') {
                $this->channels[] = [
                    'name' => $obj['name'],
                    'guid' => $obj ['guid'],
                    'parent' => $obj ['parent'],
                ];
            }
        }
        return $objects;
    }

    /**
     * return array of indicators
     * @return array|mixed
     */
    public function getHealth()
    {
        $url = 'https://' . trim($this->ip) . ':8080/health?sid=' . trim($this->sid);
        $responseJson_str = file_get_contents($url, null, $this->stream_context);
        $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
        $responseJson_str = substr($responseJson_str, 0, $comment_position);
        $server_health = json_decode($responseJson_str, true);

        $channelsHealth = [];
        $result = $server_health;
        if (!empty($this->channels)) {
            foreach ($this->channels as $channel) {
                $url = 'https://' . trim($this->ip) . ':8080/settings/channels/' . $channel['guid'] . '/flags/signal?sid=' . trim($this->sid); //получения статуса канала
                $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
                $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
                $responseJson_str = substr($responseJson_str, 0, $comment_position);
                $channelHealth = json_decode($responseJson_str, true);

                $channelsHealth[] = [
                    'name' => $channel['name'],
                    'guid' => $channel['guid'],
                    'signal' => $channelHealth['value']
                ];
            }
            if (isset($channelsHealth) && !empty($channelsHealth) && is_array($channelsHealth)) {
                $result = array_merge($server_health, $channelsHealth);
            }
        }

        return $result;
    }

    /**
     * @param array $channel One of private $channels
     * @param string $folder folder to save shots
     * @param \DateTime|null $timestamp take last available shot if timestamp is null
     * @return string url to image
     */
    public function saveScreenshot(array $channel, $folder = 'shots', \DateTime $timestamp = null)
    {
        if ($timestamp) {
            $time = $timestamp->format('Ymd-His');
        } else {
            $time = '0';
        }

        $img = 'https://' . trim($this->ip) . ':8080/screenshot/' . $channel['guid'] . '?timestamp=' . $time . '&sid=' . trim($this->sid);
        $path = $folder . '/shot_' . $channel['name'] . rand(1, 1000) . $time . '.jpg';
        $curl = curl_init($img);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $content = curl_exec($curl);
        $response = json_decode($content, true);
        if ($response['success'] === 0) {
            return $response['error_code'];
        } else {
            curl_close($curl);
            if (file_exists($path)) {
                unlink($path);
            }
            $fp = fopen($path, 'x');
            if ($fp !== false) {
                fwrite($fp, $content);
                fclose($fp);
            }
            return $path;
        }
    }

    /**
     * @param array $channel
     * @param string $stream should be main or sub
     * @param string $container should be mjpeg|flv|jpeg
     * @return bool|string return url to live video stream or false if failure
     */
    public function getLiveVideoStream(array $channel, string $stream = 'main', string $container = 'mjpeg')
    {

        $tokenUrl = 'https://' . trim($this->ip) . ':8080/get_video?channel=' . $channel['guid'] . '&container=' . $container . '&stream=' . $stream . '&sid=' . $this->sid;
        $responseJson_str = file_get_contents($tokenUrl, null, $this->stream_context);
        $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
        if ($comment_position) {
            $responseJson_str = substr($responseJson_str, 0, $comment_position);
        }
        $token = json_decode($responseJson_str, true);

        if ($token['success'] == 1) {
            $videoToken = $token['token'];
        } else {
            throw new \InvalidArgumentException('Cann not get vieotoken');
        }

        $result = 'http://' . trim($this->ip) . ':555/' . $videoToken;
        return $result;
    }


}
