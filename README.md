# TRASSIR 
Library for work with Trassir NVRs.

Simple usage:

$server  = new \dglushakov\trassir\TrassirServer('1.1.1.1', 'Login', 'Password', 'UserPassword');
             
 $objects = $server->getServerObjects();
 $health = $server->getHealth();
