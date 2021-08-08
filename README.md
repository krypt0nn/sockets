<h1 align="center">🚀 Sockets</h1>

**sockets** - небольшая библиотека для работы с сокетами на PHP 7.4+

## Установка

```
composer require krypt0nn/sockets
```

## Пример работы

Клиент:

```php
<?php

require 'vendor/autoload.php';

use Sockets\Client;

$client = new Client;
$client->connect ('127.0.0.1', 53874);

while (true)
    $client->send (readline ('> '));
```

Сервер:

```php
<?php

require 'vendor/autoload.php';

use Sockets\Listener;

$listener = new Listener (53874);

$i = 0;
$client = $listener->acceptAsync()->call(function () use (&$i)
{
    if (++$i == 10)
        return false;
    
    echo 'Waiting for connections... ('. $i .')' . PHP_EOL;
    sleep (1);

    return true;
});

if ($client === null)
    die ('Client not connected');

echo 'Client connected'. PHP_EOL;

while (true)
{
    try
    {
        $messages = @$client->read ();
    }

    catch (\Exception $e)
    {
        continue;
    }
    
    foreach ($messages as $message)
        echo '> '. $message . PHP_EOL;

    sleep (1);
}
```

Автор: [Подвирный Никита](https://vk.com/technomindlp)
