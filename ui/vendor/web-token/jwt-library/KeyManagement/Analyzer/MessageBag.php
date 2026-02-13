<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Override;
use Traversable;
use function count;

final class MessageBag implements JsonSerializable, IteratorAggregate, Countable
{
    /**
     * @var Message[]
     */
    private array $messages = [];

    /**
     * Adds a message to the message bag.
     */
    public function add(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Returns all messages.
     *
     * @return Message[]
     */
    public function all(): array
    {
        return $this->messages;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return array_values($this->messages);
    }

    #[Override]
    public function count(): int
    {
        return count($this->messages);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }
}
