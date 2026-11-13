<?php

declare(strict_types=1);

namespace App\Support\Locks;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Str;

class RedisExtendedLock
{
    /**
     * Уникальный идентификатор владельца блокировки.
     * Гарантирует, что только тот процесс, который создал лок, может его продлить или удалить.
     */
    protected string $owner;

    /**
     * @param Connection $redis Экземпляр подключения к Redis.
     * @param string $name Уникальное имя ключа блокировки.
     * @param int $seconds Время жизни ключа (TTL) по умолчанию в секундах.
     */
    public function __construct(
        protected Connection $redis,
        protected string $name,
        protected int $seconds,
        protected bool $useOwnerLock = true
    ) {
        // Генерируем случайный токен для текущего процесса
        $this->owner = $useOwnerLock ? Str::random(20) : '1';
    }

    /**
     * Получить идентификатор владельца текущего объекта блокировки.
     */
    public function owner(): string
    {
        return $this->owner;
    }

    /**
     * Проверить, существует ли блокировка в данный момент.
     *
     * @return bool True, если блокировка кем-то захвачена.
     */
    public function exists(): bool
    {
        return (bool) $this->redis->exists($this->name);
    }

    /**
     * Попытка захватить блокировку (атомарно).
     * Использует команду SET с параметром NX (Only if Not Exists).
     *
     * @return bool True, если блокировка успешно захвачена.
     */
    public function get(): bool
    {
        return (bool) $this->redis->set($this->name, $this->owner, 'EX', $this->seconds, 'NX');
    }

    /**
     * Ожидание захвата блокировки в течение указанного времени.
     *
     * @param int $seconds Максимальное время ожидания (в секундах).
     * @param int $sleep Интервал между попытками в микросекундах (100 000 мкс = 100 мс).
     *
     * @return bool Всегда возвращает true при успехе.
     * @throws LockTimeoutException Если время ожидания превышено.
     */
    public function block(int $seconds, int $sleep = 100000): bool
    {
        $starting = microtime(true);

        while (!$this->get()) {
            if (microtime(true) - $starting >= $seconds) {
                throw new LockTimeoutException("Could not acquire lock [{$this->name}] within {$seconds} seconds.");
            }

            usleep($sleep);
        }

        return true;
    }

    /**
     * Безопасное продление блокировки.
     * Обновляет TTL ключа только в том случае, если текущий процесс всё еще является владельцем.
     *
     * @param int|null $seconds Новый TTL. Если не указан, используется значение из конструктора.
     * @return bool True, если блокировка была успешно продлена.
     */
    public function extend(?int $seconds = null): bool
    {
        $ttl = $seconds ?? $this->seconds;

        // Lua-скрипт проверяет: совпадает ли значение в Redis с нашим $owner.
        // Это исключает продление "чужого" лока, если наш уже истек по таймауту.
        $script = '
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("expire", KEYS[1], ARGV[2])
            else
                return 0
            end';

        return (bool) $this->redis->eval($script, 1, $this->name, $this->owner, $ttl);
    }

    /**
     * Продлевает блокировку или выбрасывает исключение с подробным описанием.
     *
     * @param int|null $seconds Новый TTL (если null, берется из конструктора).
     * @param string|null $message Кастомное описание ошибки.
     * @throws LockTimeoutException
     */
    public function extendOrFail(?int $seconds = null, ?string $message = null): void
    {
        if (!$this->extend($seconds)) {
            $detail = $message ?? "has been lost or intercepted";

            throw new LockTimeoutException("Lock [{$this->name}]: {$detail}");
        }
    }

    /**
     * Безопасное освобождение (удаление) блокировки.
     * Удаляет ключ только если текущий процесс является владельцем.
     *
     * @return bool True, если блокировка была удалена.
     */
    public function release(): bool
    {
        // Использование Lua-скрипта критично для предотвращения Race Condition:
        // защищает от ситуации, когда один процесс удаляет замок, захваченный другим процессом.
        $script = '
            if redis.call("get", KEYS[1]) == ARGV[1] then 
                return redis.call("del", KEYS[1]) 
            else 
                return 0 
            end';

        return (bool) $this->redis->eval($script, 1, $this->name, $this->owner);
    }

    /**
     * Ожидание ОСВОБОЖДЕНИЯ блокировки (снятия лока другими процессами).
     *
     * @param int $seconds Максимальное время ожидания в секундах.
     * @param int $sleep Интервал проверки в микросекундах (по умолчанию 100мс).
     * @throws LockTimeoutException Если блокировка не была снята за указанное время.
     */
    public function waitForRelease(int $seconds, int $sleep = 100000): void
    {
        $starting = microtime(true);

        // Цикл работает, пока ключ существует в Redis
        while ($this->redis->exists($this->name)) {
            if (microtime(true) - $starting >= $seconds) {
                throw new LockTimeoutException("Lock [{$this->name}] was not released within {$seconds} seconds.");
            }

            usleep($sleep);
        }
    }
}
