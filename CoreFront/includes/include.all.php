<?php
class ClassAutoloader {
    public function __construct() {
        spl_autoload_register(array($this, 'loader'));
    }
    private function loader($className) {
        include 'class.'.$className . '.php';
    }
}
$autoloader = new ClassAutoloader();


?>