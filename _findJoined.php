<?php 

	/*
	 * File _findJoined.php
	 * NOT PART OF NETTE FRAMEWORK
	 * Copyright (c) 2016, Martin Loučka
	 * Licensed under BSD 3, see /bsd.txt for details
	 * ==============================================
	 * 
	 * Designed for being called via AJAX with http parameter 'celex' representing document to work with.
	 * 
	 * This program performs search in the data files containing information about CJEU cases
	 * (currently available at curia.europa.eu/en/content/juris/c1_juris.htm and 
	 * curia.europa.eu/en/content/juris/c2_juris.htm, but cached locally) 
	 * and prints out given case popular name and, if any, celex numbers of cases that were joined to it.
	 * 
	 * WARNING:
	 * This file implements formatting used in above-mentined documents and is therefore sensitive to changes. 
	 * Regexes, replaces and explode delimiters shall remain unchanged.
	 */

	// get parameter for later usage
	$celex = $_GET['celex'];
	
	// perform preg match to verify it is celex number format
	if (!preg_match('/[0-9EC][0-9]{4}[A-Z0-9]{1,2}[0-9]{4}/', $celex)) {
		echo 'unsupported input format';
		exit;
	}
	
	// extract year and number from celex number and store 
	// $yearLast ..... last two digits of year (curia numbering does not use full year)
	// $numberFinal .. case number without preceding zeroes
	$yearLast = substr($celex, 3, 2);
	$numberFinal = '';
	$numberTmp = str_split(substr($celex, 7, 4));
	$continue = true;
	foreach ( $numberTmp as $key => $char ) {
		if ( $char == '0' && $continue ) { } else {
			$numberFinal .= $char;
			$continue = FALSE;
		}
	}
	
	// get case identifier, implementing marking rules of curia (old cases not preceded with 'C-')
	$caseNo = (( $yearLast >= 53 && $yearLast <= 88 ) ? '' : 'C-') . $numberFinal . '/' . $yearLast;
	
	// define variables for search:
	// files with cached data (cases 1/53 to 373/88 in juris_1, newer in juris_2)
	// juris_1 does not change anymore
	$fileUrlOld = "juris_1.html";
	// juris_2 is re-cached daily (called when run CelexResearch.php)
	$fileUrlNew = "juris_2.html";
	// join the files to get sum of data - necessary to search through all data at once
	$fileData = file_get_contents('./files/' . $fileUrlOld) . file_get_contents('./files/' . $fileUrlNew);
	
	// get data concerning the given case
	// perform preg match for joined cases:
	$mainCaseTmp = preg_match_all('/<tr>(.*)see case\s+<a[^>]+>' . str_replace('/','\/',$caseNo) . '<\/a>/i', $fileData, $joinedCases);	
	// perform preg match for general main case information:
	$mainCaseTmp2 = preg_match('/<a name=["]?\s*' . str_replace('/','\/',$caseNo) . '["]?><\/a>(.+)\)/i', $fileData, $matchesHR);	
	
	// joined cases data now stored in $joinedCases, 
	// main case data that match the given celex stored in $matchesHR
	
	// if translation required, fill $search and $replace with respective arrays and then perform:
	// $humanReadableTmp = explode(",",trim(str_replace($search, $replace,strip_tags(str_replace($caseNo.'<','<',$matchesHR[0])))));
	
	// now extract popular case name from the main case data...
	// get some data and store it in tmp vars
	$humanReadableTmp = explode(",",trim(strip_tags(str_replace($caseNo.'<','<',$matchesHR[0]))));
	$dTmp = date ( "j.&\\nb\sp;n.&\\nb\sp;Y",strtotime($humanReadableTmp[0]) );
	// generate human readable popular case name, show date when decided and reflect document type
	$humanReadable = rtrim( 
		count($humanReadableTmp)>1 ? 
		(
			( $dTmp == '1.&nbsp;1.&nbsp;1970' ? 
			'Case ' :
			( $humanReadableTmp[0] == 'Pending Case' ? 
			'Pending case' : 
			('Judgment of ' . $dTmp )
			) . ', ' ) . $humanReadableTmp[1]
		) : 
		// this is fallback, this node will be later removed by Java Script when processing the output
		'Joined case (to be removed): ' . $humanReadableTmp[0], ')'
	) . ')';
	
	// check if result of joined case search above was empty (0 matches or false, according to PHP docs)
	if ( $mainCaseTmp == 0 ) {
		// if so, all we return is popular case name
		echo $humanReadable;
		exit;
	}

	
	// now iterate through joined cases and generate list of their celex numbers
	// ($joinedCases[0] contains list of joined cases got on line 59)
	
	// reset var
	$celexOut = '';
	// needed for get_data function
	include_once './_client.ajax.php';
	
	// process every item in joined cases array
	foreach ( $joinedCases[0] as $key => $item ) {
		
		// use regex to extract case number from item and store it to $mainCaseMatchesTmp
		$mainCaseTmp2 = preg_match('/<a name=["]?\s*([^"^>]+)["]?>/i', $item, $mainCaseMatchesTmp);

		// get case number formatted to four-digit (add preceding zeroes)
		$mainCase = explode('/',str_replace('C-','',$mainCaseMatchesTmp[1]));
		while (strlen($mainCase[0]) < 4) {
			// now we will have the joined case number in array [0] => case number in 4-digit format, [1] => last two digits of year
			$mainCase[0] = '0' . $mainCase[0];
		}

		// generate celex number of joined case's document currently being processed, implementing celex number rules
		$celexOutNow = '6' . ( $mainCase[1] < 53 ? '20' : '19' ) . $mainCase[1] . 'CN' . $mainCase[0];
		
		// check whether document exists
		$dataTmpCelexExists = get_data(
					"http://eur-lex.europa.eu/legal-content/CS/TXT/?uri=CELEX:" . $celexOutNow, // url
					false, // no cache
					null, // no expiration
					true // return header code
				);
		
		// add processed data to output; if document exists, mark it with '*' suffix
		$celexOut .= $celexOutNow . ( $dataTmpCelexExists == 200 ? '*' : '' ) . ',';
	}

	// print out popular case name, delimiter pipe (|) and then list of joined cases (delimiter ,)
	// note: probably should better be JSON (to-do)
	echo $humanReadable . '|' . $celexOut;
	