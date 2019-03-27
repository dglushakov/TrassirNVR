# TRASSIR 
Library for work with Trassir NVRs.

$server  = new TrassirServer('1.1.1.1', 'Login', 'Password', 'SDK_Password');
             
$objects = $server->getServerObjects(); <br>
$channels = $server->getChannels();<br>
$health = $server->getHealth();
