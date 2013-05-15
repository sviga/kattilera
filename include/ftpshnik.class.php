<?php
class ftpshnik
{
    private $host;
    private $port = 21;
    private $login;
    private $pass;
    private $path = "";
    private $currDir = "";
    private $handler;
    private $lastError = false;
    public $debugMessages = array();

    public function ftpshnik($host, $login, $pass, $path="")
    {
        $matches = false;
        if (preg_match("/^(.+):(\\d+)(.*)$/", $host, $matches))
        {//host в виде host.com:21 или host.com:21/path/
            $this->host = $matches[1];
            $this->port = $matches[2];
            $this->path = $matches[3];
        }
        elseif (preg_match("/^(.+)\\/(.*)$/U", $host, $matches))
        {//host в виде host.com/path/
            $this->host = $matches[1];
            $this->path = "/".$matches[2];
        }
        else
        {
            $this->host = $host;
            $this->path = $path;
        }
        $this->login = $login;
        $this->pass = $pass;
    }

    /**
     * Выходит из папки в родительскую
     *
     */
    public function cdup()
    {
        if (!$this->checkConnectionHandler())
            return false;
        $this->debugMessages[] = "making cdup() (currDir: ".$this->currDir.")";
        if (@ftp_cdup($this->handler)=== false)
        {
            $this->lastError = "Failed to do cdup (currDir: ".$this->currDir.")";
            return false;
        }
        $currDir = $this->pwd();
        if (!$currDir)
            return false;
        $this->setCurrDir($currDir);
        return true;
    }

    /**
     * Устанавливает права на файл или папку
     *
     * @param string $path путь
     * @param string $octalMode права в восьмеричном виде
     * @return boolean
     */
    public function chmod($path, $octalMode)
    {
        if (!$this->checkConnectionHandler())
            return false;
        if (is_string($octalMode))
        {
            $this->debugMessages[] = 'chmod on file rights is string ('.$octalMode.'), converting...';
            if (strlen($octalMode)==3)
                $octalMode = '0'.$octalMode;
            //надо сконвертировать по-хитрому - http://php.net/manual/en/function.ftp-chmod.php
            $octalMode = eval("return(".$octalMode.");");
        }
        $this->debugMessages[] = "chmod '".sprintf("%o",$octalMode)."' on file ".$path;

        if (@ftp_chmod($this->handler, $octalMode, $path) === false)
        {
            $this->lastError = "Chmod '".$octalMode."' on file ".$path." failed";
            return false;
        }
        return true;
    }

    /**
     * Удаляет файл на фтп
     *
     * @param string $filename
     * @return boolean
     */
    public function deleteFile($filename)
    {
        if (!$this->checkConnectionHandler())
            return false;
        if (!@ftp_delete($this->handler, $filename))
        {
            $this->lastError = "Failed to delete file ".$filename;
            return false;
        }
        return true;
    }

    /**
     * Удаляет папку на фтп
     * @param string $path
     * @return boolean
     */
    public function deleteDir($path)
    {
        if (!$this->checkConnectionHandler())
            return false;
        if (!@ftp_rmdir($this->handler, $path))
        {
            $this->lastError = "Failed to delete dir ".$path;
            return false;
        }
        return true;
    }

    /**
     * Только для santafox
     * ищет путь установки santafox по ini.php
     * по имени файла и размеру
     *
     * @param boolean $byDefaultIni если true, будет искать ini.default.php вместо ini.php
     * @return boolean
     */
    public function findSantaRootByIni($byDefaultIni = false)
    {
        if ($byDefaultIni)
        {
            $dbhost = "[#DB_HOST#]";
            $dbbase = "[#DB_BASENAME#]";
            $dbuser = "[#DB_USERNAME#]";
            $dbprefix = "[#PREFIX#]";
            $iniFileName = "ini.default.php";
        }
        else
        {
            $dbhost = DB_HOST;
            $dbbase = DB_BASENAME;
            $dbuser = DB_USERNAME;
            $dbprefix = PREFIX;
            $iniFileName = "ini.php";
        }
        $this->debugMessages[] = "findSantaRootByIni ".$iniFileName;
        if (!$this->init(false, false))
            return false;
        $files = $this->findFileRecursive($iniFileName, filesize($iniFileName));
        if (!$files)
            return false;
        foreach ($files as $file)
        {
            $this->debugMessages[] = "checking ini.php at ".$file['path'];
            $fc = $this->getFileContentsComplete($file['name'], $file['path']);
            if (!$fc)
                return false;
            if ($this->isMySantaIni($fc, $dbhost, $dbbase, $dbuser, $dbprefix))
                return $file['path'];
        }
        return false;
    }

