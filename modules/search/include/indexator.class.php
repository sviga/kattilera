<?PHP
/**
 * Индексатор для поискового движка.
 *
 */
class Indexator
{
    var $tag_koeffs = array
        (
            'title'     => 30.0,
            'h1'        => 20.0,
            'h2'        =>  5.0,
            'h3'        =>  3.0,
            'h4'        =>  2.0,
            'strong'    =>  1.8,
            'b'         =>  1.8,
            'bold'      =>  1.8,
            'em'        =>  1.8,
            'i'         =>  1.5,
            'italic'    =>  1.5
        );

    var $stop_words;
    var $urls = array();
    var $parsed_url_keys = array();
    var $parsed_url_ids = array();

    /** @var HtmlParser */
    var $documentparser;

    var $current_url;
    var $current_url_id;

    var $stem_cache;

    var $hash;

    var $new_doc;

    var $file_tmp = '/upload/tmp_indexator_state.tmp';

    /**
     * Конструктор.
     *
     * @return Indexator
     */
    function __construct()
    {
        //setlocale (LC_ALL, array ('ru_RU.CP1251', 'rus_RUS.1251'));
        mb_regex_encoding("UTF-8");
        $this->stop_words = searcher::get_stop_words();
    }




    /********************** public **************************/

    public function clear_index_data()
    {
        $this->delete_state();
        searchdb::clear_index();
    }

    /**
     * Индексировать/переиндексировать сайт,
     * например $indexator->index_site('http://artprom.ap');
     *
     * @param String $site_root
     * @param mixed $cookie_header
     * @return string
     */
    function index_site($site_root, $cookie_header = false)
    {
        global $kernel;
        error_reporting(E_ALL);
        set_time_limit(0);
        $state = $this->load_state();
        if (!$state)
            $this->urls[] = $site_root;
        else
        {
            $this->urls             = $state['urls'];
            $this->parsed_url_ids   = $state['parsed_url_ids'];
            $this->parsed_url_keys  = $state['parsed_url_keys'];
        }

        $html = '';
        $i = 1;
        while ($url=$this->get_next_url())
        {
            $i++;
            //Выедем, что индексируем
            $kernel->pub_console_show("Индексирую урл ".$url);
            flush();
            //Собственно индексация
            $this->index_url($url, $cookie_header);
            if ($i > 1)
            {
                $this->save_state();
                if (isset($_SERVER['HTTP_HOST']))//только если через веб
                    $kernel->pub_redirect_refresh('start_index');
            }
        }

        $kernel->pub_console_show("Индексация завершена");
        searchdb::delete_doubles();
        searchdb::optimize_tables();
        $this->delete_state();
        if (isset($_SERVER['HTTP_HOST']))//только если через веб
            $kernel->pub_redirect_refresh('index');
        return $html;
    }

    private static $ignores_strings=array();



    function is_url_ignored($url)
    {
        global $kernel;
        if (!isset(self::$ignores_strings[$kernel->pub_module_id_get()]))
        {
            self::$ignores_strings[$kernel->pub_module_id_get()]=array();
            foreach(searchdb::get_ignored_strings() as $isr)
            {
               self::$ignores_strings[$kernel->pub_module_id_get()][]=$isr['word'];
            }
        }
        foreach(self::$ignores_strings[$kernel->pub_module_id_get()] as $istring)
        {
            if (strpos($url,$istring)!==false)
                return true;
        }
        return false;
    }



    function save_state()
    {
        global $kernel;

        $state = array();
        $state['urls'] = $this->urls;
        $state['parsed_url_ids'] = $this->parsed_url_ids;
        $state['parsed_url_keys'] = $this->parsed_url_keys;
        $kernel->pub_file_save($this->file_tmp, serialize($state));
    }

    function load_state()
    {
        $filename = '../..'.$this->file_tmp;
        if (!file_exists($filename))
            return false;
        $file = file_get_contents($filename);
        if ($file == false)
            return false;

        $arr = @unserialize($file);
        return $arr;
    }

