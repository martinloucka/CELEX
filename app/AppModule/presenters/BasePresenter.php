<?php

namespace App\AppModule\Presenters;

use Nette,
	App\Model,
	Nette\Utils\Html,
	Nette\Application\UI,
	Nette\Application\UI\Form as Form,
	Nette\Forms\Controls,
	Nette\Diagnostics\Debugger,
	App\Model\Misc;

/**
 * Base presenter for all application presenters.
 * Implements common presenter-related methods and contact form.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter {
	public $config;
	public $user_data;

	/** @persistent */
	public $backlink;

	public function startup() {
		Debugger::$maxDepth = 5;
		parent::startup();
	}

	/*
	 * Returns the name of current Module (in this project always App)
	 */
	public function getModuleName() {
		$pos = strrpos($this->name, ':');
		if (is_int($pos)) {
			return substr($this->name, 0, $pos + 1);
		}
		return '';
	}

	/*
	 * Returns the name of current Presenter (so far Base, Home, Error)
	 */
	public function getPresenterName() {
		$pos = strrpos($this->name, ':');
		if (is_int($pos)) {
			return substr($this->name, $pos + 1);
		}
		return $this->name;
	}

	/*
	 * Three following methods implement and handle a simple contant form
	 */
	public function createComponentContactForm() {
		$form = new Form();

		$form->addText('from_name')->setRequired();
		$form->addText('from_email')->addRule(FORM::EMAIL, 'Please enter valid e-mail address.')->setRequired();
		$form->addTextArea('text')->setRequired();
		$form->addSubmit('send', 'Send');

		$form->onValidate[] = $this->contactFormValidated;
		$form->onSuccess[] = $this->contactFormSucceeded;

		return Misc::polishForm($form);
	}

	public function contactFormValidated($form) {
		$values = $form->getValues();
		return $values;
	}

	public function contactFormSucceeded($form) {
		$values = $form->getValues();

		$to = 'Jakub Harašta <harasta.jakub@gmail.com>, Martin Loučka <martin.loucka@mail.muni.cz>';
		$subject = 'CELEX - new inquiry';
		$message = "New message from: " . $values->from_name . " <" . $values->from_email . ">\r\Body:\r\n\r\n" . str_replace("\n","\r\n",$values->text) . "\r\n";
		$message = wordwrap($message, 70, "\r\n");
		$headers = 'From: ' . $values->from_email . "\r\n" .
			'Reply-To: ' . $values->from_email . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

		mail($to, $subject, $message, $headers);
		
		$this->flashMessage('We appreciate your feedback!', 'alert-success');
		$this->redirect($this->getPresenterName() . ':' . $this->getAction());
	}
}