    /**
     * Только для santafox
     *
     * проверяет ini.php по DB_HOST, DB_BASENAME, DB_USERNAME, PREFIX
     * @param string $fc содержимое файла ini.php
     * @param string $dbhost
     * @param string $dbbase
     * @param string $dbuser
     * @param string $dbprefix
     * @return boolean
     */
    public function isMySantaIni($fc, $dbhost, $dbbase, $dbuser, $dbprefix)
    {
        $matches = false;
        if (!preg_match('/\\(\\s*"DB_HOST"\\s*,\\s*"(.+)"\\s*\\)/', $fc, $matches))
        {
            $this->debugMessages[] = "failed to find DB_HOST in ini.php";
            return false;
        }
        if ($dbhost!=$matches[1])
        {
            $this->debugMessages[] = "DB_HOST not match in ini.php";
            return false;
        }
        if (!preg_match('/\\(\\s*"DB_BASENAME"\\s*,\\s*"(.+)"\\s*\\)/', $fc, $matches))
        {
            $this->debugMessages[] = "failed to find DB_BASENAME in ini.php";
            return false;
        }
        if ($dbbase!=$matches[1])
        {
            $this->debugMessages[] = "DB_BASENAME not match in ini.php";
            return false;
        }
        if (!preg_match('/\\(\\s*"DB_USERNAME"\\s*,\\s*"(.+)"\\s*\\)/', $fc, $matches))
        {
            $this->debugMessages[] = "failed to find DB_USERNAME in ini.php";
            return false;
        }
        if ($dbuser!=$matches[1])
        {
            $this->debugMessages[] = "DB_USERNAME not match in ini.php";
            return false;
        }
        if (!preg_match('/\\(\\s*"PREFIX"\\s*,\\s*"(.+)"\\s*\\)/', $fc, $matches))
        {
            $this->debugMessages[] = "failed to find PREFIX in ini.php";
            return false;
        }
        if ($dbprefix!=$matches[1])
        {
            $this->debugMessages[] = "PREFIX not match in ini.php";
            return false;
        }

        return true;
    }

    /**
     * Для дебага - отображает информацию о коннекте
     *
     */
    public function printCredentionals()
    {
        print "host: ".$this->host."\n";
        print "login: ".$this->login."\n";
        print "pass: ".$this->pass."\n";
        print "port: ".$this->port."\n";
        print "path: ".$this->path."\n";
    }

    /**
     * Возвращает последнюю ошибку
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Соединяется с фтп-хостом
     *
     * @return boolean
     */
    public function connect()
    {
        $this->debugMessages[] = "Connecting to ".$this->host.":".$this->port;
        $this->handler = @ftp_connect($this->host, $this->port);
        if ($this->handler === false)
        {
            $this->lastError = "Connect failed";
            return false;
        }
        return true;
    }

    /**
     * Выполняет логин на фтп
     *
     * @return boolean
     */
    public function login()
    {
        if (!$this->checkConnectionHandler())
            return false;
        $this->debugMessages[] = "Loggin in ".$this->login.":".$this->pass;
        if (!@ftp_login($this->handler, $this->login, $this->pass))
        {
            $this->lastError = "Login failed";
            return false;
        }
        return true;
    }

    /**
     * Устанавливает текущую папку
     *
     * @param string $dir
     */
    private function setCurrDir($dir)
    {
        if (substr($dir,0,1) == '/')
            $this->currDir = $dir;
        elseif (empty($this->currDir))
            $this->currDir = $dir;
        else
            $this->currDir.= $dir;
        //если заканчивается на / - обрезаем
        if (substr($this->currDir, -1) == '/')
            $this->currDir = substr($this->currDir, 0, strlen($this->currDir)-1);
        //если начинается НЕ на / - добавляем
        if (substr($this->currDir, 0, 1) != '/')
            $this->currDir = '/'.$this->currDir;
    }

