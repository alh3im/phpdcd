#!/usr/local/zend/bin/php
<?php
/**
 * Parse the files and detect the functions not used into the project
 *
 * @author Alexandre Heimburger
 */
	require_once 'PHP/Token/Stream.php';
	if (count($argv)!=4) {
		echo 'Missing arguments. Usage "bkdcd filepath output (html|text)"';
		return;
	}
	$file          = $argv[1];
	$output        = $argv[2];
	$lineSep       = $output == 'html' ? '<br>' : '\n';
	$argIndex      = 1;
	$funcs         = array();
	
	$basePath1 = '/some/path';
	$basePath2 = '/some/path';
	$paths = array($basePathBk, $basePathBackend);
	
		echo "Processing file $file";
		$tokens        = new PHP_Token_Stream($file);
		$class         = '';
		$nbTokens      = count($tokens);
		for ($i = 0; $i<$nbTokens; $i++) {
			if($tokens[$i] instanceof PHP_Token_CLASS) {
				$class = $tokens[$i]->getName();
			}
			if($tokens[$i] instanceof PHP_Token_FUNCTION) {
				// Compute line number
				$openBlock = 0;
				$j         = 0;
				while ($j < $nbTokens) {
					$j++;
					if ($tokens[$i+$j] instanceof PHP_Token_OPEN_CURLY) {
						$openBlock++;
					} else if($tokens[$i+$j] instanceof PHP_Token_CLOSE_CURLY) {
						$openBlock--;
						if ($openBlock == 0) {
							break;
						}
					}
				}
				$lineStartFunc = $tokens[$i]->getLine();
				$lineEndFunc   = $tokens[$i+$j] ? $tokens[$i+$j]->getLine() : $lineStartFunc;
				$linesCount    = $lineEndFunc == $lineStartFunc ? 1 : $lineEndFunc-$lineStartFunc;
				$func          = array('class'=>$class,'name'=>$tokens[$i]->getName(), 'linesCount'=>$linesCount);
				if ($tokens[$i-2] instanceof PHP_Token_STATIC || $tokens[$i-4] instanceof PHP_Token_STATIC) {
					$func['isStatic'] = true;
				} else {
					$func['isStatic'] = false;
				}		
				if ($tokens[$i-2] instanceof PHP_Token_PUBLIC || $tokens[$i-4] instanceof PHP_Token_PUBLIC) {
					$func['isPublic'] = true;
				} else {
					$func['isPublic'] = false;
				}		
				$funcs[]       = $func;
			}
		}
	// Look for dead functions
	if (count($funcs)) {
		$globalSavedLines = 0;
		$deadFunctions    = 0;
		$reservedFuncs    = array('__construct', '__call', '__toString');
		foreach ($funcs as $func) {	
			if (in_array($func['name'], $reservedFuncs)) continue;
			$pattern =  $func['isStatic'] ? ($func['isPublic'] ? $func['class']."::".$func['name']."(" : "self::".$func['name']."(") : "\->".$func['name']."(";
			$found = false;
			foreach($paths as $path) {
				$cmd     = "find $path -name '*.php' -not -path \*test\* -not -path \*lib\* | xargs grep '$pattern'";
				$output  = array();
				exec($cmd, $output);
				if (count($output)) {
					$found = true;
					//if ($func['name'] == 'publish') print_r($output);
					break;
				}
			}
			if (!$found) {
				// Try to grep the mapping file
				$pattern = $func['isStatic'] ? $func['class']."::".$func['name'] : "::".$func['name'];
				$cmd     = "find $paths[0] -name 'mapping.php' -not -path \*test\* -not -path \*lib\* | xargs grep '$pattern'";
				$output  = array();
				exec($cmd, $output);
				if (!count($output)) {
					echo "function ".$func['class']."::".$func['name']." is not used. ".$func['linesCount']." lines of code can be saved $lineSep";
					$globalSavedLines+=$func['linesCount'];
					$deadFunctions++;
				} /*else {
					if ($func['name'] == 'publish') print_r($output);
				}*/
			}
			unset($output);
		}
		echo "*********************************************$lineSep";
		echo "$deadFunctions dead functions detected$lineSep";
		echo "$globalSavedLines lines of code can be saved$lineSep";			
		echo "*********************************************$lineSep";
	} else {
		echo 'no function to process<br>';
	}
?>
