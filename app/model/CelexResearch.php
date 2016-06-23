<?php
/*
 * Copyright 2016, Martin Loučka, licensed under BSD 3
 */

namespace App\Model;

use Nette,
    Nette\Utils\Strings;

/*
 * Provides function get_data used for optimized curl-loading from third party servers
 */
include_once ROOT_DIR . '_client.ajax.php';

class CelexResearch extends Nette\Object {
	/*
	 * list of all cases that appeared before CJEU is on-line and divided into two files.
	 * one we have permanently cached (these do not change)
	 * one is regularly updated with new cases so we need to re-cache
	 */
    const JURIS_URL_LAST = 'http://curia.europa.eu/en/content/juris/c2_juris.htm';
    const JURIS_CACHE_FILENAME = '/files/juris_2.html';
    const JURIS_CACHE_LASTUPDATE = '/files/juris_2_last_update.txt';

	/*
	 * updates local cache meta-data
	 */
    public function updateJuris() {
        file_put_contents(ROOT_DIR . self::JURIS_CACHE_FILENAME, get_data(self::JURIS_URL_LAST));
        file_put_contents(ROOT_DIR . self::JURIS_CACHE_LASTUPDATE, date('Y-m-d'));
    }
    
	/*
	 * update every day
	 */
    public function caseListUpdateCheck() {
        if(!file_exists(ROOT_DIR . self::JURIS_CACHE_LASTUPDATE) || !file_exists(ROOT_DIR . self::JURIS_CACHE_FILENAME)) {
            $this->updateJuris();
        } else {
            if ( (new Nette\Utils\DateTime())->diff(new Nette\Utils\DateTime(date(file_get_contents(ROOT_DIR . self::JURIS_CACHE_LASTUPDATE))), TRUE)->days > 0 ) {
                $this->updateJuris();
            } else {
                // up to date
            }
        }
    }

	/*
	 * main research method
	 * returns data array of [celex number, directive name, case-law array]
	 */
    public function processDirective($y, $z = null) {
        // maintenance:
        $this->caseListUpdateCheck();
        
		// used variables
        $year = null;
        $number = null;
        $celex = null;

		// prepare return variable
        $data = array(
            'year' => '',
            'number' => '',
            'celex' => '',
            'docInfo' => ''
        );

		// populate basic vars
        if (!$z) { // celex number given
            if (preg_match('/3[1-2]{1}[0-9]{3}L[0-9]{4}/i', $y)) {
                $year = substr($y, 1, 4);
                $number = substr($y, 6, 4);
                $celex = $y;
            } else {
                return false;
            }
        } else { // only year and number given, get celex number
            $year = $y;
            $number = $z;
            $celex = $this->createDirectiveCelexNumber($y, $z);
        }

		// tmp var with eur-lex meta data (XML)
        $fgc = @file_get_contents('http://eur-lex.europa.eu/legal-content/CS/NOT/XML/?locale=cs&uri=CELEX:' . $celex);
        if ($fgc === false) {
            return false;
        }
        $rawData = html_entity_decode($fgc, ENT_QUOTES, "utf-8");
        $XMLdata = simplexml_load_string($rawData);
		
		// now we have XML data loaded and ready for initial research

		/*
		 * 1st step - get data from eur-lex XML
		 * Further steps are operated by AJAX commands on the research output page (performance improvement)
		 * (see AppModule/templates/research.latte)
		 */
		
		// XML structure is extremely complicated and confusing at times, so this might not be the most efficient way how to get basic info about the directive
        $info = $XMLdata->WORK->WORK_HAS_EXPRESSION->xpath("//EMBEDDED_NOTICE[EXPRESSION[EXPRESSION_USES_LANGUAGE[IDENTIFIER = 'ENG']]]");
		
		// here we extract directive name
        $directiveName = isset($info[1]) ? $info[1]->EXPRESSION->EXPRESSION_TITLE->VALUE : $info[0]->EXPRESSION->EXPRESSION_TITLE->VALUE;
		
		// get all nodes of XML that are related to case-law
		// and save all matches to $caseLawTypes
        $caseLawMatch = preg_match_all('/<([A-Z0-9_]+_CASE-LAW)/i', $rawData, $caseLawTypes);
		
		// get list of all case-law related node types to iterate through later
        $caseLawTypes = array_unique($caseLawTypes[1]);

		// now finally extract all case-law nodes and save them
		// $caseLaw contains case-law raw data
        foreach ($caseLawTypes as $key => $item) {
            $caseLaw[$item] = $XMLdata->INVERSE->xpath($item);
        }

		// prepare result variable
        $caseLawArray = [];
		
		// if no case-law found above, return it
        if (!isset($caseLaw)) {
            return [$celex, $directiveName, []];
        }

		// iterate through raw data and extract interesting stuff (identificators)
        foreach ($caseLaw as $type => $typeData) {
            foreach ($typeData as $key => $item) {
                // note: $item->URI->VALUE seems not usable as later throws e502 on file_get_contents() call and HTTP/1.0 303 on curl
				// so we rather get celex number
                $itemCelexTmp = $item->URI->TYPE == 'celex' ? $item->URI : $item->xpath("SAMEAS/URI[TYPE = 'celex']");
				// catch whether it is listed in IDENTIFIER node directly or under some subnode
                $itemCelex = is_array($itemCelexTmp) ? $itemCelexTmp[0]->IDENTIFIER : $itemCelexTmp->IDENTIFIER;
                $itemCelexUrl = is_array($itemCelexTmp) ? $itemCelexTmp[0]->VALUE : $itemCelexTmp->VALUE;
				// note: to understand perfectly please consult respective XML file and see for yourself
				
				// save extracted data to return variable
                $caseLawArray[(string) $itemCelex] = [
                    'type' => $type,
                    'uri' => (string) $itemCelexUrl,
                    'identifier' => (string) $itemCelex
                ];
            }
        }

		// return data
        return [$celex, $directiveName, $caseLawArray];
    }
	