    /**
     * Возвращает текущий путь на фтп или false при ошибке
     *
     * @return string
     */
    public function pwd()
    {
        if (!$this->checkConnectionHandler())
            return false;
        $res = ftp_pwd($this->handler);
        if ($res === false)
        {
            $this->lastError ="pwd failed";
            return false;
        }
        else
            return $res;
    }

    /**
     * Заходит в папку на фтп
     *
     * @param string $path
     * @return boolean
     */
    public function chdir($path)
    {
        if (!$this->checkConnectionHandler())
            return false;

        if ($path != "/" && substr($path, -1) == '/')
            $path = substr($path, 0, strlen($path)-1);

        $this->debugMessages[] = "chdir to '".$path."'...";
        //если мы уже в этой папке - команды на сервер посылать не надо
        if ($path == $this->currDir)
        {
            $this->debugMessages[] = "chdir = currDir, skipping";
            return true;
        }

        if (!@ftp_chdir($this->handler, $path))
        {
            $this->lastError = "Chdir failed";
            return false;
        }
        $this->setCurrDir($path);
        return true;
    }


    /**
     * Всё-в-одном для скачивания файла по фтп
     *
     * @param string $filename имя файла на сервере
     * @param mixed $remoteDir папка на фтп (если пусто, то берём из текущей
     * @return string
     */
    public function getFileContentsComplete($filename, $remoteDir = false)
    {
        if (!$this->init($remoteDir, false))
            return false;
        //chdir уже в init
        return $this->getFileContents($filename);
    }

    /**
     * Скачивает файл из ТЕКУЩЕЙ папки на сервере
     * сохраняет во временный файл и возвращает его содержимое
     *
     * @param string $remotename имя файла на сервере
     * @return string
     */
    public function getFileContents($remotename)
    {
        if (!$this->checkConnectionHandler())
            return false;
        $this->debugMessages[] = "getFileContents() '".$remotename."'";
        $transferType = $this->getTransferModeByExt(@pathinfo($remotename, PATHINFO_EXTENSION));
        $tmpFile = tmpfile();
        if ($tmpFile === false)
        {
            $this->lastError = "getFileContents(): create tmp file failed";
            return false;
        }
        if (@ftp_fget($this->handler, $tmpFile, $remotename, $transferType)===false)
        {
            $this->lastError = "getFileContents() '".$remotename."' failed";
            @fclose($tmpFile);
            return false;
        }

        $contents = "";
        fseek($tmpFile, 0);
        while (!feof($tmpFile))
        {
          $contents .= fread($tmpFile, 8192);
        }
        @fclose($tmpFile);
        return $contents;
    }

    /**
     * Возвращает нужный transfer mode для указанного расширения
     *
     * @param string $ext
     * @return integer
     */
    private function getTransferModeByExt($ext)
    {
        if (empty($ext))
            return FTP_BINARY;
        $ascii = array('am','asp','bat','c','cfm','cgi','conf','cpp','css','dhtml','diz','h','hpp','htm',
            		  'html','in','inc','js','m4','mak','nfs','nsi','pas','patch','php','php3','php4','php5',
                      'phtml','pl','po','py','qmail','sh','shtml','sql','tcl','tpl','txt','vbs','xml','xrc');
        if (in_array(strtolower($ext), $ascii))
            return FTP_ASCII;
        else
            return FTP_BINARY;
        /*
        $bins = array('zip','jpeg','jpg','png','gif','pdf','xls','doc','avi', 'mpg','mpeg');
        if (in_array(strtolower($ext), $bins))
            return FTP_BINARY;
        else
            return FTP_ASCII;
		*/
    }

