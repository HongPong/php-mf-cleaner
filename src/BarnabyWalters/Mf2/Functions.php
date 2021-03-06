<?php
/**
 * @file Functions.php provides utility functions for processing and validating microformats
 * $mf is generally expected to be an array although some functions can verify this, as well.
 * @link microformats.org/wiki/microformats2
 */
namespace BarnabyWalters\Mf2;

use DateTime;
use Exception;

/**
 * Iterates over array keys, returns true if has numeric keys.
 * @param array $arr
 * @return bool
 */
function hasNumericKeys(array $arr) {
	foreach ($arr as $key=>$val) if (is_numeric($key)) return true;
	return false;
}

/**
 * Verifies if $mf is an array without numeric keys, and has a 'properties' key.
 * @param $mf
 * @return bool
 */
function isMicroformat($mf) {
	return (is_array($mf) and !hasNumericKeys($mf) and !empty($mf['type']) and isset($mf['properties']));
}

/**
 * Verifies if $mf has an 'items' key which is also an array, returns true.
 * @param $mf
 * @return bool
 */
function isMicroformatCollection($mf) {
	return (is_array($mf) and isset($mf['items']) and is_array($mf['items']));
}

/**
 * Verifies if $p is an array without numeric keys and has key 'value' and 'html' set.
 * @param $p
 * @return bool
 */
function isEmbeddedHtml($p) {
	return is_array($p) and !hasNumericKeys($p) and isset($p['value']) and isset($p['html']);
}

/**
 * Verifies if property named $propName is in array $mf.
 * @param array $mf
 * @param $propName
 * @return bool
 */
function hasProp(array $mf, $propName) {
	return !empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]);
}

/**
 * shortcut for getPlaintext.
 * @deprecated use getPlaintext from now on
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return mixed|null
 */
function getProp(array $mf, $propName, $fallback = null) {
	return getPlaintext($mf, $propName, $fallback);
}

/**
 * If $v is a microformat or embedded html, return $v['value']. Else return v.
 * @param $v
 * @return mixed
 */
function toPlaintext($v) {
	if (isMicroformat($v) or isEmbeddedHtml($v))
		return $v['value'];
	return $v;
}

/**
 * Returns plaintext of $propName with optional $fallback
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return mixed|null
 * @link http://php.net/manual/en/function.current.php
 */
function getPlaintext(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName])) {
		return toPlaintext(current($mf['properties'][$propName]));
	}

	return $fallback;
}

/**
 * Converts $propName in $mf into array_map plaintext, or $fallback if not valid.
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return null
 */
function getPlaintextArray(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return array_map(__NAMESPACE__ . '\toPlaintext', $mf['properties'][$propName]);

	return $fallback;
}

/**
 * Returns ['html'] element of $v, or ['value'] or just $v, in order of availablility.
 * @param $v
 * @return mixed
 */
function toHtml($v) {
	if (isEmbeddedHtml($v))
		return $v['html'];
	elseif (isMicroformat($v))
		return htmlspecialchars($v['value']);
	return htmlspecialchars($v);
}

/**
 * Gets HTML of $propName or if not, $fallback
 * @param array $mf
 * @param $propName
 * @param null|string $fallback
 * @return mixed|null
 */
function getHtml(array $mf, $propName, $fallback = null) {
	if (!empty($mf['properties'][$propName]) and is_array($mf['properties'][$propName]))
		return toHtml(current($mf['properties'][$propName]));

	return $fallback;
}

/**
 * Returns 'summary' element of $mf or a truncated Plaintext of $mf['properties']['content'] with 19 chars and ellipsis.
 * @deprecated as not often used
 * @param array $mf
 * @return mixed|null|string
 */
function getSummary(array $mf) {
	if (hasProp($mf, 'summary'))
		return getProp($mf, 'summary');

	if (!empty($mf['properties']['content']))
		return substr(strip_tags(getPlaintext($mf, 'content')), 0, 19) . '…';
}

/**
 * Gets the date published of $mf array.
 * @param array $mf
 * @param bool $ensureValid
 * @param null|string $fallback optional result if date not available
 * @return mixed|null
 */
function getPublished(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('published', $mf, $ensureValid, $fallback);
}

/**
 * Gets the date updated of $mf array.
 * @param array $mf
 * @param bool $ensureValid
 * @param null $fallback
 * @return mixed|null
 */
function getUpdated(array $mf, $ensureValid = false, $fallback = null) {
	return getDateTimeProperty('updated', $mf, $ensureValid, $fallback);
}

/**
 * Gets the DateTime properties including published or updated, depending on params.
 * @param $name string updated or published
 * @param array $mf
 * @param bool $ensureValid
 * @param null|string $fallback
 * @return mixed|null
 */
