<?php
/** Arrange only phpMyAdmin presentation metadata; application tables are read-only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/donation_consolidation_support.php';
$p = consolidationTarget('application');
$apply = in_array('--apply', $argv, true);
$columns = $p->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bloodmatch' ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll();
$schema = [];
foreach ($columns as $c) { $schema[$c['TABLE_NAME']][] = $c; }
$lanes = [
    'Accounts and verification' => ['member_documents','verification_decisions','account_closures','audit_log','pending_registrations','auth_throttle'],
    'Chat and moderation' => ['chat_requests','conversations','conversation_participants','messages','chat_reports','chat_report_evidence','user_presence'],
    'Members and locations' => ['users','chapters','bataan_locations','hospitals','donor_response_state'],
    'Requests and coordination' => ['blood_requests','matches','donation_chat_contexts','donation_coordination_events','compatibility_pairs'],
    'Donation completion' => ['donation_reports','donation_report_evidence','donation_report_events','donation_correction_requests'],
    'Notifications and delivery' => ['donor_opportunities','notifications','email_outbox','email_attempts','email_donor_reservations','system_settings','schema_migrations'],
];
$planned = array_merge(...array_values($lanes));
if (count($planned)!==count(array_unique($planned)) || array_diff(array_keys($schema),$planned) || array_diff($planned,array_keys($schema))) {
    throw new LogicException('Live table inventory differs from the reviewed 34-table layout.');
}
$positions = []; $x = 45; $height = 0; $headings = [];
// Use the same Arial size as phpMyAdmin's default theme, rather than reserving
// eight pixels for every character (which greatly overestimates narrow text).
$font = 'C:/Windows/Fonts/arial.ttf';
$bold = 'C:/Windows/Fonts/arialbd.ttf';
$measure = static function (string $text, bool $isBold = false) use ($font,$bold): int {
    if (function_exists('imagettfbbox') && is_file($font) && is_file($bold)) {
        $box = imagettfbbox(9.84,0,$isBold?$bold:$font,$text);
        if ($box!==false) { return (int)ceil(max($box[0],$box[2],$box[4],$box[6])-min($box[0],$box[2],$box[4],$box[6])); }
    }
    return (int)ceil(strlen($text)*6.5);
};
$tableWidths = [];
foreach ($schema as $table=>$cols) {
    $w = 62 + $measure('bloodmatch '.$table,true);
    foreach ($cols as $c) { $w = max($w,40+$measure($c['COLUMN_NAME'].' : '.$c['COLUMN_TYPE'])); }
    $tableWidths[$table] = $w;
}
foreach ($lanes as $heading=>$tables) {
    $width = max(array_map(fn($table)=>$tableWidths[$table],$tables));
    $y = 65; $headings[] = [$heading,$x];
    foreach ($tables as $table) {
        $h = 26 + count($schema[$table])*23;
        // Align account and chat dependents toward the central members lane.
        $tx = $x + (in_array($heading,['Accounts and verification','Chat and moderation'],true) ? $width-$tableWidths[$table] : 0);
        $positions[$table] = ['x'=>$tx,'y'=>$y,'width'=>$tableWidths[$table],'height'=>$h];
        $y += $h + 38;
    }
    $height = max($height,$y); $x += $width + 78;
}
// Check rectangles before saving, allowing space for expanded columns and types.
foreach ($positions as $a=>$pa) foreach ($positions as $b=>$pb) {
    if ($a >= $b) { continue; }
    if ($pa['x'] < $pb['x']+$pb['width'] && $pb['x'] < $pa['x']+$pa['width'] && $pa['y'] < $pb['y']+$pb['height'] && $pb['y'] < $pa['y']+$pa['height']) {
        throw new LogicException('Overlapping planned tables.');
    }
}
$root = dirname(__DIR__).'/docs/';
if ($apply) {
    $backup = [
        'database'=>'bloodmatch',
        'pages'=>$p->query("SELECT * FROM phpmyadmin.pma__pdf_pages WHERE db_name='bloodmatch'")->fetchAll(),
        'coordinates'=>$p->query("SELECT * FROM phpmyadmin.pma__table_coords WHERE db_name='bloodmatch'")->fetchAll(),
    ];
    $backupPath = $root.'phpmyadmin_designer_backup_'.date('Ymd_His').'.json';
    if (file_put_contents($backupPath,json_encode($backup,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false) { throw new RuntimeException('Layout backup failed.'); }
    $p->beginTransaction();
    try {
        $page = $p->query("SELECT page_nr FROM phpmyadmin.pma__pdf_pages WHERE db_name='bloodmatch' AND page_descr='bloodmatch' ORDER BY page_nr LIMIT 1")->fetchColumn();
        if ($page===false) {
            $p->exec("INSERT INTO phpmyadmin.pma__pdf_pages (db_name,page_descr) VALUES ('bloodmatch','bloodmatch')");
            $page = $p->lastInsertId();
        }
        $save = $p->prepare("INSERT INTO phpmyadmin.pma__table_coords (db_name,table_name,pdf_page_number,x,y) VALUES ('bloodmatch',?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y)");
        foreach ($positions as $table=>$pos) { $save->execute([$table,$page,$pos['x'],$pos['y']]); }
        $read = $p->prepare("SELECT table_name,x,y FROM phpmyadmin.pma__table_coords WHERE db_name='bloodmatch' AND pdf_page_number=?");
        $read->execute([$page]); $actual = $read->fetchAll();
        if (count($actual)!==count($positions)) { throw new RuntimeException('Saved inventory mismatch.'); }
        foreach ($actual as $row) {
            $pos = $positions[$row['table_name']];
            if ((int)$row['x']!==$pos['x'] || (int)$row['y']!==$pos['y']) { throw new RuntimeException('Saved coordinate mismatch.'); }
        }
        $p->commit();
        echo 'Saved and read back '.count($actual).' table positions on default Designer page '.$page.'. Backup: '.basename($backupPath).PHP_EOL;
    } catch (Throwable $e) { if ($p->inTransaction()) { $p->rollBack(); } throw $e; }
}
// Companion schematic for reviewing the saved positions without browser access.
$e = fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$x.'" height="'.$height.'" viewBox="0 0 '.$x.' '.$height.'"><rect width="100%" height="100%" fill="#fff"/><g font-family="Arial,sans-serif"><text x="70" y="30" font-size="22" font-weight="bold">BloodMatch — phpMyAdmin Designer layout — 34 tables</text>';
foreach ($headings as [$heading,$hx]) { $svg.='<text x="'.$hx.'" y="53" font-size="15" fill="#475569">'.$e($heading).'</text>'; }
$fks = $p->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='bloodmatch' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
foreach ($fks as $i=>$fk) {
    $a = $positions[$fk['TABLE_NAME']]; $b = $positions[$fk['REFERENCED_TABLE_NAME']];
    if ($fk['TABLE_NAME']===$fk['REFERENCED_TABLE_NAME']) { continue; }
    $right = $a['x'] < $b['x'];
    $ax = $a['x']+($right?$a['width']:0); $bx = $b['x']+($right?0:$b['width']);
    $ay = $a['y']+18; $by = $b['y']+18;
    $svg.='<path d="M'.$ax.','.$ay.' C'.(($ax+$bx)/2).','.$ay.' '.(($ax+$bx)/2).','.$by.' '.$bx.','.$by.'" fill="none" stroke="#94a3b8" stroke-opacity=".4"><title>'.$e($fk['TABLE_NAME'].'.'.$fk['COLUMN_NAME'].' → '.$fk['REFERENCED_TABLE_NAME'].'.'.$fk['REFERENCED_COLUMN_NAME']).'</title></path>';
}
foreach ($positions as $table=>$pos) {
    $svg.='<g transform="translate('.$pos['x'].','.$pos['y'].')"><rect width="'.$pos['width'].'" height="'.$pos['height'].'" fill="white" stroke="#94a3b8"/><rect width="'.$pos['width'].'" height="30" fill="#dbeafe"/><text x="10" y="21" font-size="16" font-weight="bold">'.$e($table).'</text>';
    foreach ($schema[$table] as $i=>$c) { $svg.='<text x="10" y="'.(46+$i*23).'" font-size="13">'.$e($c['COLUMN_NAME'].' : '.$c['COLUMN_TYPE']).'</text>'; }
    $svg.='</g>';
}
$svg.='</g></svg>';
file_put_contents($root.'phpmyadmin_designer_layout.svg',$svg);
echo 'Verified layout coverage and non-overlapping planned rectangles. '.($apply?'Applied.':'Dry run; use --apply to save.').PHP_EOL;
echo 'Canvas estimate: '.$x.' x '.$height.' pixels.'.PHP_EOL;
