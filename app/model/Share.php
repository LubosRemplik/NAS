<?php

namespace App\Model;

/**
 * Description of Share
 *
 * @author bkralik
 */
class Share extends Table {
    /**
    * @var string
    */
    protected $tableName = 'share';
    
    const shareuid = 65000;
    const sharegid = 65000;
    
    const dataBaseUrl = '/mnt/nas';
    const nfsParams = 'rw,all_squash,insecure,removable,anonuid=65000,anongid=65000';
    
    const rsyncHeader = "uid = share\ngid = share\nuse chroot = true\nlog file = /var/log/rsyncd.log\nlist = false\nread only = false\nmunge symlinks = true";
    
    private function getExportString($folder, $ips) {
        $ips_lines = explode(',', $ips);
        
        $out = self::dataBaseUrl . $folder;
        if(count($ips_lines) > 0) {
            foreach($ips_lines as $ip) {
                $out .= " " . $ip . "(" . self::nfsParams . ")";
            }
        } else {
            $out .= " (" . self::nfsParams . ")";
        }
        
        return($out);
    }
    
    public function exportNFS() {
        $shares = $this->findBy(array(
            'shareType_id' => 3
        ));

        $tmpfname = tempnam("/tmp", "nfsexport");
        $handle = fopen($tmpfname, "w");
        
        foreach ($shares as $share) {
            $line = $this->getExportString($share->folder->name, $share->var);
            fwrite($handle, $line."\n");
        }
        
        fclose($handle);
        
        $o = exec('sudo ../app/scripts/exportnfs.sh '.$tmpfname);
        if(!preg_match('/ok/', $o)) {
            return(FALSE);
        }
        
        unlink($tmpfname);
        return(TRUE);
    }
    
    public function checkNFSShare($folder, $ips) {
        $tmpfname = tempnam("/tmp", "nfstest");
        $handle = fopen($tmpfname, "w");
        
        $str = $this->getExportString($folder, $ips);
        fwrite($handle, $str."\n");

        fclose($handle);
        
        
        $cmd = 'sudo ../app/scripts/testnfs.sh '.$tmpfname;
        $proc = proc_open($cmd, [
            1 => ['pipe','w'],
            2 => ['pipe','w'],
        ], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $return_value = proc_close($proc);
  
        if(preg_match('/error/', $stderr) || ($return_value > 0)) {
           return(FALSE);
        }
                
        unlink($tmpfname);
        return(TRUE);
    }
    
    public function exportRsync() {
        $shares = $this->findBy(array(
            'shareType_id' => 5
        ));

        $tmp_config = tempnam("/tmp", "rsyncconfig");
        $tmp_secret = tempnam("/tmp", "rsyncsecret");
        $handle_config = fopen($tmp_config, "w");
        $handle_secret = fopen($tmp_secret, "w");
        
        fwrite($handle_config, self::rsyncHeader . "\n");
        
        foreach ($shares as $share) {
            $folderParsed = explode("/", $share->folder->name);
            $folderName = $folderParsed[2];
            //$line = $this->getExportString($share->folder->name, $share->var);
            fwrite($handle_config, "\n[" . $share->folder->user_id . "-" . $folderName . "]\n");
            fwrite($handle_config, "path = " . self::dataBaseUrl . $share->folder->name . "\n");
            fwrite($handle_config, "auth users = " . $share->var . "\n");
            fwrite($handle_config, "secrets file = /etc/rsyncd.secrets\n");
            
            fwrite($handle_secret, $share->var . ":" . $share->var2 . "\n");
        }
        
        fclose($handle_config);
        fclose($handle_secret);
        
        
        $o = exec('sudo ../app/scripts/exportrsync.sh ' . $tmp_config. ' ' . $tmp_secret);
        if(!preg_match('/ok/', $o)) {
            return(FALSE);
        }
        
        unlink($tmp_config);
        unlink($tmp_secret);
        return(TRUE);
    }
}
