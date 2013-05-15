<?php
/**
 * Описывает инсталируемые CMS mySQL таблицы
 *
 * Содрежит SQL запросы для создания необходимых CMS
 * таблиц. Помимо этого, содержит список значений по умолчанию
 * которые прописываются в таблицы при инсталяции
 *
 * @copyright ArtProm (с) 2001-2013
 * @version 2.0
 */
class mysql_table
{
	/**
	 * Создает необходимые таблицы в базе данных
     * @param $is_etalon_install
	 * @return void
	 */
	function install($is_etalon_install)
    {
        global $kernel;
        if (!$kernel)
            $kernel = new kernel(PREFIX);
        $sql_files_dir = $kernel->pub_site_root_get()."/sinstall/sql";
        $log_file = $kernel->pub_site_root_get()."/sinstall/_install.log";
        $sql_files = array_keys($kernel->pub_files_list_get($sql_files_dir));
        foreach($sql_files as $sql_file)
        {
            $kernel->pub_add_line2file($log_file, "processing ".$sql_file);
            $file_content = file_get_contents($sql_file);
	    	$queries = explode(";\n", $file_content);
            $kernel->pub_add_line2file($log_file, "queries: ".count($queries));
            foreach ($queries as $query)
            {
                $query = trim($query);
                if (empty($query))
                    continue;
                $query = str_replace("`%PREFIX%","`".PREFIX, $query);
                $kernel->runSQL($query);
                $err = mysql_error();
                if (!empty($err))
                {
                    $msg = "MySQL ERROR: ".$err.", query: ".$query;
                    $kernel->pub_add_line2file($log_file, $msg);
                    print $msg."<br>\n";
                }
            }
        }

        if ($is_etalon_install)
            $last_sql_file = "after_etalon_install.sql";
        else
            $last_sql_file = "after_clean_install.sql";

        $kernel->pub_add_line2file($log_file, "processing ".$last_sql_file);

        $file_content = file_get_contents($kernel->pub_site_root_get()."/sinstall/".$last_sql_file);
        $queries = explode(";\n", $file_content);

        $kernel->pub_add_line2file($log_file, "queries: ".count($queries));
        foreach ($queries as $query)
        {
            $query = trim($query);
            if (empty($query))
                continue;
            $query = str_replace("`%PREFIX%","`".PREFIX, $query);
            $kernel->runSQL($query);
            $err = mysql_error();
            if (!empty($err))
            {
                $msg = "MySQL ERROR: ".$err.", query: ".$query;
                $kernel->pub_add_line2file($log_file, $msg);
                print $msg."<br>\n";
            }
        }
        //добавляем общие языковые метки в БД
        $this->add_langauge('include/install/lang');

        //и языковые метки для всех модулей, если это эталонная инсталяция
        if ($is_etalon_install)
        {
            $moddirs = array_keys($kernel->pub_dirs_list_get('modules'));
            foreach($moddirs as $moddir)
            {
                $this->add_langauge($moddir.'lang');
            }
        }
    }


	/**
	 * Проверяет наличие в базе данных укзанной языковой переменной
	 *
	 * @param string $lang Двубуквенный код языка.
	 * @param String $elem Языковая переменная
	 * @return bool
	 */
	function lang_exist($lang, $elem)
	{
        global $kernel;
        $rec = $kernel->db_get_record_simple('_all_lang',"lang = '".$lang."' and element ='".$elem."'");
        if ($rec)
            return true;
        return false;
	}

    /**
    * Считывает языковые файлы и по их содержимому заполняет языковую таблицу
    *
    * @param  string $path_in_lang Путь к языковам файлам
    * @return void
    */
	function add_langauge($path_in_lang)
    {
        global $kernel;
		if (!file_exists($path_in_lang))
            return;
        $lang_isset = $kernel->priv_languages_get(false);
        foreach ($lang_isset as $lang_code => $lang_name)
        {
            $file_name = $path_in_lang.'/'.$lang_code.'.php';
            if (file_exists($file_name))
            {
                include $file_name;
                foreach ($il as $key => $val)
                	if (!$this->lang_exist($type_langauge, $key))
                    	$this->add_data_langauge($type_langauge, $key, $val);
                $il = array();
            }
        }
    }

    /**
    * Считывает языковые файлы и удаляет их из таблицы
    *
    * Вызывается при удалении базавого модуля с тем что бы очистить
    * языковую таблицу от лишней информации
    * @param  string $path_in_lang Путь к языковам файлам
    * @return void
    */
	public static function del_langauge($path_in_lang)
    {
        global $kernel;
		if (!file_exists($path_in_lang))
            return ;

        $dir = dir($path_in_lang);
        while ($file = $dir->read())
        {
            if (is_file($path_in_lang.'/'.$file))
            {
            	$il = array();
                include $path_in_lang.'/'.$file;

                //Удалим все записи
                if (!empty($il))
                {
					$query = "DELETE FROM ".PREFIX."_all_lang WHERE element IN ('".join("','",array_keys($il))."')";
					$kernel->runSQL($query);
                }
            }
        }
    }

    /**
    * Производит непосредственно запись значений языка в таблицу
    *
    * @param string $lang Двух буквенный код языка
    * @param string $id id языковой переменной
    * @param string $val Представление языковой переменной
    * @return void
    */
	function add_data_langauge($lang, $id, $val)
    {
        global $kernel;
    	//Если такой такой id, такой языковой переменной существет, то он будет заменён
		$query = "REPLACE INTO ".PREFIX."_all_lang VALUES
				   (
    	           		'".$lang."',
        	            '".$id."',
            	        NULL,
                	    '".mysql_real_escape_string($val)."'
                   )
	             ";
		$kernel->runSQL($query);
    }

    /**
     * Очищает всю языковую таблицу
     *
     * @param array $nodel значения, которые не нужно удалять
     * @return void
     */
    function lang_all_clear($nodel)
    {
        global $kernel;
		$query = "DELETE FROM ".PREFIX."_all_lang";
		if (count($nodel) > 0)
		  $query .= " WHERE element NOT IN ('".join("','",array_values($nodel))."')";
		$kernel->runSQL($query);
    }
}