function getDateTimeProperty($name, array $mf, $ensureValid = false, $fallback = null) {
	$compliment = 'published' === $name ? 'updated' : 'published';

	if (hasProp($mf, $name))
		$return = getProp($mf, $name);
	elseif (hasProp($mf, $compliment))
		$return = getProp($mf, $compliment);
	else
		return $fallback;

	if (!$ensureValid)
		return $return;
	else {
		try {
			new DateTime($return);
			return $return;
		} catch (Exception $e) {
			return $fallback;
		}
	}
}

/**
 * True if same hostname is parsed on both
 * @param $u1 string url
 * @param $u2 string url
 * @return bool
 * @link http://php.net/manual/en/function.parse-url.php
 */
function sameHostname($u1, $u2) {
	return parse_url($u1, PHP_URL_HOST) === parse_url($u2, PHP_URL_HOST);
}

/**
 * Large function for fishing out author of $mf from various possible array elements.
 * @param array $mf
 * @param array|null $context
 * @param null $url
 * @param bool $matchName
 * @param bool $matchHostname
 * @return mixed|null
 * @todo: this needs to be just part of an indiewebcamp.com/authorship algorithm, at the moment it tries to do too much
 * @todo: maybe split some bits of this out into separate functions
 *
 */
function getAuthor(array $mf, array $context = null, $url = null, $matchName = true, $matchHostname = true) {
	$entryAuthor = null;
	
	if (null === $url and hasProp($mf, 'url'))
		$url = getProp($mf, 'url');
	
	if (hasProp($mf, 'author') and isMicroformat(current($mf['properties']['author'])))
		$entryAuthor = current($mf['properties']['author']);
	elseif (hasProp($mf, 'reviewer') and isMicroformat(current($mf['properties']['author'])))
		$entryAuthor = current($mf['properties']['reviewer']);
	elseif (hasProp($mf, 'author'))
		$entryAuthor = getPlaintext($mf, 'author');
	
	// If we have no context that’s the best we can do
	if (null === $context)
		return $entryAuthor;
	
	// Whatever happens after this we’ll need these
	$flattenedMf = flattenMicroformats($context);
	$hCards = findMicroformatsByType($flattenedMf, 'h-card', false);

	if (is_string($entryAuthor)) {
		// look through all page h-cards for one with this URL
		$authorHCards = findMicroformatsByProperty($hCards, 'url', $entryAuthor, false);

		if (!empty($authorHCards))
			$entryAuthor = current($authorHCards);
	}

	if (is_string($entryAuthor) and $matchName) {
		// look through all page h-cards for one with this name
		$authorHCards = findMicroformatsByProperty($hCards, 'name', $entryAuthor, false);
		
		if (!empty($authorHCards))
			$entryAuthor = current($authorHCards);
	}
	
	if (null !== $entryAuthor)
		return $entryAuthor;
	
	// look for page-wide rel-author, h-card with that
	if (!empty($context['rels']) and !empty($context['rels']['author'])) {
		// Grab first href with rel=author
		$relAuthorHref = current($context['rels']['author']);
		
		$relAuthorHCards = findMicroformatsByProperty($hCards, 'url', $relAuthorHref);
		
		if (!empty($relAuthorHCards))
			return current($relAuthorHCards);
	}

	// look for h-card with same hostname as $url if given
	if (null !== $url and $matchHostname) {
		$sameHostnameHCards = findMicroformatsByCallable($hCards, function ($mf) use ($url) {
			if (!hasProp($mf, 'url'))
				return false;

			foreach ($mf['properties']['url'] as $u) {
				if (sameHostname($url, $u))
					return true;
			}
		}, false);

		if (!empty($sameHostnameHCards))
			return current($sameHostnameHCards);
	}

	// Without fetching, this is the best we can do. Return the found string value, or null.
	return empty($relAuthorHref)
		? null
		: $relAuthorHref;
}

/**
 * Returns array per parse_url standard with pathname key added.
 * @param $url
 * @return mixed
 * @link http://php.net/manual/en/function.parse-url.php
 */
function parseUrl($url) {
	$r = parse_url($url);
	$r['pathname'] = empty($r['path']) ? '/' : $r['path'];
	return $r;
}

/**
 * See if urls match for each component of parsed urls. Return true if so.
 * @param $url1
 * @param $url2
 * @return bool
 * @see parseUrl()
 */
function urlsMatch($url1, $url2) {
	$u1 = parseUrl($url1);
	$u2 = parseUrl($url2);

	foreach (array_merge(array_keys($u1), array_keys($u2)) as $component) {
		if (!array_key_exists($component, $u1) or !array_key_exists($component, $u1)) {
			return false;
		}

		if ($u1[$component] != $u2[$component]) {
			return false;
		}
	}

	return true;
}