    /**
     * Сохраняет файл на фтп в ТЕКУЩУЮ папку
     * при использовании надо сначала сделать connect, login и chdir (или только init)
     *
     * @param string $fullPath локальный путь к файлу
     * @param string $storeName имя файла для сохранения на фтп
     * @param boolean $aggressivePut удалять существующий файл с таким именем, если просто upload не получается?
     * @param boolean $passiveMode использовать passive mode для FTP?
     * @return boolean
     */
    public function putFile($fullPath, $storeName, $aggressivePut=true,$passiveMode=false)
    {
        if (!$this->checkConnectionHandler())
            return false;
        $transferType = $this->getTransferModeByExt(@pathinfo($fullPath, PATHINFO_EXTENSION));
        $this->debugMessages[] = "putFile(".$fullPath.", ".$storeName.($aggressivePut?"true":"false").",".($passiveMode?"true":"false");
        ftp_pasv($this->handler,$passiveMode);
        if (!@ftp_put($this->handler, $storeName, $fullPath, $transferType))
        {
            $this->lastError = "put file '".$storeName."' failed (storeName: ".$storeName.", ttype: ".$transferType.")";
            if (!$aggressivePut)
                return false;
            else
            {
                $this->debugMessages[] = "Trying aggressive mode...";
                if (!$this->deleteFile($storeName))
                {
                    $this->lastError = "delete file '".$storeName."' in aggressive put failed";
                    return false;
                }
                if (!@ftp_put($this->handler, $storeName, $fullPath, $transferType))
                {
                    $this->lastError = "put file '".$storeName."' (after delete) failed";
                    return false;
                }
            }
        }
        return true;
    }

	/**
     * Сохраняет контент в файл на фтп в ТЕКУЩУЮ папку
     * при использовании надо сначала сделать connect, login и chdir (или только init)
     *
     * @param string $contents содержимое файла
     * @param string $storeName имя файла для сохранения на фтп
     * @param boolean $aggressivePut удалять существующий файл с таким именем, если просто upload не получается?
     * @return boolean
     */
    public function putFileContents($contents, $storeName, $aggressivePut=true)
    {
        if (!$this->checkConnectionHandler())
            return false;

        $tmpFile = @tmpfile();
        if ($tmpFile === false)
        {
            $this->lastError = "putFileContents(): create tmp file failed";
            return false;
        }
        if (@fwrite($tmpFile, $contents) === false)
        {
            $this->lastError = "putFileContents(): write tmp file failed";
            return false;
        }
        @rewind($tmpFile);

        $transferType = $this->getTransferModeByExt(@pathinfo($storeName, PATHINFO_EXTENSION));
        if (!@ftp_fput($this->handler, $storeName, $tmpFile, $transferType))
        {
            if (!$aggressivePut)
            {
                $this->lastError = "putFileContents() '".$storeName."' failed";
                @fclose($tmpFile);
                return false;
            }
            else
            {
                $this->debugMessages[] = "putFileContents() Trying aggressive mode...";
                if (!ftp_delete($this->handler, $storeName))
                {
                    $this->lastError = "delete file '".$storeName."' in aggressive putFileContents() failed";
                    @fclose($tmpFile);
                    return false;
                }
                if (!@ftp_fput($this->handler, $storeName, $tmpFile, $transferType))
                {
                    $this->lastError = "putFileContents() '".$storeName."' (after delete) failed";
                    @fclose($tmpFile);
                    return false;
                }
            }
        }
        @fclose($tmpFile);
        return true;
    }

    /**
     * Отсоединяется от фтп
     *
     * @return boolean
     */
    public function disconnect()
    {
        if (!$this->checkConnectionHandler())
            return false;
        @ftp_close($this->handler);
        return true;
    }

    /**
     * Рекурсивно ищет файл
     *
     * @param string $filename имя файла
     * @param mixed $filesize
     * @param mixed $dir папка, откуда начинать искать
     * @return mixed
     */
    public function findFileRecursive($filename, $filesize=false, $dir=false)
    {
        $this->debugMessages[] = "finding file '".$filename."' recursive at '".$dir."'";
        if (!$this->checkConnectionHandler())
            return false;
        if ($dir)
        {
            if (!$this->chdir($dir))
                return false;
        }
        $dirlist = $this->getFullDirectoryListing(true);
        if (!$dirlist)
            return false;
        $return = array();
        foreach ($dirlist as $name=>$val)
        {
            if ($val['isdir'])//это папка, а мы ищем файлы
                continue;
            //$this->debugMessages[] = "findFileRecursive compare entry name: ".$val['name'].", size: ".$val['size']."...";
            if ($filesize && $val['name'] == $filename && $val['size']==$filesize)
            {
                //$this->debugMessages[] = " got by size and name!";
                $return[$name] = $val;
            }
            else if ($val['name'] == $filename)
            {
                //$this->debugMessages[] = " got by name!";
                $return[$name] = $val;
            }
        }
        return $return;
    }

