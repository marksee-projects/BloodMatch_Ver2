<?php

declare(strict_types=1);

namespace BloodMatch\Controllers\Auth;

use BloodMatch\Utils\Csrf;
use BloodMatch\Utils\Response;
use Throwable;

final class CsrfController
{
    public function token(): void
    {
        try {
            Response::success(['csrf_token' => Csrf::token()]);
        } catch (Throwable $e) {
            Response::error('Failed to generate CSRF token: ' . $e->getMessage(), 500);
        }
    }
}
