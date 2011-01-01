<?php

error_reporting(E_ALL ^ E_NOTICE);

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(__DIR__ . '/../library'),
    get_include_path(),
)));

function ZendTest_Autoloader($class)
{
    $class = ltrim($class, '\\');

    if (!preg_match('#^(Zend(Test)?|PHPUnit)(\\\\|_)#', $class)) {
        return false;
    }

    // $segments = explode('\\', $class); // preg_split('#\\\\|_#', $class);//
    $segments = preg_split('#[\\\\_]#', $class); // preg_split('#\\\\|_#', $class);//
    $ns       = array_shift($segments);
    if ($ns=='PHPUnit') return false;

    $segment = $segments[0] . $segments[1] . $segments[2];

    switch ($ns) {
        case 'Zend':
            if ($segment=='Cache\\Backend') {
                $file = dirname(__DIR__) . '/library/Zend/Cache/';
                array_shift($segments);
                array_shift($segments);
                array_shift($segments);
                break;
            }
            $file = 'Zend/';
            break;
        default:
            $file = false;
            break;
    }

    if ($file) {
        $file .= implode('/', $segments) . '.php';
        if ($file[0]=='/') {
            if (file_exists($file)) {
                return include_once $file;
            }
        } else {
            foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
                $pathname = realpath($path) . '/' . $file;
                if (file_exists($pathname)) {
                    return include_once $pathname;
                }
            }
        }
    }
    return false;
}
spl_autoload_register('ZendTest_Autoloader', true, true);

require_once __DIR__ . '/configuration.php';

