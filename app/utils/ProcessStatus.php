<?php

class ProcessStatus
{
    public const BUDGET_PENDING = 'Orçamento Pendente';
    public const SERVICE_PENDING = 'Serviço Pendente';
    public const SERVICE_IN_PROGRESS = 'Serviço em Andamento';

    private const NORMALIZATION_MAP = [
        'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ç' => 'c', 'ç' => 'c',
    ];

    public static function normalizeStatus(?string $status): string
    {
        if ($status === null) {
            return '';
        }

        $trimmed = trim($status);
        if ($trimmed === '') {
            return '';
        }

        $lowercase = mb_strtolower($trimmed, 'UTF-8');
        $normalized = strtr($lowercase, self::NORMALIZATION_MAP);

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }
}

class ProcessAlertType
{
    public const BUDGET_PENDING = 'pendencia_orcamento';
    public const SERVICE_PENDING = 'pendencia_servico';
}
