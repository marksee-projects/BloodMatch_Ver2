<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
ini_set('display_errors','0');ini_set('log_errors','0');
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\{DonationFollowupService,AuthService};
$options=getopt('',['target:','run','scheduled']);$run=isset($options['run'])||isset($options['scheduled']);$target=$options['target']??'application';$lock=null;
$result=['writes'=>$run,'smtp'=>false,'status'=>'failed','exit_code'=>1];
try {
    if (isset($options['scheduled']) && $target!=='application') throw new LogicException('Scheduled target must be application.');
    $p=operationalTarget($target);$result['target']=$p->query('SELECT DATABASE() AS database_name,@@port AS port')->fetch();
    $now=AuthService::nowUtc();$result['candidate_count']=count(DonationFollowupService::candidates($now));
    if (!$run) { $result['status']='review';$result['exit_code']=0; }
    else {
        if (Env::get('DONATION_FOLLOWUP_ENABLED','0')!=='1') throw new LogicException('Donation follow-up is disabled.');
        $dir=dirname(__DIR__).'/logs';if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new LogicException('Logging unavailable.');
        $logPath=$dir.'/donation-followup-'.$target.'.jsonl';
        $probe=fopen($logPath,'ab');if (!$probe) throw new LogicException('Logging unavailable.');fclose($probe);
        $lock=fopen($dir.'/donation-followup-'.$target.'.lock','c');
        if (!$lock) throw new LogicException('Lock unavailable.');
        if (!flock($lock,LOCK_EX|LOCK_NB)) { $result['status']='overlap_skipped';$result['exit_code']=0; }
        else { $result['counts']=DonationFollowupService::process($now);$result['status']='completed';$result['exit_code']=0; }
    }
} catch(Throwable $e) { $result['status']='failed'; }
finally {
    $result['utc']=gmdate('Y-m-d\TH:i:s\Z');
    if ($run && isset($logPath) && @file_put_contents($logPath,json_encode($result).PHP_EOL,FILE_APPEND|LOCK_EX)===false) $result['exit_code']=1;
    if (is_resource($lock)) { flock($lock,LOCK_UN);fclose($lock); }
    echo json_encode($result).PHP_EOL;
}
exit($result['exit_code']);
