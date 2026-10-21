<?php
// Router exclusively for the isolated demo. No changes to normal runtime.
require_once dirname(__DIR__).'/backend/src/autoload.php';
\BloodMatch\Config\Env::load(dirname(__DIR__).'/.env');
$_ENV['MAIL_USER']='';$_ENV['MAIL_PASS']='';
$p=\BloodMatch\Config\Database::pdo();$t=$p->query('SELECT DATABASE() db,@@port port')->fetch();
if($t['db']!=='bloodmatch_demo_20261022_7c91'||(int)$t['port']!==3306){http_response_code(503);exit('Demo target refused.');}
if(\BloodMatch\Config\Env::get('MAIL_HOST')!=='127.0.0.1'||\BloodMatch\Config\Env::get('MAIL_PORT')!=='1026'){http_response_code(503);exit('Local capture required.');}
$_SERVER['SCRIPT_NAME']='/index.php';
require dirname(__DIR__).'/backend/public/index.php';
