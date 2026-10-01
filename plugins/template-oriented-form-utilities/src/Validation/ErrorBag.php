<?php

namespace TofuPlugin\Validation;

/**
 * Collects failures as `field => [ruleName => message]`.
 *
 * Insertion order follows the order fields appear in the `rules` config, not
 * the order they appear in the submitted data — that is what `firstOfAll()`
 * hands back, and what a client iterating the REST `errors` object relies on
 * to focus the first invalid field.
 */
class ErrorBag
{
    /** @var array<string, array<string, string>> */
    protected array $messages = [];

    public function add(string $field, string $rule, string $message): void
    {
        if (!isset($this->messages[$field])) {
            $this->messages[$field] = [];
        }

        $this->messages[$field][$rule] = $message;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    public function has(string $field): bool
    {
        return isset($this->messages[$field]);
    }

    /**
     * The first message recorded for each field.
     *
     * @return array<string, string>
     */
    public function firstOfAll(): array
    {
        $first = [];

        foreach ($this->messages as $field => $byRule) {
            $first[$field] = reset($byRule) ?: '';
        }

        return $first;
    }

    /**
     * Every message, grouped by field and rule.
     *
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        return $this->messages;
    }
}
