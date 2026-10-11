<?php
declare(strict_types=1);
namespace BloodMatch\Controllers\Officer;
/** Legacy entry point; canonical global staff workflow. */
final class OfficerVerificationController
{
    public function queue(): void { (new \BloodMatch\Controllers\Admin\MembershipVerificationController())->queue(); }
    public function detail(array $params): void { (new \BloodMatch\Controllers\Admin\MembershipVerificationController())->detail($params); }
    public function decide(array $params): void { (new \BloodMatch\Controllers\Admin\MembershipVerificationController())->decide($params); }
    public function adminQueue(): void { $this->queue(); }
    public function adminDetail(array $params): void { $this->detail($params); }
    public function adminDecide(array $params): void { $this->decide($params); }
}
