<?php
declare(strict_types=1);

namespace App;

/** 입력 오류 묶음. 화면에서 칸마다 오류 문구를 보여 줄 때 쓴다. */
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
