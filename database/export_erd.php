<?php
/** Read-only schema exporter. Run again after the user-applied maintenance. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
Env::load(dirname(__DIR__).'/.env');
try {
    $args=getopt('',['target:']);$db=$args['target']??'bloodmatch';
    if (!in_array($db,['bloodmatch','bloodmatch_test'],true)) throw new LogicException('Unsupported target.');
    $port=(int)($db==='bloodmatch_test'?Env::get('TEST_DB_PORT','3306'):Env::get('DB_PORT','3306'));
    $p=new PDO('mysql:host='.Env::get('DB_HOST','127.0.0.1').';port='.$port.';dbname='.$db.';charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    if ((int)$p->query('SELECT @@port')->fetchColumn()!==$port) throw new LogicException('Target mismatch.');
    $tables=$p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $fks=$p->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll();
    $schema=[];
    foreach ($tables as $table) {
        $s=$p->prepare('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$s->execute([$table]);$schema[$table]=$s->fetchAll();
    }
    $date=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('j F Y H:i').' Asia/Manila';
    $missing=array_keys(array_filter($schema,fn($columns)=>!array_filter($columns,fn($c)=>$c['COLUMN_KEY']==='PRI')));
    $md="# BloodMatch entity relationship diagram\n\nGenerated from the actual `$db` schema on port $port, $date. This snapshot includes all ".count($tables)." base tables; account count does not change its relationships.\n\n";
    if ($missing) $md.="**Import repair pending:** these tables have no primary key: `".implode('`, `',$missing)."`. Chat foreign keys are also absent in this snapshot. Run the reviewed maintenance and regenerate this file before submitting the final ERD.\n\n";
    $md.="[Open the SVG diagram](erd.svg). PK = primary key; FK = enforced foreign key; UK = single-column unique key. Nullable FK fields permit an absent parent.\n\n```mermaid\nerDiagram\n";
    foreach ($fks as $fk) {
        $column=current(array_filter($schema[$fk['TABLE_NAME']],fn($c)=>$c['COLUMN_NAME']===$fk['COLUMN_NAME']));
        $single=$p->prepare('SELECT COUNT(*) FROM (SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MAX(COLUMN_NAME)=?) x');$single->execute([$fk['TABLE_NAME'],$fk['COLUMN_NAME']]);
        $parent=$column['IS_NULLABLE']==='YES'?'|o':'||';$child=(int)$single->fetchColumn()>0?'o|':'o{';
        $md.=sprintf("    %s %s--%s %s : \"%s\"\n",$fk['REFERENCED_TABLE_NAME'],$parent,$child,$fk['TABLE_NAME'],$fk['COLUMN_NAME']);
    }
    foreach ($schema as $table=>$columns) {
        $md.="    $table {\n";
        foreach ($columns as $c) {
            $flags=[];if($c['COLUMN_KEY']==='PRI')$flags[]='PK';elseif($c['COLUMN_KEY']==='UNI')$flags[]='UK';
            if(array_filter($fks,fn($f)=>$f['TABLE_NAME']===$table && $f['COLUMN_NAME']===$c['COLUMN_NAME']))$flags[]='FK';
            $notes=[];if($c['IS_NULLABLE']==='YES')$notes[]='nullable';if(str_contains($c['EXTRA'],'GENERATED'))$notes[]='generated';
            $md.='        '.strtoupper($c['DATA_TYPE']).' '.$c['COLUMN_NAME'].($flags?' '.implode(',',$flags):'').($notes?' "'.implode('; ',$notes).'"':'')."\n";
        }
        $md.="    }\n";
    }
    $md.="```\n\n## Relationships not enforced by foreign keys\n\n- `notifications.related_type` / `related_id` and `audit_log.target_type` / `target_id` are polymorphic application references.\n- `email_donor_reservations.donor_id` is a deliberate accounting reference without a user FK; cleanup explicitly handles removed donors.\n- `auth_throttle.identifier` contains opaque throttle keys, rather than an enforced user relationship.\n- `compatibility_matrix.allowed_donor_types` stores the fixed reference compatibility list used by the matching service.\n\n## Chat repair target\n\nThe intended relationship is `users -> conversation_participants -> conversations -> messages`, with a separate sender FK from `messages` to `users`. Chat IDs require primary keys and automatic generation; participant pairs require uniqueness. `last_read_at` must be DATETIME(3). The exporter includes these edges only after the actual database has them.\n\nNo tables or columns were removed to simplify this diagram. See [the database review and execution guide](CHAT_DATABASE_NOTIFICATION_HANDOFF.md).\n";
    file_put_contents(dirname(__DIR__).'/docs/erd.md',$md);
    $groups=[['chapters','bataan_locations','hospitals','compatibility_matrix','system_settings'],['users','member_documents','verification_decisions','password_resets','auth_throttle'],['blood_requests','matches','donation_reports','notifications','audit_log'],['conversations','conversation_participants','messages','donor_opportunities','donor_response_state','email_outbox','email_attempts','email_donor_reservations','schema_migrations']];
    $positions=[];$bottom=0;
    foreach($groups as $lane=>$group){$y=130;foreach($group as $table)if(isset($schema[$table])){$height=58+count($schema[$table])*21;$positions[$table]=['x'=>30+$lane*420,'y'=>$y,'h'=>$height];$y+=$height+36;}$bottom=max($bottom,$y);}
    $escape=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_XML1,'UTF-8');
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="1720" height="'.$bottom.'" viewBox="0 0 1720 '.$bottom.'"><title>BloodMatch actual database ERD</title><desc>All actual tables, columns and enforced foreign-key relationships. Tables without primary keys are marked as needing import repair.</desc><rect width="100%" height="100%" fill="#f1f5f9"/><defs><marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8" fill="none" stroke="#64748b"/></marker></defs><g font-family="Arial,sans-serif"><text x="30" y="36" font-size="24" fill="#0f172a">BloodMatch · actual database ERD</text><text x="30" y="64" font-size="14" fill="#475569">'.$escape($db.' · port '.$port.' · '.$date).'</text><text x="30" y="90" font-size="14" fill="#475569">PK primary · UK unique · FK enforced reference · arrows point from child to parent · nullable fields marked ?</text>';
    foreach($fks as $fk)if(isset($positions[$fk['TABLE_NAME']],$positions[$fk['REFERENCED_TABLE_NAME']])){$a=$positions[$fk['TABLE_NAME']];$b=$positions[$fk['REFERENCED_TABLE_NAME']];$ax=$a['x'];$bx=$b['x']+390;$ay=$a['y']+24;$by=$b['y']+24;$mid=($ax+$bx)/2;$svg.='<path d="M'.$ax.','.$ay.' C'.$mid.','.$ay.' '.$mid.','.$by.' '.$bx.','.$by.'" fill="none" stroke="#94a3b8" stroke-width="1.5" marker-end="url(#arrow)"><title>'.$escape($fk['TABLE_NAME'].'.'.$fk['COLUMN_NAME'].' references '.$fk['REFERENCED_TABLE_NAME'].'.'.$fk['REFERENCED_COLUMN_NAME']).'</title></path>';}
    foreach($positions as $table=>$pos){$x=$pos['x'];$y=$pos['y'];$bad=in_array($table,$missing,true);$svg.='<g transform="translate('.$x.','.$y.')"><rect width="390" height="'.$pos['h'].'" rx="10" fill="#fff" stroke="'.($bad?'#c8102e':'#cbd5e1').'"/><text x="14" y="26" font-size="17" font-weight="bold" fill="#0f172a">'.$escape($table).'</text>';if($bad)$svg.='<text x="14" y="45" font-size="12" fill="#c8102e">Import repair needed: no primary key</text>';foreach($schema[$table] as $i=>$c){$flags=$c['COLUMN_KEY']==='PRI'?'PK ':($c['COLUMN_KEY']==='UNI'?'UK ':'');if(array_filter($fks,fn($f)=>$f['TABLE_NAME']===$table&&$f['COLUMN_NAME']===$c['COLUMN_NAME']))$flags.='FK ';$label=$flags.$c['COLUMN_NAME'].($c['IS_NULLABLE']==='YES'?' ?':'');$svg.='<text x="14" y="'.(60+$i*21).'" font-size="12" fill="#334155">'.$escape($label).'<title>'.$escape($c['COLUMN_TYPE'].' '.$c['EXTRA']).'</title></text><text x="310" y="'.(60+$i*21).'" font-size="11" fill="#64748b">'.$escape($c['DATA_TYPE']).'</text>';}$svg.='</g>';}
    $svg.='</g></svg>';file_put_contents(dirname(__DIR__).'/docs/erd.svg',$svg);
    echo 'Generated docs/erd.md and docs/erd.svg from '.$db.'; missing primary keys: '.implode(',',$missing).PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,'STOP: '.$e->getMessage().PHP_EOL);exit(1);}
