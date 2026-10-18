<?php
declare(strict_types=1);
// Isolated activation: membership decisions only; no Emergency mail or backlog.
define('BM_MEMBERSHIP_EMAIL_ONLY', true);
require __DIR__.'/run_application_email.php';
