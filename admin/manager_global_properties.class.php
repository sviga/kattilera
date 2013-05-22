<?php
/**
 * Обеспечевает редактирование общих настроек Всего движка
 *
 */
class manager_global_properties
{
	var $curent_action = '';
	var $manager_update_delimiter;

	function manager_global_properties()
	{

		if (isset($_SESSION['vars_kernel']['curent_action_in_globalprop']))
			$this->curent_action = $_SESSION['vars_kernel']['curent_action_in_globalprop'];
		else
			$this->curent_action = '';

		//Определим разделитель для файлов, при этом не в явном виде
		//что бы по нему не разделился этот файл во время передачи по обдейту
        $bad_delimiter = "92e67e11a16b91---553d74dcb1f760eb76";
        $delimiter = str_replace("---", "", $bad_delimiter);
        $this->manager_update_delimiter = $delimiter;
	}

    /**
     * Предопределённая функция для формирования меню
     *
     * Функция орпеделяет какие элементы меню присутсвуют в меню раздела
     * @param pub_interface $show Объект класса pub_interface
     * @return void
     * @access private
     */
	function interface_get_menu($show)
    {
        $show->set_menu_block('[#global_prop_label_menu1#]');
        $show->set_menu("[#global_prop_label_save_rezerv#]","backup");
        $show->set_menu("[#global_prop_label_backup_files#]","backup&backup=backup_files");
        $show->set_menu("[#global_prop_label_constant#]","sys_prop");
        $show->set_menu("[#global_prop_label_info_site#]","info_site");
        //$show->set_menu_block('[#global_prop_label_sys_action#]');
        $show->set_menu("[#global_prop_label_sys_action#]","glob_action");
        $show->set_menu_default('form_save');
    }

	function start()
    {
    	global $kernel;
        $my_post = $kernel->pub_httppost_get();
        $my_get = $kernel->pub_httpget_get();

        $action = $kernel->pub_section_leftmenu_get();
		$html_content = '';
        switch ($action)
        {
            //Формирует форму для резервного копирования сайта
            default:
            case 'backup':
                $backup_action = $kernel->pub_httpget_get("backup");
				$backup = new backup();
				$html_content .= $backup->backup_start($backup_action, $my_get, $my_post);
            	break;

            //Выводим форму, с глобальными параметрами, прописанными та же в файле ini.php
            case 'sys_prop':

            	$html_content = $this->show_form_global_param();
                break;

            //Записывает отредактированные свойства в ini файл
            case 'save_sys_prop':
                $this->save_sys_properties();
                //$kernel->pub_redirect_refresh_reload("sys_prop");
                $html_content = $kernel->pub_json_encode(array("info"=>"[#kernel_ajax_data_saved_ok#]","success"=>true));
            	break;

            //Выводит информацию о ПО используемом на сайте
            case 'info_site':
            	$html_content = $this->show_form_info();
				break;

			//Выводит список доступных системных дейсвтий, которые можно совершить на сайте
			case 'glob_action':
			    $html_content = $this->global_actio_start();
			    break;


			//Вызвано действие с переинсталяицей языковых переменных
			case 'lang_reinstal':
			    $this->lang_reinstall();
			    $kernel->pub_redirect_refresh('glob_action');
			    break;

            //Вызывает действие по простановке полных прав
			case 'set_full_cmod':
			    $mychmod = new manager_chmod();
			    $mychmod->files_set_acces(true);
			    $kernel->pub_redirect_refresh('glob_action');
			    break;

			//Вызывает действие по простановке ограниченных прав
			case 'set_lim_cmod':
			    $mychmod = new manager_chmod();
			    $mychmod->files_set_acces();
			    $kernel->pub_redirect_refresh('glob_action');
			    break;

			//Теперь обновление тоже без полных
			//прав, и потому мы сразу приступаем к обновлению
			case 'update_step_1':

			    $this->manager_update(SANTAFOX_VERSION);
			    $kernel->pub_redirect_refresh('update_step_4');
                break;
           case 'update_step_4':
                $this->lang_reinstall();
                $kernel->pub_redirect_refresh("info_site");
                break;

        }
	    return $html_content;
    }

    /**
     * Формирует форму, для редактирования параметров INI файла.
     *
     * @return string
     */
    function show_form_global_param()
    {
    	global $kernel;
    	$html = file_get_contents("admin/templates/default/edit_ini_file.html");
    	//Сразу пропишем текущее дествие для формы
    	$html = str_replace("[#form_aсtion#]", $kernel->pub_redirect_for_form("save_sys_prop"), $html);
		foreach ($this->parse_global_ini_file() as $key => $val)
		{
            $val = trim($val);
            if ($val=="true")
                $val="checked";
            elseif ($val=="false")
                $val="";
			$html = str_replace("[#".trim(strtolower($key))."_value#]", $val, $html);
		}
    	return $html;
    }


