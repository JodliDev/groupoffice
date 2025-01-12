<?php /** @noinspection RegExpSimplifiable */
/** @noinspection PhpUnused */

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace go\core\util;

use Exception;
use go\core\App;
use Html2Text\Html2Text;
use Normalizer;
use Throwable;
use Transliterator;

/**
 * Collection of string functions
 * 
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 *
 */
class StringUtil {

  /**
   * Normalize the line end style of text.
   *
   * @param string|null $text
   * @param string $crlf
   * @return string
   */
	public static function normalizeCrlf(?string $text, string $crlf = "\r\n"): ?string
	{
		if(empty($text)) {
			return $text;
		}
		
		$normalized =  preg_replace('/\R/u', $crlf, $text);
		if(empty($normalized)) {
			//fallback on str_replace in case of bad utf8
			return preg_replace("/\r\n|\r|\n/", $crlf, $text);
			//throw new \Exception(array_flip(get_defined_constants(true)['pcre'])[preg_last_error()]. ': while normalizing crlf for: '. $text);
	}
		return $normalized;
	}

  /**
   * Normalize UTF-8 to form C
   *
   * @param string $text
   * @return string
   */
	public static function normalize(string $text): string
	{
		if(empty($text)) {
			return $text;
		}

		$normalized = Normalizer::normalize($text, Normalizer::FORM_C);
		if($normalized === false) {

			//try to clean the string
			$normalized = static::cleanUtf8($text);
		}

		return $normalized;
	}

  /**
   * Check if UTF-8 string is in FORM_C
   *
   * @param $text
   * @return bool
   */
	public static function isNormalized($text): bool
	{
		return Normalizer::isNormalized($text, Normalizer::FORM_C);
	}

  /**
   * Converts any "CamelCased" into an "underscored_word".
   * @param string $camelCasedString the word(s) to underscore
   * @param string
   * @return string
   */
	public static function camelCaseToUnderscore(string $camelCasedString): string
	{
		return strtolower(preg_replace('/(?<=\\w)([A-Z][a-z])/', '_\\1', $camelCasedString));
	}

  /**
   * Converts and cleans a string to valid UTF-8
   *
   * @param ?string $str
   * @param string|null $sourceCharset
   * @param string
   * @return string
   */
	public static function cleanUtf8(?string $str, string $sourceCharset = null): string
	{
		if(empty($str)) {
			return $str;
		}
		
		if(!isset($sourceCharset)){
			$sourceCharset = mb_detect_encoding($str);
			if(!$sourceCharset){
				$sourceCharset = 'UTF-8';
			}
		}
		
		$str = self::convertToUTF8($str, $sourceCharset);

		//Check if preg validates it as UTF8
		if (!mb_check_encoding($str, 'utf8')) {

			//remove non utf8. taken from http://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string
			$regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;

			$str = preg_replace($regex, '$1', $str);
		}

		$normalized = Normalizer::normalize($str, Normalizer::FORM_C);
		if($normalized === false) {
			return $str;
		} else{
			return $normalized;
		}

	}
	
	private static function convertToUTF8($str, $fromCharset) {
		$fromCharset = strtoupper($fromCharset);
		$toCharset = 'UTF-8';
		
		if ($fromCharset == $toCharset) {
			// cleanup invalid chars
			return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
		}
		try {
			if(in_array($fromCharset, array_map("strtoupper", mb_list_encodings()))) {								
				$str = mb_convert_encoding($str, 'UTF-8', $fromCharset);			
			} else
			{
				$str = iconv($fromCharset, 'UTF-8//TRANSLIT', $str);					
			}
		}catch (Throwable $e) {
			App::get()->debug("Could not convert from ".$fromCharset." to UTF8 ".$e->getMessage());
		}
		
		return $str;
	}
	
	

	/**
	 * Check if string has UTF8 characters
	 * 
	 * @param string $str
	 * @return boolean
	 */
	public static function isUtf8(string $str): bool
	{
		return strlen($str) != strlen(utf8_decode($str));
	}

