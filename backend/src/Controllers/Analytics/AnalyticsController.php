<?php

declare(strict_types=1);

namespace BloodMatch\Controllers\Analytics;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\AnalyticsRepository;
use BloodMatch\Utils\Request;
use BloodMatch\Utils\Response;

final class AnalyticsController
{
    private const ENDPOINT = 'analytics.summary';

    public function summary(): void
    {
        Response::success($this->data());
    }

    public function csv(): void
    {
        $csv=\BloodMatch\Utils\AggregateCsv::render($this->data());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bloodmatch-aggregate-analytics.csv"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
    }

    public function confirmations(): void
    {
        try { Response::success($this->confirmationData()); }
        catch (\BloodMatch\Services\Exceptions\ValidationException $e) { Response::error($e->getMessage(), 400, $e->errors()); }
    }

    public function confirmationsCsv(): void
    {
        try { $csv = \BloodMatch\Utils\ConfirmedDonationSummary::csv($this->confirmationData()); }
        catch (\BloodMatch\Services\Exceptions\ValidationException $e) { Response::error($e->getMessage(), 400, $e->errors()); return; }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bloodmatch-confirmed-donation-summary.csv"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
    }

    private function confirmationData(): array
    {
        AuthMiddleware::requireRoles(['admin'], 'analytics.confirmations');
        $range = \BloodMatch\Utils\ConfirmedDonationSummary::range(Request::str('date_from'), Request::str('date_to'));
        $chapter = Request::str('chapter_id');
        if ($chapter !== null && (!ctype_digit($chapter) || (int)$chapter < 1 || (int)$chapter > 255)) {
            throw new \BloodMatch\Services\Exceptions\ValidationException(['chapter_id'=>['Choose a valid chapter scope.']]);
        }
        return (new AnalyticsRepository())->confirmedDonationSummary($range, $chapter === null ? null : (int)$chapter);
    }

    private function data(): array
    {
        $actor = AuthMiddleware::requireRoles(['admin'], self::ENDPOINT);

        $scopedChapterId = null;

        $filters = [
            'chapter_id' => $scopedChapterId ?? Request::int('chapter_id'),
            'date_from' => Request::str('date_from'),
            'date_to' => Request::str('date_to'),
        ];

        $data = (new AnalyticsRepository())->getAnalyticsSummary($filters, $scopedChapterId);

        return $data;
    }
}
