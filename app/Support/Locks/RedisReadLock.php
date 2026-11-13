<?php

declare(strict_types=1);

namespace App\Support\Locks;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Redis\Connections\Connection;

class RedisReadLock
{
    /**
     * Создать новый экземпляр блокировки чтения.
     *
     * @param Connection $redis
     * @param string $name Имя ключа в Redis (счетчик).
     * @param int $seconds Время жизни счетчика (TTL) в секундах.
     */
    public function __construct(
        protected Connection $redis,
        protected string $name,
        protected int $seconds // Переименовали ttl -> seconds для единообразия
    ) {
    }

    /**
     * Текущее количество читателей.
     */
    public function count(): int
    {
        return (int) $this->redis->get($this->name);
    }

    /**
     * Проверить, есть ли активные блокировки на чтение.
     *
     * @return bool True, если хотя бы один процесс читает ресурс.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Увеличивает счетчик активных читателей.
     */
    public function acquire(): void
    {
        // Используем pipeline, чтобы минимизировать сетевые задержки
        $this->redis->pipeline(function($pipe) {
            $pipe->incr($this->name);
            $pipe->expire($this->name, $this->seconds);
        });
    }

    /**
     * Уменьшает счетчик читателей.
     * Если счетчик ушел в минус (ошибка синхронизации), сбрасывает в 0.
     */
    public function release(): void
    {
        /**
         * Используем Lua, чтобы операция уменьшения и проверки на ноль
         * была атомарной и исключала гонку данных (Race Condition).
         */
        $script = '
            local count = redis.call("decr", KEYS)
            if count < 0 then
                redis.call("set", KEYS, 0)
                return 0
            end
            return count';

        $this->redis->eval($script, 1, $this->name);
    }

    /**
     * Продлевает TTL счетчика (Heartbeat).
     *
     * @param int|null $seconds Новый TTL. Если не указан, используется значение из конструктора.
     */
    public function extend(?int $seconds = null): void
    {
        $this->redis->expire($this->name, $seconds ?? $this->seconds);
    }

    /**
     * Ожидание обнуления счетчика читателей с выполнением callback в цикле.
     *
     * @param int $timeout Максимальное время ожидания в секундах.
     * @param int $sleep Интервал проверки в микросекундах.
     * @param \Closure|null $onWait Callback, выполняемый при каждой итерации ожидания.
     * @throws LockTimeoutException
     */
    public function wait(int $timeout, int $sleep = 100000, ?\Closure $onWait = null): void
    {
        $starting = microtime(true);

        while ($this->count() > 0) {
            if (microtime(true) - $starting >= $timeout) {
                throw new LockTimeoutException("ReadLock [{$this->name}]: timeout waiting for readers to finish.");
            }

            // Выполняем стороннее действие (например, продление WriteLock)
            if ($onWait) {
                $onWait();
            }

            usleep($sleep);
        }
    }
}
