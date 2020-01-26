<?php
$bd_server = "127.0.0.1";

$bd_login = 'q98548fz_admin';//логин базы данных

$bd_pass = 'A20072005b';//пароль базы данных

$bd_name = 'q98548fz_admin';//имя базы данных
 
mysql_connect($bd_server, $bd_login, $bd_pass)//параметры в скобках ("хост", "имя пользователя", "пароль")
or die("<p>Ошибка подключения к базе данных!</p>");
mysql_select_db($bd_name)//параметр в скобках ("имя базы, с которой соединяемся")
 or die("<p>Ошибка выбора базы данных!</p>");
mysql_query("SET NAMES utf8");
?>