/**
 * Representative h-card
 *
 * Given the microformats on a page representing a person or organisation (h-card), find the single h-card which is
 * representative of the page, or null if none is found.
 *
 * @see http://microformats.org/wiki/representative-h-card-parsing
 *
 * @param array $mfs The parsed microformats of a page to search for a representative h-card
 * @param string $url The URL the microformats were fetched from
 * @return array|null Either a single h-card array structure, or null if none was found
 */
function getRepresentativeHCard(array $mfs, $url) {
	$hCardsMatchingUidUrlPageUrl = findMicroformatsByCallable($mfs, function ($hCard) use ($url) {
		return hasProp($hCard, 'uid') and hasProp($hCard, 'url')
			and urlsMatch(getPlaintext($hCard, 'uid'), $url)
			and count(array_filter($hCard['properties']['url'], function ($u) use ($url) {
				return urlsMatch($u, $url);
			})) > 0;
	});
	if (!empty($hCardsMatchingUidUrlPageUrl)) return $hCardsMatchingUidUrlPageUrl[0];

	if (!empty($mfs['rels']['me'])) {
		$hCardsMatchingUrlRelMe = findMicroformatsByCallable($mfs, function ($hCard) use ($mfs) {
			if (hasProp($hCard, 'url')) {
				foreach ($mfs['rels']['me'] as $relUrl) {
					foreach ($hCard['properties']['url'] as $url) {
						if (urlsMatch($url, $relUrl)) {
							return true;
						}
					}
				}
			}
			return false;
		});
		if (!empty($hCardsMatchingUrlRelMe)) return $hCardsMatchingUrlRelMe[0];
	}

	$hCardsMatchingUrlPageUrl = findMicroformatsByCallable($mfs, function ($hCard) use ($url) {
		return hasProp($hCard, 'url')
			and count(array_filter($hCard['properties']['url'], function ($u) use ($url) {
				return urlsMatch($u, $url);
			})) > 0;
	});
	if (count($hCardsMatchingUrlPageUrl) === 1) return $hCardsMatchingUrlPageUrl[0];

	// Otherwise, no representative h-card could be found.
	return null;
}

/**
 * Makes microformat properties into a flattened array, returned.
 * @param array $mf
 * @return array
 */
function flattenMicroformatProperties(array $mf) {
	$items = array();
	
	if (!isMicroformat($mf))
		return $items;
	
	foreach ($mf['properties'] as $propArray) {
		foreach ($propArray as $prop) {
			if (isMicroformat($prop)) {
				$items[] = $prop;
				$items = array_merge($items, flattenMicroformatProperties($prop));
			}
		}
	}
	
	return $items;
}

/**
 * Flattens microformats. Can intake multiple Microformats including possible MicroformatCollection.
 * @param array $mfs
 * @return array
 */
function flattenMicroformats(array $mfs) {
	if (isMicroformatCollection($mfs))
		$mfs = $mfs['items'];
	elseif (isMicroformat($mfs))
		$mfs = array($mfs);
	
	$items = array();
	
	foreach ($mfs as $mf) {
		$items[] = $mf;
		
		$items = array_merge($items, flattenMicroformatProperties($mf));
		
		if (empty($mf['children']))
			continue;
		
		foreach ($mf['children'] as $child) {
			$items[] = $child;
			$items = array_merge($items, flattenMicroformatProperties($child));
		}
	}
	
	return $items;
}

/**
 *
 * @param array $mfs
 * @param $name
 * @param bool $flatten
 * @return mixed
 */
function findMicroformatsByType(array $mfs, $name, $flatten = true) {
	return findMicroformatsByCallable($mfs, function ($mf) use ($name) {
		return in_array($name, $mf['type']);
	}, $flatten);
}

/**
 * Can determine if a microformat key with value exists in $mf. Returns true if so.
 * @param array $mfs
 * @param $propName
 * @param $propValue
 * @param bool $flatten
 * @return mixed
 * @see findMicroformatsByCallable()
 */
function findMicroformatsByProperty(array $mfs, $propName, $propValue, $flatten = true) {
	return findMicroformatsByCallable($mfs, function ($mf) use ($propName, $propValue) {
		if (!hasProp($mf, $propName))
			return false;
		
		if (in_array($propValue, $mf['properties'][$propName]))
			return true;
		
		return false;
	}, $flatten);
}

/**
 * $callable should be a function or an exception will be thrown. $mfs can accept microformat collections.
 * If $flatten is true then the result will be flattened.
 * @param array $mfs
 * @param $callable
 * @param bool $flatten
 * @return mixed
 * @link http://php.net/manual/en/function.is-callable.php
 * @see flattenMicroformats()
 */
function findMicroformatsByCallable(array $mfs, $callable, $flatten = true) {
	if (!is_callable($callable))
		throw new \InvalidArgumentException('$callable must be callable');
	
	if ($flatten and (isMicroformat($mfs) or isMicroformatCollection($mfs)))
		$mfs = flattenMicroformats($mfs);
	
	return array_values(array_filter($mfs, $callable));
}
