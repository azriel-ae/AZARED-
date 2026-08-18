<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * AZARED - minimal server-side validation helper.
 * All validation happens server-side; never trust client-side checks.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = "{$label} wajib diisi.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (isset($this->errors[$field])) {
            return $this;
        }
        if (mb_strlen($value) < $min) {
            $this->errors[$field] = "{$label} minimal {$min} karakter.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (isset($this->errors[$field])) {
            return $this;
        }
        if (mb_strlen($value) > $max) {
            $this->errors[$field] = "{$label} maksimal {$max} karakter.";
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value === '' || isset($this->errors[$field])) {
            return $this;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} tidak valid.";
        }
        return $this;
    }

    public function alphaNumericUnderscore(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (isset($this->errors[$field])) {
            return $this;
        }
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $value)) {
            $this->errors[$field] = "{$label} hanya boleh huruf, angka, titik, dan underscore.";
        }
        return $this;
    }

    public function matches(string $field, string $otherField, string $label): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        if (($this->data[$field] ?? null) !== ($this->data[$otherField] ?? null)) {
            $this->errors[$field] = "{$label} tidak sama.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if (isset($this->errors[$field])) {
            return $this;
        }
        if ($value !== null && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "{$label} tidak valid.";
        }
        return $this;
    }

    public function strongPassword(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (isset($this->errors[$field])) {
            return $this;
        }
        if (mb_strlen($value) < 8 || !preg_match('/[A-Z]/', $value) || !preg_match('/[0-9]/', $value)) {
            $this->errors[$field] = "{$label} minimal 8 karakter, mengandung huruf besar dan angka.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
