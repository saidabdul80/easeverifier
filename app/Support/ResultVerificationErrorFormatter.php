<?php

namespace App\Support;

class ResultVerificationErrorFormatter
{
    public static function publicMessage(?string $message, ?string $code = null, ?string $board = null): ?string
    {
        if ($message === null || trim($message) === '') {
            return $message;
        }

        if (self::isInternalSystemMessage($message, $code, $board)) {
            return 'NECO e-Verify could not complete this request. Please try again or contact support if it persists.';
        }

        return $message;
    }

    public static function isInternalSystemMessage(?string $message, ?string $code = null, ?string $board = null): bool
    {
        $message = strtolower(trim((string) $message));
        $code = strtolower(trim((string) $code));
        $board = strtolower(trim((string) $board));

        if ($message === '') {
            return false;
        }

        if (str_contains($message, 'bearer token is not configured')) {
            return true;
        }

        if (
            str_contains($board, 'neco')
            && $code === 'exception'
            && (
                str_contains($message, 'configured')
                || str_contains($message, 'configuration')
                || str_contains($message, 'config')
            )
        ) {
            return true;
        }

        return false;
    }
}
