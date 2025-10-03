<?php

declare(strict_types=1);

if (!function_exists('stripNonDigits')) {
    function stripNonDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}

if (!function_exists('normalizeDDD')) {
    function normalizeDDD(string $value): string
    {
        $digits = stripNonDigits($value);
        if ($digits === '') {
            throw new InvalidArgumentException('Informe o DDD com exatamente dois dígitos.');
        }

        if (strlen($digits) !== 2) {
            throw new InvalidArgumentException('O DDD deve conter exatamente 2 dígitos.');
        }

        return $digits;
    }
}

if (!function_exists('limitPhoneDigits')) {
    function limitPhoneDigits(string $digits): string
    {
        $trimmed = ltrim($digits, '0');

        if (strlen($trimmed) > 11 && strncmp($trimmed, '55', 2) === 0) {
            $trimmed = substr($trimmed, 2);
        }

        if (strlen($trimmed) > 11) {
            $trimmed = substr($trimmed, -11);
        }

        return $trimmed;
    }
}

if (!function_exists('normalizePhone')) {
    function normalizePhone(string $value): string
    {
        $digits = stripNonDigits($value);
        if ($digits === '') {
            throw new InvalidArgumentException('Informe um número de telefone válido.');
        }

        $digits = limitPhoneDigits($digits);

        if (strlen($digits) > 9) {
            $digits = substr($digits, 2);
        }

        $length = strlen($digits);
        if ($length < 8 || $length > 11) {
            throw new InvalidArgumentException('O telefone deve conter entre 8 e 11 dígitos.');
        }

        if ($length === 11 && $digits[0] !== '9') {
            throw new InvalidArgumentException('Telefones com 11 dígitos devem iniciar com 9.');
        }

        return $digits;
    }
}

if (!function_exists('extractPhoneParts')) {
    function extractPhoneParts(string $value): array
    {
        $digits = stripNonDigits($value);
        if ($digits === '') {
            return ['ddd' => null, 'phone' => null];
        }

        $digits = limitPhoneDigits($digits);

        if (strlen($digits) < 10) {
            throw new InvalidArgumentException('Informe o telefone com DDD (ex.: 11 91234-5678).');
        }

        $dddDigits = substr($digits, 0, 2);
        $phoneDigits = substr($digits, 2);

        $ddd = normalizeDDD($dddDigits);
        $phone = normalizePhone($phoneDigits);

        return ['ddd' => $ddd, 'phone' => $phone];
    }
}

