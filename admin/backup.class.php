<?php

class backup
{
    private $root;
    private $path_content = array("/content");
    private $path_system = array(
        "/admin",
        "/include",
        "/modules"
    );
    private $path_design = array("/design");
    private $path_save;
    private $debug_trace;


    function __construct()
    {
        $this->root = dirname(dirname(__FILE__));
        $this->path_save = $this->root."/backup/";
    }

    function error_last_get()
    {
        return $this->debug_trace;
    }


    function error_last_set($data)
    {
        $this->debug_trace = $data;
    }


    function backup_restore($id, $file_index)
    {
        global $kernel;
        //В качесте $id нам могут передать имя файла, а так же
        //непосредствено ID mysql записи
        if (intval($id)>0)
        {
            $sql = "SELECT real_filename
                    FROM ".PREFIX."_backup
                    WHERE id='".$id."'";
            $result = $kernel->runSQL($sql);
            $data = mysql_fetch_assoc($result);
            $real_filename = $data['real_filename'];
        }
        elseif (file_exists($this->root."/backup/".$id))
            $real_filename = $id;
        else
            return false;

		require_once(dirname(dirname(__FILE__))."/components/pclzip/pclzip.lib.php");

		$full_zip_path = $this->root."/backup/".$real_filename;
		$archive = new PclZip($full_zip_path);
		$list = $archive->listContent();
		if ($list == 0) //ошибка чтения архива
		{
		    $kernel->pub_console_show("open zip error: ".$archive->errorName().", file: ".$full_zip_path);
		    flush();
		    $this->error_last_set("open zip error: ".$archive->errorName().", file: ".$full_zip_path);
		    return false;
		}
		$total = count($list);

		if ($total == $file_index)
		{
		    $kernel->pub_console_show("Done!");
		    flush();
		    return true;
		}

        $afile = $list[$file_index];
		if ($file_index == 0)
		    $need_santa_root_check = true;
		else
		    $need_santa_root_check = false;


	    //if ((int)ini_get('max_execution_time') < 180)
	    ini_set('max_execution_time', 180);


	    //$list1 = $archive->extract(PCLZIP_OPT_BY_NAME, $afile['stored_filename'], PCLZIP_OPT_EXTRACT_AS_STRING);
	    $list1 = $archive->extract(PCLZIP_OPT_BY_INDEX, array($file_index), PCLZIP_OPT_EXTRACT_AS_STRING);
	    if ($list1 == 0)
	    {
	        $this->error_last_set("extract zip error: ".$archive->errorName());
	        $kernel->pub_console_show("extract zip error: ".$archive->errorName());
	        flush();
	        return false;
	    }

	    $kernel->pub_console_show('file: '.$afile['stored_filename']);
	    flush();

	    $file_content = $list1[0]['content'];

	    if (substr($afile['stored_filename'], 0, 5) == "data/")
	    {//файлы контента и системные

	        $relative_filename = substr($afile['stored_filename'], 5);
		    $full_path = $relative_filename;
		    $pathinfo = pathinfo($relative_filename);

            if(!$pathinfo['dirname'])//может быть и такое в архивах созданных вручную
                $kernel->pub_redirect_refresh("backup&backup=restore_step1&id=".$id."&total=".$total."&findex=".++$file_index);
		    if (!$kernel->pub_file_dir_create($pathinfo['dirname']))
		    {
    	        $kernel->pub_console_show("dir create failed: ".$pathinfo['dirname']);
    	        flush();
    	        return false;
		    }

		    $file_save_res = $kernel->pub_file_save($full_path, $file_content, $kernel->is_ftp_credentionals_set(), $need_santa_root_check);
            if (!$file_save_res)
            {
                $err = debug_backtrace();
                $err = $err[0];
                if (empty($err))
                    $err = "Failed to restore file ".$full_path;
                $this->error_last_set($err);
                $kernel->pub_console_show($err);
                flush();
            }
	    }
	    elseif (substr($afile['stored_filename'], 0, 4) == "sql/")
	    {//sql-файлы
	    	$queries = explode(";\n\r", $file_content);
            foreach ($queries AS $query)
            {
                $query = trim($query);
                if (empty($query))
                    continue;
                $query = str_replace("`%PREFIX%","`".PREFIX, $query);
                $kernel->runSQL($query);
            }
	    }
		$kernel->pub_redirect_refresh("backup&backup=restore_step1&id=".$id."&total=".$total."&findex=".++$file_index);
        return true;
    }

    function backup_download($id)
    {
        global $kernel;
        $full_path = false;
        $filename="";
        if (is_numeric($id) && $id > 0)
        {

            $data = $kernel->db_get_record_simple("_backup","id='".intval($id)."'");
            if ($data)
            {
                $real_filename = $data['real_filename'];
                $filename = $data['filename'];
                $full_path = $this->root."/backup/".$real_filename;
            }
        }
        else
            $full_path = $this->root."/backup/".$id;

        if (!$filename)
            $filename=$id;
        if ($full_path)
        {
            header("Cache-control: private");
            header("Content-Type: application/zip");
            header("Content-Size: ".filesize($full_path));
            header("Content-Disposition: attachment; filename=".$filename);
            ob_clean();
            flush();
            readfile($full_path);
            die();
        }
        else
            $kernel->pub_redirect_refresh("backup&backup=backup_files");
    }

