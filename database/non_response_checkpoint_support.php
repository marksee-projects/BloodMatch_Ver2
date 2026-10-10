<?php
declare(strict_types=1);

function nonResponseShape(PDO $pdo): array
{
    $expected=[
        'donor_response_state'=>['user_id'=>['bigint unsigned','NO'],'standby_started_at'=>['datetime(6)','YES'],'consumed_through'=>['datetime(6)','YES']],
        'donor_opportunities'=>['id'=>['bigint unsigned','NO'],'request_id'=>['bigint unsigned','NO'],'donor_id'=>['bigint unsigned','NO'],
            'generation'=>['int unsigned','NO'],'notification_id'=>['bigint unsigned','YES'],'notified_at'=>['datetime(6)','NO'],
            'due_at'=>['datetime(6)','NO'],'status'=>["enum('pending','answered','invalidated','acknowledged','superseded')",'NO']],
    ];
    $indexes=['donor_response_state'=>['PRIMARY'=>[0,'user_id']],
        'donor_opportunities'=>['PRIMARY'=>[0,'id'],'uq_opportunity_generation'=>[0,'request_id,donor_id,generation'],
            'idx_opportunity_due'=>[1,'status,due_at,id'],'idx_opportunity_donor'=>[1,'donor_id,status,due_at']]];
    $foreign=['donor_response_state'=>['user_id'=>['users','id','CASCADE']],
        'donor_opportunities'=>['request_id'=>['blood_requests','id','CASCADE'],'donor_id'=>['users','id','CASCADE'],
            'notification_id'=>['notifications','id','SET NULL']]];
    $result=[];
    foreach ($expected as $table=>$columns) {
        $s=$pdo->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $s->execute([$table]);$meta=$s->fetch(PDO::FETCH_ASSOC);
        if (!$meta) { $result[$table]='missing';continue; }
        if ($meta['ENGINE']!=='InnoDB'||$meta['TABLE_COLLATION']!=='utf8mb4_unicode_ci') { throw new LogicException('Schema drift: '.$table); }
        $s=$pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $s->execute([$table]);$actual=[];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // MySQL/MariaDB integer display widths do not change storage.
            $type=preg_replace('/^(bigint|int)\(\d+\)/','$1',$row['COLUMN_TYPE']);
            $actual[$row['COLUMN_NAME']]=[$type,$row['IS_NULLABLE']];
            if (($row['COLUMN_NAME']==='id')!==str_contains($row['EXTRA'],'auto_increment')) { throw new LogicException('Unexpected identity shape.'); }
            if ($row['COLUMN_NAME']!=='id' && $row['EXTRA']!=='') { throw new LogicException('Unexpected generated/on-update column.'); }
            if ($row['COLUMN_NAME']==='status' && trim((string)$row['COLUMN_DEFAULT'],"'")!=='pending') { throw new LogicException('Unexpected opportunity default.'); }
            if ($row['COLUMN_NAME']!=='status' && !in_array(strtoupper((string)$row['COLUMN_DEFAULT']),['','NULL'],true)) { throw new LogicException('Unexpected column default.'); }
        }
        if ($actual!==$columns) { throw new LogicException('Column drift: '.$table); }
        $s=$pdo->prepare('SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE');
        $s->execute([$table]);$actual=[];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $actual[$row['INDEX_NAME']]=[(int)$row['NON_UNIQUE'],$row['cols']]; }
        $required=$indexes[$table];ksort($actual);ksort($required);
        // InnoDB may add indexes needed by the foreign keys.
        foreach ($required as $name=>$shape) { if (($actual[$name]??null)!==$shape) { throw new LogicException('Index drift: '.$table); } }
        $s=$pdo->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=?');
        $s->execute([$table]);$actual=[];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $actual[$row['COLUMN_NAME']]=[$row['REFERENCED_TABLE_NAME'],$row['REFERENCED_COLUMN_NAME'],$row['DELETE_RULE']]; }
        $required=$foreign[$table];ksort($actual);ksort($required);
        if ($actual!==$required) { throw new LogicException('Foreign key drift: '.$table); }
        $result[$table]='ready';
    }
    return $result;
}