     /**
     * Возвращает в виде массива, все настройки прописаные через define в ini файле.
     * @return array
     */
    function parse_global_ini_file()
    {
    	$content = file_get_contents('ini.php');
   		$str_preg  = "/define(?:\\s*)\\((.*)\\)(?:\\s*);/iU";

   		$array_define = array();
    	preg_match_all($str_preg, $content, $array_define);
    	$array_define = $array_define[1];

    	$ret = array();
    	foreach ($array_define as $val)
    	{
    		$tmp = explode(",",$val);
    		$new_key = $tmp[0];
    		$new_val = $tmp[1];
    		$new_key = str_replace("'", " ", $new_key);
    		$new_val = str_replace("'", " ", $new_val);
    		$new_key = str_replace('"', " ", $new_key);
    		$new_val = str_replace('"', " ", $new_val);

    		$ret[trim($new_key)] = trim($new_val);
    	}
    	//Добавим заплатку для старых версий, в которых может не быть как-их
    	//то парметров
    	$ret['ssl_connection'] = 'false';
    	$ret['webform_coding'] = 'false';
        if (!isset($ret['PRINT_MYSQL_ERRORS']))
            $ret['PRINT_MYSQL_ERRORS']='false';
        if (!isset($ret['IS_1251_TEMPLATES']))
            $ret['IS_1251_TEMPLATES']='false';
        if (!isset($ret['PAGE_FOR_404']))
            $ret['PAGE_FOR_404']='index';

    	return $ret;
    }

      /**
     * Сохраняет данные формы в INI файл.
     *
     */
    function save_sys_properties()
    {
    	global $kernel;
    	$array_data = array();
    	$post       = $kernel->pub_httppost_get();
        foreach ($post as $key => $val)
            $array_data[$key] = '"'.$val.'"';
        if (isset($array_data['time_creat']))
            $array_data['time_creat'] = "true";
        else
            $array_data['time_creat'] = "false";

        if (isset($array_data['generate_statistic']))
            $array_data['generate_statistic'] = "true";
        else
            $array_data['generate_statistic'] = "false";

    	if (isset($array_data['close_windows_on_save']))
    	    $array_data['close_windows_on_save'] = "true";
    	else
    	    $array_data['close_windows_on_save'] = "false";

    	if (isset($array_data['show_int_errore_message']))
    	    $array_data['show_int_errore_message'] = "true";
    	else
    	    $array_data['show_int_errore_message'] = "false";

    	if (isset($array_data['cached_page']))
    	    $array_data['cached_page'] = "true";
    	else
    	    $array_data['cached_page'] = "false";

    	if (isset($array_data['redir_www']))
    	    $array_data['redir_www'] = "true";
    	else
    	    $array_data['redir_www'] = "false";

    	if (isset($array_data['ssl_connection']))
    	    $array_data['ssl_connection'] = "true";
    	else
    	    $array_data['ssl_connection'] = "false";

    	if (isset($array_data['webform_coding']))
    	    $array_data['webform_coding'] = "true";
    	else
    	    $array_data['webform_coding'] = "false";

    	if (isset($array_data['print_mysql_errors']))
    	    $array_data['print_mysql_errors'] = "true";
    	else
    	    $array_data['print_mysql_errors'] = "false";

    	if (isset($array_data['is_1251_templates']))
    	    $array_data['is_1251_templates'] = "true";
    	else
    	    $array_data['is_1251_templates'] = "false";

        if (isset($array_data['ftp_host']) && !empty($array_data['ftp_host']) && substr($array_data['ftp_host'],0,6)=="ftp://")
    	    $array_data['ftp_host'] = substr($array_data['ftp_host'],6);


        $array_data['SANTAFOX_VERSION'] = '"'.SANTAFOX_VERSION.'"';

    	if (!empty($array_data))
    	{
    		$str_ini_php = "";
    		foreach ($array_data as $key => $val)
    			$str_ini_php .= '    define("'.strtoupper($key).'", '.trim($val).')'.";\n";
			if (!empty($str_ini_php))
			{
			    $str_ini_php = "<?php\n    mb_internal_encoding(\"UTF-8\");\n".$str_ini_php."\n?>";
			    $kernel->pub_file_save($kernel->pub_site_root_get()."/ini.php", $str_ini_php);
			}
    	}
    }


