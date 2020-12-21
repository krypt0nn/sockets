<?php

namespace Sockets;

/**
 * Объект реализации "асинхронного" вызова анонимной функции
 * Бред полнейший, но пришлось сделать потому что
 * метод ->call у Closure первым аргументом принимает
 * объект, от чьего имени надо вызвать функцию,
 * поэтому я решил просто сделать свою реализацию
 */
final class AsyncObject
{
    # Хранилище коллбэка
    protected $callback = [];

    /**
     * @param mixed $data - какие-либо данные, которые будут переданы в коллбэк при вызове
     * @param callable $callback - коллбэк, вызываемый методом call. Первый аргумент - $data пунктом выше
     */
    public function __construct (protected $data, callable $callback)
    {
        // Один из способов хранить анонимные функции в свойствах объекта. Костыль, но иначе никак
        $this->callback = [$callback];
    }

    /**
     * Вызов коллбэка
     * 
     * @param ...$args - аргументы коллбэка
     */
    public function call (...$args)
    {
        return $this->callback[0]($this->data, ...$args);
    }
}

/**
 * Объект реализации получения соединения со сторонним сокетом
 */
class SocketListener
{
    // Ресурс socket listener'а
    protected $socket;

    /**
     * [@param int $port = 0] - порт, на котором мы слушаем соединения
     * Понятия не имею что значит 0, так было в документации. Возможно это широковещательный канал
     * 
     * @throws \Exception - выбрасывает исключение при ошибке создания сокета
     */
    public function __construct (int $port = 0)
    {
        $this->socket = socket_create_listen ($port);

        if ($this->socket === false)
            throw new \Exception ('Socket creating error: '. socket_strerror (socket_last_error ()));

        // Переводим сокет в non-blocking режим чтобы socket_accept работал сразу, а не ждал соединения
        socket_set_nonblock ($this->socket);
    }

    /**
     * Приём входящего соединения если оно присутствует
     * 
     * @return SocketClient|null - возвращает клиент сокета либо null, если его нет
     */
    public function accept (): ?SocketClient
    {
        $client = socket_accept ($this->socket);

        return $client === false ? null :
            SocketClient::fromResource ($client);
    }

    /**
     * Приём входящего соединения в "асинхронном" режиме (while true короче)
     * 
     * @return AsyncObject - возвращает объект асинхронного получения соединения
     * 
     * @example
     * 
     * $i = 0;
     * $client = $listener->acceptAsync()->call (function () use (&$i)
     * {
     *     if (++$i == 10)
     *         return false; // Закрываем асинхронный поиск подключения
     * 
     *     echo 'Waiting for connections... ('. $i .')'. PHP_EOL;
     *     sleep (1);
     * 
     *     return true;
     * });
     */
    public function acceptAsync (): AsyncObject
    {
        return new AsyncObject ($this->socket, function ($socket, callable $stuff = null): ?SocketClient
        {
            while (($client = socket_accept ($socket)) === false)
                if (is_callable ($stuff) && $stuff ($socket) === false)
                    break;

            return $client === false ? null :
                SocketClient::fromResource ($client);
        });
    }
}
