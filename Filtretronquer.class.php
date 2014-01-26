<?php
/**
 * Plugin filtre tronquer Thelia 1.5.x
 *
 * @package	Thelia
 * @author	Benoit Asselin <contact@ab-d.fr>
 * @version	Filtretronquer.class.php, 2014/01/26
 * @link	http://www.ab-d.fr/
 *
 */


//error_reporting(E_ALL);
//ini_set('display_errors', true);


include_once(dirname(__FILE__) . '/../../../classes/PluginsClassiques.class.php');



class Filtretronquer extends PluginsClassiques {
	
	const VERSION = '1.0';
	const MODULE = 'Filtretronquer';
	const NOM_MODULE = 'Filtre tronquer';
	
	const FIN = '...';
	
	
	
	/**
	 * Constructeur du plug-in
	 */
	public function __construct() {
		parent::__construct(self::MODULE);
		
	}
	
	/**
	 * Traitement post Thelia
	 * @global string $res
	 */
	public function post() {
		global $res;
		if(strstr($res, "#FILTRE_tronquer_mot")) { $res = $this->filtre_tronquer_mot($res); }
		if(strstr($res, "#FILTRE_tronquer_exact")) { $res = $this->filtre_tronquer_exact($res); }
		
	}
	
	/**
	 * Traitement global du filtre tronquer sans couper les mots
	 * @param string $html
	 * @return string
	 */
	public function filtre_tronquer_mot($html) {
		$regexp = '/\#FILTRE_tronquer_mot\(([^\|]*)\|\|([^\)]+)\)/';
		
		if(preg_match_all($regexp, $html, $matches, PREG_SET_ORDER)) {
			foreach($matches as $match) {
				$tmpSearch = $match[0];
				$tmpHTML = $match[1];
				$tmpLength = (int)$match[2];
				
				$tmpReplace = $this->truncate($tmpHTML, $tmpLength, array('ending' => self::FIN, 'exact' => false, 'html' => true));
				
				$html = str_replace($tmpSearch, $tmpReplace, $html);
				
			} // for
		}
		return $html;
	}
	
	/**
	 * Traitement global du filtre tronquer exactement a la lettre
	 * @param string $html
	 * @return string
	 */
	public function filtre_tronquer_exact($html) {
		$regexp = '/\#FILTRE_tronquer_exact\(([^\|]*)\|\|([^\)]+)\)/';
		
		if(preg_match_all($regexp, $html, $matches, PREG_SET_ORDER)) {
			foreach($matches as $match) {
				$tmpSearch = $match[0];
				$tmpHTML = $match[1];
				$tmpLength = (int)$match[2];
				
				$tmpReplace = $this->truncate($tmpHTML, $tmpLength, array('ending' => self::FIN, 'html' => true));
				
				$html = str_replace($tmpSearch, $tmpReplace, $html);
				
			} // for
		}
		return $html;
	}
	
	/**
	 * Truncates text.
	 *
	 * Cuts a string to the length of $length and replaces the last characters
	 * with the ending if the text is longer than length.
	 *
	 * ### Options:
	 *
	 * - `ending` Will be used as Ending and appended to the trimmed string
	 * - `exact` If false, $text will not be cut mid-word
	 * - `html` If true, HTML tags would be handled correctly
	 *
	 * @param string $text String to truncate.
	 * @param integer $length Length of returned string, including ellipsis.
	 * @param array $options An array of html attributes and options.
	 * @return string Trimmed string.
	 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/text.html#TextHelper::truncate
	 */
	public function truncate($text, $length = 100, $options = array()) {
		$default = array(
		    'ending' => '...', 'exact' => true, 'html' => false
		);
		$options = array_merge($default, $options);
		extract($options);
		
		if (!function_exists('mb_strlen')) {
			class_exists('Multibyte');
		}
		
		if ($html) {
			if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
				return $text;
			}
			$totalLength = mb_strlen(strip_tags($ending));
			$openTags = array();
			$truncate = '';
			
			preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
			foreach ($tags as $tag) {
				if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
					if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
						array_unshift($openTags, $tag[2]);
					} elseif (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
						$pos = array_search($closeTag[1], $openTags);
						if ($pos !== false) {
							array_splice($openTags, $pos, 1);
						}
					}
				}
				$truncate .= $tag[1];
				
				$contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));
				if ($contentLength + $totalLength > $length) {
					$left = $length - $totalLength;
					$entitiesLength = 0;
					if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
						foreach ($entities[0] as $entity) {
							if ($entity[1] + 1 - $entitiesLength <= $left) {
								$left--;
								$entitiesLength += mb_strlen($entity[0]);
							} else {
								break;
							}
						}
					}
					
					$truncate .= mb_substr($tag[3], 0, $left + $entitiesLength);
					break;
				} else {
					$truncate .= $tag[3];
					$totalLength += $contentLength;
				}
				if ($totalLength >= $length) {
					break;
				}
			}
		} else {
			if (mb_strlen($text) <= $length) {
				return $text;
			} else {
				$truncate = mb_substr($text, 0, $length - mb_strlen($ending));
			}
		}
		if (!$exact) {
			$spacepos = mb_strrpos($truncate, ' ');
			if ($html) {
				$truncateCheck = mb_substr($truncate, 0, $spacepos);
				$lastOpenTag = mb_strrpos($truncateCheck, '<');
				$lastCloseTag = mb_strrpos($truncateCheck, '>');
				if ($lastOpenTag > $lastCloseTag) {
					preg_match_all('/<[\w]+[^>]*>/s', $truncate, $lastTagMatches);
					$lastTag = array_pop($lastTagMatches[0]);
					$spacepos = mb_strrpos($truncate, $lastTag) + mb_strlen($lastTag);
				}
				$bits = mb_substr($truncate, $spacepos);
				preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
				if (!empty($droppedTags)) {
					if (!empty($openTags)) {
						foreach ($droppedTags as $closingTag) {
							if (!in_array($closingTag[1], $openTags)) {
								array_unshift($openTags, $closingTag[1]);
							}
						}
					} else {
						foreach ($droppedTags as $closingTag) {
							array_push($openTags, $closingTag[1]);
						}
					}
				}
			}
			$truncate = mb_substr($truncate, 0, $spacepos);
		}
		$truncate .= $ending;
		
		if ($html) {
			foreach ($openTags as $tag) {
				$truncate .= '</' . $tag . '>';
			}
		}
		
		return $truncate;
	}
	
}


