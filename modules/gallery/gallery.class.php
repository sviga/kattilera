<?php
/**
 * Модуль "Галерея"
 *
 * @author Alehandr aleks-konsultant@mail.ru, s@nchez s@nchez.me
 * @name gallery
 * @version 3.0
 *
 */

require_once dirname(dirname(dirname(__FILE__)))."/include/basemodule.class.php";

class gallery  extends basemodule
{
    private $offset_param_name="go";
    private $cat_param_name="gcat";


    function get_categories_count($moduleid)
    {
        global $kernel;
        $total = 0;
        $crec = $kernel->db_get_record_simple("_gallery_cats","module_id='".$moduleid."'","count(*) AS count");
        if ($crec)
            $total = $crec['count'];
        return $total;
    }

	//показываем содержимое галереи пользователю
    function pub_create_content($template, $perpage)
    {				
        global $kernel;
        $perpage = intval($perpage);
        if ($perpage<1)
            $perpage=20;
        $offset = intval($kernel->pub_httpget_get($this->offset_param_name));
        $catid = intval($kernel->pub_httpget_get($this->cat_param_name));
        $total = 0;
        $curr_cat = false;
        $moduleid = $kernel->pub_module_id_get();
        $this->set_templates($kernel->pub_template_parse($template));

        if ($this->get_categories_count($moduleid)==0 || $catid>0)
        {//если нету категорий или указана категория - выводим фотки
            $show_cats = false;

            $cond="module_id='".$moduleid."'";
            if ($catid>0)
                $cond.=" AND `cat_id`='".$catid."'";
            $items = $kernel->db_get_list_simple("_gallery",$cond,"*",$offset, $perpage);

            $crec = $kernel->db_get_record_simple("_gallery",$cond,"count(*) AS count");

            if ($crec)
                $total = $crec['count'];
            $content = $this->get_template_block('content');
            if ($catid>0)
                $curr_cat = $this->get_category($catid);
        }
        else
        {//иначе - список категорий
            $show_cats = true;
            $items = array();
            $query = "SELECT cats.*, photos.image AS photoimage FROM ".$kernel->pub_prefix_get()."_gallery_cats AS cats
                      LEFT JOIN ".$kernel->pub_prefix_get()."_gallery AS photos ON photos.cat_id=cats.id
                      WHERE cats.module_id='".$moduleid."' GROUP BY cats.id";
            $res=$kernel->runSQL($query);
            while ($row=mysql_fetch_assoc($res))
            {
                $items[]=$row;
            }
            mysql_free_result($res);
            $content = $this->get_template_block('categories_content');
        }

        if (count($items)==0)
            return $this->get_template_block('no_data');
        $curr_page = $kernel->pub_page_current_get().".html";
        $lines = '';
        foreach($items as $data)
        {
            if ($show_cats)
            {
                $line = $this->get_template_block('categories_rows');
                $line = str_replace('%name%', $data['name'], $line);
                $line = str_replace('%link%', $curr_page."?".$this->cat_param_name."=".$data['id'], $line);
                $line = str_replace('%small_image%',"/content/images/".$moduleid."/tn/".$data['photoimage'], $line);
            }
            else
            {
                $line = $this->get_template_block('rows');
                $line = str_replace('%link_small_image%',"/content/images/".$moduleid."/tn/".$data['image'], $line);
                $line = str_replace('%link_big_image%',"/content/images/".$moduleid."/".$data['image'], $line);
                $line = str_replace('%link_source_image%',"/content/images/".$moduleid."/source/".$data['image'], $line);
                $line = str_replace('%title_image%',  $data['title_image'], $line);
                $line = str_replace('%description%', $data['description'], $line);
                $line = str_replace('%post_date%',   $data['post_date'], $line);
            }

            $lines .= $line;
        }

        $content = str_replace('%rows%', $lines, $content);
        if (!$show_cats)
        {
            $purl = $curr_page."?";
            if ($curr_cat)
                $purl.=$this->cat_param_name.'='.$curr_cat['id'].'&';
            $purl.=$this->offset_param_name."=";
            $content = str_replace('%pages%', $this->build_pages_nav($total,$offset,$perpage,$purl), $content);
            if ($curr_cat)
            {
                $content = str_replace('%category_name%', str_replace('%category_name%',$curr_cat['name'],$this->get_template_block('category_name')), $content);
                $content = str_replace('%back2cats_link%', str_replace('%link%',$curr_page,$this->get_template_block('back2cats_link')), $content);
            }
            else
            {
                $content = str_replace('%category_name%', '', $content);
                $content = str_replace('%back2cats_link%', '', $content);
            }
        }
        return $content;
    }

