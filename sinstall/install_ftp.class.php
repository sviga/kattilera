<?php

class install_ftp
{
    var $fullsize = 0;
    var $curentsize = 0;
    var $test = false;
    var $link = 'distrib';
    var $cmod_for_all = '0755';
    var $cmod_for_limit = '0755';
    var $file_array = array();
    var $dir_array = array();
    var $cmod_array = array();
    var $dir_for_all = array('content' => '', 'templates_user' => '', 'backup' => '', 'upload' => ''); //Список директорий, которым нужно дать разрешение на запись

    var $id_conect; //Линк на соединение с ФТП
    var $ftp_host = false;
    var $ftp_login = false;
    var $ftp_pass = false;

    private function json_response($success, $info = "")
    {
        if ($success)
            $ss = "true";
        else
            $ss = "false";
        return '{"success":' . $ss . ',"info":"' . addslashes($info) . '"}';
    }

    function start()
    {
        //Определяем текущий шаг установки
        $step = 'step-1';
        if ((isset($_GET['step'])) && ((intval($_GET['step'])) >= 0))
            $step = 'step' . intval($_GET['step']);

        if ((isset($_GET['savestep'])) && ((intval($_GET['savestep'])) > 0))
            $step = 'savestep' . intval($_GET['savestep']);
        $html_content = file_get_contents("template/main.html");
        switch ($step)
        {
            //Самый первый шаг, нужно вывести основную обвеску, всё остальное
            //будет подгружаться уже в неё
            case 'step0':
                $html_content = file_get_contents("template/step0.html");
                //Сюда же добавим провреку на то что у нас PHP именно нужной вресии
                $needVersion="5.0.0";
                $result = version_compare($needVersion, phpversion());
                $err = '';
                $errors_found = false;
                if (intval($result) > 0)
                {
                    $err .= "<p>&nbsp;</p><p><b>Версия PHP должна быть не ниже ".$needVersion."</b></p>";
                    $errors_found = true;
                }
                //проверка установленных модулей
                $exts = get_loaded_extensions();
                $need_exts = array("calendar", "session", "iconv", "pcre", "mbstring", "gd", "mysql");
                foreach ($need_exts as $need_ext)
                {
                    if (!in_array($need_ext, $exts))
                    {
                        $err .= "<p>&nbsp;</p><p><b>Не установлен модуль " . $need_ext . " в PHP</b></p>";
                        $errors_found = true;
                    }
                }
                //опциональные модули (без них будет работать)
                $opt_exts = array("ftp");
                foreach ($opt_exts as $need_ext)
                {
                    if (!in_array($need_ext, $exts))
                        $err .= "<p>&nbsp;</p><p><b>Не установлен модуль " . $need_ext . " в PHP (не критично)</b></p>";
                }
                if ($errors_found)
                    $html_content .= "<script>btnNext.setDisabled(true);</script>";
                $html_content = str_replace("[#errore_php#]", $err, $html_content);
                break;
            //========================================================================
            //Показываем форму первого шага
            case 'step1':
                $html_content = file_get_contents("template/step1.html");

                $host = 'ftp.' . $_SERVER['HTTP_HOST'];
                if (isset($_SESSION['ftp']['host']) && !empty($_SESSION['ftp']['host']))
                    $host = trim($_SESSION['ftp']['host']);

                $login = '';
                if (isset($_SESSION['ftp']['login']) && !empty($_SESSION['ftp']['login']))
                    $login = trim($_SESSION['ftp']['login']);

                $pass = '';
                if (isset($_SESSION['ftp']['password']) && !empty($_SESSION['ftp']['password']))
                    $pass = trim($_SESSION['ftp']['password']);

                $path = '/';
                if (isset($_SESSION['ftp']['path']) && !empty($_SESSION['ftp']['path']))
                    $path = trim($_SESSION['ftp']['path']);

                $domen = 'http://' . $_SERVER['HTTP_HOST'] . '/';
                if (isset($_SESSION['domain']) && !empty($_SESSION['domain']))
                    $domen = trim($_SESSION['domain']);

                $html_content = str_replace("[#value_domen#]", $domen, $html_content);
                $html_content = str_replace("[#value_hostadress#]", $host, $html_content);
                $html_content = str_replace("[#value_login#]", $login, $html_content);
                $html_content = str_replace("[#value_password#]", $pass, $html_content);
                $html_content = str_replace("[#value_path#]", $path, $html_content);
                break;

            //Проверяем и сохраняем данные, введённые в форму
            case 'savestep1':
                $host = trim($_POST['hostadress']);
                $login = trim($_POST['login']);
                $pass = trim($_POST['password']);
                $path = "/";
                $domen = trim($_POST['domen']);

                //Сразу пропишем значения в сессию, что бы брать их
                //из неё при повторном открытии формы
                $_SESSION['ftp']['host'] = $host;
                $_SESSION['ftp']['login'] = $login;
                $_SESSION['ftp']['password'] = $pass;
                $_SESSION['ftp']['path'] = $path;
                $_SESSION['domain'] = $domen;
                return $this->json_response(true);

                //Проверяет возможность подключения к хостингу
                /*
               $errore = '';
               if ((empty($host)) || (empty($login)) || (empty($pass)) || (empty($path))|| (empty($domen)))
                       $errore = "Форма заполнена не полностью";
               else
               {
                   //Пытаемся соединится с сервером по фтп
                   $str_conect = $host;
                   $id_conect = @ftp_connect($host);
                   if ($id_conect === false)
                       $errore = "Ошибка в соединении или неправильно указан сервер";
                   else
                   {
                       if (!@ftp_login($id_conect, $login, $pass))
                           $errore = "Ошибка в логине и пароле";
                       else
                       {
                           if (@ftp_chdir($id_conect, $path) === false)
                               $errore = "Ошибка в пути для инсталляции";
                       }
                   }
               }
               //Если есть ошибки то вернём false для скрипта и что это за ошибки
               //в противном случае вернём true, и скрипт сразу перейдёт к другому шагу
               if (empty($errore))
                   return $this->json_response(true);
               else
                   return $this->json_response(false,$errore);

                */
                break;

            case 'step2':
                $html_content = file_get_contents("template/step2.html");
                $host = 'localhost';
                if (isset($_SESSION['sqll_inst']['host']) && !empty($_SESSION['sqll_inst']['host']))
                    $host = trim($_SESSION['sqll_inst']['host']);

                $login = '';
                if (isset($_SESSION['sqll_inst']['login']) && !empty($_SESSION['sqll_inst']['login']))
                    $login = trim($_SESSION['sqll_inst']['login']);

                $pass = '';
                if (isset($_SESSION['sqll_inst']['password']) && !empty($_SESSION['sqll_inst']['password']))
                    $pass = trim($_SESSION['sqll_inst']['password']);

                $namebase = '';
                if (isset($_SESSION['sqll_inst']['namebase']) && !empty($_SESSION['sqll_inst']['namebase']))
                    $namebase = trim($_SESSION['sqll_inst']['namebase']);

                $prefix = 'sf';
                if (isset($_SESSION['sqll_inst']['prefix']) && !empty($_SESSION['sqll_inst']['prefix']))
                    $prefix = trim($_SESSION['sqll_inst']['prefix']);

                $html_content = str_replace("[#value_prefix#]", $prefix, $html_content);
                $html_content = str_replace("[#value_hostadress#]", $host, $html_content);
                $html_content = str_replace("[#value_login#]", $login, $html_content);
                $html_content = str_replace("[#value_password#]", $pass, $html_content);
                $html_content = str_replace("[#value_namebase#]", $namebase, $html_content);
                break;

            //Проверяет возможность подключения к базе данных mysqll_inst
            case 'savestep2':
                $host = isset($_POST['hostadress']) ? trim($_POST['hostadress']) : "";
                $login = isset($_POST['login']) ? trim($_POST['login']) : "";
                $pass = isset($_POST['password']) ? trim($_POST['password']) : "";
                $namebase = isset($_POST['namebase']) ? trim($_POST['namebase']) : "";
                $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : "";

                $_SESSION['sqll_inst']['host'] = $host;
                $_SESSION['sqll_inst']['login'] = $login;
                $_SESSION['sqll_inst']['password'] = $pass;
                $_SESSION['sqll_inst']['namebase'] = $namebase;
                $_SESSION['sqll_inst']['prefix'] = $prefix;

                $errore = '';
                if (empty($host) || empty($login) || empty($namebase)|| empty($prefix))
                    $errore = "Форма заполнена не полностью.";
                else
                {
                    //Теперь собственно проверка на соединение с mySql
                    $resurs = @mysql_connect($host, $login, $pass);
                    if (!$resurs)
                        $errore = "Не могу соединиться с базой данных. Проверьте адрес сервера MySQL, а также логин и пароль для доступа к нему.";
                    else
                    {
                        if (!@mysql_select_db($namebase, $resurs))
                            $errore = 'Невозможно выбрать базу данных <i>' . $namebase . '</i> (не существует либо нет прав).';
                        else
                        {
                            $res = mysql_query("SELECT VERSION() AS v", $resurs);
                            $rec = mysql_fetch_assoc($res);
                            $v = explode(".", $rec['v']);
                            $v = intval($v[0]);
                            if ($v < 4)
                                $errore = "Версия MySQL должна быть не ниже 4";
                        }
                    }
                }
                return $this->json_response(!$errore, $errore);

            case 'step3':
                //логин-пароль для админа, выбор типа инсталяции
                $html_content = file_get_contents("template/step3.html");
                $admin_login = "";
                $admin_pass = "";
                $etalon_install = false;
                $is1251templates = false;
                if (isset($_SESSION['admin']['login']) && !empty($_SESSION['admin']['login']))
                    $admin_login = htmlspecialchars($_SESSION['admin']['login']);
                if (isset($_SESSION['admin']['pass']) && !empty($_SESSION['admin']['pass']))
                    $admin_pass = htmlspecialchars($_SESSION['admin']['pass']);
                if (isset($_SESSION['etalon_install']) && !empty($_SESSION['etalon_install']))
                    $etalon_install = $_SESSION['etalon_install'];
                if (isset($_SESSION['is1251templates']) && !empty($_SESSION['is1251templates']))
                    $is1251templates = $_SESSION['is1251templates'];
                $html_content = str_replace("[#admin_login#]", $admin_login, $html_content);
                $html_content = str_replace("[#admin_pass#]", $admin_pass, $html_content);
                if ($etalon_install)
                    $html_content = str_replace("[#etalon_install_checked#]", "checked", $html_content);
                else
                    $html_content = str_replace("[#etalon_install_checked#]", "", $html_content);
                if ($is1251templates)
                    $html_content = str_replace("[#is1251templates_checked#]", "checked", $html_content);
                else
                    $html_content = str_replace("[#is1251templates_checked#]", "false", $html_content);
                break;

            case 'savestep3':
                $etalon_install = false;
                $is1251templates = false;
                if (isset($_POST['etalon_install']))
                    $etalon_install = true;
                if (isset($_POST['is1251templates']))
                    $is1251templates = true;
                $admin_login = $_POST['admin_login'];
                $admin_pass = $_POST['admin_pass'];
                $_SESSION['admin']['login'] = $admin_login;
                $_SESSION['admin']['pass'] = $admin_pass;
                $_SESSION['etalon_install'] = $etalon_install;
                $_SESSION['is1251templates'] = $is1251templates;

                if (!preg_match('/^[a-z0-9][a-z0-9_\\.-]*@[a-z0-9\\.-]+\\.[a-z]{2,6}$/i',$admin_login))
                    return $this->json_response(false,"Указан некорректный email");
                else
                    return $this->json_response(true);
            //========================================================================
            case 'step4':
                $html_content = file_get_contents("template/step4.html");
                $etalon_install = $_SESSION['etalon_install'];
                $is1251templates = $_SESSION['is1251templates'];
                $admin_login = $_SESSION['admin']['login'];
                $admin_pass = $_SESSION['admin']['pass'];

                $html_content = str_replace("[#admin_login#]", $admin_login, $html_content);
                $html_content = str_replace("[#admin_pass#]", $admin_pass, $html_content);
                if ($etalon_install)
                    $html_content = str_replace("[#install_type#]", "эталонная", $html_content);
                else
                    $html_content = str_replace("[#install_type#]", "чистая", $html_content);
                if ($is1251templates)
                    $html_content = str_replace("[#templates_encoding#]", "windows1251", $html_content);
                else
                    $html_content = str_replace("[#templates_encoding#]", "UTF-8", $html_content);
                $host = $_SESSION['ftp']['host'];
                $login = $_SESSION['ftp']['login'];
                $pass = $_SESSION['ftp']['password'];
                $path = $_SESSION['ftp']['path'];
                $html_content = str_replace("[#ftp_value_hostadress#]", $host, $html_content);
                $html_content = str_replace("[#ftp_value_login#]", $login, $html_content);
                $html_content = str_replace("[#ftp_value_password#]", $pass, $html_content);
                $html_content = str_replace("[#ftp_value_path#]", $path, $html_content);

                $host = $_SESSION['sqll_inst']['host'];
                $login = $_SESSION['sqll_inst']['login'];
                $pass = $_SESSION['sqll_inst']['password'];
                $namebase = $_SESSION['sqll_inst']['namebase'];
                $prefix = $_SESSION['sqll_inst']['prefix'];
                $html_content = str_replace("[#mysql_value_prefix#]", $prefix, $html_content);
                $html_content = str_replace("[#mysql_value_hostadress#]", $host, $html_content);
                $html_content = str_replace("[#mysql_value_login#]", $login, $html_content);
                $html_content = str_replace("[#mysql_value_password#]", $pass, $html_content);
                $html_content = str_replace("[#mysql_value_namebase#]", $namebase, $html_content);
                break;
            case 'savestep4':
                return $this->json_response(true);
            //Понеслось копирование
            case 'step5':
                //Непосредственно процесс записи ini файла
                $html = '';
                $html .= '<script type="text/javascript">';
                $html .= '    $(document).ready(function(){$("#btnNext").attr("disabled","disabled");$("#btnPrev").attr("disabled","disabled");});';
                $html .= '</script>';
                clearstatcache();
                if (!file_exists('../ini.default.php'))
                    return $html . '<p align="center">Не могу найти файл настроек /ini.default.php.</p>';

                $html_ini = file_get_contents('../ini.default.php');
                $html_ini = str_replace('[#PREFIX#]', $_SESSION['sqll_inst']['prefix'], $html_ini);
                $html_ini = str_replace('[#DB_HOST#]', $_SESSION['sqll_inst']['host'], $html_ini);
                $html_ini = str_replace('[#DB_BASENAME#]', $_SESSION['sqll_inst']['namebase'], $html_ini);
                $html_ini = str_replace('[#DB_USERNAME#]', $_SESSION['sqll_inst']['login'], $html_ini);
                $html_ini = str_replace('[#DB_PASSWORD#]', $_SESSION['sqll_inst']['password'], $html_ini);
                $html_ini = str_replace('[#FTP_HOST#]', $_SESSION['ftp']['host'], $html_ini);
                $html_ini = str_replace('[#FTP_LOGIN#]', $_SESSION['ftp']['login'], $html_ini);
                $html_ini = str_replace('[#FTP_PASSWORD#]', $_SESSION['ftp']['password'], $html_ini);
                if ($_SESSION['is1251templates'])
                    $html_ini = str_replace('[#IS_1251_TEMPLATES#]', "true", $html_ini);
                else
                    $html_ini = str_replace('[#IS_1251_TEMPLATES#]', "false", $html_ini);

                //Запишем файл настроек через функции ядра, при этом, ядру будут нужны данные для FTP
                //соединения поэтому надо обхявить такие константы переменные
                DEFINE ('PREFIX', $_SESSION['sqll_inst']['prefix']);
                DEFINE ('DB_HOST', $_SESSION['sqll_inst']['host']);
                DEFINE ('DB_BASENAME', $_SESSION['sqll_inst']['namebase']);
                DEFINE ('DB_USERNAME', $_SESSION['sqll_inst']['login']);
                DEFINE ('DB_PASSWORD', $_SESSION['sqll_inst']['password']);
                DEFINE ("FTP_HOST", $_SESSION['ftp']['host']);
                DEFINE ("FTP_LOGIN", $_SESSION['ftp']['login']);
                DEFINE ("FTP_PASS", $_SESSION['ftp']['password']);
                DEFINE ("PATH_PAGE_CONTENT", "content/pages");
                $etalon_install = $_SESSION['etalon_install'];
                $admin_login = $_SESSION['admin']['login'];
                $admin_pass = $_SESSION['admin']['pass'];

                //Собственно запись файла настроек, пишем сразу в корень сайта
                require_once ("../include/kernel.class.php"); //Ядро
                require_once ("../include/pub_interface.class.php");
                $kernel = new kernel(PREFIX);
                chdir('..');
                $root = $kernel->pub_site_root_get() . "/";
                if (!$etalon_install)
                { //чистим design + content, если это чистая установка

                    $files = array();
                    $files += $kernel->pub_files_list_get($root . "design");
                    $files += $kernel->pub_files_list_get($root . "design/images");
                    $files += $kernel->pub_files_list_get($root . "design/scripts");
                    $files += $kernel->pub_files_list_get($root . "design/styles");
                    $files += $kernel->pub_files_list_recursive_get($root . "content");

                    $files = array_keys($files);
                    $skip_files = array($root . "design/template1.html");
                    foreach ($files as $file)
                    {
                        if (!in_array($file, $skip_files))
                            @unlink($file);
                    }
                }

                if ($_SESSION['is1251templates'])
                { //сконвертируем файлы в 1251, если выбран такой тип
                    $dfiles = $this->getTextfilesRecursive($root . "design");
                    $mfiles = $this->getTextfilesRecursive($root . "modules", true);
                    $txtFiles = array_merge($dfiles, $mfiles);
                    foreach ($txtFiles as $txtFile)
                    {
                        $tmpl = file_get_contents($txtFile);
                        $tmpl = @iconv('UTF-8', 'cp1251//TRANSLIT', $tmpl);
                        //+заменяем кодировку в хидере
                        $tmpl = str_ireplace("UTF-8", "windows-1251", $tmpl);
                        $kernel->priv_file_save_script($txtFile, $tmpl, true); //@todo а если не хватает прав сохранить изменённые файлы?
                    }
                }
                $this->mysql_install($etalon_install, $admin_login, $admin_pass);
                //сохранение созданного ini-файла
                $result = $kernel->priv_file_save_script($kernel->priv_file_full_patch("/ini.php"), $html_ini, true);
                $url_admin = 'http://' . $_SERVER['HTTP_HOST'] . '/admin/';
                if (!$result)
                {
                    $result2 = $kernel->priv_file_save_script($kernel->priv_file_full_patch("/sinstall/ini.php"), $html_ini, true);
                    if ($result2)
                    {
                        $html .= '<p align="center">Файл настроек успешно создан</p>';
                        $html .= '<br/><p align="center"><b>Программе не удалось автоматически скоприровать файл настроек из папки в <i>sinstall</i> в корень сайта. Необходимо сделать это вручную.</b></p>';
                        $html .= '<br/><p align="center">После этого вы можете перейти в интерфейс администратора, пройдя по ссылке <a href="' . $url_admin . '">' . $url_admin . '</a></p>';
                    }
                    else
                        $html .= '<br/><p align="center"><b>Программе не удалось создать файл настроек <i>ini.php</i> в корне сайта, а также в папке <i>sinstall</i>. Необходимо создать этот файл вручную.Содержимое:</b></p><pre>' . str_replace(array("<", ">"), array("&lt;", "&gt;"), $html_ini) . '</pre>';
                }
                else
                {
                    $html .= '<p align="center">Файл настроек успешно создан</p>';
                    $html .= '<script type="text/javascript">';
                    $html .= '    redirect_admin("' . $url_admin . '");';
                    $html .= '</script>';
                    $html .= '<p align="center">Для перехода в административный интерфейс нажмите кнопку "Готово". Не забудьте стереть папку sinstall с вашего сайта.</p>';
                }
                $html .= '<p align="center">Для авторизации в административном интерфейсе используйте логин "' . $admin_login . '" и пароль "' . $admin_pass . '"</p>';


                return $html;
        }
        return $html_content;
    }

