<?php
/** Run reviewed SELECT queries against a database enforced read-only by Firebird. */
declare(strict_types=1);
$pdo=new PDO(getenv('MRP_DSN'), getenv('MRP_USER') ?: 'SYSDBA', getenv('MRP_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$pdo->query('SELECT MON$READ_ONLY FROM MON$DATABASE')->fetchColumn()!==1)throw new RuntimeException('Writable database refused');
$queries=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$out=[];
foreach($queries as $name=>$sql){
 if(!preg_match('/^SELECT\s/i',trim($sql))) throw new RuntimeException('SELECT required');
 try{
  $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as &$row)foreach($row as &$v){if(is_resource($v))$v=stream_get_contents($v);if(is_string($v))$v=trim($v);}
  unset($row,$v);
  $out[$name]=['sql'=>$sql,'rows'=>$rows];
 }catch(Throwable $e){$out[$name]=['sql'=>$sql,'error'=>$e->getMessage()];}
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),"\n";
