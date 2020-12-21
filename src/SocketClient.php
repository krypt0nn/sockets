<?php

namespace Observer\Sockets;

/**
 * Объект реализации клиента сокета
 */
class SocketClient
{
    protected $socket; // Русурс сокета
    protected string $offset = ''; // Хранилище части полученных сокетов данных (см. метод read)

    /**
     * [@param int $domain = AF_INET]   - протокол сокета (IPv4)
     * [@param int $type = SOCK_STREAM] - тип сокета
     * [@param int $protocol = SOL_TCP] - протокол сокета (TCP/IP)
     * [@param mixed $socket = null]    - ресурс сокета (если есть)
     * 
     * @throws \Exception - выбрасывает исключение при ошибке создания сокета
     */
    public function __construct (int $domain = AF_INET, int $type = SOCK_STREAM, int $protocol = SOL_TCP, $socket = null)
    {
        $this->socket = $socket === null ?
            socket_create ($domain, $type, $protocol) : $socket;

        if ($this->socket === false)
            throw new \Exception ('Socket creating error: '. socket_strerror (socket_last_error ()) . var_dump ($this->socket));
    }

    /**
     * Создание объекта SocketClient из ресурса сокета
     * 
     * @param mixed $socket - ресурс сокета
     * 
     * @return SocketClient
     */
    public static function fromResource ($socket): self
    {
        return new SocketClient (AF_INET, SOCK_STREAM, SOL_TCP, $socket);
    }

    /**
     * Подключение к удалённому сокету
     * 
     * @param string $address - адрес подключения (127.0.0.1)
     * [@param int $port = 0] - порт подключения
     * 
     * @return SocketClient
     * 
     * @throws \Exception - выбрасывает исключение при ошибке подключения к сокету
     */
    public function connect (string $address, int $port = 0): self
    {
        if (socket_connect ($this->socket, $address, $port) === false)
            throw new \Exception ('Socket connecting error: '. socket_strerror (socket_last_error ()));

        return $this;
    }

    /**
     * Отправка сообщения
     * 
     * @param string $message - сообщение для отправки
     * 
     * @return SocketClient
     * 
     * @throws \Exception - выбрасывает исключение при ошибке отправки сообщения
     */
    public function send (string $message): self
    {
        $encoded    = '';
        $breakpoint = chr(255);

        # Кодируем сообщение чтобы отделить его в методе read
        for ($i = 0, $len = strlen ($message); $i < $len; ++$i)
            if ($message[$i] == $breakpoint)
                $encoded .= $breakpoint . $breakpoint;

            else $encoded .= $message[$i];
        
        if (socket_write ($this->socket, $encoded . $breakpoint) === false)
            throw new \Exception ('Socket writing error: '. socket_strerror (socket_last_error ()));

        return $this;
    }

    /**
     * Получаем список принятых сообщений
     * 
     * @return array - возвращает массив принятых сообщений
     * 
     * @throws \Exception - выбрасывает исключение при ошибке чтения сообщения
     */
    public function read (): array
    {
        if (($data = socket_read ($this->socket, 4096)) === false)
            throw new \Exception ('Socket reading error: '. socket_strerror (socket_last_error ()));

        $data     = $this->offset . $data;
        $messages = [];

        $breakpoint = chr(255);
        $breaked    = false;

        $message = '';

        # Декодируем данные, разделяя их на сообщения
        for ($i = 0, $len = strlen ($data); $i < $len; ++$i)
            if ($data[$i] == $breakpoint)
            {
                if ($breaked)
                    $message .= $breakpoint;

                $breaked = !$breaked;
            }

            elseif ($breaked)
            {
                $messages[] = $message;
                $message    = $data[$i];

                $breaked = false;
            }

            else $message .= $data[$i];

        if ($breaked)
            $messages[] = $message;

        else $this->offset = $message;

        return $messages;
    }
}
