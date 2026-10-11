<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Services\StaffRoleMigration;

$pdo = null; $locked = false;
try {
    $options = [];
    foreach (array_slice($argv,1) as $arg) {
        if ($arg === '--apply') { $options['apply'] = true; continue; }
        if (!preg_match('/^--(target|expect-sql-sha256|expect-review-sha256|maintenance-confirmed)=(.+)$/D',$arg,$m) || isset($options[$m[1]])) {
            throw new LogicException('Use --target=test|application; review is default. Apply requires both reviewed hashes and --maintenance-confirmed=yes.');
        }
        $options[$m[1]] = $m[2];
    }
    $target = $options['target'] ?? 'test';
    $pdo = operationalTarget($target);
    $name = StaffRoleMigration::NAME;
    $file = __DIR__.'/migrations/'.$name;
    $sql = file_get_contents($file);
    $sqlHash = hash('sha256',$sql);
    $column = $pdo->query("SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetch(PDO::FETCH_ASSOC);
    $old = "enum('member','officer','admin')"; $new = "enum('member','admin')";
    if (!$column || !in_array($column['COLUMN_TYPE'],[$old,$new],true) || $column['IS_NULLABLE'] !== 'NO'
        || trim((string)$column['COLUMN_DEFAULT'],"'") !== 'member') { throw new LogicException('Unexpected users.role shape; refuse conversion.'); }
    $q = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?'); $q->execute([$name]);
    $recorded = (bool)$q->fetchColumn();
    $rows = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $preview = StaffRoleMigration::preview($rows);
    $report = ['database'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),'host'=>'127.0.0.1','port'=>3306,
        'mode'=>empty($options['apply'])?'review':'apply','migration'=>$name,'sql_sha256'=>$sqlHash,
        'role_shape'=>$column['COLUMN_TYPE'],'recorded'=>$recorded,'writes'=>false] + $preview;
    if (!empty($options['apply'])) {
        if ($recorded && $column['COLUMN_TYPE']===$new && $preview['affected_count']===0) {
            $report['status']='already_applied';
        } else {
            if (($options['maintenance-confirmed']??'')!=='yes' || !hash_equals($sqlHash,$options['expect-sql-sha256']??'')
                || !hash_equals($preview['review_sha256'],$options['expect-review-sha256']??'')) {
                throw new LogicException('Maintenance acknowledgment or reviewed hashes missing/stale. Review again; do not guess.');
            }
            if ($recorded) { throw new LogicException('Ledger/role shape disagree; refuse automatic repair.'); }
            foreach (['SAFETY_AUTOMATION_ENABLED','EMAIL_WORKER_ENABLED'] as $flag) {
                if (\BloodMatch\Config\Env::get($flag,'0')!=='0') { throw new LogicException($flag.' must be 0 during conversion.'); }
            }
            $q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute(['bloodmatch.staff_role_consolidation']);
            if ((int)$q->fetchColumn()!==1) { throw new LogicException('Another conversion is running.'); }
            $locked=true;
            // User must stop application writers/tasks. Snapshot is checked again under row locks.
            $pdo->beginTransaction();
            $before=$pdo->query('SELECT * FROM users ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            if (!hash_equals($preview['review_sha256'],StaffRoleMigration::preview($before)['review_sha256'])) {
                throw new LogicException('Accounts changed after review; refuse conversion.');
            }
            $statements=preg_split('/;\s*[\r\n]+/',$sql) ?: [];
            foreach ($statements as $statement) {
                $statement=trim(preg_replace('/^\s*--.*$/m','',$statement)??'');
                if ($statement==='' || in_array($statement,['START TRANSACTION','COMMIT'],true) || str_starts_with($statement,'ALTER TABLE')) { continue; }
                $pdo->exec($statement);
            }
            StaffRoleMigration::assertPreserved($before,$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
            $pdo->commit();
            // MariaDB DDL auto-commits. A crash here may leave converted accounts with old enum/no ledger.
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('member', 'admin') NOT NULL DEFAULT 'member'");
            StaffRoleMigration::assertPreserved($before,$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
            $q=$pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)');$q->execute([$name]);
            $report['writes']=true;$report['status']='applied';$report['role_shape']=$new;$report['recorded']=true;
            $report['remaining_officers']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='officer'")->fetchColumn();
        }
    } else {
        $report += StaffRoleMigration::readiness($column['COLUMN_TYPE'],$recorded,$preview['affected_count']);
    }
    $readiness = StaffRoleMigration::readiness($report['role_shape'],(bool)$report['recorded'],
        (int)($report['remaining_officers']??$preview['affected_count']));
    $report['ready']=$readiness['ready'];
    $report['remaining_officers']=$readiness['remaining_officers'];
    // Never emit full account snapshots, hashes of passwords, OTPs or reset tokens.
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    if ($readiness['status']==='inconsistent') { exit(2); }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,'Staff-role checkpoint failed: '.$e->getMessage().PHP_EOL.'Do not rerun blindly; retain backups. DDL may have committed separately.'.PHP_EOL);
    exit(1);
} finally {
    if ($locked && $pdo instanceof PDO) { $q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['bloodmatch.staff_role_consolidation']); }
}
