<?php
declare(strict_types=1);
// auto_prepend_file for every test process, including parallel workers.
foreach(['BLOODMATCH_TEST_ISOLATION'=>'1','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_NAME'=>'bloodmatch_test','TEST_DB_NAME'=>'bloodmatch_test','TEST_DB_PORT'=>'3306','MAIL_HOST'=>'mock','MAIL_PORT'=>'1','MAIL_USER'=>'','MAIL_PASS'=>'','APP_SECURE_COOKIES'=>'false'] as $key=>$value){putenv($key.'='.$value);$_ENV[$key]=$value;}