    /**
     * Рекурсивно возвращает список текстовых файлов (html, css, js) из папки
     * используется при перекодировании шаблонов в 1251
     * @param string $path путь
     * @param bool $isModules список в модулях?
     * @return array
     */
    function getTextfilesRecursive($path, $isModules = false)
    {
        $ret = array();
        if (!(empty($path)))
        {
            $d = dir($path);
            while (false !== ($entry = $d->read()))
            {
                if (($entry == ".") || ($entry == ".."))
                {
                    continue;
                }
                $link = $path . '/' . $entry;
                if (is_file($link))
                {
                    if (preg_match("/(\\.html|\\.htm|\\.css|\\.js)$/i", $entry))
                    {
                        $ret[] = $link;
                    }
                    //$ret[$link] = $entry;
                }
                elseif (is_dir($link))
                {
                    //для модулей - не заходим слишком глубоко
                    if ($isModules && in_array($entry, array("lang", "templates_admin")))
                    {
                        continue;
                    }
                    else
                    {
                        $ret = array_merge($ret, $this->getTextfilesRecursive($link . "/"));
                    }
                    //$ret += $this->getTextfilesRecursive($link."/");
                }
            }
            $d->close();
        }
        return $ret;
    }


    function mysql_install($etalon_install, $admin_login, $admin_pass)
    {
        //error_reporting(0);
        error_reporting(E_ALL);
        //require_once dirname(dirname(__FILE__)) . "/ini.php"; // Файл с настройками
        require_once dirname(dirname(__FILE__)) . "/include/kernel.class.php"; //Ядро
        //require_once dirname(dirname(__FILE__))."/include/pub_interface.class.php";
        require_once dirname(dirname(__FILE__)) . "/include/mysql_table.class.php";
        $kernel = new kernel(PREFIX);
        $m_table = new mysql_table();
        $m_table->install($etalon_install);
        $kernel->runSQL("INSERT INTO " . PREFIX . "_admin_group (name, full_name, main_admin) VALUES ('admin', 'Главные администраторы', 1), ('all_admin', 'Администраторы', 0)");
        $kernel->runSQL("INSERT INTO " . PREFIX . "_admin (login, pass, full_name, lang, code_page) VALUES ('" . mysql_real_escape_string($admin_login) . "', '" . mysql_real_escape_string($admin_pass) . "', 'Главный администратор', 'ru', 'utf-8')");
        $kernel->runSQL("INSERT INTO " . PREFIX . "_admin_cross_group (user_id, group_id) VALUES (1, 1)");
    }

