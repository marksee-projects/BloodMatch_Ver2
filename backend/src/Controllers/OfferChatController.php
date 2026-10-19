<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;
use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Services\OfferChatService;
use BloodMatch\Utils\{Request,Response};
use RuntimeException;
final class OfferChatController {
    private function respond(callable $fn): void { try { Response::success($fn()); } catch(\Throwable $e) { $c=(int)$e->getCode();Response::error($e instanceof RuntimeException && !$e instanceof \PDOException?$e->getMessage():'Donation coordination is temporarily unavailable.',$c>=400&&$c<=599?$c:503); } }
    public function offers(array $p): void { $a=AuthMiddleware::requireActiveUser('offer.list');$this->respond(fn()=>OfferChatService::offers($a,(int)$p['id'])); }
    public function accept(array $p): void { $a=AuthMiddleware::requireActiveUser('offer.accept');$this->respond(fn()=>OfferChatService::accept($a,(int)$p['id'],Request::int('expected_cycle')??-1)); }
    public function ownOffer(array $p): void { $a=AuthMiddleware::requireActiveUser('offer.own');$this->respond(fn()=>OfferChatService::ownOffer($a,(int)$p['id'])); }
    public function contexts(array $p): void { $a=AuthMiddleware::requireActiveUser('offer.contexts');$this->respond(fn()=>['contexts'=>OfferChatService::contexts($a,(int)$p['id'])]); }
    public function signal(array $p): void { $a=AuthMiddleware::requireActiveUser('offer.signal');$this->respond(fn()=>OfferChatService::signal($a,(int)$p['id'],Request::str('action')??'',Request::int('expected_version')??0)); }
}
