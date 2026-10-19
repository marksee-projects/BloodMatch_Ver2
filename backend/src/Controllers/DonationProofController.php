<?php
declare(strict_types=1);
namespace BloodMatch\Controllers;
use BloodMatch\Middleware\AuthMiddleware;
use BloodMatch\Services\{DonationProofService,DonationCorrectionService};
use BloodMatch\Utils\{Request,Response};
use RuntimeException;
final class DonationProofController {
    private function respond(callable $fn): void { try { Response::success($fn()); } catch(\Throwable $e) { $c=(int)$e->getCode();Response::error($e instanceof RuntimeException && !$e instanceof \PDOException?$e->getMessage():'Private donation evidence is unavailable.',$c>=400&&$c<=599?$c:503); } }
    public function listing(array $p): void { $a=AuthMiddleware::requireActiveUser('donation.proof.list');$this->respond(fn()=>['evidence'=>DonationProofService::listing($a,(int)$p['id'])]); }
    public function add(array $p): void {
        $a=AuthMiddleware::requireActiveUser('donation.proof.add');$this->respond(function() use($a,$p) {
            $files=DonationProofService::uploads($a);
            try {return ['result'=>DonationProofService::add($a,(int)$p['id'],Request::int('expected_version',$_POST)??0,$files)];}
            catch(\Throwable $e) {DonationProofService::discard($files);throw $e;}
        });
    }
    public function file(array $p): void {
        $a=AuthMiddleware::requireActiveUser('donation.proof.file');
        try {$file=DonationProofService::file($a,(int)$p['id']);}
        catch(\Throwable $e) { $c=(int)$e->getCode();Response::error('Private proof is unavailable.',$c>=400&&$c<=599?$c:503);return; }
        header('Content-Type: '.$file['mime_type']);header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="donation-proof"');echo $file['bytes'];
    }
    public function correction(array $p): void { $a=AuthMiddleware::requireActiveUser('donation.correction');$this->respond(fn()=>['result'=>DonationCorrectionService::request($a,(int)$p['id'],Request::int('expected_version')??0,Request::str('reason')??'')]); }
    public function corrections(array $p): void { $a=AuthMiddleware::requireActiveUser('donation.correction.list');$this->respond(fn()=>['corrections'=>DonationCorrectionService::listing($a,(int)$p['id'])]); }
    public function resolve(array $p): void { $a=AuthMiddleware::requireRoles(['admin'],'donation.correction.resolve');$this->respond(fn()=>['result'=>DonationCorrectionService::resolve($a,(int)$p['id'],Request::int('correction_id')??0,Request::int('expected_version')??0,Request::str('basis')??'')]); }
}