    private function delete_state()
    {
        global $kernel;
        $kernel->pub_file_delete($this->file_tmp);
    }


    /**
     * Индексировать один конкретный урл,
     * например $indexator->index_url('http://artprom.ap/sitemap.html');
     *
     * @param string $url
     * @param mixed $cookie_header
     * @return void
     */
    function index_url($url, $cookie_header = false)
    {
        global $kernel;

        if ($cookie_header)
        {
            $curl_downloader = new CurlDownloader();
            $curl_downloader->add_header($cookie_header);
            $result = $curl_downloader->get($url);
            $contents = $result->responsecontent->content;
        }
        else
            $contents = @file_get_contents($url);
        if ($contents === false)
        {
            $kernel->pub_console_show(" get failed");
            return;
        }
        $contents = preg_replace("/<!--.{0,1024}?-->/", "", $contents);

        $is_ignored=$this->is_url_ignored($url);


        $this->hash = md5($contents);

        $this->current_url = $url;
        if (!$is_ignored)
        {
            $this->current_url_id = $this->get_url_id($this->current_url);
            $this->parsed_url_ids[] = $this->current_url_id;
            $changed = true;
            if (!$this->new_doc)
            {
                $contents_hash = searchdb::get_contents_hash($this->current_url_id);
                if ($contents_hash == $this->hash)
                    $changed = false;
            }
        }
        else
            $changed=false;



        $this->stem_cache = array();
        if (preg_match("'\\.pdf$'", $url))
        {
            if (!$changed)
                return;
            $pdfparser = new PdfParser($contents);
            $pdfparser->parse();
            if (!$pdfparser->encrypted)
            {
                if (preg_match("'/([^/]+?)$'", $url, $matches))
                    $contents = "<title>$matches[1]</title>";
                else
                    $contents = "";
                $contents .= "<body>".htmlspecialchars($pdfparser->get_text())."</body>";
                $format_id = Searcher::format2format_id("pdf");
            }
            else
            {
                $kernel->pub_console_show("pdf is encrypted");
                return;
            }
        }
        else
            $format_id = Searcher::format2format_id("html");

        $this->documentparser = new HtmlParser($contents);
        $links = $this->documentparser->get_links($this->current_url);
        foreach ($links as $link)
        {
            if (!in_array($link, $this->urls))
            {
                if (strpos($link,'"')!==false)
                    continue;
                $url_parts = parse_url($url);
                $link_parts = @parse_url($link);
                if (!$link_parts)
                {
                    $kernel->pub_console_show("Документ содержит неправильную ссылку: <b>".$link."</b>");
                    flush();
                    continue;
                }
                if ($url_parts['host'] == $link_parts['host'])
                {
                    if (preg_match("~^/content/~i", $link) && !preg_match("~\\.(html|pdf|txt)$~i", $link))
                        continue;
                    $this->urls[] = $link;
                }
            }
        }

        if ($is_ignored)
        {
            $kernel->pub_console_show("ignored: ".$url);
            return;
        }
        if ($changed)
            $this->index_html($format_id);
    }


    /**
     * Очистить индекс для конкретного урла
     *
     * @param String $url
     */
    function empty_url_index($url)
    {
        $url_id   = $this->get_url_id($url);
        searchdb::empty_url_data_from_index($url_id);
    }


    /***************** private ****************************************************/

    private function get_next_url()
    {
        $url_keys = array_keys($this->urls);

        $non_parsed_keys = array_diff($url_keys, $this->parsed_url_keys);
        $non_parsed_keys = array_values($non_parsed_keys);
        if (count($non_parsed_keys) > 0)
        {
            $key = $non_parsed_keys[0];
            $this->parsed_url_keys[] = $key;
            return $this->urls[$key];
        }
        else
            return false;
    }