    function show_form_info()
    {
    	global $kernel;

    	//Зачитаем структуру файлов и узнаем версии
    	$html = $kernel->pub_template_parse("admin/templates/default/info_sait.html");
    	$html = $html['body'];
    	$html = str_replace('[#SERVER_SOFTWARE#]', $_SERVER['SERVER_SOFTWARE'], $html);
    	$html = str_replace('[#SERVER_ADDR#]', $_SERVER['SERVER_ADDR'], $html);
    	$html = str_replace('[#REMOTE_ADDR#]', $_SERVER['REMOTE_ADDR'], $html);
    	$html = str_replace('[#version_kernel#]', SANTAFOX_VERSION, $html);


        //Проверим возможность скриптом менять права всех файлах сайта
        $result = '--';

        $html = str_replace('[#SERVER_CAN_CHMOD_SCRIPT#]', $result, $html);

        //Теперь проверим возможность подключения к FTP
        $html = str_replace('[#SERVER_CAN_FTP_CONNECT#]', $result, $html);

        $descript = $this->update_get(SANTAFOX_VERSION);
        switch($descript)
        {
            case -2:
                $descript = '[#admin_glob_prop_update_no_uplink#]';
                break;
            case -1;
                $descript = '[#admin_glob_prop_update_no_update#]';
                break;
            case -3;
                $descript = '[#admin_glob_prop_modified_version_no_update#]';
                break;
            default:
                $descript = '<p><button onclick="jspub_click(\'update_step_1\');">Обновить</button></p>'.$descript;//@todo move to template
                break;
        }
        $html = str_replace('[#new_version_info#]', $descript, $html);
    	return $html;
    }


    /**
     * Возвращает описание доступного обновления, если оно есть
     *
     * @param string $version Строка типа #.# ,где # - число
     * @param boolean $only_code
     * @return string
     */

    function update_get($version, $only_code = false)
    {
        if (preg_match('~m$~i',$version))
            return -3;

        if ($only_code && isset($_SESSION['vars_kernel']['need_update_santa']))
            return $_SESSION['vars_kernel']['need_update_santa'];


        $content = $this->file_get($version, "request");

        //Сообщение о том что сервер не доступен
        $result = -2;
        if ($content != false)
        {
            $temp_files = explode($this->manager_update_delimiter, $content);

            if ((isset($temp_files[0])) && (isset($temp_files[1])))
            {
                $new_version = $temp_files[0];
                //Проверка на совпадение версий
                if ($version == $new_version)
                    $result = -1;
                else
                {
                    $description = $temp_files[1];
                    if ($only_code)
                        $result = 1;
                    else
                        $result = $description;
                }
            }
        }
        $_SESSION['vars_kernel']['need_update_santa'] = $result;

        return $result;
    }


