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
     * @var string|false $sid
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

        if (empty($this->ip)) {
            throw new \InvalidArgumentException('You myst set IP before auth');
        }
        if (!$this->check_connection()) {
            throw new \InvalidArgumentException('Server is offline');
        }
        $this->auth();
        if (!$this->sid) {
            throw new \InvalidArgumentException('Wrong username or password');
        }
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
    private function check_connection()
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
     * @return bool|string
     */
    private function auth()
    {
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
     * @return array
     */
    public function getServerObjects()
    {
        $url = 'https://' . trim($this->ip) . ':8080/objects/?password=' . trim($this->sdk_password); //получения объектов сервера
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

        $result = $server_health;
        if (!empty($this->channels)) {
            foreach ($this->channels as $channel) {
                $url = 'https://' . trim($this->ip) . ':8080/settings/channels/' . $channel['guid'] . '/flags/signal?sid=' . trim($this->sid); //получения статуса канала
                $responseJson_str = file_get_contents($url, NULL, $this->stream_context);
                $comment_position = strripos($responseJson_str, '/*');    //отрезаем комментарий в конце ответа сервера
                $responseJson_str = substr($responseJson_str, 0, $comment_position);
                $channelHealth = json_decode($responseJson_str, true);

                $channelsHealth[] = [
                    'guid' => $channel['guid'],
                    'signal' => $channelHealth['value']
                ];
            }
            if (!empty($channelsHealth)) {
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
        curl_close($curl);
        if (file_exists($path)) :
            unlink($path);
        endif;
        $fp = fopen($path, 'x');
        if ($fp) {
            fwrite($fp, $content);
            fclose($fp);
        }

        return $img;
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
