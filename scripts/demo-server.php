<?php
// Local capture interface to the MAIN database, not an isolated database.
require_once dirname(__DIR__).'/backend/src/autoload.php';
\BloodMatch\Config\Env::load(dirname(__DIR__).'/.env');
$_ENV['MAIL_USER']='';$_ENV['MAIL_PASS']='';
$p=\BloodMatch\Config\Database::pdo();$t=$p->query('SELECT DATABASE() db,@@port port')->fetch();
if($t['db']!=='bloodmatch'||(int)$t['port']!==3306){http_response_code(503);exit('Main demo target refused.');}
if(\BloodMatch\Config\Env::get('MAIL_HOST')!=='127.0.0.1'||\BloodMatch\Config\Env::get('MAIL_PORT')!=='1026'){http_response_code(503);exit('Local capture required.');}
$demoPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);$demoMethod=$_SERVER['REQUEST_METHOD']??'GET';
// This helper interface must never broadcast new matches to genuine donors.
// The normal application routes/code are unchanged. Use the bounded CLI helper
// for request creation/material edits while genuine and demo data share the DB.
if(($demoMethod==='POST'&&($demoPath==='/api/requests'||preg_match('~/requests/\d+/(renew|re-match)$~',$demoPath)))||($demoMethod==='PUT'&&preg_match('~^/api/requests/\d+$~',$demoPath))){http_response_code(409);header('Content-Type: application/json');echo json_encode(['success'=>false,'error'=>['message'=>'This main-database demo interface prevents broadcasts to genuine donors. Use the prepared request examples or database/main_demo_request.php for scoped creation/edits.']]);exit;}
$_SERVER['SCRIPT_NAME']='/index.php';
require dirname(__DIR__).'/backend/public/index.php';