    /**
     * Для дебага
     * Возвращает в удобном виде результат getFullDirectoryListing()
     *
     * @param array $dirlist
     * @return string
     */
    public function formatFullDirectoryListingOut($dirlist)
    {
        $nameColWidth = 50;
        $out = "";
        if (!$dirlist)
            return $out;
        foreach ($dirlist as $name=>$val)
        {
            if ($val['isdir'])
                $out.="[".$name."]".str_repeat(" ", strlen($nameColWidth)-strlen($name)-2);
            else
                $out.=$name.str_repeat(" ", strlen($nameColWidth)-strlen($name));
            $out.="|\n";

        }
        return $out;
    }

    /**
     * Возвращает полный листинг текущей директории на фтп или false при ошибке
     *
     * @param boolean $recursive рекурсивно?
     * @return array
     */
    public function getFullDirectoryListing($recursive = false)
    {
        if (!$this->checkConnectionHandler())
            return false;
         if ($recursive)
             $this->debugMessages[] = "getFullDirectoryListing recursive";
         else
             $this->debugMessages[] = "getFullDirectoryListing";

        $folder0 = $this->pwd();
        if ($folder0 == false)
            return false;
        $this->setCurrDir($folder0);
        if ($this->currDir!="/")
            $folder0 = $this->currDir."/";
        $this->debugMessages[] = "folder0: ".$folder0;
        /*Some FTP servers only allow you to get list of files under current working directory.
     	 * So if you always get result as empty array (array(0){ }),
     	 * try changing the cwd befor get the list */
        $dirlist = @ftp_rawlist($this->handler, ".", $recursive);
        if ($dirlist === false)
        {
            $this->lastError = "getFullDirectoryListing  failed (dir: ".$this->currDir.")";
            return false;
        }
        //print_r($dirlist);
        $allItems = array();
        $folder = $folder0;
        $listingSize = count($dirlist);
            $this->debugMessages[] = "directory listing size: ".$listingSize;
        foreach ($dirlist as $diritem)
        {
            $info = array();
            $vinfo = preg_split("/[\\s]+/", $diritem, 9);
            //print_r($vinfo);
            if (empty($vinfo))
                continue;
            if (count($vinfo)==1)
            {
                if (!empty($vinfo[0]) && substr($vinfo[0],-1)==":")
                    $folder = $folder0.substr($vinfo[0],0,strlen($vinfo[0])-1)."/";
                continue;
            }
            if ($vinfo[0] !== "total" && count($vinfo)>=9)
            {
              //$info['folder'] = $folder;
              $info['chmod'] = $vinfo[0];
              $info['num'] = $vinfo[1];
              $info['owner'] = $vinfo[2];
              $info['group'] = $vinfo[3];
              $info['size'] = $vinfo[4];
              $info['month'] = $vinfo[5];
              $info['day'] = $vinfo[6];
              $info['time'] = $vinfo[7];
              $info['name'] = $vinfo[8];
              $info['path'] = $folder;
              //elseif ($v['chmod']{0} == "-")  - file
              if ($info['chmod']{0} == "d")
                  $info['isdir'] = true;
              else
                  $info['isdir'] = false;
              $allItems[$folder.$info['name']] = $info;
            }
        }
        //print_r($allItems);
        return $allItems;
    }

    /**
     * Проверяет, инициализирован ли connection handler для фтп
     *
     * @return boolean
     */
    private function checkConnectionHandler()
    {
        if (!is_resource($this->handler))
        {
            $this->lastError = "No connection handler";
            return false;
        }
        return true;
    }

    /**
     * Выполняет инициализацию при необходимости (connect, login, chdir)
     *
     * @param mixed $path папка, в которую сделать chdir
     * @param boolean $createDirs создавать папки, указанные в пути, если их не существует?
     * @return boolean
     */
    public function init($path = false, $createDirs = false)
    {
        if (!$path)
            $dir2change = $this->path;
        else
            $dir2change = $path;
        $this->debugMessages[] = "init(".$dir2change.")";
        if (!is_resource($this->handler))
        {//если ещё нет коннекта, законнектимся
            $this->debugMessages[] = "not yet connected, creating connection";
            if (!$this->connect())
                return false;
            if (!$this->login())
                return false;
        }
        else
            $this->debugMessages[] = "already connected!";
        if (!empty($dir2change))
        {
            if (!$this->chdir($dir2change))
            {
                if (!$createDirs)
                    return false;
                else
                {
                    if (!$this->createDirs($dir2change))
                        return false;
                }
            }
        }
        return true;
    }