  /**
   * Replace a string within a string once.
   *
   * @param string $search
   * @param string $replace
   * @param string $subject
   * @param bool $found Pass this to check if an occurence was replaced or not
   * @param string
   * @return string
   */
	public static function replaceOnce(string $search, string $replace, string $subject, bool &$found = false): string
	{
		$firstChar = strpos($subject, $search);
		if ($firstChar !== false) {
			$found = true;
			$beforeStr = substr($subject, 0, $firstChar);
			$afterStr = substr($subject, $firstChar + strlen($search));
			return $beforeStr . $replace . $afterStr;
		} else {
			$found = false;
			return $subject;
		}
	}

	/**
	 * Cut's off a string if it exceeds a given length
	 *
	 * @param ?string $str The string to chop
	 * @param int $maxLength The maximum number of characters in the string
	 * @param bool $cutWholeWords
	 * @param string $append
	 * @return string
	 */
	public static function cutString(?string $str, int $maxLength, bool $cutWholeWords = true, string $append = '...'): string
	{
		if(!isset($str)) {
			return "";
		}

		if (strlen($str) <= $maxLength) {
			return $str;
		}
		
		$substrFunc = function_exists('mb_substr') ? 'mb_substr' : 'substr';

		$maxLength -= strlen($append);

		$temp = $substrFunc($str, 0, $maxLength);

		if ($cutWholeWords) {
			if (($pos = strrpos($temp, ' '))) {
				return $substrFunc($temp, 0, $pos) . $append;
			} else {
				return $substrFunc($str, 0, $maxLength) . $append;
			}
		} else {
			return $temp . $append;
		}
		
	}


  /**
   * Convert plain text to HTML
   *
   * @param string $text Plain text string
   * @param string HTML formatted string
   * @return string
   */
	public static function textToHtml(string $text, $convertLinks = true): string
	{

		if ($convertLinks) {
			$text = preg_replace("/\b(https?:\/\/[\pL0-9.&\-\/@#;`~=%?:_+,)(]+)\b/ui", '{lt}a href={quot}$1{quot} target={quot}_blank{quot}{gt}$1{lt}/a{gt}', $text . "\n");
			$text = preg_replace("/\b([\pL0-9._\-]+@[\pL0-9.\-_]+\.[a-z]{2,4})(\s)/ui", "{lt}a href={quot}mailto:$1{quot}{gt}$1{lt}/a{gt}$2", $text);
		}

		//replace repeating spaces with &nbsp;		
		$text = htmlspecialchars($text, ENT_COMPAT);
		$text = str_replace('  ', '&nbsp;&nbsp;', $text);


		$text = nl2br(trim($text));
		//$text = str_replace("\r", "", $text);
		//$text = str_replace("\n", "", $text);
		//we dont use < and > directly with the preg functions because htmlspecialchars will screw it up. We don't want to use
		//htmlspecialchars before the pcre functions because email address like <mschering@intermesh.nl> will fail.

		$text = str_replace("{quot}", '"', $text);
		$text = str_replace("{lt}", "<", $text);
		return str_replace("{gt}", ">", $text);
	}
	
	/**
	 * Convert HTML to plain text
	 * 
	 * @param string $html
	 * @return string
	 */
	public static function htmlToText(string $html): string
	{
		
		//normalize html and remove line breaks
		$html = StringUtil::normalizeCrlf($html);
		
		$html = new Html2Text($html);
		
		return trim($html->getText());
	}