    private function index_html($format_id)
    {
        $url_id = $this->current_url_id;

        $words_and_tags = $this->documentparser->get_words_and_its_tags();
        if (count($words_and_tags) == 0)
            return;

        $weights = $this->get_weights($words_and_tags);
        $snippeds = $this->get_snippeds($words_and_tags);

        $words = array_keys($weights);
        $word_ids = $this->get_word_ids($words);

        searchdb::update_doc_data($url_id, serialize($snippeds), $this->hash, $format_id);
        searchdb::empty_url_data_from_index($url_id);

        searchdb::lock_index();
        foreach ($words as $word)
        {
            $word_id = $word_ids[(string)$word];
            $weight = (int)round($weights[(string)$word]*1000);
            searchdb::add_to_index($url_id, $word_id, $weight);
        }
        searchdb::unlock_tables();
    }




    private function get_snippeds($words_and_tags)
    {

        $text = "";
        $title = "";
        foreach ($words_and_tags as $word)
        {
            if (empty($title) && in_array("title", $word['tags']))
                $title = trim($word['text']);

            $text .= " ".$word['text'];
        }
        $text = " ".$text." ";
        $word_symbols = searcher::get_word_symbols();
        $non_word_symbols = "[^$word_symbols]";

        $low_text = mb_strtolower($text);
        $positions = array();
        foreach ($this->stem_cache as $word => $stem)
        {

            if (!isset($positions[(string)$stem]))
                $positions[(string)$stem] = array();

            $word_length = strlen($word);
            if (preg_match_all("'$non_word_symbols($word)$non_word_symbols'su", $low_text, $matches, PREG_OFFSET_CAPTURE))
            {
                foreach ($matches[1] as $match)
                {
                    $position = $match[1];
                    $positions[(string)$stem][$position] = $word_length;
                }
            }
        }

        //Проверим, если тайт пустой, то вместо него внесём урл
        if (empty($title))
            $title = $this->current_url;

        $snippeds['text'] = $text;
        $snippeds['positions'] = $positions;
        $snippeds['title']  = $title;

        return $snippeds;
    }

    private function get_weights($words_and_tags)
    {
        $weights = array();
        foreach ($words_and_tags as $word_and_tags)
        {
            $words = $word_and_tags['words'];

            foreach ($words as $word)
            {
                if (in_array($word, $this->stop_words) || mb_strlen($word) > 50)
                    continue;
                $koeff = 1;
                foreach ($word_and_tags['tags'] as $tag)
                    $koeff *= $this->tag_koeffs[$tag];
                if (!isset($this->stem_cache[(string)$word]))
                {
                    $stem = Lingua_Stem_Ru::stem_word($word);
                    $this->stem_cache[(string)$word] = $stem;
                }
                else
                    $stem = $this->stem_cache[(string)$word];

                if (!isset($weights[(string)$stem]))
                    $weights[(string)$stem] = $koeff;
                else
                    $weights[(string)$stem] += $koeff;
            }
            arsort($weights);
        }
        $sum = array_sum($weights);
        foreach ($weights as $stem => $weight)
            $weights[(string)$stem] /= $sum;
        return $weights;
    }


    private function get_word_ids($words)
    {
        $existing_word_ids = searchdb::get_word_ids($words);
        $existing_words = array_keys($existing_word_ids);
        $new_words = array_diff($words, $existing_words);
        $new_word_ids = searchdb::add_words($new_words);
        $ids = $existing_word_ids;
        foreach ($new_word_ids as $word => $id)
            $ids[(string)$word] = $id;
        return $ids;
    }


    private function get_url_id($url)
    {
        $url_id = searchdb::get_url_id($url);
        if ($url_id === false)
        {
            $url_id = searchdb::add_url($url, $this->hash);
            $this->new_doc = true;
        }
        else
            $this->new_doc = false;
        return $url_id;
    }

}