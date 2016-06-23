<?php

namespace App\AppModule\Presenters;

use Nette, 
	Nette\Application\UI\Form;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter {

	public $celexNumber;
	public $celexLink;
	public $celexHtml;
	
	// not used right now, planned for future UX enhancement
	public $celexSructure = array(
			'sector' => array(
				'1' => 'Smlouvy', 
				'2' => 'Externí dohody', 
				'3' => 'Legislativa', 
				'4' => 'Interní dohody', 
				'5' => 'Návrhy a přípravné dokumenty', 
				'6' => 'Judikatura', 
				'7' => 'Národní implementace', 
				'9' => 'Otázky evropského parlamentu', 
				'C' => 'Dokumenty OJC', 
				'E' => 'Dokumenty EFTA'
			), 
			'sectorType' => array(
				'1' => array(
					'D' => 'Amsterodamská úmluva (1997)', 
					'M' => 'Maastrichtské smlouvy', 
					'E' => 'Smlouvy o založení EEC', 
					'?' => '(bude doplněno)'
				), 
				'2' => array(
					'?' => '(bude doplněno)'
				),
				'3' => array(
					'R' => 'Nařízení', 
					'L' => 'Směrnice', 
					'?' => '(bude doplněno)'
				),
				'4' => array(
					'?' => '(bude doplněno)'
				),
				'5' => array(
					'?' => '(bude doplněno)'
				),
				'6' => array(
					'C' => '[SDEU] Stanovisko generálního advokáta', 
					'J' => '[SDEU] Rozsudek', 
					'?' => '(bude doplněno)'
				),
				'7' => array(
					'L' => 'Národní implementace směrnic'
				),
				'9' => array(
					'?' => '(bude doplněno)'
				),
				'E' => array(
					'?' => '(bude doplněno)'
				),
				'C' => array(
					'?' => '(bude doplněno)'
				)
			)
		);
	
	public function renderDefault() {
		// All we need is in template
	}
	
	/*
	 * Creates form used on homepage and returns its object
	 */
	public function createComponentRechercheForm() {
		$form = new Form;
		
		$form->setMethod('get');
		$form->addSelect('type', 'Type', ['DIR_STD'=>'Directive']);
		$form->addText('year', 'Year');
		$form->addText('number', 'Number');
		//$form->addText('input')->setOption('id', 'input');
		$form->addSubmit('send', 'Carry out the research')->setAttribute('class', 'btn-block');
		$form->onSuccess[] = $this->rechercheFormSuccess;
		
		return \App\Model\Misc::polishForm($form);
	}
	
	/*
	 * Form hander
	 */
	public function rechercheFormSuccess( $form ) {
		// get values from submitted form (now uses get method to enable link sharing)
		$values = $form->getValues();
		$year = $values->year;
		$number = $values->number;
		
		// new instance of class designed to contain methods for operating the research
		$research = new \App\Model\CelexResearch;
		
		// gets $data as array of [celex number, directive name, case-law array]
		$data = $research->processDirective($year,$number);
		
		// should be false if the method indentifies invalid directive
		if ( $data === false ) {
			$this->template->setFile(ROOT_DIR . 'app/AppModule/templates/directiveNotFound.latte');
			return false;
		}
		
		// load variables into the template for presentation
		$this->template->rechercheDataCelex = $data[0];
		$this->template->rechercheDataTitle = $data[1];
		$this->template->rechercheDataArray = $research::sortByCelexType($data[2]);
		
		// let's use dedicated template for output presentation
		$this->template->setFile(ROOT_DIR . 'app/AppModule/templates/research.latte');
	}
}
