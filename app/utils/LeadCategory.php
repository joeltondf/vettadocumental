<?php

class LeadCategory
{
    public const DEFAULT = 'Entrada';

    private const CATEGORIES = [
        self::DEFAULT,
        'Qualificado',
        'Com Orçamento',
        'Em Negociação',
        'Cliente Ativo',
        'Sem Interesse',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::CATEGORIES;
    }

    public static function isValid(?string $category): bool
    {
        if ($category === null) {
            return false;
        }

        return in_array($category, self::CATEGORIES, true);
    }
}