    /**
     * Выполняет бэкап для правила
     * передаём для чего надо выполнять бэкап $needcontent, $needsystem, $needtables
     * т.к. при выполнении из админки может быть override для правила
     *
     * @param array $rule правило бэкапа
     * @param boolean $needcontent
     * @param boolean $needsystem
     * @param boolean $needtables
     * @param boolean $needdesign
     * @param string $descr описание
     * @return boolean
     */
    function backup_run_save($rule, $needcontent, $needsystem, $needtables, $needdesign, $descr='')
    {
        global $kernel;
        $root = dirname(dirname(__FILE__));

        if (!is_dir($root."/backup"))
            $kernel->pub_file_dir_create($root."/backup");

        $ignored_paths = $this->get_ignored_paths($rule['id']);
        $ignored_exts = $this->get_ignored_extensions($rule['id']);

        $data_files = array();
        $sql_files = array();
        $bool_content = "0";
        $bool_system = "0";
        $bool_mysql = "0";
        $bool_design = "0";
        $str_filename = array();

        if ($needcontent)
        {
            $content_files = $this->get_files4backup($this->path_content,$ignored_paths, $ignored_exts);
            $data_files = array_merge($content_files, $data_files);
            $str_filename[] = "content";
            $bool_content = "1";
        }
        if ($needsystem)
        {
            $system_files = $this->get_files4backup($this->path_system, $ignored_paths, $ignored_exts);
            $data_files = array_merge($system_files, $data_files);
            $str_filename[] = "system";
            $bool_system = "1";
        }
        if ($needdesign)
        {
            $system_files = $this->get_files4backup($this->path_design, $ignored_paths, $ignored_exts);
            $data_files = array_merge($system_files, $data_files);
            $str_filename[] = "design";
            $bool_design = "1";
        }

        if ($needtables)
        {
            $str_filename[] = "mysql";
            $bool_mysql = "1";
        }

        if (isset($_SERVER['HTTP_HOST']))
        {
            $httphost = $_SERVER['HTTP_HOST'];
            if (strlen($httphost)>3 && strtolower(substr($httphost, 0, 4))=="www.")
                $httphost = substr($httphost, 4);
        }
        else
            $httphost = "cron";

        $uniq_id = $httphost."-".date("Y-m-d")."-".implode("-", $str_filename)."-".substr(md5(time()), 0, 16);

        if ($needtables)
        {
            $kernel->pub_file_dir_create($root."/backup/".$uniq_id."/");
            $sql_files = $this->dump_mysql_table4backup($rule['id'], $uniq_id);
            if ($sql_files == false)
                return false;
        }

        if (empty($data_files) && empty($sql_files))
            return false;
        $description = htmlspecialchars(strip_tags($descr));

        define('PCLZIP_TEMPORARY_DIR', $root."/backup/");
		include($root."/components/pclzip/pclzip.lib.php");
		$real_archive_name = $uniq_id.".zip";
		$name_new_archive = $kernel->priv_file_full_patch("backup/".$real_archive_name);



        $archive = new PclZip($name_new_archive);
        if (empty($sql_files))
        {//только системные и контент-файлы
            $archive->create($data_files, PCLZIP_OPT_REMOVE_PATH, $root, PCLZIP_OPT_ADD_PATH, "data");
        }
        elseif (empty($data_files))
        {//только SQL-дампы
            $archive->create($sql_files, PCLZIP_OPT_REMOVE_PATH, $root."/backup/".$uniq_id, PCLZIP_OPT_ADD_PATH, "sql");
            $kernel->pub_file_delete($root."/backup/".$uniq_id."/", true);
        }
        else
        {// дампы + файлы
            $archive->create($data_files, PCLZIP_OPT_REMOVE_PATH, $root, PCLZIP_OPT_ADD_PATH, "data");
            //$addres =
            $archive->add($sql_files, PCLZIP_OPT_REMOVE_PATH, $root."/backup/".$uniq_id."/", PCLZIP_OPT_ADD_PATH, "sql");
            //if ($addres == 0)
            //    print "zip err:".$archive->errorName().":".$archive->errorInfo();
            $kernel->pub_file_delete($root."/backup/".$uniq_id."/", true);
        }
        $archive_filename = $httphost."-".date("Y-m-d")."-".implode("-", $str_filename).".zip";

        $sql = "INSERT INTO
                ".PREFIX."_backup
                (real_filename, filename, date, content, system, mysql, design, description)
                VALUES
                (
                '".$real_archive_name."',
                '".$archive_filename."',
                NOW(),
                '".$bool_content."',
                '".$bool_system."',
                '".$bool_mysql."',
                '".$bool_design."',
                '".$description."'
                )
                ";
        $kernel->runSQL($sql);
        //аплоад на фтп, если указаны данные
        if (!empty($rule['ftphost']) && !empty($rule['ftpuser']) &&
            !empty($rule['ftppass']) && !empty($rule['ftpdir']))
        {
            require_once($root."/include/ftpshnik.class.php");
            $ftpshnik = new ftpshnik($rule['ftphost'], $rule['ftpuser'], $rule['ftppass'], $rule['ftpdir']);
            if (!$ftpshnik->init($rule['ftpdir']))
            {
                 $this->error_last_set("FTP: ".$ftpshnik->getLastError());
                 return false;
            }
            $zip_archive = "backup/".$real_archive_name;
            if (!$ftpshnik->putFile($zip_archive, $real_archive_name, true))
            {
                if (!$ftpshnik->putFile($zip_archive, $real_archive_name, true,true))
                {
                    $this->error_last_set("FTP: ".$ftpshnik->getLastError()."\ndebug trace:".implode("\n",$ftpshnik->debugMessages));
                    $ftpshnik->disconnect();
                    return false;
                }
            }
            $ftpshnik->disconnect();
        }

        return true;
    }

