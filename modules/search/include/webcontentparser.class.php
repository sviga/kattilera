<?php

class WebContentParser
{


	function get_links()
	{
		return array();
	}


	function text2words($text)
	{
		$word_symbols = $this->get_word_symbols();
		$text = preg_replace("'[^".$word_symbols."]+'s", " ", $text);
		$text = preg_replace("/\\s+/", " ", $text);

		$text = trim($text);
		$text = $this->strtolower($text);
		if (strlen($text) > 0)
			$words = explode(" ", $text);
		else
			$words = array();

		return $words;
	}


	function get_word_symbols()
	{
		$rus_small_letters = "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";
		$rus_big_letters   = "АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ";
		$word_symbols = $rus_small_letters.$rus_big_letters."a-zA-Z0-9";
		return $word_symbols;
	}


	function strtolower($str)
	{
		$low = "а,б,в,г,д,е,ё,ж,з,и,й,к,л,м,н,о,п,р,с,т,у,ф,х,ц,ч,ш,щ,ъ,ы,ь,э,ю,я";
		$up  = "А,Б,В,Г,Д,Е,Ё,Ж,З,И,Й,К,Л,М,Н,О,П,Р,С,Т,У,Ф,Х,Ц,Ч,Ш,Щ,Ъ,Ы,Ь,Э,Ю,Я";

		$low = explode(",", $low);
		$up  = array_flip(explode(",", $up));

		$result = "";
		for ($i=0; $i< strlen($str); $i++)
		{
			$char = $str{$i};
			if (isset($up[$char]))
				$result .= $low[$up[$char]];
			else
				$result .= strtolower($char);
		}
		return $result;
	}



	function html_entity_decode($html)
	{
		$trans = get_html_translation_table(HTML_ENTITIES);
		$trans = array_flip($trans);
		$html = strtr($html, $trans);
		return $html;
	}
}