    //вывод случайных фотографий
    function pub_random_photos($template, $max)
    {
        global $kernel;
        $max = intval($max);
        if ($max<1)
            $max=5;
        $moduleid = $kernel->pub_module_id_get();
        $this->set_templates($kernel->pub_template_parse($template));
        $cond="module_id='".$moduleid."' ORDER BY RAND()";
        $items = $kernel->db_get_list_simple("_gallery",$cond,"*",0, $max);
		if (count($items)==0)
           	return $this->get_template_block('no_data');
        $lines = '';
        foreach($items as $data)
        {
            $line = $this->get_template_block('rows');
            $line = str_replace('%link_small_image%',"content/images/".$moduleid."/tn/".$data['image'], $line);
            $line = str_replace('%link_big_image%',"content/images/".$moduleid."/".$data['image'], $line);
            $line = str_replace('%link_source_image%',"content/images/".$moduleid."/source/".$data['image'], $line);
            $line = str_replace('%title_image%',  $data['title_image'], $line);
            $line = str_replace('%description%', $data['description'], $line);
            $line = str_replace('%post_date%',   $data['post_date'], $line);
            $lines .= $line;
        }
        $content = $this->get_template_block('content');
        $content = str_replace('%rows%', $lines, $content);
        //если используется тот же шаблон, что и для вывода галереи, уберём метку для постранички, название категории, и ссылку на список категорий
        $content = str_replace('%pages%', '', $content);
        $content = str_replace('%category_name%', '', $content);
        return $content;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления построением меню
     * @return boolean true
     */
   	function interface_get_menu($menu)
	{
        $menu->set_menu_block('[#gallery_module_label_block_menu#]');
        $menu->set_menu("[#gallery_menu_photos#]","show_photos");
	    $menu->set_menu("[#gallery_menu_add#]","image_edit&image_id=0");
	    $menu->set_menu("[#gallery_menu_cats#]","show_cats");
	    $menu->set_menu("[#gallery_import_archive#]","import_archive_form");
        $menu->set_menu_default('show_photos');
	    return true;
	}

    function get_categories($moduleid)
    {
        global $kernel;
        return $kernel->db_get_list_simple("_gallery_cats","`module_id`='".$moduleid."'");
    }

    function get_category($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_gallery_cats","id=".$id);
    }

    function get_image($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_gallery","id=".$id);
    }

    static function image_delete($irec)
    {
        global $kernel;
        $query = "DELETE FROM ".$kernel->pub_prefix_get()."_gallery WHERE id=".$irec['id'].";";
        $kernel->runSQL($query);
        if (!empty($irec['image']))
        {
            $kernel->pub_file_delete('content/images/'.$irec['module_id'].'/tn/'.$irec['image']);
            $kernel->pub_file_delete('content/images/'.$irec['module_id'].'/source/'.$irec['image']);
            $kernel->pub_file_delete('content/images/'.$irec['module_id'].'/'.$irec['image']);
        }
    }

    private function process_file_upload($file,$dir)
    {
        global $kernel;

        if (!is_uploaded_file($file['tmp_name']))
            return false;

        //Имя файла пропустим через транслит, что бы исключить руские буквы
        //отделив сначала расширение
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = basename($file['name'], ".".$file_ext);
        $only_name = $kernel->pub_translit_string($filename);
        $filename = $only_name.".".$file_ext;

        //Проверим наличе дубликата, и добавим цифру если что
        $i = 0;
        while (file_exists($dir.$filename))
        {
            $filename = $only_name.'_'.$i.'.'.$file_ext;//$i."_".$filename;
            $i++;
        }

        $kernel->pub_file_move($file['tmp_name'], $dir.$filename, true, true);
        return $dir.$filename;
    }

    function process_image($image_path)
    {
        global $kernel;
        $img_big_width    = $kernel->pub_modul_properties_get('img_big_width');
        $img_big_height   = $kernel->pub_modul_properties_get('img_big_height');
        $img_small_width  = $kernel->pub_modul_properties_get('img_small_width');
        $img_small_height = $kernel->pub_modul_properties_get('img_small_height');

        $watermark_path   = $kernel->pub_modul_properties_get('path_to_copyright_file');
        $watermark_place  = $kernel->pub_modul_properties_get('copyright_position');
        $watermark_transparency = $kernel->pub_modul_properties_get('copyright_transparency');

        $big_image = array('width' => $img_big_width['value'],
                           'height' => $img_big_height['value']);
        $thumb_image = array('width' => $img_small_width['value'],
                             'height' => $img_small_height['value']);


        if(intval($kernel->pub_httppost_get('copyright'))>0)
            $watermark_image = array('path' => $watermark_path['value'],
                                     'place' => $watermark_place['value'],
                                     'transparency' => $watermark_transparency['value']);
        else
            $watermark_image = 0;
        $path_to_save = 'content/images/'.$kernel->pub_module_id_get();
        $filename = $kernel->pub_image_save($image_path, 'img'.rand(1000,9999), $path_to_save, $big_image, $thumb_image, $watermark_image);
        return $filename;
    }

    function start_admin()
    {
        global $kernel;
        $content = '';
        $select_menu = $kernel->pub_section_leftmenu_get();
        $moduleid = $kernel->pub_module_id_get();
        $this->set_templates($kernel->pub_template_parse("modules/gallery/templates_admin/template_admin.html"));
        switch ($select_menu)
        {
            case "import_archive_form":
                $msg = '';
                $sessionMsg = "123";
                $content = $this->get_template_block('import_form');

                if (isset($_POST['do_archive_import']))
                {
                    $deleteArchive=false;
                    if ($kernel->pub_httppost_get('import_from')=="upload_file")
                    {
                        $archive_file = $this->process_file_upload($_FILES['archive'],$kernel->pub_site_root_get()."/upload/");
                        if (!$archive_file)
                            $sessionMsg="[#gallery_import_upload_failed#]";
                        else
                            $deleteArchive=true;
                    }
                    else
                        $archive_file = $kernel->pub_httppost_get('file_on_server');

                    if ($archive_file)
                    {
                        set_time_limit(180);
                        ini_set('max_execution_time', 180);


                        require_once(dirname(dirname(dirname(__FILE__)))."/components/pclzip/pclzip.lib.php");

                        $archive = new PclZip($archive_file);
                        $list = $archive->listContent();
                        $added=0;
                        if ($list == 0) //ошибка чтения архива
                            $sessionMsg="[#gallery_import_archive_read_failed#]";
                        else
                        {
                            $sessionMsg = 'file: '.preg_replace('~(.+)/([^/]+)~','$2',$archive_file);
                            //$sessionMsg = 'file: '.$archive_file;
                            $cat_id = $kernel->pub_httppost_get('cat_id');
                            foreach ($list as $file_index=>$afileRec)
                            {
                                $afile = $afileRec['filename'];
                                //нам нужны только изображения
                                if (!preg_match('~\.(gif|jpg|png|jpeg)$~i',$afile))
                                    continue;
                                $eres = $archive->extract(PCLZIP_OPT_BY_INDEX, array($file_index), PCLZIP_OPT_PATH,$kernel->pub_site_root_get()."/upload/",PCLZIP_OPT_REMOVE_ALL_PATH);
                                if ($eres == 0)
                                {//ошибка распаковки
                                    $sessionMsg.=" [#gallery_import_archive_read_failed#] (".$afile.") ";
                                    continue;
                                }
                                $filenameOrig=preg_replace('~(.+)/([^/]+)~','$2',$afile);
                                $file_path = $kernel->pub_site_root_get()."/upload/".$filenameOrig;
                                if (file_exists($file_path))
                                {
                                    $filename=$this->process_image($file_path);
                                    $kernel->pub_file_delete($file_path,false);
                                    if ($kernel->pub_httppost_get('gen_names')=='filenames')
                                        $title_image=$kernel->pub_translit_string(preg_replace('~\.(gif|jpg|png|jpeg)~i','',$filenameOrig));
                                    else
                                        $title_image=($file_index+1);

                                    $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_gallery` ( `module_id`, `cat_id`, `description`, `title_image`, `image`, `post_date`)
                                              VALUES("'.$kernel->pub_module_id_get().'", '.$cat_id.', "","'.$title_image.'", "'.$filename.'", "'.date("Y-m-d").'");';
                                    $kernel->runSQL($query);
                                    $added++;

                                }
                            }
                            $sessionMsg.=', [#gallery_import_added#]'.$added;
                        }
                        if ($deleteArchive)
                            $kernel->pub_file_delete($archive_file,false);
                    }
                    $kernel->pub_session_set('import_msg', $sessionMsg);
                    $kernel->pub_redirect_refresh_reload('import_archive_form');
                    exit;

                }
                $cats_lines='';
                $cats = $this->get_categories($moduleid);
                foreach ($cats as $cat)
                {
                    $cats_lines.="<option value='".$cat['id']."'>".$cat['name']."</option>";
                }
                $files_lines = '';
                $files = array_keys($kernel->pub_files_list_get($kernel->pub_site_root_get()."/upload"));
                foreach ($files as $file)
                {
                    if (preg_match('~\.zip$~i', $file))
                    {
                        $files_lines.='<option value="'.$file.'">'.preg_replace('~(.+)/([^/]+)~','$2',$file).'</option>';
                    }
                }

                $content = str_replace('%action%', $kernel->pub_redirect_for_form('import_archive_form'), $content);
				$content = str_replace("%max_upload_size%", ini_get('post_max_size'), $content);
				$content = str_replace("%catlines%", $cats_lines, $content);
				$content = str_replace("%server_files%", $files_lines, $content);
                $smsg = $kernel->pub_session_get('import_msg');
                if (!is_null($smsg))
                {
                    $msg .= $smsg;
                    $kernel->pub_session_unset('import_msg');
                }
                $content = str_replace("%msg%",$msg, $content);
                break;
            case "category_delete":
                $catid=intval($kernel->pub_httpget_get('id'));
                $kernel->runSQL("UPDATE `".$kernel->pub_prefix_get()."_gallery` SET `cat_id`=0 WHERE `cat_id`=".$catid." AND module_id='".$moduleid."'");
                $kernel->runSQL("DELETE FROM `".$kernel->pub_prefix_get()."_gallery_cats` WHERE id=".$catid);
                $kernel->pub_redirect_refresh('show_cats');
                break;
            case "category_edit":
                $cat = $this->get_category(intval($kernel->pub_httpget_get('catid')));
                if (!$cat)
                    $cat=array('id'=>0, 'name'=>'');
                $content = $this->get_template_block('category_form');
                $content = str_replace('%action%', $kernel->pub_redirect_for_form('category_save'), $content);
                foreach ($cat as $k=>$v)
                {
                    $content = str_replace('%'.$k.'%', htmlspecialchars($v), $content);
                }
                break;
            case "category_save":
                $id=intval($kernel->pub_httppost_get('id'));
                $name=$kernel->pub_httppost_get('name');
                if ($id==0)
                    $q="INSERT INTO `".$kernel->pub_prefix_get()."_gallery_cats` (`name`,`module_id`) VALUES
                                           ('".$name."','".$moduleid."')";
                else
                    $q="UPDATE `".$kernel->pub_prefix_get()."_gallery_cats` SET `name`='".$name."' WHERE id=".$id;
                $kernel->runSQL($q);
                $kernel->pub_redirect_refresh_reload('show_cats');
                break;
            case "show_cats":
                $content = $this->get_template_block('categories_list');
                $cats = $this->get_categories($moduleid);
                $rows = '';
                foreach ($cats as $cat)
                {
                    $row = $this->get_template_block('categories_row');
                    $row = str_replace('%id%', $cat['id'], $row);
                    $row = str_replace('%name%', $cat['name'], $row);
                    $rows.=$row;
                }
                $content = str_replace('%rows%', $rows, $content);
                break;
            case "image_save":
                $id = intval($kernel->pub_httppost_get('id'));
                $title_image = $kernel->pub_httppost_get('title_image');
                $cat_id = intval($kernel->pub_httppost_get('cat_id'));
                $descr = $kernel->pub_httppost_get('koment_image');
                if ($id==0)//обработка изображения только при новой фото
                {
                    if (!is_uploaded_file($_FILES['file_image']['tmp_name']))
                    {//нечего обрабатывать
                        $kernel->pub_redirect_refresh_reload("show_photos");
                        return "";
                    }
                    $tmp_file_image = $_FILES['file_image']['tmp_name'];
                    $filename=$this->process_image($tmp_file_image);
                    $query = 'INSERT INTO `'.$kernel->pub_prefix_get().'_gallery` ( `module_id`, `cat_id`, `description`, `title_image`, `image`, `post_date`)
                              VALUES("'.$moduleid.'", '.$cat_id.', "'.$descr.'","'.$title_image.'", "'.$filename.'", "'.date("Y-m-d").'");';
                }
                else
                {
                    $query = 'UPDATE `'.$kernel->pub_prefix_get().'_gallery`
                              SET `cat_id`='.$cat_id.', `description`="'.$descr.'",`title_image`="'.$title_image.'"
                              WHERE id='.$id;
                }
                $kernel->runSQL($query);
                $kernel->pub_redirect_refresh_reload("show_photos");
            break;

			case "image_delete":
                $irec=$kernel->db_get_record_simple("_gallery","id=".intval($kernel->pub_httpget_get('image_id')));
                if ($irec)
                    self::image_delete($irec);
                $kernel->pub_redirect_refresh('show_photos');
                break;

		    //список фотографий
            case 'show_photos':
                $perpage = 50;
                $total = 0;
                $offset = intval($kernel->pub_httpget_get('offset'));

                $cond = "module_id='".$moduleid."'";
                $catid = intval($kernel->pub_httpget_get('catid'));
                if ($catid>0)
                    $cond.=" AND cat_id=".$catid;
                $sortby=$kernel->pub_httpget_get('sortby');
                switch ($sortby)
                {
                    case 'id':
                    default:
                        $cond.=" ORDER BY id";
                        break;
                    case 'title':
                        $cond.=" ORDER BY `title_image`";
                        break;
                    case 'date':
                        $cond.=" ORDER BY `post_date`";
                        break;
                }


				$images = $kernel->db_get_list_simple("_gallery",$cond);
                $cats = $this->get_categories($moduleid);
                $crec = $kernel->db_get_record_simple("_gallery",$cond,"count(*) AS count");
                if ($crec)
                    $total=$crec['count'];
                $lines='';
                foreach ($images as $data)
                {
                    $line = $this->get_template_block('line');
                    $line = str_replace("%id%",   		  $data['id'],        		$line);
                    $line = str_replace("%module_id%",    $data['module_id'],       $line);
                    $line = str_replace("%title_image%",  $data['title_image'],     $line);
                    $line = str_replace("%description%",  $data['description'],     $line);
                    $line = str_replace("%link_image%",   "/content/images/".$moduleid."/tn/".$data['image'],	$line);
                    $line = str_replace("%post_date%",    $data['post_date'],       $line);
                    $lines .= $line;
                }
                $content = $this->get_template_block('begin').$lines.$this->get_template_block('end');

                $cats_lines='';
                foreach ($cats as $cat)
                {
                    if ($catid==$cat['id'])
                        $cats_lines.="<option value='".$cat['id']."' selected>".$cat['name']."</option>";
                    else
                        $cats_lines.="<option value='".$cat['id']."'>".$cat['name']."</option>";
                }
				$content = str_replace("%sortby%", $sortby, $content);
				$content = str_replace("%catlines%", $cats_lines, $content);
				$content = str_replace("%total%", $total, $content);
                $purl='show_photos&catid='.$catid.'&sortby='.$sortby.'&offset=';
                $content = str_replace('%pages%', $this->build_pages_nav($total,$offset,$perpage,$purl,0,'url'), $content);

             break; 
		    //выводим форму для добавления или редактирования картинки
			 case "image_edit":
                $id = intval($kernel->pub_httpget_get('image_id'));
                $image = $this->get_image($id);
                if (!$image)
                    $image = array('id'=>0, 'title_image'=>'','description'=>'','image'=>'','cat_id'=>0);
                if ($image['id']==0)
				    $content = $this->get_template_block('form_add');
                else
				    $content = $this->get_template_block('form_edit');
                $cats_lines='';
                $cats = $this->get_categories($moduleid);
                foreach ($cats as $cat)
                {
                    if ($image['cat_id']==$cat['id'])
                        $cats_lines.="<option value='".$cat['id']."' selected>".$cat['name']."</option>";
                    else
                        $cats_lines.="<option value='".$cat['id']."'>".$cat['name']."</option>";
                }
				$content = str_replace("%catlines%", $cats_lines, $content);
				$content = str_replace("%img_src%", "/content/images/".$moduleid."/tn/".$image['image'], $content);
				$content = str_replace("%id%", $image['id'], $content);
				$content = str_replace("%title_image%", htmlspecialchars($image['title_image']), $content);
				$content = str_replace("%action%", $kernel->pub_redirect_for_form("image_save"), $content);
                $editor = new edit_content(true);
                $editor->set_edit_name('koment_image');
                $editor->set_simple_theme();
                $editor->set_content($image['description']);
                $content = str_replace('%editor%', $editor->create(), $content);
			break;
        }
        
        return $content;
    }

}