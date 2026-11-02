<?php
declare(strict_types=1);
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
final class DbBackup extends BaseCommand
{
    protected $group='ChildCare';protected $name='db:backup';protected $description='Create a compressed MySQL backup and retain the latest 14 copies.';
    public function run(array $params): void
    {
        $config=config('Database')->default;$bin=(string)env('database.mysqldumpPath',env('database.mysqldump','mysqldump'));$folder=WRITEPATH.'backups';if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder)){CLI::error('Cannot create writable/backups.');return;}if(DIRECTORY_SEPARATOR!=='\\'&&!chmod($folder,0700)){CLI::error('Cannot restrict writable/backups permissions.');return;}
        $defaults=tempnam(sys_get_temp_dir(),'cc-mysql-');
        if($defaults===false){CLI::error('Cannot create a temporary database defaults file.');return;}
        $errorFile=tempnam(sys_get_temp_dir(),'cc-mysql-error-');
        if($errorFile===false){@unlink($defaults);CLI::error('Cannot create a temporary error file.');return;}
        $contents="[client]\nuser=".$this->ini((string)$config['username'])."\npassword=".$this->ini((string)$config['password'])."\nhost=".$this->ini((string)$config['hostname'])."\nport=".(int)$config['port']."\n";
        if(file_put_contents($defaults,$contents,LOCK_EX)===false){@unlink($defaults);@unlink($errorFile);CLI::error('Cannot write temporary database defaults.');return;}
        chmod($defaults,0600);chmod($errorFile,0600);
        $target=$folder.'/childcare-'.date('Ymd-His').'.sql.gz';$output=gzopen($target,'wb6');if(!$output){@unlink($defaults);@unlink($errorFile);CLI::error('Cannot write backup output.');return;}if(DIRECTORY_SEPARATOR!=='\\'&&!chmod($target,0600)){gzclose($output);@unlink($target);@unlink($defaults);@unlink($errorFile);CLI::error('Cannot restrict backup file permissions.');return;}
        // Keep stderr out of a pipe: if mysqldump fills that pipe while stdout is
        // being compressed, waiting on stdout first can deadlock the backup job.
        $pipes=[];$process=proc_open([$bin,'--defaults-extra-file='.$defaults,'--single-transaction','--routines','--triggers',(string)$config['database']],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$errorFile,'a']],$pipes,ROOTPATH);
        if(!is_resource($process)){gzclose($output);@unlink($target);@unlink($defaults);@unlink($errorFile);CLI::error('Unable to start mysqldump. Configure database.mysqldump in .env.');return;}fclose($pipes[0]);
        while(!feof($pipes[1])){$chunk=fread($pipes[1],65536);if($chunk!==false&&$chunk!=='')gzwrite($output,$chunk);}fclose($pipes[1]);$status=proc_close($process);gzclose($output);$error=(string)file_get_contents($errorFile);@unlink($defaults);@unlink($errorFile);
        if($status!==0){@unlink($target);CLI::error('mysqldump failed: '.trim((string)$error));return;}
        $files=glob($folder.'/childcare-*.sql.gz')?:[];usort($files,static fn($a,$b)=>filemtime($b)<=>filemtime($a));foreach(array_slice($files,14) as $old)@unlink($old);
        CLI::write('Backup created: '.basename($target));
    }
    private function ini(string $value): string {return '"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','',''],$value).'"';}
}
