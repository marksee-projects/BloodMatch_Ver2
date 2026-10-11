<?php
declare(strict_types=1);
namespace BloodMatch\Controllers\Officer;
/** Legacy URL entry point; canonical global admin authorization is enforced by the delegate. */
final class AuditLogOfficerController
{
    public function index(): void { (new \BloodMatch\Controllers\Admin\AuditLogAdminController())->index(); }
}