    function set_ftp_parametrs($host, $login, $pass)
    {
        $this->ftp_host = $host;
        $this->ftp_login = $login;
        $this->ftp_pass = $pass;

    }

    function create_ftp_connect()
    {
        if ($this->id_conect)
        {
            ftp_close($this->id_conect);
        }
        $this->id_conect = ftp_connect($this->ftp_host);
        ftp_login($this->id_conect, $this->ftp_login, $this->ftp_pass);
        //ftp_pasv($this->id_conect, true);
        ftp_set_option($this->id_conect, FTP_TIMEOUT_SEC, 10);
        return $this->id_conect;
    }

    function pub_template_parse($filename)
    {
        if (!file_exists($filename))
        {
            return array();
        }

        $tmpl = file_get_contents($filename);
        $parts = preg_split("/<!--\\s*?\\@([^\\@]*?)\\s*?-->/i", $tmpl);
        $arr = array();
        preg_match_all("/<!--\\s*?\\@([^\\@]*?)\\s*?-->/", $tmpl, $matches);
        foreach ($matches[1] as $i => $word)
            $arr[$word] = $parts[$i + 1];

        if (!isset($arr['begin']))
        {
            $arr['begin'] = "";
        }

        if (!isset($arr['end']))
        {
            $arr['end'] = "";
        }

        foreach ($arr as $key => $val)
        {
            $level_templates = $val;
            $level_templates_arr = preg_split("/<!--\\s*?\\@@nextlevel\\s*?-->/i", $level_templates);
            if (count($level_templates_arr) == 1)
            {
                $level_templates_arr = $val;
            }
            $arr[$key] = $level_templates_arr;
        }

        return $arr;
    }


