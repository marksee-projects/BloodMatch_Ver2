<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;
use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\AuthThrottleRepository;
use BloodMatch\Services\{ChatConsentService,ChatPolicy,AuthService};
use BloodMatch\Utils\{Request,Response};
use Throwable;
final class ChatRequestsController
{
    private function run(callable $fn): void {
        $actor=AuthMiddleware::requireActiveUser('chat.requests');
        if (!ChatPolicy::isEligible($actor)) { Response::error('Verification required.',403);return; }
        try { Response::success($fn($actor)); } catch(Throwable $e) { $code=(int)$e->getCode();Response::error($e instanceof \PDOException?'Message requests are temporarily unavailable.':$e->getMessage(),$code>=400&&$code<=499?$code:503); }
    }
    public function index(): void { $this->run(fn($a)=>['requests'=>ChatConsentService::listing((int)$a['id'])]); }
    public function create(): void { $this->run(function($a){
        if (!(new AuthThrottleRepository())->hitAndCheckRateLimitSeconds('chat_request:'.$a['id'],10,3600,AuthService::nowUtc())) throw new \RuntimeException('Too many message requests. Try again later.',429);
        $r=ChatConsentService::request($a,Request::int('recipient_id')??0);
        return ['request'=>$r];
    }); }
    public function decide(array $params): void { $this->run(fn($a)=>ChatConsentService::decide($a,(int)$params['id'],Request::str('action')??'')); }
}
