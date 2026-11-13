<?php

declare(strict_types=1);

namespace App\Support\Locks;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class LockManager
{
    /**
     * Создать экземпляр расширенной блокировки Redis.
     *
     * Метод позволяет гибко инициализировать блокировку, принимая как имя
     * подключения из конфига, так и уже существующий объект соединения.
     *
     * @param string $name Имя ключа блокировки в Redis.
     * @param int $seconds Время жизни блокировки (TTL) в секундах.
     * @param Connection|string|null $connection Имя подключения (из database.php) или объект соединения.
     *                                          Если null, используется подключение по умолчанию.
     *
     * @return RedisExtendedLock
     */
    public function lock(string $name, int $seconds, Connection|string|null $connection = null, bool $useOwnerLock = true): RedisExtendedLock
    {
        // Если передана строка или null — получаем связь через фасад Redis.
        // Если передан готовый объект Connection — используем его напрямую.
        $redisConnection = $connection instanceof Connection
            ? $connection
            : Redis::connection($connection);

        return new RedisExtendedLock($redisConnection, $name, $seconds, $useOwnerLock);
    }


    /**
     * Создать экземпляр блокировки чтения (Shared Lock) на базе счетчика.
     *
     * Используется для отслеживания количества активных читателей ресурса.
     * Позволяет синхронизатору дождаться момента, когда все клиенты завершат
     * чтение, прежде чем начать обновление данных.
     *
     * @param string $name Имя ключа счетчика в Redis.
     * @param int $seconds Время жизни счетчика (TTL) в секундах.
     * @param Connection|string|null $connection Имя подключения или объект соединения.
     *
     * @return RedisReadLock
     */
    public function readLock(string $name, int $seconds, Connection|string|null $connection = null): RedisReadLock
    {
        $redisConnection = $connection instanceof Connection
            ? $connection
            : Redis::connection($connection);

        return new RedisReadLock($redisConnection, $name, $seconds);
    }
}
