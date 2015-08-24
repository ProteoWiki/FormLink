<?php

# FormLink mechaninsm. Used for internal networks

#Check to see if we're being called as an extension or directly
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

//self executing anonymous function to prevent global scope assumptions
call_user_func( function() {

	#register ourselves with Special:Version
	$GLOBALS['wgExtensionCredits']['parserhook'][] = array(
	  'name' => 'FormLink',
	  'url' => 'https://www.mediawiki.org/wiki/User:Toniher',
	  'author' => 'Toni Hermoso',
	  'description' => 'Adds a simple Form Link.',
	  'version' => '1.0'
	);
	
	$GLOBALS['wgHooks']['ParserFirstCallInit'][] = 'wfFormLinkSetup';
	$GLOBALS['wgHooks']['LanguageGetMagic'][]       = 'wfFormLinkFuncMagic';

});

function wfFormLinkSetup( Parser $parser ) {
	$parser->setHook( 'FormLink', 'wfFormLinkRender' );
	$parser->setFunctionHook( 'FormLink', 'wfFormLinkFuncRender' );
	return true;
}

function wfFormLinkFuncMagic( &$magicWords, $langCode ) {
	$magicWords['FormLink'] = array( 0, 'FormLink' );
	# unless we return true, other parser functions extensions won't get loaded.
	return true;
}


function wfFormLinkFuncRender(&$parser) {

	$params = func_get_args();
	array_shift( $params ); // We already know the $parser ...
	
	$endval = wfCreateFormLink( $parser, $params );
	if (is_numeric($endval)) {
		return $endval;
	}

	else { return $parser->insertStripItem( $endval, $parser->mStripState ); }

}

