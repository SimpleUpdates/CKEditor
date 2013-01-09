<?php
header('Content-type: text/html; charset=utf-8');

// The following variables values must reflect your installation needs.

//$aspell_prog	= '"C:\Program Files\Aspell\bin\aspell.exe"';	// by FredCK (for Windows)
$aspell_prog	= 'aspell';										// by FredCK (for Linux)

$lang			= 'en_US';
$aspell_opts	= "-a --lang=$lang --encoding=utf-8 -H --rem-sgml-check=alt";		// by FredCK

$tempfiledir = './';

$spellercss		= 'third-party/CKEditor/plugins/aspell/spellerpages/spellerStyle.css';						// by FredCK
$word_win_src	= 'third-party/CKEditor/plugins/aspell/spellerpages/wordWindow.js';							// by FredCK

$textinputs		= $_POST['textinputs']; # array
$input_separator = "A";
define("USEPSPELL", function_exists("pspell_new"));	//Set to TRUE to use PSPELL in PHP instead of ASPELL, it defaults to checking to see if pspell is installed.

# set the JavaScript variable to the submitted text.
# textinputs is an array, each element corresponding to the (url-encoded)
# value of the text control submitted for spell-checking
function print_textinputs_var() {
	global $textinputs;
	foreach( $textinputs as $key=>$val ) {
		# $val = str_replace( "'", "%27", $val );
		echo "textinputs[$key] = decodeURIComponent(\"" . $val . "\");\n";
	}
}

# make declarations for the text input index
function print_textindex_decl( $text_input_idx ) {
	echo "words[$text_input_idx] = [];\n";
	echo "suggs[$text_input_idx] = [];\n";
}

# set an element of the JavaScript 'words' array to a misspelled word
function print_words_elem( $word, $index, $text_input_idx ) {
	echo "words[$text_input_idx][$index] = '" . escape_quote( $word ) . "';\n";
}


# set an element of the JavaScript 'suggs' array to a list of suggestions
function print_suggs_elem( $suggs, $index, $text_input_idx ) {
	echo "suggs[$text_input_idx][$index] = [";
	foreach( $suggs as $key=>$val ) {
		if( $val ) {
			echo "'" . escape_quote( $val ) . "'";
			if ( $key+1 < count( $suggs )) {
				echo ", ";
			}
		}
	}
	echo "];\n";
}

# escape single quote
function escape_quote( $str ) {
	return preg_replace ( "/'/", "\\'", $str );
}


# handle a server-side error.
function error_handler( $err ) {
	echo "error = '" . preg_replace( "/['\\\\]/", "\\\\$0", $err ) . "';\n";
}

