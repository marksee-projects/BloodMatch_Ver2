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
        $actor = AuthMiddleware::requireRoles(['admin', 'officer', 'member'], self::ENDPOINT);

        $scopedChapterId = null;
        if ($actor['role'] === 'officer' || $actor['role'] === 'member') {
            $ownChapter = $actor['chapter_id'] === null ? null : (int) $actor['chapter_id'];
            if ($ownChapter === null) {
                Response::error('Assign a chapter to your account to view chapter demand.', 409);
                return;
            }

            $requestedChapter = Request::int('chapter_id');
            if ($requestedChapter !== null && $requestedChapter !== $ownChapter) {
                Response::error('You can only view aggregated demand for your own chapter.', 403);
                return;
            }

            $scopedChapterId = $ownChapter;
        }

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
