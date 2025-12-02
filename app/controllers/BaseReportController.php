<?php

abstract class BaseReportController
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    protected function startSessionAndAuth(array $roles): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_once __DIR__ . '/../../config.php';
        require_once __DIR__ . '/../../auth_check.php';
        require_once __DIR__ . '/../../access_control.php';

        require_permission($roles);
    }

    protected function sanitizeDateFilters(): array
    {
        $currentMonthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $currentMonthEnd = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');

        $startDate = $currentMonthStart;
        $endDate = $currentMonthEnd;

        $inputStartDate = $_GET['start_date'] ?? null;
        $inputEndDate = $_GET['end_date'] ?? null;

        if ($inputStartDate !== null && $this->isValidDate($inputStartDate)) {
            $startDate = $inputStartDate;
        }

        if ($inputEndDate !== null && $this->isValidDate($inputEndDate)) {
            $endDate = $inputEndDate;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $dateTime !== false && $dateTime->format('Y-m-d') === $date;
    }
}