    /**
     * Создаёт папки на сервере (вложенные)
     *
     * @param string $path
     * @return boolean
     */
    public function createDirs($path)
    {
        $this->debugMessages[] = "create dirs '".$path."'";
        if (!$this->checkConnectionHandler())
            return false;
        $dirs = explode("/", $path);
        foreach ($dirs as $dir)
        {
            if (empty($dir))
                continue;
            $this->debugMessages[] = " Processing dir '".$dir."'";
            //если папка существует - не создаём её, просто пытаемся зайти

            $dirItems = $this->getFullDirectoryListing();
            if (!$dirItems)
                return false;
            if (array_key_exists($this->currDir."/".$dir, $dirItems))
            {
                $this->debugMessages[] = " dir '".$dir."' exists in file listing";
                if ($dirItems[$this->currDir."/".$dir]['isdir'])
                {
                    if (!$this->chdir($dir))
                        return false;
                    else
                        continue;
                }
                else
                {
                    $this->lastError = "dir '".$dir."' create FAILED - file exists, dirItems: ".var_export($dirItems, true);
                    return false;
                }
            }
            $created = @ftp_mkdir($this->handler, $dir);
            if ($created === false)
            {
                //попробуем сделать pwd для статистики
                $cdir = $this->pwd();
                if (!$cdir)
                    $cdir = $this->currDir;
                $this->lastError = "Failed to create dir '".$dir."' (full path: '".$path."', currDir:".$cdir.", dirItems:".var_export($dirItems, true).")";
                return false;
            }
            if (!$this->chdir($dir))
            {
                $this->lastError = "Failed to chdir '".$dir."' after create (full path: '".$path."')";
                return false;
            }
        }
        return true;
    }

    /**
     * Возвращает массив debug-сообщений
     *
     * @return array
     */
    public function getDebugMessages()
    {
        return $this->debugMessages;
    }

    /**
     * Всё-в-одном для загрузки файла по фтп
     *
     * @param string $fullPath локальный путь к файлу
     * @param string $storeName имя файла для сохранения на ФТП
     * @param mixed $remoteDir папка на ФТП, куда сохранять файл
     * @param boolean $createDirs создавать папки на ФТП, если их не существует?
     * @param boolean $aggressivePut удалять существующий файл с таким именем, если просто upload не получается?
     * @return boolean
     */
    public function putFileComplete($fullPath, $storeName, $remoteDir=false, $createDirs = true, $aggressivePut = true)
    {
        if (!$this->init($remoteDir, $createDirs))
                return false;
        //chdir уже в init
        if (!$this->putFile($fullPath, $storeName, $aggressivePut))
            return false;
        return true;
    }

    /**
     * Всё-в-одном для загрузки содержимого в файл по фтп
     *
     * @param string $contents содержимое файла
     * @param string $fullFtpPath полный путь на ФТП (с папкой от корня), куда сохранять файл
     * @param boolean $createDirs создавать папки на ФТП, если их не существует?
     * @param boolean $aggressivePut удалять существующий файл с таким именем, если просто upload не получается?
     * @return boolean
     */
    public function putFileContentsComplete($contents, /*$storeName, $remoteDir=false,*/ $fullFtpPath, $createDirs = true, $aggressivePut = true)
    {
        $lastSlashPos = strrpos($fullFtpPath, "/");
        if ($lastSlashPos === false)
        {//значит у нас только имя файла, сохраняем в текущую папку
            $remoteDir = false;
            $storeName = $fullFtpPath;
        }
        else
        {
           $remoteDir = substr($fullFtpPath, 0, $lastSlashPos);
           $storeName = substr($fullFtpPath, $lastSlashPos+1);
        }
        if (!$this->init($remoteDir, $createDirs))
            return false;
        //chdir уже в init
        if (!$this->putFileContents($contents, $storeName, $aggressivePut))
            return false;
        return true;
    }
}