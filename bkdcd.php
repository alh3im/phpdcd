#!/usr/local/zend/bin/php
<?php
/**
 * Parse the files and detect the functions not used into the project
 *
 * @author Alexandre Heimburger
 */
	require_once 'PHP/Token/Stream.php';
	require_once 'PHP/Token.php';
	if (count($argv)!=3) {
		echo 'Missing arguments. Usage "bkdcd filepath output (html|text)"';
		return;
	}
	$filePath      = $argv[1];
	$output        = $argv[2];
	$lineSep       = $output == 'html' ? "<br>" : "\n";
	$argIndex      = 1;
	$funcs         = array();
	
	$basePath1 = '/Users/ahb/Documents/work/code/trunk';
	$basePath2 = '/Users/ahb/Documents/work/code/bkbackend';
	$paths     = array($basePath1, $basePath2);
	$dir       = opendir($filePath); 
	while($file = readdir($dir)) {
		
		if ($file == "." || $file == "..") continue;
		if (is_dir($filePath."/".$file)) continue;
		
		echo "Processing file $file $lineSep";
		$tokens        = new PHP_Token_Stream($filePath."/".$file);
		$class         = '';
		$nbTokens      = count($tokens);
		$funcs         = array();
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
		// Look for dead class
		$pattern = $class;
		$found   = false;
		foreach($paths as $path) {
			$cmd     = "find $path -name '*.php' -not -path \*test\* -not -path \*lib\* | xargs grep '$pattern'";
			$output  = array();
			exec($cmd, $output);
			if (count($output)) {
				$found = true;
				break;
			}
		}
		if ($found==false) {
			echo "$lineSep*********************************************$lineSep";
			echo "$class is never used $lineSep";
			echo "*********************************************$lineSep";
			return 0;
		}
		unset($output);
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
						break;
					}
				}
				unset($output);
				if (!$found) {
					echo "function ".$func['class']."::".$func['name']." is not used. ".$func['linesCount']." lines of code can be saved $lineSep";
					$globalSavedLines+=$func['linesCount'];
					$deadFunctions++;
				}
				unset($output);
			}
			echo "$lineSep*********************************************$lineSep";
			echo "$deadFunctions dead functions detected$lineSep";
			echo "$globalSavedLines lines of code can be saved$lineSep";			
			echo "*********************************************$lineSep";
		} else {
			echo "no function to process $lineSep";
		}
	}
?>
