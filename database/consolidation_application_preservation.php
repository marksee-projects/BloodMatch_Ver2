<?php
declare(strict_types=1);
// Application rollout evidence only: metadata and aggregate hashes, never row contents.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';require __DIR__.'/donation_consolidation_support.php';
try {
    $o=getopt('',['snapshot:','verify:']);$p=consolidationTarget('application');
    if(isset($o['snapshot'])) {
        $tables=$p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME<>'schema_migrations' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        $state=['database'=>'bloodmatch','port'=>3306,'tables'=>[]];
        foreach($tables as $table){
            $s=$p->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$s->execute([$table]);$columns=$s->fetchAll(PDO::FETCH_COLUMN);
            $s=$p->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION");$s->execute([$table]);$keys=$s->fetchAll(PDO::FETCH_COLUMN);if(!$keys)throw new LogicException('Table has no primary key.');
            $state['tables'][$table]=['columns'=>$columns,'keys'=>$keys]+preserveRows($p,$table,$columns,$keys);
        }
        file_put_contents($o['snapshot'],json_encode($state,JSON_THROW_ON_ERROR));
        echo json_encode(['database'=>'bloodmatch','port'=>3306,'original_tables_snapshotted'=>count($tables),'status'=>'saved_hashes_only']),PHP_EOL;
    }elseif(isset($o['verify'])) {
        $state=json_decode(file_get_contents($o['verify']),true,512,JSON_THROW_ON_ERROR);if($state['database']!=='bloodmatch'||$state['port']!==3306)throw new LogicException('Snapshot target mismatch.');
        $mismatches=[];foreach($state['tables'] as $table=>$before){$after=preserveRows($p,$table,$before['columns'],$before['keys']);if($after['rows']!==$before['rows']||$after['sha256']!==$before['sha256'])$mismatches[]=$table;}
        if($mismatches)throw new LogicException('Preservation mismatch in tables: '.implode(',',$mismatches));
        echo json_encode(['database'=>'bloodmatch','port'=>3306,'original_tables_verified'=>count($state['tables']),'mismatches'=>0,'status'=>'preserved']),PHP_EOL;
    }else throw new LogicException('Choose snapshot or verify.');
}catch(Throwable $e){fwrite(STDERR,'Preservation stopped: '.$e->getMessage().PHP_EOL);exit(1);}
function preserveRows(PDO $p,string $table,array $columns,array $keys):array {
    foreach(array_merge([$table],$columns,$keys) as $identifier)if(!preg_match('/^[a-z_]+$/D',$identifier))throw new LogicException('Invalid metadata identifier.');
    $quote=fn($s)=>'`'.$s.'`';$s=$p->query('SELECT '.implode(',',array_map($quote,$columns)).' FROM '.$quote($table).' ORDER BY '.implode(',',array_map($quote,$keys)));
    $hash=hash_init('sha256');$rows=0;while($r=$s->fetch(PDO::FETCH_ASSOC)){hash_update($hash,json_encode($r,JSON_THROW_ON_ERROR)."\n");$rows++;}return ['rows'=>$rows,'sha256'=>hash_final($hash)];
}
