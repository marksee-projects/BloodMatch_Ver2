<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\EmailTemplate;
$cases = [
    'otp'=>EmailTemplate::otp('012345',600),
    'otp-resend'=>EmailTemplate::otp('654321',600,true),
    'verified'=>EmailTemplate::notifications([['type'=>'verification.decision','title'=>'Your membership has been verified','body'=>'Your membership review is complete.']]),
    'correction'=>EmailTemplate::notifications([['type'=>'verification.decision','title'=>'Account verification rejected','body'=>'The uploaded ID is blurred. Upload a clearer image.']]),
];
$request=['type'=>'match.new','title'=>'Compatible donation opportunity','body'=>'A compatible emergency opportunity is available.','requester_name'=>'Maria Santos','required_blood_type'=>'A-','facility_name'=>'Bataan hospital with a long facility name for wrapping','municipality_name'=>'Balanga','needed_datetime'=>'2026-10-21 07:00:00'];
$cases['emergency-single']=EmailTemplate::notifications([$request]);
$cases['emergency-group']=EmailTemplate::notifications([$request,array_replace($request,['requester_name'=>'Juan Dela Cruz','facility_name'=>'Second hospital','municipality_name'=>null])]);
$cases['escaping']=EmailTemplate::notifications([array_replace($request,['requester_name'=>'<script>Bad name</script>','facility_name'=>'<script>alert("test")</script> & Hospital','body'=>'<img src=x onerror=alert(1)>'])]);
$checks=0;
function check(bool $ok,string $label):void { global $checks; if(!$ok)throw new RuntimeException($label);$checks++; }
foreach($cases as $name=>$template) {
    check(str_contains($template['html'],'BloodMatch'),"$name shared branding");
    check(str_contains($template['html'],'max-width:600px'),"$name fluid wrapper");
    check(!preg_match('/<(a|script|img)\b|https?:\/\//i',$template['html']),"$name no website links or unsafe markup");
    check(!str_contains($template['text'],'<table'),"$name plain text fallback");
}
check(str_contains($cases['otp']['html'],'012345')&&str_contains($cases['otp']['text'],'10 minutes'),'OTP preserves leading zero and actual expiry');
check(str_contains($cases['otp-resend']['text'],'previous code has been replaced'),'resend explains replacement');
check(str_contains($cases['verified']['text'],'account is now verified'),'approved heading');
check(str_contains($cases['correction']['text'],'blurred')&&str_contains($cases['correction']['text'],'Profile > Verification'),'reason and correction steps');
check(str_contains($cases['emergency-group']['html'],'2 emergency requests')&&substr_count($cases['emergency-group']['html'],'<h2 ')==2,'grouped request cards');
check(str_contains($cases['emergency-single']['text'],'3:00 PM PHT (UTC+08:00)'),'deadline timezone conversion');
check(str_contains($cases['emergency-group']['text'],'Not provided'),'missing optional municipality');
check(str_contains($cases['escaping']['html'],'&lt;script&gt;')&&str_contains($cases['escaping']['html'],'&amp; Hospital'),'HTML content escaped');
check(str_contains($cases['emergency-group']['text'],'Requester: Maria Santos') && str_contains($cases['emergency-group']['text'],'Requester: Juan Dela Cruz'),'each grouped card has its requester');
check(str_contains($cases['escaping']['html'],'&lt;script&gt;Bad name&lt;/script&gt;'),'requester name escaped');
check(str_contains($cases['verified']['text'],'enroll as a donor') && str_contains($cases['verified']['text'],'administrator has reviewed'),'approval explains review and donor enrollment');
if(in_array('--previews',$argv,true)) {
    $directory=dirname(__DIR__).'/docs/previews/email-design';
    if(!is_dir($directory))mkdir($directory,0777,true);
    foreach($cases as $name=>$template) {
        file_put_contents("$directory/$name.html",$template['html']);
        file_put_contents("$directory/$name.txt",$template['text']);
    }
}
echo "PASS $checks email presentation checks; no database, SMTP or delivery.\n";