	/**
	 * Detect known XSS attacks.
	 * 
	 * @param boolean $string
	 * @return boolean
	 * @throws Exception 
	 */
	public static function detectXSS(bool $string): bool
	{

// Keep a copy of the original string before cleaning up
		$orig = $string;

// URL decode
		$string = urldecode($string);

// Convert Hexadecimals
//		$string = preg_replace('!(&#|\\\)[xX]([0-9a-fA-F]+);?!e', 'chr(hexdec("$2"))', $string);		
		$string = preg_replace_callback('!(&#|\\\)[xX]([0-9a-fA-F]+);?!', function ($matches) {
			return chr(hexdec($matches[2]));
		}, $string);

// Clean up entities
		$string = preg_replace('!(&#0+[0-9]+)!', '$1;', $string);

// Decode entities
		$string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');

// Strip whitespace characters
		$string = preg_replace('!\s!', '', $string);

// Set the patterns we'll test against
		$patterns = array(
// Match any attribute starting with "on" or xmlns
			//'#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>?#iUu',
			'#(<[^>]+[\s])(on|xmlns)[^>]*>?#iUu',
// Match javascript:, livescript:, vbscript: and mocha: protocols
			'!((java|live|vb)script|mocha):(\w)*!iUu',
			'#-moz-binding[\x00-\x20]*:#u',
// Match style attributes
			'#(<[^>]*+[\x00-\x20\"\'\/])*style=[^>]*(expression|behavior)[^>]*>?#iUu',
// Match unneeded tags
			'#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>?#i'
		);

		foreach ($patterns as $pattern) {
// Test both the original string and clean string
			if (preg_match($pattern, $string, $matches) || preg_match($pattern, $orig, $matches)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check the length of a string. Works with UTF8 too.
	 * 
	 * @param string $str
	 * @return int 
	 */
	public static function length(string $str): int
	{
		return mb_strlen($str, 'UTF-8');
	}

  /**
   * Turn string with underscores into lowerCamelCase
   *
   * eg. message_id or message-id will become messageId
   *
   * @param string $str
   * @param string
   * @return string
   */
	public static function lowerCamelCasify(string $str): string
	{
		
		$str = str_replace('-','_', strtolower($str));		
		$parts = explode('_', $str);		
		$str = array_shift($parts);		
		$str .= implode('', array_map('ucfirst', $parts));
		
		return $str;		
	}


  /**
   * Turn string with underscores into UpperCamelCase
   *
   * eg. message_id or message-id will become MessageId
   *
   * @param string $str
   * @return string
   */
	public static function upperCamelCasify(string $str): string
	{
		$str = str_replace('-','_', strtolower($str));		
		$parts = explode('_', $str);
		return implode('', array_map('ucfirst', $parts));
	}
	
	/**
	 * Explode a search expression in to tokens
	 * 
	 * For example:
	 * 
	 * $expression = "apple bear \"Tom Cruise\" or 'Mickey Mouse' another word";
	 * 
	 * $tokens = StringUtil::explodeSearchExpression($expression);
	 * 
	 * The result will be:
	 * Array
	 * (
	 *     [0] => apple
	 *     [1] => bear
	 *     [2] => Tom Cruise
	 *     [3] => or
	 *     [4] => Mickey Mouse
	 *     [5] => another
	 *     [6] => word
	 * )
	 * 
	 * 1. Accepted delimiters: white spaces (space, tab, new line etc.) and commas.
	 * 
	 * 2. You can use either simple (') or double (") quotes for expressions which contains more than one word.
	 * 
	 * @param string $expression
	 * @return string[]
	 */
	public static function explodeSearchExpression(string $expression): array
	{
		return preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/", $expression, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
	}
	
	
	public static function debugUTF8($str): string
	{
		$ord = "";
		for ( $pos=0, $l = strlen($str); $pos < $l; $pos ++ ) {
		 $byte = substr($str, $pos);
		 $ord .= " U+" . ord($byte);
		}
		
		return $ord;
	}

  /**
   * Generate random string
   *
   * @param int $length
   * @return string
   * @throws Exception
   */
	public static function random(int $length): string
	{
		return bin2hex(random_bytes($length));
	}

	/**
	 * Converts to ASCII.
	 * @param  string  UTF-8 encoding
	 * @return string  ASCII
	 *
	 * @see https://3v4l.org/CiH8j
	 */
	public static function toAscii($str): string
	{
		static $transliterator = null;
		if ($transliterator === null && class_exists('Transliterator', false)) {
			$transliterator = Transliterator::create('Any-Latin; Latin-ASCII');
		}

		$str = preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#u', '', $str);
		$str = strtr($str, '`\'"^~?', "\x01\x02\x03\x04\x05\x06");
		$str = str_replace(
			["\xE2\x80\x9E", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9A", "\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB0"],
			["\x03", "\x03", "\x03", "\x02", "\x02", "\x02", "\x04"], $str
		);
		if ($transliterator !== null) {
			$str = $transliterator->transliterate($str);
		}
		if (ICONV_IMPL === 'glibc') {
			$str = str_replace(
				["\xC2\xBB", "\xC2\xAB", "\xE2\x80\xA6", "\xE2\x84\xA2", "\xC2\xA9", "\xC2\xAE"],
				['>>', '<<', '...', 'TM', '(c)', '(R)'], $str
			);
			$str = iconv('UTF-8', 'WINDOWS-1250//TRANSLIT//IGNORE', $str);
			$str = strtr($str, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e"
				. "\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3"
				. "\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8"
				. "\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
				. "\x96\xa0\x8b\x97\x9b\xa6\xad\xb7",
				'ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt- <->|-.');
			$str = preg_replace('#[^\x00-\x7F]++#', '', $str);
		} else {
			$str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
		}
		$str = str_replace(['`', "'", '"', '^', '~', '?'], '', $str);
		return strtr($str, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
	}


	/**
	 * Split text by non word characters to get useful search keywords.
	 *
	 * @param ?string $text
	 * @param bool $forSave Split differently on save then when searching
	 *
	 * Case "Jansen-Pietersen"
	 *
	 * For save store:
	 * - Jansen-Pietersen
	 * - Jansen
	 * - Pietersen
	 *
	 * On search do:
	 * - Jansen-Pietersen"%
	 *
	 * @return string[]
	 */
	public static function splitTextKeywords(?string $text, bool $forSave = true): array
	{

		if(empty($text)) {
			return [];
		}

		//remove all characters we don't care about
		$text = preg_replace('/[^\w\-_+\\\\\/\s:@,;]/u', '', mb_strtolower($text));


		// TODO transliterate to ascii so utf8 chars can be found with their ascii
		// counterparts too ???
		//
//		$text = StringUtil::toAscii($text);

		//split on white space
		$keywords = mb_split("[\s,;]+", $text);

		if($forSave) {
			// Add words separated too when they are joined with -, /, \ or _. eg. Jansen-Pietersen or test/foo
			// so they can be found with Jansen-Pietersen but also with Pietersen
			$secondPassKeywords = [];
			foreach ($keywords as $keyword) {
				// handle email differently. We want info@group-office.com split in
				// info and group-office.com. With names like Jansen-Pietersen we want
				// Jansen and Pietersen.
				$split = explode('@', $keyword);
				if(count($split) != 2) {
					//also split domains on . and words on -,\,/ for searching
					$split = mb_split('[_\-\\\\\/.]', $keyword);
				}

				if (count($split) > 1) {
					$secondPassKeywords = array_merge($secondPassKeywords, $split);
				}

			}

			$keywords = array_merge($keywords, $secondPassKeywords);
		}

		//remove empty stuff.
		return array_filter($keywords, function($keyword) {
			$word = preg_replace("/[^\w]/u", "", $keyword);
			return !empty($word);
		});


	}


	/**
	 * Filter out words that are already in the beginning of other words.
	 *
	 * We search for:
	 *
	 * where word like 'foo%'
	 *
	 * So if we store a word 'foobar' we don't need to store 'foo' as 'foobar'
	 * will already cover that.
	 *
	 * @param array $keywords
	 * @return void
	 */
	public static function filterRedundantSearchWords(array $keywords) : array {
		$keywords = array_unique($keywords);

		return array_values(array_filter($keywords, function($word) use ($keywords) {
			foreach($keywords as $keyword) {
				//don't check the same
				if($keyword === $word) {
					continue;
				}

				if(mb_strpos($keyword, $word) === 0) {
					return false;
				}
			}
			return true;
		}));
	}
}
