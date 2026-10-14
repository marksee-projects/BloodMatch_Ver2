<?php
declare(strict_types=1);
define('BLOODMATCH_REPAIR_FUNCTIONS_ONLY', true);
require dirname(__DIR__).'/database/repair_imported_email_schema.php';
$jobs=[[1,475,'sent'],[2,480,'cancelled'],[3,481,'cancelled'],[4,482,'cancelled'],[5,483,'cancelled'],[6,484,'cancelled'],[7,485,'pending']];
$rows=array_map(fn($j)=>['id'=>$j[0],'notification_id'=>$j[1],'status'=>$j[2],'delivery_key'=>'fixture-'.$j[0],'attempts'=>0],$jobs);
$hash=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
assertReviewedOrphans($rows,$hash);$passed=1;
$variants=[];
$r=$rows;array_pop($r);$variants[]=$r;
$r=$rows;$r[]=$rows[0];$variants[]=$r;
$r=$rows;$r[0]['id']=99;$variants[]=$r;
$r=$rows;$r[0]['notification_id']=99;$variants[]=$r;
$r=$rows;$r[6]['status']='sent';$variants[]=$r;
$r=$rows;$r[0]['delivery_key']='changed';$variants[]=$r;
$r=$rows;$r[0]['attempts']=1;$variants[]=$r;
$variants[]=array_reverse($rows);
foreach($variants as $r){try{assertReviewedOrphans($r,$hash);throw new RuntimeException('Unsafe variation was accepted.');}catch(LogicException $e){$passed++;}}
try{assertReviewedOrphans($rows);throw new RuntimeException('Production fingerprint bypassed.');}catch(LogicException $e){$passed++;}
echo 'Archive exact-record guards: '.$passed." checks passed; no database or email operations.\n";
