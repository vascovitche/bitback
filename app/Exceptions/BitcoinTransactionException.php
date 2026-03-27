<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BitcoinTransactionException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $status = Response::HTTP_BAD_REQUEST,
        protected array $context = []
    )
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
