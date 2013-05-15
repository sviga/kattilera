<?php

/**
 * Класс управляет простановкой прав на файлы
 *
 * Права ставятся разными способами, с помощью скрипта или с помощью
 * FTP соединения
 */
class manager_chmod
{
    private $dir_access = array();

    function manager_chmod()
    {
        global $kernel;

        //Даже в "поставить стандартные права" надо давать 777 на backup, upload, content, design
        // + templates_user в модулях
        //список директорий нужно строго зафиксировать, так как там могут быть
        //и другие папки, на которые сайт не обращает внимания
        $root = $kernel->pub_site_root_get();
        if ($kernel->is_windows)
            $root = str_replace("\\", "/", $root);


        $this->dir_access['|'.$root."/admin|i"]      = false;
        //$this->dir_access['|'.$root."/backup|i"]     = false;
        $this->dir_access['|'.$root."/backup|i"]     = true;
        $this->dir_access['|'.$root."/cache|i"]      = false;
        $this->dir_access['|'.$root."/components|i"] = false;
        //$this->dir_access['|'.$root."/content|i"]    = false;
        $this->dir_access['|'.$root."/content|i"]    = true;
        //$this->dir_access['|'.$root."/design|i"]     = false;
        $this->dir_access['|'.$root."/design|i"]     = true;
        $this->dir_access['|'.$root."/include|i"]    = false;
        $this->dir_access['|'.$root."/modules|i"]    = false;
        //$this->dir_access['|'.$root."/upload|i"]     = false;
        $this->dir_access['|'.$root."/upload|i"]     = true;
        $this->dir_access['|'.$root."/temp|i"]       = false;
        $this->dir_access['|'.$root."/ini.php|i"]    = false;
        $this->dir_access['|'.$root."/index.php|i"]  = false;

        $this->dir_access['|'.$root."/modules/.*/templates_user|i"] = true;

        //$this->dir_access['|'.$root."/content|i"] = true;
        //$this->dir_access['|'.$root."/design|i"] = true;
        //$this->dir_access['|'.$root."/backup|i"] = true;
        //$this->dir_access['|'.$root."/upload|i"] = true;
        //$this->dir_access['|'.$root."/modules/.*/templates_user|i"] = true;
    }


    /**
     * Ставит права на все файлы
     *
     * Значения прав берутся из ядра.
     * Сначала проставляются права с помощью скрипта, а на всё,
     * что не проставилось права ставятся с помощью FTP
     * если переда истина в качестве параметра, то ставятся полные права
     * на все файла.
     * @param boolean $type_chmod Если истина, то будут проставлены полные права, если ложь - то стандартные
     * @return boolean
     * @access private
     */

    function files_set_acces($type_chmod = false)
    {
        global $kernel;
        //Создадим массив файлов, с типом их прав
        $root = $kernel->pub_site_root_get();
        $root2 = str_replace("\\","/", $root);

        if ($type_chmod)
            $array_files = $this->files_list_type_acces_get_one($root2);//массив всех файлов CMS
        else
            $array_files = $this->files_list_type_acces_get($root2);//Возвращает массив, где ключ - это полный путь к файлу, а значение - один из трёх типов прав
        $array_files[$root] = 3; //было 2 - полные права на wwwroot, в результате может сделать сайт нерабочим


        //Сначала проставим куда можно права скриптом
        $array_files = $this->chmod_script_files_set($array_files);

        //Теперь оставшиеся по фтп
        if (count($array_files)>0)
        {
            $ftpshnik = $kernel->get_ftp_client(true, false);
            if (!$ftpshnik)
                return false;
            foreach ($array_files as $link => $type_chmod)
            {
                if ($kernel->is_windows)
                    $link = str_replace("/","\\", $link);
                $ftp_path = $kernel->convert_path4ftp($link, true);
                $octal_rights = $kernel->pub_chmod_type_get($type_chmod, false);
                if ($ftpshnik->chmod($ftp_path, $octal_rights))
                    unset($array_files[$link]);

            }
            //$msgs = $ftpshnik->getDebugMessages() ; print_r($msgs);
        }
        if (empty($array_files))
            return true;
        else
            return false;
    }

    /**
     * Скриптом, пытается поставить права на все файлы
     *
     * Возвращает массив с теми файлами, на которые права не смогли
     * быть поставлены, с тем что бы их изменила уже функция через FTP
     * @param array $files
     * @return array
     */
    function chmod_script_files_set($files)
    {
        global $kernel;
        foreach ($files as $link => $type_chmod)
        {
            if (@chmod($link, $kernel->pub_chmod_type_get($type_chmod)))
                unset($files[$link]);
        }
        return $files;
    }

    /**
     * Сохдаёт массив всех файлов с типом необходимых прав
     *
     * Возвращает массив, где ключ - это полный путь к файлу
     * а значение - один из трёх типов прав.
     *  1 - ограниченные права
     *  2 - полные права
     *  3 - права директории
     * @param string $path Путь для просмотра файлов
     * @param array $arr Используется для рекурсии
     * @param bolean $full Используется для рекурсии
     * @return array
     */
    function files_list_type_acces_get($path, $arr = array()/*, $full = false*/)
    {
        $handle = opendir($path);
        while ( false !== ($file = readdir($handle)) )
        {
            if ( ($file !== ".") && ($file !== "..") )
            {
                $link = $path . "/" . $file;

                //Проверим, какие права нужны на эту папку или файл
                //либо нужны полные либо нет, а может их вообще не надо менять
                $curent_full = '';
                //if (!$full)
                //{
                foreach ($this->dir_access as $key => $val)
                {
                    $temp = false;
                    if (preg_match($key, $link, $temp))
                        $curent_full = $val;
                }
                //}
                //Если путь не попал под шаблон, значит это вообще не наша директория
                if ($curent_full === '')
                    continue;

                //Расставим тип прав
                if ($curent_full)
                    $arr[$link] = 2;
                else
                {
                    if (is_dir($link))
                        $arr[$link] = 3;
                    else
                        $arr[$link] = 1;
                }

                //Рекурсивный вызов для директориии
                if (is_dir($link))
                    $arr = $this->files_list_type_acces_get($link, $arr/*, $curent_full*/);
            }
        }
        closedir($handle);
        return $arr;
    }


    /**
     * Возвращает массив всех файлов CMS
     *
     * Рекурсивная. Используется при простановке прав на файлы
     * @param string $path
     * @param array $arr
     * @return array
     */
    function files_list_type_acces_get_one($path, $arr = array())
    {
        //global $kernel;

        $handle = opendir($path);
        while ( false !== ($file = readdir($handle)) )
        {
            if ( ($file !== ".") && ($file !== "..") )
            {
                $link = $path . "/" . $file;

                //Проверим, относиться ли этот файл или папка к CMS
                foreach (array_keys($this->dir_access) as $key)
                {
                    $temp = false;
                    if (preg_match($key, $link, $temp))
                        $arr[$link] = 2;

                }
                //Рекурсивный вызов для директориии
                if (is_dir($link))
                    $arr = $this->files_list_type_acces_get_one($link, $arr);
            }
        }
        closedir($handle);
        return $arr;
    }

}
?>