<?PHP
require_once("sape.php");
/**
 * Основной управляющий класс модуля «Sape»
 *
 * Модуль организует размещение ссылок для продажи
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */

class sape
{

    var $sape_base = null;


    function sape()
    {
        global $kernel;

        //ВНИМАНИЕ!!!!

        //Конструктор класса вызывается один раз, при первом обращении к дейсвтию модуля
        //модуля, при последующих обращениях (рамках одной страницы) вызов конструктра не происходит


        $user_code = $kernel->pub_modul_properties_get('user_code_sape');
        if ($user_code['isset'])
            $user_code = $user_code['value'];
        else
            $user_code = '';

        define('_SAPE_USER', $user_code);

        $options = array();
        $options['verbose'] = true;
        $options['charset'] = 'UTF-8';
        //$options['debug'] = true;
        $this->sape_base    = new SAPE_client($options);
    }


    //***********************************************************************
    //	Наборы Публичных методов из которых будут строится макросы
    //**********************************************************************

    //Первый параметр не используется в публичном действии
    //Он используется ядром для сортирвки.
    function pub_link_show($priority, $count_link = 0, $template_file='')
    {
        global $kernel;
        $count_link = intval($count_link);
        $html='';//' <!-- tpl:'.$template_file.', count link:'.$count_link.' -->';
        $use_template=false;
        if (!empty($template_file) && file_exists($template_file))//шаблон указан, файл шаблона существует
        {
            $this->sape_base->_links_delimiter=' <!-- sape_separator -->| ';
            $use_template=true;
        }
        else
            $html.='<!-- sape template not found: '.$template_file.' -->';

        if ($count_link > 0 )
            $html .= $this->sape_base->return_links($count_link);
        else
            $html .= $this->sape_base->return_links();

        if ($use_template)
        {
            if ($count_link>0)
                $chunks = explode($this->sape_base->_links_delimiter, $html,$count_link);
            else
                $chunks = explode($this->sape_base->_links_delimiter, $html);
            $template=$kernel->pub_template_parse($template_file);
            $tpl_content=isset($template['links'])?$template['links']:"";
            $link_lines=array();
            foreach ($chunks as $chunk)
            {
                if (!preg_match('~^(?P<before>.*)<a(?:[^>]+)href(?:\\s*)=(?:[\"\']+)(?P<url>[^>]+)(?:[\"\']+)(?:[^>]*)>(?P<text>[^<]+)</a>(?P<after>.*)$~isU',$chunk, $match))
                    return $html.'<!-- preg fail -->';//preg_match не сработал, возвращаем как есть
                $link = isset($template['link'])?$template['link']:"";
                $link = str_replace('%url%', $match['url'],$link);
                $link = str_replace('%text%', $match['text'],$link);
                $link = str_replace('%before%', $match['before'],$link);
                $link = str_replace('%after%', $match['after'],$link);
                $link_lines[] = $link;
            }
            $separator=isset($template['separator'])?$template['separator']:"";
            $tpl_content = str_replace('%links%', implode($separator,$link_lines),$tpl_content);
            return $tpl_content;
        }
        return $html;
    }

	function interface_get_menu($menu)
	{

	    return true;
	}

    /**
     * Основной метод модуля, из которого расходиться всё управление административным разделом модуля
	 *
	 */
    function start_admin()
    {
        return '';
    }

}
?>