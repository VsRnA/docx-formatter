<?php

namespace App\Domain\Document\ValueObject;

final readonly class DocumentMeta
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function with(string $key, mixed $value): self
    {
        $data = $this->data;
        $data[$key] = $value;

        return new self($data);
    }

    public function merge(array $patch): self
    {
        return new self(array_merge($this->data, $patch));
    }

    public function shouldTranslate(): bool
    {
        return ($this->data['translate'] ?? true) !== false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
