<?php
declare(strict_types=1);

namespace App;

/** 입력값 검사 도우미. 화면에서 막아도 서버에서 반드시 다시 검사한다. */
final class Validator
{
    private array $errors = [];

    public function required(array $data, string $field, string $label): self
    {
        if (trim((string) ($data[$field] ?? '')) === '') {
            $this->errors[$field] = "{$label}을(를) 입력하세요.";
        }
        return $this;
    }

    public function maxLen(array $data, string $field, string $label, int $max): self
    {
        if (mb_strlen((string) ($data[$field] ?? '')) > $max) {
            $this->errors[$field] = "{$label}은(는) {$max}자 이하로 입력하세요.";
        }
        return $this;
    }

    public function in(array $data, string $field, string $label, array $allowed): self
    {
        if (!in_array($data[$field] ?? null, $allowed, true)) {
            $this->errors[$field] = "{$label} 값이 올바르지 않습니다.";
        }
        return $this;
    }

    public function date(array $data, string $field, string $label): self
    {
        $v = (string) ($data[$field] ?? '');
        if ($v !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $v);
            if ($d === false || $d->format('Y-m-d') !== $v) {
                $this->errors[$field] = "{$label}은(는) YYYY-MM-DD 형식으로 입력하세요.";
            }
        }
        return $this;
    }

    public function ip(array $data, string $field, string $label): self
    {
        $v = (string) ($data[$field] ?? '');
        if ($v !== '' && filter_var($v, FILTER_VALIDATE_IP) === false) {
            $this->errors[$field] = "{$label} 형식이 올바르지 않습니다.";
        }
        return $this;
    }

    public function pattern(array $data, string $field, string $label, string $regex, string $hint): self
    {
        $v = (string) ($data[$field] ?? '');
        if ($v !== '' && !preg_match($regex, $v)) {
            $this->errors[$field] = "{$label} 형식이 올바르지 않습니다. ({$hint})";
        }
        return $this;
    }

    public function add(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }
}