    /**
     * Производит процесс обновления файлов в системе
     *
     * @param string $version Текущая версия движка
     * @return string
     */
    function manager_update($version)
    {
        global $kernel;

        $files = array();
        $root = $kernel->pub_site_root_get();

        $content = $this->file_get($version);
        if ($content === false)
            return false;


        $temp_files = explode($this->manager_update_delimiter, $content);
        $new_version = $temp_files[0];

        //Получаем имена файлов, которые нужно переписать
        unset($temp_files[0], $content);
		
        foreach ($temp_files AS $file)
        {
			
            preg_match("/\\{\\@(.*?)\\@\\}(.*)/is", $file, $result);
            $filename = $result[1];
            $files[$filename] = $result[2];
        }

        //Определим модули, которые есть у клиента
        $modules_arr = array();
        $dir = opendir($root."/modules");
        while ($file = readdir($dir))
        {
            if (($file != ".") && ($file != ".."))
            {
                if (is_dir($root."/modules/".$file))
                    $modules_arr[] = "^/modules/".$file;
            }
        }

        //Уберем из апдейта те модули, которых нет у клиента
        $need_reinstal = array();
        $modules_str = implode("|", $modules_arr);
        foreach ($files AS $tmp_filename => $null)
        {
            if (preg_match("|^/modules/([a-z0-9_-]+)\\.*|i", $tmp_filename, $tmp_arr))
            {
                $need_reinstal[$tmp_arr[1]] = $tmp_arr[1];
                if (!preg_match("@".$modules_str."@i", $tmp_filename))
                    unset($files[$tmp_filename]);
            }
        }

        //Собственно начнем процесс апдейта
        $upd_temp_dir = "/upload/update".$new_version;
        $kernel->pub_file_dir_create($root.$upd_temp_dir);

        // Сохраняем во временную папку
        //прежде всего создадим в необходимые дирректории во врменной папке
        $this->update_dir_create($files, $upd_temp_dir);

        $html = '';
        foreach ($files AS $path => $content)
        {
            if (!$kernel->pub_file_save($upd_temp_dir.$path, $content))
                $html .= $this->error_message_get($upd_temp_dir.$path);
            else
                $html .= 'Upload file: '.$path.'<br>';
        }

        //Файлы получены и лежат в папке update
        //Запустим инструкции перед копированием
        error_reporting(E_ALL);
        include_once($root.$upd_temp_dir."/_update.php");

        $update = new site_update();
        $update->start();
			
        unset($files["/_update.php"], $files["/_description.html"]);
        reset($files);

        //Теперь, нужно пройтись и создать в случае необходимости директории уже в самом сайте
        $this->update_dir_create($files, "/");

        //Непосредственно копирование файлов
        $errore_copy = false;
        foreach ($files AS $path => $content)
        {
            if (!$kernel->pub_file_copy($upd_temp_dir.$path, $root.$path))
            {
                $errore_copy = true;
                $html .= "Ошибка копирования <i>".$upd_temp_dir.$path."</i> в <i>".$root.$path."</i><br>";
            }
            else
                $html .= 'Copy: '.$root.$path.'<br>';
        }
        //Инструкции пост копирования
        $update->end();

        $array_modules = $kernel->pub_modules_get();
        $manager_modules = new manager_modules();
        foreach ($array_modules AS $modul_id => $modul_val)
        {
            if ((empty($modul_val['id_parent'])) && (isset($need_reinstal[$modul_id])))
                $manager_modules->reinstall_module($modul_id);
        }

        //Укажем новую версию в ini файле если не было ошибок в обновлении
        if ($errore_copy)
        {
            $kernel->debug($html, true);
            die(0);
        }
        if ($ini_php = file_get_contents($root."/ini.php"))
        {
            $ini_php = str_replace('define("SANTAFOX_VERSION", "'.$version.'");', 'define("SANTAFOX_VERSION", "'.$new_version.'");', $ini_php);
            $kernel->pub_file_save("/ini.php", $ini_php);
            $kernel->pub_file_delete($root.$upd_temp_dir,true);
        }
        else
            $html .= $this->error_message_get('Отсутствует файл ini.php');
        return $html;
    }


    function file_get($version, $request="update")
    {
        if (defined('DO_NOT_UPDATE') && DO_NOT_UPDATE) {
            return false;
        }
        $file_content = "";
        if (!($resource = @fopen("http://update.santafox.ru/?ver=".$version."&request=".$request, "r")))
            return false;
        while (!feof($resource))
        {
            $file_content .= fread($resource, 8192);
        }
        fclose($resource);
        if (!preg_match("|".$this->manager_update_delimiter."|", $file_content))
            return false;

        return $file_content;
    }


    function error_message_get($file)
    {
        return "ERROR! (".$file.")<br>";
    }


    /**
     * Сохдаёт папки, которые возможно появились в обновлении
     *
     * @param array $files
     * @param string $link
     * @return boolean
     */
    function update_dir_create($files, $link)
    {
    	global $kernel;

    	$arr = array();
    	//Сначала создадаим список уникальных директорий
    	foreach ($files as $key => $val)
    	{
    		$path = pathinfo($key);
	        $path = $path['dirname'];
	        $arr[$path] = $path;
    	}
    	//Теперь будем создавать директории
    	$result = true;
    	foreach ($arr as $val)
    	{
    	   $result = $kernel->pub_dir_create_in_files($link.$val, true);
    	}
    	return $result;
    }

    function global_actio_start()
    {
        $html = file_get_contents('admin/templates/default/global_actions.html');
        return $html;
    }

    /**
     * Функция проводит переинсталяцию существующих языковых переменных
     *
     * Обновляются как языковые переменные на уровне ядра иак и на уровне модулей
     */
    function lang_reinstall()
    {
        $m_table = new mysql_table();

        $modul = new manager_modules();
        $arr = $modul->return_modules();

        //Определим тем записи, которые нам нужно оставить
        //А оставить нам нужно названия модуля для разных языков
        $lang_save = array();
        foreach ($arr as $val)
        {
            $lkey = $val['caption'];
            $lkey = str_replace("[#", "", $lkey);
            $lkey = str_replace("#]", "", $lkey);
            $lang_save[] = $lkey;
        }

        $m_table->lang_all_clear($lang_save);
        //Основные языковые переменные
        $m_table->add_langauge('include/install/lang');

        foreach ($arr as $key => $val)
        {
            if (!empty($val['id_parent']))
                continue;

            //Это модуль родитель. Его языковые переменые и проинсталируем
            $m_table->add_langauge('modules/'.$key.'/lang');
        }
    }

}