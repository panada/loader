<?php

namespace Panada\Loader;

class Auto
{
    public static $maps = [];
    private static $psrs = [];
    private static $psrIncluded = [];
    private $vendor;
    
    public function __construct($vendorDir)
    {
        $this->vendor = $vendorDir;
        
        spl_autoload_register([$this, 'init']);
    }
    
    public function init($class)
    {
        // file already included, no need to proceed.
        if(isset(self::$psrIncluded[$class])) {
            return;
        }
        
        $this->composerGetMap($class);
        
    }
    
    /**
     * An alternative approach to autoload composer base file without the need of vendor/autoload.php native file
     */
    private function composerGetMap($class)
    {
        if(! self::$psrs) {
            self::$psrs['cm'] = include $this->vendor.'composer/autoload_classmap.php';
            self::$psrs['psr4'] = include $this->vendor.'composer/autoload_psr4.php';
            self::$psrs['ns'] = include $this->vendor.'composer/autoload_namespaces.php';
        }
        
        // check in class map first, since it the most simplest form.
        if(isset(self::$psrs['cm'][$class])) {
            $this->composerIncludeFile(self::$psrs['cm'][$class]);
            
            return self::$psrIncluded[$class] = 1;
        }
        
        // check file by \ separator
        if(! $this->getFileMap($class)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];
            throw new LoaderException('Resource ' . $class . ' not available! please check your class or namespace name.', 0, 1, $trace['file'], $trace['line']);
        }
    }
    
    private function getFileMap($class)
    {
        $separator = (strpos($class, '_') === false) ? '\\':'_';
        $prefix = explode($separator, $class);
        $ns     = null;
        $list   = self::$psrs;
        
        unset($list['cm']);
        
        foreach($prefix as $path) {
            
            $ns .= $path.$separator;
            
            foreach($list as $psrType => $folder) {
                
                $method = 'composerAL'.$psrType;
                
                if(isset(self::$psrs[$psrType][$ns])) {
                    $this->$method($ns, $folder[$ns], $class, $psrType);
                    self::$psrIncluded[$class] = 1;
                    
                    break 2;
                }
                
                $map = trim($ns, $separator);
                
                if( isset(self::$psrs[$psrType][$map]) ) {
                    
                    $this->$method($map, $folder[$map], $class, $psrType);
                    self::$psrIncluded[$class] = 1;
                    
                    break 2;
                }
            }
        }
        
        return isset(self::$psrIncluded[$class]);
    }
    
    /**
     * Composer autoload psr0
     */
    private function composerALpsr0($file, $class)
    {
        $file .= str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
        
        $this->composerIncludeFile($file);
    }
    
    /**
     * Composer autoload psr4
     */
    private function composerALpsr4($key, $val, $class)
    {
        $class = substr_replace($class, '', 0,strlen($key));
        
        $this->composerALns($key, $val, $class);
    }
    
    /**
     * Composer autoload namespaces
     */
    private function composerALns($key, $val, $class)
    {
        foreach($val as $val) {
            $folder = str_replace(['\\', '_', '/'], DIRECTORY_SEPARATOR, $key);
            $file   = $val . DIRECTORY_SEPARATOR . str_replace(['\\', '_', '/'], DIRECTORY_SEPARATOR, $class).'.php';
            
            $this->composerIncludeFile($file);
        }
    }
    
    private function composerIncludeFile($file)
    {
        try{
            include $file;
        }
        catch(\Exception $e) {
            if( substr($e->getMessage(), 0, 7) == 'include' ) {
                
                $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];
        
                throw new LoaderException('Resource ' . $file . ' not available! please check your class or namespace name. Exception error message is: '.$e->getMessage(), 0, 1, $trace['file'], $trace['line']);
            }
            else {
                throw $e;
            }
        }
    }
}