    /**
     * Производит непосредственно процесс копирования движка на удаленный FTP
     */
    function install()
    {
        $host = $_SESSION['ftp']['host'];
        $login = $_SESSION['ftp']['login'];
        $pass = $_SESSION['ftp']['password'];
        $path = $_SESSION['ftp']['path'];

        //Сразу выведем табличку, что бы мы могли туда подгружать наш контент
        //а так же функцию, подгружающую этот контент
        print('<table width="100%" height="100%" border="0"><tr><td align="left" valign="top" id="content_iframe" style="font-size:9px"></td></tr></table>');
        print('<script>function show_message_content(show_msg)
                   {
                          document.all.content_iframe.innerHTML = show_msg + "<br>"+ document.all.content_iframe.innerHTML
                   }</script>');
        flush();


        print('<script>parent.window.show_message("Подключение к удаленному серверу...");</script>');
        flush();

        $this->set_ftp_parametrs($host, $login, $pass);
        $this->create_ftp_connect();
        //ftp_chdir($id_conect, $path);

        $restire_umask = umask(0);
        print('<script>parent.window.show_message("Создание списка копируемых файлов...");</script>');
        flush();

        //Пропишем, сколько мы всего должны скопировать файлов
        clearstatcache();
        $this->dir_array = array();
        $this->file_array = array();
        $this->cmod_array = array();
        $this->curentsize = 0;

        $this->get_array_file($this->id_conect, $this->link, $path, $this->cmod_for_limit);
        $this->fullsize = round(($this->curentsize / 1024), 0);
        $this->curentsize = 0;

        //Можно приступать к процессу копирования файлов. Сначала разберемся с директориями
        $this->ftp_creat_dir($this->dir_array);

        //Теперь можно копировать файлы
        $this->ftp_creat_file($this->file_array, $this->cmod_array);

        print('<script>parent.window.show_message("Создание файла настроек ini.php");</script>');
        flush();

        //Теперь ещё надо создать файл ini.php, сначала создадим временный на локалке
        clearstatcache();
        $html_ini = file_get_contents($this->link . '/ini.php');
        $html_ini = str_replace('[#PREFIX#]', $_SESSION['sqll_inst']['prefix'], $html_ini);
        $html_ini = str_replace('[#DB_HOST#]', $_SESSION['sqll_inst']['host'], $html_ini);
        $html_ini = str_replace('[#DB_BASENAME#]', $_SESSION['sqll_inst']['namebase'], $html_ini);
        $html_ini = str_replace('[#DB_USERNAME#]', $_SESSION['sqll_inst']['login'], $html_ini);
        $html_ini = str_replace('[#DB_PASSWORD#]', $_SESSION['sqll_inst']['password'], $html_ini);
        $html_ini = str_replace('[#FTP_HOST#]', $_SESSION['ftp']['host'], $html_ini);
        $html_ini = str_replace('[#FTP_LOGIN#]', $_SESSION['ftp']['login'], $html_ini);
        $html_ini = str_replace('[#FTP_PASSWORD#]', $_SESSION['ftp']['password'], $html_ini);

        $temp_ini = 'temp_ini/' . session_id() . '.php';
        $handle = fopen($temp_ini, "w");
        fwrite($handle, $html_ini);
        fclose($handle);

        //Скопируем новый ini.php
        clearstatcache();
        $chmod_cmd = "CHMOD " . $this->cmod_for_all . " " . $path . '/ini.php';
        @ftp_site($this->id_conect, $chmod_cmd);
        $upload = @ftp_put($this->id_conect, $path . '/ini.php', $temp_ini, FTP_ASCII);

        //Удалим временный файл
        @unlink($temp_ini);

        print('<script>parent.window.show_message("Создание файла инсталяции install.php");</script>');
        flush();

        //попробуем скопировать просто один файл и запустить его
        $upload = ftp_put($this->id_conect, $path . '/install.php', $this->link . '/install.php', FTP_ASCII);

        //Проставим права на INI и инсталл
        $chmod_cmd = "CHMOD " . $this->cmod_for_all . " " . $path . '/ini.php';
        @ftp_site($this->id_conect, $chmod_cmd);
        $chmod_cmd = "CHMOD " . $this->cmod_for_all . " " . $path . '/install.php';
        @ftp_site($this->id_conect, $chmod_cmd);

        umask($restire_umask);

        //Включим кнопку далее, после того как все скоприровалось
        print('<script>show_message_content("Нашмите &quot;Далее&quot; для продолжения интсталляции<br><br>")</script>');
        print('<script>show_message_content("Копирование файлов завершено<br><br>")</script>');
        print('<script>parent.window.show_message_hide();</script>');
        flush();
    }


    function ftp_creat_dir_bead($dist)
    {
        //print('Реконект к FTP при создании: '.$dist.'<br>');
        //flush();
        //Прежде всего попробуем переконектится к ФТП
        $this->create_ftp_connect();
        return @ftp_mkdir($this->id_conect, $dist);
    }

    function ftp_creat_dir($arr)
    {
        $i = 1;
        foreach ($arr as $dist => $mycmod)
        {
            $dist = str_replace("//", "/", $dist);

            if (!$this->test)
            {
                $result = 'NO';

                if (!@ftp_chdir($this->id_conect, $dist))
                    $result = @ftp_mkdir($this->id_conect, $dist);

                if ($result === false)
                {
                    //Произошла какая то ошибка при создании директории
                    if (!$this->ftp_creat_dir_bead($dist))
                    {
                        print('<script>show_message_content("Директория так и не создана: ' . $dist . '")</script>');
                        print('<script>show_message_content("Автоматическая инсталляция невозможна")</script>');
                        flush();
                        die();
                    }
                }

                if ($result == 'NO')
                    print('<script>show_message_content("Уже существует: ' . $dist . '")</script>');
                else
                {
                    $chmod_cmd = "CHMOD " . $mycmod . " " . $dist;
                    ftp_site($this->id_conect, $chmod_cmd);
                    print('<script>show_message_content("Создана директория: ' . $dist . '")</script>');
                }
            }
            print('<script>parent.window.show_message_dir(' . $i . ',' . count($arr) . ')</script>');
            flush();
            $i++;
        }
    }

    function ftp_creat_file_bead($dist, $src)
    {
        print('<script>parent.window.show_message("Реконект к FTP при копировании: ' . $dist . '");</script>');
        flush();
        //Прежде всего попробуем переконектится к ФТП
        $this->create_ftp_connect();
        if (preg_match("/\\.(zip|jpeg|jpg|png|gif|pdf|xls|avi)$/i", $dist))
            $upload = @ftp_put($this->id_conect, $dist, $src, FTP_BINARY);
        else
            $upload = @ftp_put($this->id_conect, $dist, $src, FTP_ASCII);
        return $upload;
    }

    function ftp_creat_file($arr, $mycmod)
    {
        foreach ($arr as $src => $dist)
        {
            $dist = str_replace("//", "/", $dist);
            if (!$this->test)
            {
                if (preg_match("/\\.(zip|jpeg|jpg|png|gif|pdf|xls|avi)$/i", $dist))
                    $upload = @ftp_put($this->id_conect, $dist, $src, FTP_BINARY);
                else
                    $upload = @ftp_put($this->id_conect, $dist, $src, FTP_ASCII);

                if (!$upload)
                {
                    //Произошла какая то ошибка при сохранении файла
                    if (!$this->ftp_creat_file_bead($dist, $src))
                    {
                        print('<script>show_message_content("Не удалось скопировать файл: ' . $dist . '")</script>');
                        print('<script>show_message_content("Автоматическая инсталляция невозможна")</script>');
                        flush();
                        die();
                    }
                }
            }
            $this->curentsize += filesize($src);
            $size = round((($this->curentsize) / 1024), 0);
            print('<script>show_message_content("Скопирован ' . $dist . '")</script>');
            print('<script>parent.window.show_message_files(' . $size . ',' . $this->fullsize . ')</script>');
            flush();
        }
    }


    function get_array_file($conn_id, $src_dir, $dst_dir, $mycmod)
    {
        $modules = $_SESSION['install_modules'];
        $d = dir($src_dir);
        while ($file = $d->read())
        {
            //Пропустим возрат на верх
            if ($file == "." || $file == "..")
                continue;
            //Если это дериктория
            if (is_dir($src_dir . "/" . $file))
            {
                //Пропускаем дирректорию с модулем, который мы не хотим исталировать
                if (($src_dir == ($this->link . '/modules')) && (!isset($modules[$src_dir . "/" . $file])))
                    continue;

                //Значит начинаем копировать папку c другими правами
                if (isset($this->dir_for_all[$file]))
                    $mycmod = $this->cmod_for_all;

                $this->dir_array[$dst_dir . "/" . $file] = $mycmod;
                $this->get_array_file($conn_id, $src_dir . "/" . $file, $dst_dir . "/" . $file, $mycmod);

                //востанавливаем права, если мы их меняем
                if (isset($this->dir_for_all[$file]))
                    $mycmod = $this->cmod_for_limit;
            }
            else
            {
                //Значит это файл
                //Отключим проверку на наличие такого файла на сервере
                $size_src = filesize($src_dir . "/" . $file);
                $this->file_array[$src_dir . "/" . $file] = $dst_dir . "/" . $file;
                $this->cmod_array[$dst_dir . "/" . $file] = $mycmod;
                $this->curentsize += $size_src;
                //$size_curent = number_format((round((($this->curentsize) / 1024), 0)), 0, '0', ' ');
            }

        }
        $d->close();
    }


    function show_modules($modtemplate)
    {
        $arr = $this->get_all_modules();
        if (!$arr)
            return '';
        $i = 0;
        $arrmod = array();
        foreach ($arr as $val)
        {
            $tmp = $modtemplate;
            $tmp = str_replace("[#label#]", $val['caption'], $tmp);
            $tmp = str_replace("[#name#]", "modules[" . $val['link'] . "]", $tmp);
            $arrmod[] = $tmp;
            $i++;
        }
        $html = join(",", $arrmod);
        return $html;
    }

    /**
     * Возвращает массив модулей, доступных для инсталяции
     *
     */
    function get_all_modules()
    {
        $ret_array = array();
        $src_dir = $this->link . '/modules';
        $d = dir($src_dir);
        $i = 0;
        while ($file = $d->read())
        {
            if ($file == "." || $file == "..")
                continue;
            if (is_dir($src_dir . "/" . $file))
            {
                $ret_array[$i]['name'] = trim($file);
                $ret_array[$i]['link'] = trim($src_dir . "/" . $file);
                $install = new install_modules();
                include($src_dir . "/" . $file . '/install.php');
                $str = $install->get_name();
                if (file_exists($src_dir . "/" . $file . '/lang/ru.php'))
                {
                    include($src_dir . "/" . $file . '/lang/ru.php');
                    $id = str_replace("[#", "", $str);
                    $id = str_replace("#]", "", $id);
                    $str = str_replace("[#" . $id . "#]", $il[$id], $str);
                }
                $ret_array[$i]['caption'] = $str;
                $ret_array[$i]['versin'] = '';
                $i++;
            }
        }
        $d->close();
        return $ret_array;
    }
}