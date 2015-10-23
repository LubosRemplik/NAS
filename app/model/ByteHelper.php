<?php

namespace App\Model;

use Nette,
    App\Model;

/**
 * Description of ByteHelper
 * 
 * @author bkralik
 */
class ByteHelper extends Nette\Object {
    
    private static $prefixes = array('','k','M','G','T','P','E','Z','Y');
    
    public static function humanToBytes($human) {
        $human = trim($human);
        if(!preg_match('/^([0-9\.]+)\s*([kMGTPEZY]?)\s*[B]?$/', $human, $matches)) {
            return(0);
        } else {
            $base = floatval($matches[1]);
            $exp = array_search($matches[2], self::$prefixes);
            $num = $base * pow(1024, $exp);
            return(intval($num));
        }
    }
    
    public static function bytesToHuman($size, $precision = 2) {
        for($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {}
        return(round($size, $precision) . self::$prefixes[$i] . 'B');
    }
}