	/*
	 * method used to order case-law items by their type (CN, CC, CJ,...)
	 * returns array arranged to new top-level keys according to the types, no other data is changed
	 */
    public static function sortByCelexType($array) {
		// note: in XML there is no way (probably) to find related 'CC' items, so we will check their existence later via AJAX
        $result = [];
        foreach ($array as $celex => $item) {
            $id = self::getYearFromCelexNumber($celex) . '-' . self::getNumberFromCelexNumber($celex);
            $result[self::getTypeFromCelexNumber($celex)][$id] = array_merge(
                    $item, ['id' => $id]
            );
        }
        return $result;
    }

    /*
	 * Method for directives only
	 * returns array of data extracted from given celex number
	 */
    public static function getDataFromCelexNumber($celex) {
        if (preg_match('/[0-9]{1}[1-2]{1}[0-9]{3}L[0-9]{4}/i', $celex)) {
            $sector = $celex[0];
            $type = $celex[5];
            $year = substr($celex, 1, 4);
            $number = substr($celex, 6, 4);
            return [
                'sector' => $sector,
                'type' => $type,
                'year' => $year,
                'number' => $number
            ];
        } else {
            return false;
        }
    }

	/*
	 * Auxiliary methods
	 */
    public static function getYearFromCelexNumber($celex) {
        return substr($celex, 1, 4);
    }

    public static function getTypeFromCelexNumber($celex) {
        return substr($celex, 5, 2);
    }

    public static function getNumberFromCelexNumber($celex) {
        return substr($celex, 7, 4);
    }

    public static function createDirectiveCelexNumber($year, $number, $type = 'DIR_STD', $rAdd = '') {
        $keys = array(
            'DIR_STD' => [3, 'L'],
            'DIR_IMPL' => [7, 'L']
        );

        if (strlen($number) > 4 || strlen($year) < 4) {
            return false;
        } else {
            while (strlen($number) < 4) {
                $number = '0' . $number;
            }
            return $keys[$type][0] . $year . $keys[$type][1] . $number . $rAdd;
        }
    }

}