    function backup_delete($id)
    {
        global $kernel;
        $root = $kernel->pub_site_root_get();

        $sql = "SELECT
                real_filename
                FROM
                ".PREFIX."_backup
                WHERE
                id='".$id."'
                ";
        $result = $kernel->runSQL($sql);
        if (mysql_num_rows($result))
        {
            $data = mysql_fetch_assoc($result);
            $real_filename = $data['real_filename'];

            $kernel->pub_file_delete($root."/backup/".$real_filename);

            $sql = "DELETE
                    FROM
                    ".PREFIX."_backup
                    WHERE
                    id='".$id."'
                    ";
            $kernel->runSQL($sql);
            return true;
        }
        return false;
    }


    function backup_table_get()
    {
        global $kernel;
        $html = "";
        //$root = $kernel->pub_site_root_get();
        $sql = "SELECT
                *, UNIX_TIMESTAMP(date) as `mtime`
                FROM
                ".PREFIX."_backup
                ORDER BY
                date
                DESC
                ";
        $result = $kernel->runSQL($sql);
        if (mysql_num_rows($result))
        {
            $lines = "";
            $template = $kernel->pub_template_parse("admin/templates/default/backup_files.html");

            $html = $template['backup_table'];
            $i = 1;
            while ($data = mysql_fetch_assoc($result))
            {
                $archive_content_arr = array();
                $line = $template['backup_table_row'];
                $line = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($i), $line);
                if ($data['content'] == "1")
                    $archive_content_arr[] = "[#admin_save_components_save_content#]";
                if ($data['system'] == "1")
                    $archive_content_arr[] = "[#admin_save_components_save_system#]";
                if ($data['mysql'] == "1")
                    $archive_content_arr[] = "[#admin_save_components_save_mysql#]";
                if ($data['design'] == "1")
                    $archive_content_arr[] = "[#admin_save_components_save_design#]";
                $archive_content_str = implode(", ", $archive_content_arr);

                $data ['real_filename'] = $kernel->pub_redirect_for_form("rules&backup=dwld&id=".trim($data['id']));
                $data['date'] = $kernel->pub_data_to_string(date('Y.m.d', $data['mtime']), true);
                $line = str_replace("%archive_content%", $archive_content_str, $line);

                $line = $kernel->pub_array_key_2_value($line, $data);
                $line = str_replace("%num_order%", $i, $line);
                $lines .= $line;
                $i++;
            }
            $html = str_replace("%lines%", $lines, $html);
        }
        return $html;
    }

    function backup_table_get_uploaded($files)
    {
        global $kernel;
        $sql = 'SELECT * FROM '.PREFIX.'_backup';
        $result = $kernel->runSQL($sql);

        $array = array();
        while ($row = mysql_fetch_assoc($result))
        {
            $array[] = $row['real_filename'];
        }
        $files = array_diff($files, $array);

        $template = $kernel->pub_template_parse("admin/templates/default/backup_files.html");
        $content = $template['backup_table'];

        $lines = ''; $i = 1;
        foreach ($files as $value)
        {
            if ($value == '.' || $value == '..'|| $value == '.htaccess') {
            	continue;
            }
            $line = $template['backup_table_row'];
            $line = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($i), $line);

            $info = stat('backup/'.$value);

            $line = str_replace("%num_order%", $i++, $line);
            $line = str_replace("%archive_content%", 'Неизвестно', $line);
            $line = str_replace("%description%", '', $line);
            $line = str_replace('%real_filename%', $kernel->pub_redirect_for_form("rules&backup=dwld&id=".$value), $line);
            $line = str_replace(array('%id%', '%filename%'), $value, $line);
            $line = str_replace("%date%", $kernel->pub_data_to_string(date('Y.m.d', $info['mtime']), true), $line);
            $lines .= $line;
        }
        $content = str_replace("%lines%", $lines, $content);

        return $content;
    }

    /**
     * Возвращает список всех вложенных папок в папке (рекурсивно)
     *
     * @param string $path путь к папке
     * @param boolean $recursive рекурсивно?
     * @return array
     */
    function dirlist_recursive_get($path, $recursive=true)
    {
        $root = $this->root;
        $dir = opendir($root.$path);
        $array = array();
        while ($file = readdir($dir))
        {
            if (($file != ".") && ($file != ".."))
            {
                if (is_dir($root.$path."/".$file."/"))
                {
                    //$array[] = $root.$path."/".$file."/";
                    $array[] = $path."/".$file."/";
                    if ($recursive)
                        $array = array_merge($array, $this->dirlist_recursive_get($path."/".$file, $recursive));
                }
            }
        }
        closedir($dir);
        return $array;
    }

    /**
     * Возвращает список всех вложенных файлов в папке (рекурсивно)
     *
     * @param string $path путь к папке
     * @param boolean $recursive рекурсивно?
     * @return array
     */
    function filelist_recursive_get($path, $recursive=true)
    {
        $root = $this->root;
        $dir = opendir($root.$path);
        $array = array();
        while ($file = readdir($dir))
        {
            if (($file != ".") && ($file != ".."))
            {
                if (is_file($root.$path."/".$file))
                {
                    $array[] = $root.$path."/".$file;
                }
                elseif (is_dir($root.$path."/".$file) && $recursive)
                {
                    $array = array_merge($array, $this->filelist_recursive_get($path."/".$file, $recursive));
                }
            }
        }
        closedir($dir);
        return $array;
    }

    /**
     * Возвращает файлы по указанным путям, которые надо забэкапить
     * с учётом игнорируемых путей и расширений
     *
     * @param array $paths пути, откуда собираем файлы
     * @param array $ignored_paths игнорируемые пути
     * @param array $ignored_exts игнорируемые расширения
     * @return array
     */
    function get_files4backup($paths, $ignored_paths, $ignored_exts)
    {
        $root = $this->root;
        $saved_files = array();
        if (count($paths) ==1 && $paths[0] == "/design")
        {//если это папка design, то добавим и юзер-темплейты всех модулей
            $mod_dirs = $this->dirlist_recursive_get("/modules", false);
            foreach ($mod_dirs as $mod_dir)
            {
                if (is_dir($root.$mod_dir."templates_user"))
                    $paths[] = $mod_dir."templates_user";
            }
        }
        foreach ($paths as $path)
        {
            $array_files = $this->filelist_recursive_get($path);
            foreach ($array_files as $filepath)
            {
                $epos=strrpos($filepath, ".");
                if ($epos === false)
                    continue;
                $ext = strtolower(substr($filepath, $epos+1));
                if (in_array($ext, $ignored_exts))
                    continue;
                foreach ($ignored_paths as $ipath)
                {
                    if (strpos($filepath, $ipath) !== false)
                        continue 2;
                }
                $saved_files[] = $filepath;
            }
        }
        return $saved_files;
    }

    /**
     * Возвращает список таблиц, которые на надо бэкапить
     *
     * @param number $id ID-шник правила
     * @return array
     */
    private function get_ignored_tables($id)
    {
        global $kernel;
        $query = "SELECT `tablename` FROM `".PREFIX."_backup_ignoredtables` WHERE `ruleid`=".$id;
        $result = $kernel->runSQL($query);
        $items = array();
        while ($row = mysql_fetch_assoc($result))
            $items[] = $row['tablename'];
        mysql_free_result($result);
        return $items;
    }

    /**
     * Возвращает список расширений, которые на надо бэкапить
     *
     * @param number $id ID-шник правила
     * @return array
     */
    private function get_ignored_extensions($id)
    {
        global $kernel;
        $query = "SELECT `ext` FROM `".PREFIX."_backup_ignoredexts` WHERE `ruleid`=".$id;
        $result = $kernel->runSQL($query);
        $items = array();
        while ($row = mysql_fetch_assoc($result))
            $items[] = $row['ext'];
        mysql_free_result($result);
        return $items;
    }
    /**
     * Возвращает список путей, которые на надо бэкапить
     *
     * @param number $id ID-шник правила
     * @return array
     */
    private function get_ignored_paths($id)
    {
        global $kernel;
        $query = "SELECT `path` FROM `".PREFIX."_backup_ignoredpaths` WHERE `ruleid`=".$id;
        $result = $kernel->runSQL($query);
        $items = array();
        while ($row = mysql_fetch_assoc($result))
            $items[] = $row['path'];
        mysql_free_result($result);
        return $items;
    }


    /**
     * Возвращает всех список таблиц сантафокс (те, что с префиксом PREFIX)
     *
     * @return array
     */
    function get_santa_tables()
    {
        global $kernel;
        $tables = array();
        $result = $kernel->runSQL("SHOW TABLES");
        while ($row = mysql_fetch_row($result))
		{
		    $table_name = $row[0];
			//Надо проверить, что бы это была таблица с нужным нам префиксом
       		$ppos = strpos($table_name, PREFIX."_");
       		if ($ppos!=0 || $ppos === false)
       			continue;
       		$tables[] = $table_name;
		}
		mysql_free_result($result);
        return $tables;
    }

    /**
     * Сохраняет всю структуру mySql в файл для бэакапа в файлы
     * (за исключением игнорируемых таблиц)
     *
     * @param number $ruleid ID-шник правила
     * @param string $uniqID уникальный ID-шник бэкапа
     * @return array
     */
    function dump_mysql_table4backup($ruleid, $uniqID)
    {
    	global $kernel;
    	$ignored_tables = $this->get_ignored_tables($ruleid);
    	$tables = $this->get_santa_tables();
    	$saved_files = array();
		foreach ($tables as $table_name)
		{
		    $str_save = '';
       		//если таблица в списке игнорируемых, то пропускаем
       		if (in_array($table_name,$ignored_tables))
       		    continue;
       		$table_name_prefixed = preg_replace("/^".PREFIX."_/", "%PREFIX%_",$table_name);
       		//Значит это та таблица, которую нам надо загужать
       		//Сначала пропишем строку с удаленнием этой таблицы
			$str_save .= "DROP TABLE IF EXISTS `".$table_name_prefixed."`;\n\r\n\r\n\r";
			$res = $kernel->runSQL("SHOW CREATE TABLE `".$table_name."`");
			$row_table = mysql_fetch_array($res);
			$table_sql = $row_table[1];
			$table_sql = str_replace($table_name, $table_name_prefixed, $table_sql);
			$str_save .= $table_sql.";\n\r\n\r";

			//Теперь можем вставлять непосредственно данные
			$res = $kernel->runSQL("SELECT * FROM ".$table_name);
			while ($data = mysql_fetch_assoc($res))
			{
				foreach ($data as $key => $val)
				{
                    if (is_null($val))
						$data[$key] = "NULL";
					else
						$data[$key] = "'".mysql_real_escape_string($val)."'";
				}
				$str_save .= "INSERT INTO `".$table_name_prefixed."` VALUES (".join(",",$data).");\n\r";
			}
			//$str_save .= "\n\r\n\r\n\r\n\r\n\r\n\r";
			$sql_file_path = $this->path_save.$uniqID."/".$table_name.".sql";
			$saved_files[] = $sql_file_path;
			if (!$kernel->pub_file_save($sql_file_path, $str_save))
			    return false;
   		}
   		return $saved_files;
    }

    /**
     * Возвращает список бэкап-правил из БД
     *
     * @return array
     */
    function get_backup_rules()
    {
        global $kernel;
        $query = "SELECT * FROM `".PREFIX."_backup_rules`";
        $result = $kernel->runSQL($query);
        $items = array();
        while ($row = mysql_fetch_assoc($result))
            $items[] = $row;
        mysql_free_result($result);
        return $items;
    }

    /**
     * Возвращает бэкап-правило по ID
     *
     * @param number $id
     * @return array
     */
    function get_backup_rule($id)
    {
        global $kernel;
        $query = "SELECT * FROM `".PREFIX."_backup_rules` WHERE `id`=".$id." LIMIT 1";
        $result = $kernel->runSQL($query);
        $return = false;
        if ($row = mysql_fetch_assoc($result))
            $return = $row;
        mysql_free_result($result);
        return $return;
    }

    /**
     * Возвращает бэкап-правило по ID
     *
     * @param string $stringid
     * @return array
     */
    function get_backup_rule_by_stringid($stringid)
    {
        global $kernel;
        $query = "SELECT * FROM `".PREFIX."_backup_rules` WHERE `stringid`='".$kernel->pub_str_prepare_set($stringid)."' LIMIT 1";
        $result = $kernel->runSQL($query);
        $return = false;
        if ($row = mysql_fetch_assoc($result))
            $return = $row;
        mysql_free_result($result);
        return $return;
    }

    /**
     * Админ интерфейс
     *
     * @param string $backup_action
     * @param array $my_get
     * @param array $my_post
     * @return string
     */
    function backup_start($backup_action, $my_get, $my_post)
    {
        global $kernel;
        $root = $kernel->pub_site_root_get();
        $html_content='';
        switch ($backup_action)
		{
		    default:
		    case 'rules':
		        $template = $kernel->pub_template_parse($root."/admin/templates/default/backup_rules.html");
                $html_content = $template['backup_rules_table'];
		        $rules = $this->get_backup_rules();
		        $lines = '';
		        $i=1;
		        foreach ($rules as $rule)
		        {
		            $line = $template['backup_rules_row'];
		            $line = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($i), $line);
		            $line = $kernel->pub_array_key_2_value($line, $rule);
		            $line = str_replace("%num_order%", $i++, $line);
		            $lines .= $line;
		        }
		        $html_content = str_replace("%lines%", $lines, $html_content);
		        return $html_content;
		        break;
		    case 'edit_rule':
		        $id = $my_get['id'];

		        $template = $kernel->pub_template_parse($root."/admin/templates/default/backup_rules.html");
		        $html_content = $template['backup_rule_edit'];
		        $html_content = str_replace("%form_action%",$kernel->pub_redirect_for_form("backup&backup=save_rule&id=".$id), $html_content);

                if ($id==0)
                {
                    $rule = array("needcontent"=>0, "needsystem"=>0, "needtables"=>0, "needdesign"=>0,
      		            			  "name"=>"", "stringid"=>"",
      		            			  "ftphost"=>"","ftpuser"=>"", "ftppass"=>"", "ftpdir"=>"/");
                    $edit_details_link='';
                }
                else
                {
                    $rule = $this->get_backup_rule($id);
                    $edit_details_link=$template['rule_details_link'];
                }
                $html_content=str_replace('%rule_details_link%',$edit_details_link,$html_content);

		        if ($rule['needcontent']==1)
		            $html_content = str_replace("%needcontent%", "checked", $html_content);
		        else
		            $html_content = str_replace("%needcontent%", "", $html_content);
		        if ($rule['needsystem']==1)
		            $html_content = str_replace("%needsystem%", "checked", $html_content);
		        else
		            $html_content = str_replace("%needsystem%", "", $html_content);
		        if ($rule['needtables']==1)
		            $html_content = str_replace("%needtables%", "checked", $html_content);
		        else
		            $html_content = str_replace("%needtables%", "", $html_content);
		        if ($rule['needdesign']==1)
		            $html_content = str_replace("%needdesign%", "checked", $html_content);
		        else
		            $html_content = str_replace("%needdesign%", "", $html_content);
		        $html_content = $kernel->pub_array_key_2_value($html_content, $rule);
		        return $html_content;
		        break;
		    case 'save_rule':
		        $id = $my_post['id'];
		        $stringid = $kernel->pub_httppost_get('stringid', false);
		        $name = $kernel->pub_httppost_get('name', false);

		        if (empty($name))
		             return $kernel->pub_httppost_response("[#admin_backup_error_empty_rule_name#]");
		        if (empty($stringid))
		            $stringid = $this->translate2stringid($name);
		        else
		            $stringid = $this->translate2stringid($stringid);
		        if (empty($stringid))
		            return $kernel->pub_httppost_response("[#admin_backup_error_incorrect_rule_stringid#]");

		        $stringid = strtolower($stringid);
		        $ex_rule = $this->get_backup_rule_by_stringid($stringid);
		        if ($ex_rule && $ex_rule['id']!=$id)
		            return $kernel->pub_httppost_response("[#admin_backup_error_existing_rule_stringid#]");

		        $name = $kernel->pub_str_prepare_set($name);
		        $stringid = $kernel->pub_str_prepare_set($stringid);
		        if (empty($my_post['needcontent']))
		            $needcontent = 0;
		        else
		            $needcontent = 1;
		        if (empty($my_post['needsystem']))
		            $needsystem = 0;
		        else
		            $needsystem = 1;
		        if (empty($my_post['needtables']))
		            $needtables = 0;
		        else
		            $needtables = 1;
		        if (empty($my_post['needdesign']))
		            $needdesign = 0;
		        else
		            $needdesign = 1;
		        if ($id == 0)
		            $query = "INSERT INTO `".PREFIX."_backup_rules` (`stringid`,`name`,`ftphost`, `ftpuser`,`ftppass`, `ftpdir`, ".
		            		 "`needcontent`, `needsystem`, `needtables`, `needdesign`) VALUES ".
		            		 "('".$stringid."','".$name."', '".$my_post['ftphost']."', '".$my_post['ftpuser']."','".$my_post['ftppass']."','".$my_post['ftpdir']."', ".$needcontent.", ".$needsystem.", ".$needtables.", ".$needdesign.")";
		        else
		            $query ="UPDATE `".PREFIX."_backup_rules` SET `stringid`='".$stringid."', ".
		            		"`name`='".$name."',".
		            		"`ftphost`='".$my_post['ftphost']."',".
		            		"`ftpuser`='".$my_post['ftpuser']."',".
		            		"`ftppass`='".$my_post['ftppass']."',".
		            		"`ftpdir`='".$my_post['ftpdir']."',".
		            		"`needcontent`=".$needcontent.",".
		            		"`needsystem`=".$needsystem.",".
		            		"`needtables`=".$needtables.",".
		            		"`needdesign`=".$needdesign.
		            		" WHERE `id`=".$id;
		        $kernel->runSQL($query);
		        return $kernel->pub_httppost_response("[#admin_backup_rule_saved_msg#]","backup&backup=rules");
		        break;
		    case 'delete_rule':
		        $id = $my_get['id'];
		        $query = "DELETE FROM `".PREFIX."_backup_rules` WHERE `id`=".$id;
		        $kernel->runSQL($query);
		        $query = "DELETE FROM `".PREFIX."_backup_ignoredexts` WHERE `ruleid`=".$id;
		        $kernel->runSQL($query);
		        $query = "DELETE FROM `".PREFIX."_backup_ignoredpaths` WHERE `ruleid`=".$id;
		        $kernel->runSQL($query);
		        $query = "DELETE FROM `".PREFIX."_backup_ignoredtables` WHERE `ruleid`=".$id;
		        $kernel->runSQL($query);
		        //return $kernel->pub_httppost_response("[#admin_backup_rule_deleted_msg#]","backup&backup=rules");
		        $kernel->pub_redirect_refresh("backup&backup=rules");
		        break;
		    case 'edit_rule_details':
		        $ruleid = $my_get['id'];
		        $rule = $this->get_backup_rule($ruleid);
		        $template = $kernel->pub_template_parse($root."/admin/templates/default/backup_rule_details.html");
                $html_content = $template['main'];
                $html_content = str_replace("%form_action%",$kernel->pub_redirect_for_form("backup&backup=run_save"), $html_content);
                $html_content = str_replace("%name%",$rule['name'], $html_content);

                if ($rule['needcontent']==1)
                    $html_content = str_replace("%needcontent_checked%", "checked", $html_content);
                else
                    $html_content = str_replace("%needcontent_checked%", "", $html_content);
                if ($rule['needsystem']==1)
                    $html_content = str_replace("%needsystem_checked%", "checked", $html_content);
                else
                    $html_content = str_replace("%needsystem_checked%", "", $html_content);
                if ($rule['needtables']==1)
                    $html_content = str_replace("%needtables_checked%", "checked", $html_content);
                else
                    $html_content = str_replace("%needtables_checked%", "", $html_content);
                if ($rule['needdesign']==1)
                    $html_content = str_replace("%needdesign_checked%", "checked", $html_content);
                else
                    $html_content = str_replace("%needdesign_checked%", "", $html_content);

				//игнорируемые расширения
				$block = $template['ignored_ext_table'];
				$ignored_exts = $this->get_ignored_extensions($ruleid);
			    $i = 1;
				$lines = '';
				foreach ($ignored_exts as $ext)
				{
				    $line = $template['ignored_ext_table_row'];
				    $line = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($i), $line);
				    $line = str_replace("%num_order%", $i++, $line);
				    $line = str_replace("%ext%", $ext, $line);
				    $line = str_replace("%ext_escaped%", urlencode($ext), $line);
				    $lines .= $line;
				}
				$block = str_replace("%lines%", $lines, $block);
				$block = str_replace("%form_action_add%", $kernel->pub_redirect_for_form("backup&backup=add_ext"), $block);
				$html_content = str_replace("%ignored_exts%", $block, $html_content);

				//игнорируемые пути
				$block = $template['ignored_paths_table'];
				$ignored_paths = $this->get_ignored_paths($ruleid);
				//print_r($ignored_exts);
			    $i = 1;
				$lines = '';
				foreach ($ignored_paths as $path)
				{
				    $line = $template['ignored_paths_table_row'];
				    $line = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($i), $line);
				    $line = str_replace("%num_order%", $i++, $line);
				    $line = str_replace("%path%", $path, $line);
				    $line = str_replace("%path_escaped%", urlencode($path), $line);
				    $lines .= $line;
				}
				$block = str_replace("%lines%", $lines, $block);
				$block = str_replace("%form_action_add%", $kernel->pub_redirect_for_form("backup&backup=add_path"), $block);
				$html_content = str_replace("%ignored_paths%", $block, $html_content);

				//игнорируемые таблицы
				$block = $template['ignored_table'];
				$ignored_tables = $this->get_ignored_tables($ruleid);
				$i = 1;
				$lines = '';
				foreach ($ignored_tables as $itable)
				{
				    $line = $template['ignored_table_row'];
				    $line = str_replace("[#tr_class#]", $kernel->pub_table_tr_class($i), $line);
				    $line = str_replace("%num_order%", $i++, $line);
				    $line = str_replace("%tablename%", $itable, $line);
				    $lines .= $line;
				}

			    $select_add = '';
			    $all_tables = $this->get_santa_tables();
				foreach ($all_tables as $table)
				{
				    if (in_array($table, $ignored_tables))
				        continue;
				    $line_add = $template['ignored_tables_add_option'];
				    $line_add = str_replace("%tablename%", $table, $line_add);
				    $select_add .= $line_add;
				}
				$block = str_replace("%lines%", $lines, $block);
				$block = str_replace("%form_action_add%", $kernel->pub_redirect_for_form("backup&backup=add_it"), $block);
				$block = str_replace("%select_options%", $select_add, $block);
				$html_content = str_replace("%ignored_tables%", $block, $html_content);
				$html_content = str_replace("%ruleid%", $ruleid, $html_content);
		        break;
		    case 'backup_files':
				//Копирование вновь загруженного файла, если он есть
				if (isset($_FILES['backup_file']))
				{
				    if (is_uploaded_file($_FILES['backup_file']['tmp_name']))
				    {
				        $moveto = $kernel->pub_site_root_get().'/backup/'.$_FILES['backup_file']['name'];
				        move_uploaded_file($_FILES['backup_file']['tmp_name'], $moveto);
				    }
				    $kernel->pub_redirect_refresh_reload('backup&backup=backup_files');
				}
		        /*
		        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0)
		        {
		            move_uploaded_file($_FILES['backup_file']['tmp_name'], 'backup/'.$_FILES['backup_file']['name']);
      	            $kernel->pub_redirect_refresh_reload('backup&backup=backup_files');
		        }
				*/
				//upload_max_filesize
				$upload_max_filesize = ini_get('upload_max_filesize');

				ini_set('upload_max_filesize','20M');

		        $template = $kernel->pub_template_parse($root."/admin/templates/default/backup_files.html");
                $html_content = $template['main'];
		        $block = $this->backup_table_get();
		        $block = str_replace("%header%", $template['backup_made_header'], $block);
				$html_content = str_replace("%backup_table%", $block, $html_content);
				$html_content = str_replace("%upload_max_filesize%", $upload_max_filesize, $html_content);
				$html_content = str_replace("%form_action%", $kernel->pub_redirect_for_form("backup&backup=backup_files"), $html_content);
				$files = array();
				if ($backup_dir = opendir($root."/backup/"))
				{
                    while ($file = readdir($backup_dir))
                    {
                        if (is_file($root."/backup/".$file) && strtolower(substr($file, -4))==".zip")
                            $files[] = $file;
                    }
				}
				$block = $this->backup_table_get_uploaded($files);
				$block = str_replace("%header%", $template['backup_uploaded_header'], $block);
				$html_content = str_replace("%uploaded_files%", $block, $html_content);
				return $html_content;
		        break;

		    case 'run_save':
                set_time_limit(300);
		        $ruleid = $my_post['ruleid'];
		        $rule = $this->get_backup_rule($ruleid);
		        $backup_res = $this->backup_run_save($rule, $my_post['needcontent']==1,
		                                $my_post['needsystem']==1,
		                                $my_post['needtables']==1,
		                                $my_post['needdesign']==1,
		                                $my_post['description']);
		        if ($backup_res)
		            return $kernel->pub_httppost_response("[#admin_backup_rule_finished_ok_msg#]","/admin",0);
		        else
		            return $kernel->pub_httppost_errore($this->error_last_get(),true);

		        break;

		    //удаление бэкапа
		    case 'delete':
                if (is_numeric($kernel->pub_httpget_get('id')))
                {
                    $id_delete = $my_get['id'];
                    $this->backup_delete($id_delete);
                }
                else
                {
                    $file = 'backup/'.$kernel->pub_httpget_get('id');
                    if (is_file($file))
                    	$kernel->pub_file_delete($file);
                }

        		$kernel->pub_redirect_refresh("backup&backup=backup_files");
		        break;

		    case 'dwld':
		        $id_dwld = $kernel->pub_httpget_get('id');

        		$this->backup_download($id_dwld);

		        break;

		    case 'restore_step1':
		        $id_restore = $my_get['id'];
		        if (isset($my_get['findex']))
		            $file_index = $my_get['findex'];
		        else
		            $file_index = 0;
		        $this->backup_restore($id_restore, $file_index);
                $kernel->pub_redirect_refresh("backup&backup=backup_files");
                break;
		    case 'delete_it':
		        $ruleid = $my_get['ruleid'];
		        $table_name = $my_get['tn'];
		        $kernel->runSQL("DELETE FROM `".PREFIX."_backup_ignoredtables` WHERE `tablename`='".$table_name."' AND `ruleid`=".$ruleid);
		        $kernel->pub_redirect_refresh('backup&backup=edit_rule_details&id='.$ruleid);
		        break;
		    case 'add_it':
		        $table_names = $my_post['tn'];
		        $ruleid = $my_post['ruleid'];
		        foreach ($table_names as $tn)
		        {
		            $kernel->runSQL("INSERT INTO `".PREFIX."_backup_ignoredtables` (`tablename`, `ruleid`) VALUES ('".$tn."', ".$ruleid.")");
		        }
		        $kernel->pub_redirect_refresh_reload('backup&backup=edit_rule_details&id='.$ruleid);
		        break;

		    case 'delete_ignored_path':
		        $ruleid = $my_get['ruleid'];
		        $name = $kernel->pub_str_prepare_set(urldecode($my_get['path']));
		        $query = "DELETE FROM `".PREFIX."_backup_ignoredpaths` WHERE `path`='".$name."' AND `ruleid`=".$ruleid;
		        $kernel->runSQL($query);
		        $kernel->pub_redirect_refresh('backup&backup=edit_rule_details&id='.$ruleid);
		        break;
		    case 'add_path':
		        $path = $my_post['path'];
		        $ruleid = $my_post['ruleid'];
		        $kernel->runSQL("INSERT INTO `".PREFIX."_backup_ignoredpaths` (`path`, `ruleid`) VALUES ('".$path."', ".$ruleid.")");
		        $kernel->pub_redirect_refresh_reload('backup&backup=edit_rule_details&id='.$ruleid);
		        break;

		    case 'delete_ext':
		        $ruleid = $my_get['ruleid'];
		        $name = $kernel->pub_str_prepare_set(urldecode($my_get['ext']));
		        $query = "DELETE FROM `".PREFIX."_backup_ignoredexts` WHERE `ext`='".$name."' AND `ruleid`=".$ruleid;
		        $kernel->runSQL($query);
		        $kernel->pub_redirect_refresh('backup&backup=edit_rule_details&id='.$ruleid);
		        break;
		    case 'add_ext':
		        $ruleid = $my_post['ruleid'];
		        $name = strtolower(trim($kernel->pub_str_prepare_set($my_post['ext'])));
		        $kernel->runSQL("INSERT INTO `".PREFIX."_backup_ignoredexts` (`ext`,`ruleid`) VALUES ('".$name."',".$ruleid.")");
		        $kernel->pub_redirect_refresh_reload('backup&backup=edit_rule_details&id='.$ruleid);
		        break;

		}
	   return $html_content;
    }


    /**
     * Конвертирует строку в приемлимую для использования в качестве строкового идентификатора
     *
     * @param string $s строка для конвертирования
     * @return string
     */
    private function translate2stringid($s)
    {
        global $kernel;
        $s = $kernel->pub_translit_string($s);
        $s = preg_replace("/[^0-9a-z_]/i", '', $s);
        return $s;
    }
}
