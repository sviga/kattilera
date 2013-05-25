<?PHP

class HtmlParser /*extends WebContentParser*/
{
    var $html;



    var $parts = array();

    function HtmlParser($html="")
    {
        $this->html = $html;
        $this->html = preg_replace("~<script([^>]*)>(.*)</script>~isU", "", $this->html);
        $this->html = preg_replace("~<style([^>]*)>(.*)</style>~isU", "", $this->html);
        $this->html = preg_replace("~<noindex>(.*)</noindex>~isU", "", $this->html);
        $this->html = preg_replace("~<!--(.*)-->~isU", "", $this->html);
        $this->html = preg_replace("~<!DOCTYPE(.+)>~isU", "", $this->html);
        //$this->html = preg_replace("~&[a-z]+?;~", " ", $this->html);
    }

    function get_links($base)
    {
        $hrefs = array();
        if (preg_match_all("/a[\\s]+[^>]*?href[\\s]?=[\\s\"\\']+(.*?)[\"\\']+.*?>/is", $this->html, $matches))
            $hrefs = $matches[1];

        $hrefs = array_unique($hrefs);

        $urlparser = new UrlParser($base);

        $normal_hrefs = array();
        foreach ($hrefs as $href)
        {

            $abs_url = $urlparser->get_absolute_url($href);
		    $tmp_href       = @parse_url($abs_url);
            if ($tmp_href===false)
               continue;
            $tmp_href_path  = '';
            $tmp_href_query = '';
            if (isset($tmp_href['path']))
                $tmp_href_path = trim($tmp_href['path']);

            if (isset($tmp_href['query']))
                $tmp_href_query = ($tmp_href['query']);


            if (preg_match("/\.(css|doc|xls|zip|rar|gzip|gz|tar|jpg|jpeg|avi|png|mp3|js)$/i", $tmp_href_path)) {
                continue;
            }

            //Выбираем только HTML-ки и PDF - всё остальное - лесом
            if (true || preg_match("/\\.(pdf|html|htm)$/", $tmp_href_path) || empty($tmp_href_path))
            {
                //$kernel->pub_console_show("--".$tmp_href_query);
                //Кроме того, выкинем отсюда ссылку на версию для печати, если она есть
                if (preg_match("/pub_create_pageprint/", $tmp_href_query))
                    continue;

                //Всё прошли, ссылка проходит
                //$normal_hrefs[] = $href;
                $normal_hrefs[] = $abs_url;


            }


        }
        return $normal_hrefs;

        //$urlparser = new UrlParser($base);
        //$links = array();
        //foreach ($normal_hrefs as $href)
        //{
            //$kernel->pub_console_show("---".$href);
        //  $links[] = $urlparser->get_absolute_url($href);
        //}
        //print_r($links);

        //return $links;
    }

    function get_words_and_its_tags()
    {
        preg_match_all("/(<[^!][^<>]*?>|<!--|-->)/su", $this->html, $matches, PREG_OFFSET_CAPTURE);
        $this->parts = array();
        $end_position = -1;

        foreach ($matches[0] as $match)
        {
            $start_position = $match[1];
            $tag = $match[0];

            $this->add_text($start_position, $end_position);
            $this->add_tag($tag);
            $end_position =  $start_position + strlen($tag) - 1;
        }

        $start_position = mb_strlen($this->html);
        $this->add_text($start_position, $end_position);



        $result = array();
        $tags = array();
        $word_buffer = false;
        foreach ($this->parts as $part)
        {
            if (isset($part['tag']))
            {
                if ($word_buffer !== false)
                {
                    $result[] = $word_buffer;
                    $word_buffer = false;
                }

                $tag = $part['tag'];
                if ($part['open'])
                    $tags[] = $tag;
                else
                {
                    $key = array_search($tag, $tags);
                    unset($tags[$key]);
                    $tags = array_values($tags);
                }
            }
            else
            {
                $text = $part['text'];
                $text = $this->html_entity_decode($text);
                $words = searcher::text2words($text);
                if (count($words) > 0)
                {
                    if ($word_buffer === false)
                        $word_buffer = array('text' => $text, 'words' => $words, 'tags' => $tags);
                    else
                    {
                        $word_buffer['words'] = array_merge($word_buffer['words'], $words);
                        $word_buffer['text'] .= ' '.$text;
                    }
                }
                //html_entity_decode(trim($part['text']));
            }
        }

        if ($word_buffer !== false)
            $result[] = $word_buffer;

        return $result;
        //print_r($result);
    }







    function html_entity_decode($html)
    {
        $trans = get_html_translation_table(HTML_ENTITIES);
        $trans = array_flip($trans);
        $html = strtr($html, $trans);
        return $html;
    }

    function add_text($start_position, $end_position)
    {
        $text_length = $start_position - $end_position - 1;
        if ($text_length > 0)
        {
            $plain_text = trim(substr($this->html, $end_position + 1, $text_length));
            if (!empty($plain_text))
                $this->parts[] = array('text' => $plain_text);
        }
    }


    function add_tag($tag)
    {
        $tag = strtolower($tag);
        if (preg_match("'<(/?)([a-z0-9]+?)(\\s|>)'s", $tag, $matches))
        {
            $closed = $matches[1];
            $tagname = $matches[2];
            if (in_array($tagname, array('title', 'h1', 'h2', 'h3', 'h4', 'b', 'strong', 'i', 'em', 'bold', 'italic')))
                $this->parts[] = array('tag' => $tagname, 'open' => empty($closed));
        }
    }

}