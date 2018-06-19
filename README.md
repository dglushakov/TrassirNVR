# TRASSIR 
Library for work with Trassir NVRs.

Simple usage:
1. $server = new TrassirServer ('127.0.0.1'); //Ip address of existing Trassir NVR
2. $objects = $server->getServerObjects('sdk_password'); //sdk_password = SDK Password configured on NVR.
When getServerObjectUsed it is also set up server name and server channels, so after you can use
$server->getChannels() and $server->getName()


3. $sid = $server->authorization('Login', 'password'); //Its obligatory to get sid (session id) for some functions like cheking health for example.
4. $health = $server->getServerHealth($sid); //now you can use $sid to get Health
