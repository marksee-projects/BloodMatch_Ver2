<?php
/** Preserve copied TIMESTAMP instants explicitly in UTC; isolated rows only. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\{Env,Database};
Env::load(dirname(__DIR__).'/.env');$_ENV['DB_NAME']='bloodmatch_demo_20261022_7c91';$_ENV['DB_PORT']='3306';
$dir=dirname(__DIR__).'/backend/storage/maintenance/DEMO_20261022_7C91';$b=json_decode(file_get_contents($dir.'/baseline.json'),true);$p=Database::pdo();if($p->query('SELECT DATABASE()')->fetchColumn()!==$b['database'])throw new RuntimeException('Target mismatch');
$p->beginTransaction();try{foreach($b['tables'] as $t=>$v){if(!$v['rows'])continue;$schema=$p->query('SHOW COLUMNS FROM `'.$t.'`')->fetchAll();$pk=array_column(array_filter($schema,fn($c)=>$c['Key']==='PRI'),'Field');$timestamp=array_column(array_filter($schema,fn($c)=>str_starts_with($c['Type'],'timestamp')),'Field');if(!$timestamp||!$pk)continue;
 foreach($v['rows'] as $r){$row=array_combine($v['columns'],$r);$fields=$timestamp;if($t==='users'&&in_array((int)$row['id'],[69,85],true))$fields=array_values(array_diff($fields,['updated_at']));$set=implode(',',array_map(fn($c)=>'`'.$c.'`=?',$fields));if(in_array('updated_at',$timestamp,true)&&!in_array('updated_at',$fields,true))$set.=',updated_at=updated_at';$s=$p->prepare('UPDATE `'.$t.'` SET '.$set.' WHERE '.implode(' AND ',array_map(fn($c)=>'`'.$c.'`=?',$pk)));$s->execute(array_map(fn($c)=>$row[$c],array_merge($fields,$pk)));}
 }$p->commit();echo "Copied reference/account timestamps now preserve original UTC instants; source and credentials untouched.\n";}catch(Throwable $e){$p->rollBack();throw $e;}
