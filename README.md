# TRASSIR 
Library for work with Trassir NVRs.

Simple usage:
1. $server = new TrassirServer ('127.0.0.1'); //Ip address of existing Trassir NVR
2. $objects = $server->getServerObjects('password'); //password is SDK Password configured on NVR.
3. $sid = $server->authorization('Login', 'password'); //Its obligatory to get sid (session id) for some functions
4. $health = $server->getServerHealth($sid); //now you can use $sid to get Health
