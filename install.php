<?php
//используется ТОЛЬКО для удалённой инсталяции
//@todo исключать из дистрибутива
require_once("ini.php"); // Файл с настройками

if (SHOW_INT_ERRORE_MESSAGE)
    error_reporting(E_ALL);
else
    error_reporting(0);

require_once ("include/kernel.class.php"); //Ядро
require_once ("include/pub_interface.class.php");
require_once ("include/mysql_table.class.php");
require_once ("components/pclzip/pclzip.lib.php");

session_cache_expire(60*60*24*7);
session_start();

// Сто дней хранить куки
$expiry = 60*60*24*100;
setcookie(session_name(), session_id(), time()+$expiry, "/");
$kernel = new kernel(PREFIX);

$arr_files = array();
$arr_files["admin"]      = "admin.zip";
$arr_files["components"] = "components.zip";
$arr_files["content"]    = "content.zip";
$arr_files["design"]     = "design.zip";
$arr_files["modules"]    = "modules.zip";

foreach ($arr_files as $dir_name => $file_name)
{
    $full_path = $kernel->priv_file_full_patch($dir_name."/");
    //Дадаим права на запись и на папку и на сам файл,
    $kernel->pub_ftp_dir_chmod_open($dir_name."/".$file_name);
    $kernel->pub_ftp_file_chmod_change($dir_name."/".$file_name);
    //print "unpacking ".$full_path.$file_name." to ".$full_path."...<br>\n";
    $archive = new PclZip($full_path.$file_name);
    $result = $archive->extract(PCLZIP_OPT_PATH, $full_path/*, PCLZIP_OPT_TEMP_FILE_ON, PCLZIP_OPT_SET_CHMOD, 0775*/);
    //$archive->extract(PCLZIP_OPT_SET_CHMOD, 0777);
    if ( $result <= 0)
    {
        echo '<br><br><font color="red"><b>Ошибка при распаковке <br>'.$archive->error_string.'</b><br>Это необходимо сделать вручную!</font>\n';
        error_log("unzip error: ".$archive->error_string);
    }
    $kernel->pub_ftp_dir_chmod_close($dir_name."/".$file_name);
}

$admin_login = $_GET['admin_login'];
$admin_pass = $_GET['admin_pass'];
if ($_GET['is_etalon']=="1")
    $is_etalon = true;
else
    $is_etalon = false;

$m_table = new mysql_table();
$m_table->install($is_etalon);


$kernel->runSQL("INSERT INTO ".PREFIX."_admin_group (name, full_name, main_admin) VALUES ('admin', 'Главные администраторы', 1), ('all_admin', 'Администраторы', 0)");
$kernel->runSQL("INSERT INTO ".PREFIX."_admin (login, pass, full_name, lang, code_page) VALUES ('".mysql_real_escape_string($admin_login)."', '".mysql_real_escape_string($admin_pass)."', 'Главный администратор', 'ru', 'utf-8')");
$kernel->runSQL("INSERT INTO ".PREFIX."_admin_cross_group (user_id, group_id) VALUES (1, 1)");

if (!$is_etalon)
{
    $q = 'INSERT INTO `'.PREFIX.'_structure` VALUES (\'index\', NULL, \'Главная страница\', null, \'a:6:{s:7:\"caption\";s:16:\"Главная страница\";s:11:\"title_other\";s:1:\"1\";s:10:\"name_title\";s:16:\"Главная страница\";s:9:\"only_auth\";b:0;s:8:\"template\";s:21:\"design/template1.html\";s:15:\"link_other_page\";s:0:\"\";}\', \'a:7:{s:5:\"title\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:3:\"way\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:6:\"search\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:7:\"content\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:4:\"menu\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:5:\"lenta\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:4:\"fdgf\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}}\');';
    $kernel->runSQL($q);
    $q = 'INSERT INTO `'.PREFIX.'_structure` VALUES (\'subpage\', \'index\', \'Вложенная страница\', 0, \'a:6:{s:7:\"caption\";s:16:\"Главная страница\";s:11:\"title_other\";s:1:\"1\";s:10:\"name_title\";s:16:\"Главная страница\";s:9:\"only_auth\";b:0;s:8:\"template\";s:21:\"design/template1.html\";s:15:\"link_other_page\";s:0:\"\";}\', \'a:7:{s:5:\"title\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:3:\"way\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:6:\"search\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:7:\"content\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:4:\"menu\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:5:\"lenta\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:4:\"fdgf\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}}\');';
    $kernel->runSQL($q);
}

//Выводим сообщение о том всё заврешено
echo "Всё проинсталированно.<br>";
echo 'Для входа используйте логин <i>'.$admin_login.'</i> и пароль <i>'.$admin_pass.'</i><br>';

//Теперь выведем ссылку для перехода на админку (если интсал запущен просто по прямой ссылке)
//а если из под ифрейма инстала то вызовем соответсвующую функцию в родительском окне
$arr = $_SERVER['HTTP_HOST'];
if (isset($_GET['golink']))
    echo 'Для перехода в административный интерфейс нажмите кнопку "Готово"<br><br>';
else
    echo '<a target="_blank" href="http://'.$arr.'/admin">Перейти в административный интерфейс</a><br><br>';

$kernel->pub_file_delete("install.php");
foreach ($arr_files as $dir_mane => $file_name)
{
    $kernel->pub_file_delete($dir_mane."/".$file_name);
}
