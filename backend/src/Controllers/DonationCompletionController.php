<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;

use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Repositories\DonationWorkflowRepository;
use BloodMatch\Services\{DonationCompletionService,DonationCompletionPolicy};
use BloodMatch\Services\Exceptions\ValidationException;
use BloodMatch\Utils\{Request,Response};
use RuntimeException;

final class DonationCompletionController
{
    private function respond(callable $work): void
    {
        try { Response::success($work()); }
        catch(ValidationException $e) { Response::error($e->getMessage(),400,$e->errors()); }
        catch(\Throwable $e) {
            $c=(int)$e->getCode();
            Response::error($e instanceof RuntimeException && !($e instanceof \PDOException)?$e->getMessage():'Donation reports are temporarily unavailable.', $c>=400&&$c<=599?$c:503);
        }
    }

    public function index(): void
    {
        $actor=AuthMiddleware::requireActiveUser('donation.completion.list');
        $adminQueue=Request::str('scope')==='queue';
        if ($actor['role']==='admin' || $adminQueue) $actor=AuthMiddleware::requireRoles(['admin'],'donation.completion.list');
        $this->respond(function() use($actor,$adminQueue) {
            return ['reports'=>DonationWorkflowRepository::listFor($actor,$adminQueue),'date_rules_approved'=>DonationCompletionPolicy::DATE_RULES_APPROVED];
        });
    }

    public function submit(): void
    {
        $actor=AuthMiddleware::requireRoles(['member'],'donation.completion.submit');
        $this->respond(function() use($actor) {
            $body=$_POST?:Request::json();$proofs=\BloodMatch\Services\DonationProofService::uploads($actor);
            try {
                $report=(new DonationCompletionService())->submit($actor,Request::int('match_id',$body)??0,$body['occurred_at']??null,Request::str('note',$body),$proofs,Request::int('context_id',$body));
                if (!($report['changed']??false)) \BloodMatch\Services\DonationProofService::discard($proofs);
                return ['report'=>$report];
            } catch(\Throwable $e) { \BloodMatch\Services\DonationProofService::discard($proofs);throw $e; }
        });
    }

    public function acknowledge(array $params): void
    {
        $actor=AuthMiddleware::requireActiveUser('donation.completion.acknowledge');
        $this->respond(fn()=>['result'=>(new DonationCompletionService())->acknowledge($actor,(int)$params['id'],Request::int('expected_version')??0,Request::str('response')??'',Request::str('note'))]);
    }

    public function review(array $params): void
    {
        $actor=AuthMiddleware::requireRoles(['admin'],'donation.completion.review');
        $this->respond(fn()=>['result'=>(new DonationCompletionService())->review($actor,(int)$params['id'],Request::int('expected_version')??0,Request::str('action')??'',Request::json()['basis']??null)]);
    }

    public function clarification(array $params): void
    {
        $actor=AuthMiddleware::requireActiveUser('donation.completion.clarification');
        $this->respond(fn()=>['result'=>(new DonationCompletionService())->clarify($actor,(int)$params['id'],Request::int('expected_version')??0,Request::str('note')??'')]);
    }

    public function revise(array $params): void
    {
        $actor=AuthMiddleware::requireRoles(['member'],'donation.completion.revise');
        $this->respond(function() use($actor,$params) {
            $body=$_POST?:Request::json();$proofs=\BloodMatch\Services\DonationProofService::uploads($actor);
            try { return ['result'=>(new DonationCompletionService())->revise($actor,(int)$params['id'],Request::int('expected_version',$body)??0,$body['occurred_at']??null,Request::str('note',$body)??'',$proofs)]; }
            catch(\Throwable $e) { \BloodMatch\Services\DonationProofService::discard($proofs);throw $e; }
        });
    }

    public function releaseHold(array $params): void
    {
        $actor=AuthMiddleware::requireRoles(['admin'],'donation.completion.release_hold');
        $this->respond(fn()=>['result'=>(new DonationCompletionService())->releaseHold($actor,(int)$params['id'],Request::int('expected_version')??0,Request::json()['basis']??null,(Request::json()['no_donation_occurred']??false)===true)]);
    }

    public function events(array $params): void
    {
        $actor=AuthMiddleware::requireActiveUser('donation.completion.events');
        $this->respond(fn()=>['events'=>(new DonationCompletionService())->events($actor,(int)$params['id'])]);
    }
}