function wfCreateFormLink( &$parser, $params ) {

	global $wgVersion;

	// Set defaults.
	$extForm = $inLinkStr = $inLinkType = $inTooltip =
	$inQueryStr = $inTargetName = '';

	$classStr = "";
	$inLinkStr = "Get";
	$inLinkMethod = "get";
	$jsonField = "result";
	$inQueryArr = array();

	$positionalParameters = false;
	// - support unlabelled params, for backwards compatibility
	// - parse and sanitize all parameter values
	foreach ( $params as $i => $param ) {
		
		$elements = explode('=', $param, 2 );

		// set param_name and value
		if ( count( $elements ) > 1 && !$positionalParameters ) {
			$param_name = trim( $elements[0] );

			// parse (and sanitize) parameter values
			$value = trim( $parser->recursiveTagParse( $elements[1] ) );
		} else {
			$param_name = null;

			// parse (and sanitize) parameter values
			$value = trim( $parser->recursiveTagParse( $param ) );
		}

		if ( $param_name == 'form' ) {
			$extForm = $value; 
		} elseif ( $param_name == 'link method' ) {
			$inLinkMethod = $value;
		} elseif ( $param_name == 'link text' ) {
			$inLinkStr = $value;
		} elseif ( $param_name == 'link type' ) {
			$inLinkType = $value;
		} elseif ( $param_name == 'query string' ) {
			// Change HTML-encoded ampersands directly to
			// URL-encoded ampersands, so that the string
			// doesn't get split up on the '&'.
			$inQueryStr = str_replace( '&amp;', '%26', $value );
			
			parse_str($inQueryStr, $arr);
			$inQueryArr = array_merge_recursive_distinct( $inQueryArr, $arr );
			
		} elseif ( $param_name == 'tooltip' ) {
			$inTooltip = Sanitizer::decodeCharReferences( $value );
		//} elseif ( $param_name == null && $value == 'popup' ) {
		//	self::loadScriptsForPopupForm( $parser );
		//	$classStr = 'popupformlink';
		} elseif ( $param_name == 'json field' ) {
			$jsonField = $value;
		} elseif ( $param_name !== null && !$positionalParameters ) {
			$value = urlencode($value);
			parse_str("$param_name=$value", $arr);
			$inQueryArr = array_merge_recursive_distinct( $inQueryArr, $arr );
		} elseif ( $i == 0 ) {
			$extForm = $value;
			$positionalParameters = true;
		} elseif ( $i == 1 ) {
			$inLinkStr = $value;
		} elseif ( $i == 2 ) {
			$inLinkType = $value;
		} elseif ( $i == 3 ) {
			// Change HTML-encoded ampersands directly to
			// URL-encoded ampersands, so that the string
			// doesn't get split up on the '&'.
			$inQueryStr = str_replace( '&amp;', '%26', $value );
			
			parse_str($inQueryStr, $arr);
			$inQueryArr = array_merge_recursive_distinct( $inQueryArr, $arr );
		} 
	}

	$link_url = $extForm;
	
	$hidden_inputs = "";
	if ( ! empty($inQueryArr) ) {
		
		//Initialize here
		$query_components = array();
	
		// Special handling for the buttons - query string
		// has to be turned into hidden inputs.
		if ( $inLinkType == 'button' || $inLinkType == 'post button' || ($inLinkType == 'link' && $inLinkMethod=='post')) {

			$query_components = explode( '&', http_build_query( $inQueryArr, '', '&' ) );

			foreach ( $query_components as $query_component ) {
				$var_and_val = explode( '=', $query_component, 2 );
				if ( count( $var_and_val ) == 2 ) {
					$hidden_inputs .= Html::hidden( urldecode( $var_and_val[0] ), urldecode( $var_and_val[1] ) );
				}
			}
		} 

		elseif ($inLinkType == 'retrieve') {
			
			if ($inLinkMethod == 'post') {
			
				$arraypost = array();
				$query_components = explode( '&', http_build_query( $inQueryArr, '', '&' ) );	
				foreach ( $query_components as $query_component ) {
					$var_and_val = explode( '=', $query_component, 2 );
					if ( count( $var_and_val ) == 2 ) {
						$arraypost[ urldecode( $var_and_val[0] ) ] = urldecode( $var_and_val[1] ) ;
					}
				}
				
				$postdata = http_build_query( $arraypost );

				#$username = 'test';
				#$password = 'protiproti2';			

				$end_url = strip_tags($link_url);
				if (!preg_match("/^http\S\:/", $end_url)) {
					global $wgServer;
					$end_url=$wgServer.$end_url;	
				}
					
				$opts = array('http' =>
				    array(
					'method'  => 'POST',
					'header'  => "Content-type: application/x-www-form-urlencoded",
					'content' => $postdata
				    )
				);

				$context  = stream_context_create($opts);
				$str = "";
				# Control outcome
				$str = @file_get_contents(strip_tags($end_url), false, $context);
				
				if ( $str === FALSE ) {
					return 0;
				} else {
					if ($str!= "") {
						# No content
						$jsono = json_decode($str);
						if ($jsono->$jsonField) {
							return(trim($jsono->$jsonField));
						}
					}

					else {return 0;}
				}
			
			} else {
				
				$link_url .= ( strstr( $link_url, '?' ) ) ? '&' : '?';
				$link_url .= str_replace( '+', '%20', http_build_query( $inQueryArr, '', '&' ) );
							
				$opts = array('http' =>
				    array(
					'method'  => 'GET',
					'header'  => 'Content-type: application/x-www-form-urlencoded'
				    )
				);
				
				$context  = stream_context_create($opts);
				$str = file_get_contents($link_url, false, $context);
				return $str;		
			}
			
		} else {
			$link_url .= ( strstr( $link_url, '?' ) ) ? '&' : '?';
			$link_url .= str_replace( '+', '%20', http_build_query( $inQueryArr, '', '&' ) );
		}
	}
	if ( $inLinkType == 'button' || $inLinkType == 'post button' ) {
		// $formMethod = ( $inLinkType == 'button' ) ? 'get' : 'post';
		$formMethod = 'post'; // we force Post method
		$str = Html::rawElement( 'form', array( 'action' => $link_url, 'method' => $formMethod, 'class' => $classStr ),
			Html::rawElement( 'button', array( 'type' => 'submit', 'value' => $inLinkStr ), $inLinkStr ) .
			$hidden_inputs
		);
	} else {
		if ($inLinkMethod == 'post') {
			$jsstr = "$('.linkex').bind('click', function(e) { e.preventDefault(); $(this).closest('form').submit();});";
			 
			$formMethod = 'post';
			$str = Html::rawElement( 'form', array( 'action' => $link_url, 'method' => $formMethod, 'class' => 'formex' ),
				Html::rawElement( 'a', array( 'href' => "#", 'class' => 'linkex', 'title' => $inTooltip ), $inLinkStr ) .	
				$hidden_inputs.
				Html::inlineScript($jsstr)
			);	
		}
		
		else {
			$str = Html::rawElement( 'a', array( 'href' => $link_url, 'class' => $classStr, 'title' => $inTooltip ), $inLinkStr );
		}
	}

	return $str;

}


/**
 * array_merge_recursive merges arrays, but it converts values with duplicate
 * keys to arrays rather than overwriting the value in the first array with the duplicate
 * value in the second array, as array_merge does.
 *
 * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
 * Matching keys' values in the second array overwrite those in the first array.
 *
 * Parameters are passed by reference, though only for performance reasons. They're not
 * altered by this function.
 *
 * See http://www.php.net/manual/en/function.array-merge-recursive.php#92195
 *
 * @param array $array1
 * @param array $array2
 * @return array
 * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
 * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
 */
function array_merge_recursive_distinct( array &$array1, array &$array2 ) {

	$merged = $array1;

	foreach ( $array2 as $key => &$value ) {
		if ( is_array( $value ) && isset( $merged[$key] ) && is_array( $merged[$key] ) ) {
			$merged[$key] = array_merge_recursive_distinct( $merged[$key], $value );
		} else {
			$merged[$key] = $value;
		}
	}

	return $merged;
}

