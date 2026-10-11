<?php

namespace PostRun;

use InvalidArgumentException;

/**
 * Sending priority of a message.
 *
 * When a provider is at its rate limit, PostRun sends the highest priority
 * waiting message first. Messages without a priority use the domain's
 * default priority.
 */
enum Priority: string
{
    /**
     * Mail that must arrive quickly: sign-ups, password resets, receipts.
     */
    case High = 'high';

    case Normal = 'normal';

    /**
     * Mail that can wait: reports, digests, newsletters.
     */
    case Low = 'low';

    /**
     * Resolve a priority from an enum case or its name ("high", "normal", "low").
     *
     * @throws InvalidArgumentException When the value is not a known priority.
     */
    public static function fromValue(self|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(strtolower(trim($value)))
            ?? throw new InvalidArgumentException(sprintf(
                'Invalid PostRun priority "%s". Use one of: %s.',
                $value,
                implode(', ', array_map(fn (self $case): string => $case->value, self::cases())),
            ));
    }
}
