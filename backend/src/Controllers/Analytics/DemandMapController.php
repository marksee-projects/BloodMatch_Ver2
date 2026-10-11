<?php

declare(strict_types=1);

namespace BloodMatch\Controllers\Analytics;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\AnalyticsRepository;
use BloodMatch\Utils\Request;
use BloodMatch\Utils\Response;

final class DemandMapController
{
    private const ENDPOINT = 'analytics.demand_map';

    public function index(): void
    {
        $actor = AuthMiddleware::requireRoles(['admin', 'member'], self::ENDPOINT);

        $scopedChapterId = null;

        $filters = [
            'chapter_id' => $scopedChapterId ?? Request::int('chapter_id'),
            'blood_type' => Request::str('blood_type'),
            'urgency' => Request::str('urgency'),
            'days' => Request::int('days'),
        ];

        $data = (new AnalyticsRepository())->getDemandMap($filters, $scopedChapterId);

        Response::success(['chapters' => $data]);
    }
}