## get the list of misspelled words. Put the results in the javascript words array
## for each misspelled word, get suggestions and put in the javascript suggs array
function print_checker_results() {

	global $aspell_prog;
	global $aspell_opts;
	global $tempfiledir;
	global $textinputs;
	global $lang;
	global $input_separator;
	$aspell_err = "";
	# create temp file
	// Uncomment the following line if you need help determining which version you are using.
	// print 'alert("using ' . (USEPSPELL ? 'pspell' : 'aspell') . '");' . "\n";

	// If we are to use Pspell and we can open that dictionary, use pspell
	if(USEPSPELL) {
		// PSpell version
		$text_input_index = -1;
		if(($pspell_link = @pspell_new($lang)) == 0) {
			error_handler( "System error: undefined language '$lang' can not spell check." );
			return TRUE;
		}

		for( $i = 0; $i < count( $textinputs ); $i++ ) {
			$text = urldecode( $textinputs[$i] );
			$lines = explode( "\n", $text );
			$text_input_index++;
			print_textindex_decl( $text_input_index );
			$idx = 0;
			foreach( $lines as $line ) {
				// Clean up the input so that we get 'rid' of all the HTML that may sneak into the spell check.
				$line = preg_replace("/<.*?>/", "<", $line);	//remove tags
				$line = preg_replace("/&.*?;/", "&", $line);	//remove special characters.... consider converting back to text?
				$words = preg_split("/\\W+/", $line);			//split per word
				foreach($words as $word) {
					if (!pspell_check($pspell_link, $word)) {
							print_words_elem( $word, $idx, $text_input_index );
							print_suggs_elem( pspell_suggest($pspell_link, $word), $idx, $text_input_index );
							$idx++;
					}
				} // foreach word
			} // foreach line
		} // foreach section
		return TRUE;
	} // End of PSPELL for PHP
	$tempfile = tempnam( $tempfiledir, 'aspell_data_' );

	# open temp file, add the submitted text.
	if( $fh = fopen( $tempfile, 'w' )) {
		for( $i = 0; $i < count( $textinputs ); $i++ ) {
			$text = urldecode( $textinputs[$i] );

			// Strip all tags for the text. (by FredCK - #339 / #681)
			$text = preg_replace( "/<[^>]+>/", " ", $text ) ;

			$lines = explode( "\n", $text );
			fwrite ( $fh, "%\n" ); # exit terse mode
			fwrite ( $fh, "^$input_separator\n" );
			fwrite ( $fh, "!\n" ); # enter terse mode
			foreach( $lines as $key=>$value ) {
				# use carat on each line to escape possible aspell commands
				fwrite( $fh, "^$value\n" );
			}
		}
		fclose( $fh );

		# exec aspell command - redirect STDERR to STDOUT
		$cmd = "$aspell_prog $aspell_opts < $tempfile 2>&1";
		if( $aspellret = shell_exec( $cmd )) {
			$linesout = explode( "\n", $aspellret );
			$index = 0;
			$text_input_index = -1;
			# parse each line of aspell return
			foreach( $linesout as $key=>$val ) {
				$chardesc = substr( $val, 0, 1 );
				# if '&', then not in dictionary but has suggestions
				# if '#', then not in dictionary and no suggestions
				# if '*', then it is a delimiter between text inputs
				# if '@' then version info
				if( $chardesc == '&' || $chardesc == '#' ) {
					$line = explode( " ", $val, 5 );
					print_words_elem( $line[1], $index, $text_input_index );
					if( isset( $line[4] )) {
						$suggs = explode( ", ", $line[4] );
					} else {
						$suggs = array();
					}
					print_suggs_elem( $suggs, $index, $text_input_index );
					$index++;
				} elseif( $chardesc == '*' ) {
					$text_input_index++;
					print_textindex_decl( $text_input_index );
					$index = 0;
				} elseif( $chardesc != '@' && $chardesc != "" ) {
					# assume this is error output
					$aspell_err .= $val;
				}
			}
			if( $aspell_err ) {
				$aspell_err = "Error executing `$cmd`\\n$aspell_err";
				error_handler( $aspell_err );
			}
		} else {
			error_handler( "System error: Aspell program execution failed (`$cmd`)" );
		}
	} else {
		error_handler( "System error: Could not open file '$tempfile' for writing" );
	}

	# close temp file, delete file
	unlink( $tempfile );
}


?>
<html>
<head>
<base href="/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="<?php echo $spellercss ?>" />
<script language="javascript" src="<?php echo $word_win_src ?>"></script>
<script language="javascript">
var suggs = new Array();
var words = new Array();
var textinputs = new Array();
var error;
<?php

print_textinputs_var();

print_checker_results();

?>

var wordWindowObj = new wordWindow();
wordWindowObj.originalSpellings = words;
wordWindowObj.suggestions = suggs;
wordWindowObj.textInputs = textinputs;

function init_spell() {
	// check if any error occured during server-side processing
	if( error ) {
		alert( error );
	} else {
		// call the init_spell() function in the parent frameset
		if (parent.frames.length) {
			parent.init_spell( wordWindowObj );
		} else {
			alert('This page was loaded outside of a frameset. It might not display properly');
		}
	}
}



</script>

</head>
<!-- <body onLoad="init_spell();">		by FredCK -->
<body onLoad="init_spell();" bgcolor="#ffffff">

<script type="text/javascript">
wordWindowObj.writeBody();
</script>

</body>
</html>
