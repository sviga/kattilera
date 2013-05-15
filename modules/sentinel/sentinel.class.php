<?php
require_once dirname(dirname(dirname(__FILE__)))."/include/basemodule.class.php";

class sentinel extends basemodule
{
    public $new_files=array();
    public $changed_files=array();

    public function recalc_hashes()
    {
        global $kernel;
        $q="TRUNCATE TABLE `".$kernel->pub_prefix_get()."_sentinel_filehashes`";
        $kernel->runSQL($q);
        $this->recalc_hashes_for_dir($kernel->pub_site_root_get());
    }

    private function is_ignored_path($fullpath)
    {
        if (!is_file($fullpath)) //директории не игнорируем
            return false;
        $ext = strtolower(pathinfo($fullpath,PATHINFO_EXTENSION));
        if (in_array($ext,array('avi','mpg','pdf','mpeg','jpg', 'jpeg','gif','png','swf','sql','svn-base')))
            return true;
        return false;
    }


    private function recalc_hashes_for_dir($dir)
    {
        global $kernel;
        if (substr($dir,-1)!=DIRECTORY_SEPARATOR)
            $dir.=DIRECTORY_SEPARATOR;
        $dh = opendir($dir);
        while ($file = readdir($dh))
        {
            if ($file=="." || $file=="..")
                continue;
            if ($this->is_ignored_path($dir.$file))
                continue;
            if (is_dir($dir.$file))
            {
                $this->recalc_hashes_for_dir($dir.$file);
                continue;
            }
            if (is_file($dir.$file))
            {
                $hash = md5_file($dir.$file);
                $file2save = $this->fix_stored_file($dir.$file);
                $kernel->db_add_record("_sentinel_filehashes",array("file"=>mysql_real_escape_string($file2save),"hash"=>$hash),"REPLACE");
            }
        }
        closedir($dh);
    }

    private function check_hashes_for_dir($dir)
    {
        global $kernel;
        if (substr($dir,-1)!=DIRECTORY_SEPARATOR)
            $dir.=DIRECTORY_SEPARATOR;
        $dh = opendir($dir);
        while ($file = readdir($dh))
        {
            if ($file=="." || $file=="..")
                continue;
            if ($this->is_ignored_path($dir.$file))
                continue;
            if (is_dir($dir.$file))
            {
                $this->check_hashes_for_dir($dir.$file);
                continue;
            }
            if (is_file($dir.$file))
            {
                $file2search = $this->fix_stored_file($dir.$file);
                $hrec=$kernel->db_get_record_simple("_sentinel_filehashes","`file`='".mysql_real_escape_string($file2search)."'",'hash');
                if (!$hrec)
                {
                    $this->new_files[]=$file2search;
                    continue;
                }
                $hash = md5_file($dir.$file);
                if ($hrec['hash']!=$hash)
                    $this->changed_files[]=$file2search;
            }
        }
        closedir($dh);
    }

    private function fix_stored_file($fullpath)
    {
        global $kernel;
        return str_replace($kernel->pub_site_root_get(),'',$fullpath);
    }

    public function check_hashes()
    {
        global $kernel;
        $this->check_hashes_for_dir($kernel->pub_site_root_get());
    }

    public function get_total_hash_recs()
    {
        global $kernel;
        $crec = $kernel->db_get_record_simple("_sentinel_filehashes","true","COUNT(*) AS count");
        return $crec['count'];
    }

    /**
     * Функция для отображения административного интерфейса
     *
     * @return string
     */
    public function start_admin()
    {
        global $kernel;
        switch ($kernel->pub_section_leftmenu_get())
        {
            default:
            case 'check_files':
                $this->set_templates($kernel->pub_template_parse("modules/sentinel/templates_admin/check_files.html"));
                $content = $this->get_template_block('content');

                $total = $this->get_total_hash_recs();
                if ($total==0)
                    $form = $this->get_template_block('no_records_message');
                else
                    $form = $this->get_template_block('form');
                $content = str_replace('%form%',$form,$content);
                $content = str_replace('%form_url%',$kernel->pub_redirect_for_form('check_files_do'),$content);
                $content = str_replace('%total%',$total,$content);
                return $content;

            case 'check_files_do':
                $this->check_hashes();
                if (count($this->new_files)==0 && count($this->changed_files)==0)
                    $resp='[#sentinel_results_all_same#]';
                else
                {
                    $resp='';
                    if (count($this->new_files)>0)
                    {
                        $resp.="[#sentinel_results_new_files#]<br>\n";
                        foreach ($this->new_files as $file)
                        {
                            $resp.=$file."<br>\n";
                        }
                    }
                    if (count($this->changed_files)>0)
                    {
                        $resp.="[#sentinel_results_changed_files#]<br>\n";
                        foreach ($this->changed_files as $file)
                        {
                            $resp.=$file."<br>\n";
                        }
                    }
                }
                return $kernel->pub_httppost_response($resp,'',0);

            case 'recalc_hashes':
                $this->set_templates($kernel->pub_template_parse("modules/sentinel/templates_admin/recalc_hashes.html"));
                $content = $this->get_template_block('content');
                $content = str_replace('%form_url%',$kernel->pub_redirect_for_form('recalc_hashes_do'),$content);
                return $content;

            case 'recalc_hashes_do':
                $this->recalc_hashes();
                return $kernel->pub_httppost_response('[#sentinel_recalc_hashes_done#]');
        }
    }


    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления построением меню
     * @return boolean true
     */
    public function interface_get_menu($menu)
    {
        $menu->set_menu_block('[#sentinel_base_name#]');
        $menu->set_menu("[#sentinel_menu_check_files#]","check_files");
        $menu->set_menu("[#sentinel_menu_recalc_hashes#]","recalc_hashes");
        $menu->set_menu_default('recalc_hashes');
        return true;
    }

}