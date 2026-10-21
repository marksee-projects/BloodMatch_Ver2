<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Services\HospitalLocationService;
use BloodMatch\Utils\Response;

final class HospitalController
{
    public function index(): void
    {
        $rows = HospitalLocationService::directory();
        Response::success(['hospitals' => $rows]);
    }
}
