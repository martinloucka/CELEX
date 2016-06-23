<?php
/*
 * Copyright 2016, Martin Loučka, licensed under BSD 3
 */

namespace App\Model;

use Nette,
	Nette\Utils\Strings;

/**
 * Class containing miscelaneous methods
 */
class Misc extends Nette\Object {

	public function __construct() {
	}

	/*
	 * This method accepts instance of Form and returns object optimized for bootstrap templates
	 * Copyright 2004, 2014 David Grudl, BSD license (see /bsd.txt)
	 * Code altered for use with current project.
	 */
	public static function polishForm($form, $leftWidth = 3, $rightWidth = 8) {

		// setup form rendering
		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = 'div class=form-group';
		$renderer->wrappers['pair']['.error'] = 'has-error';
		$renderer->wrappers['control']['container'] = 'div class=col-lg-' . $rightWidth;
		$renderer->wrappers['label']['container'] = 'div class="col-lg-' . $leftWidth . ' control-label"';
		$renderer->wrappers['control']['description'] = 'span class=help-block';
		$renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';

		// make form and controls compatible with Twitter Bootstrap
		$form->getElementPrototype()->class('form-horizontal');

		foreach ($form->getControls() as $control) {
			if ($control instanceof \Nette\Forms\Controls\Button) {
				$control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-flat btn-primary' : 'btn btn-flat btn-default');
				$usedPrimary = TRUE;
			} elseif ($control instanceof \Nette\Forms\Controls\TextBase || $control instanceof \Nette\Forms\Controls\SelectBox || $control instanceof \Nette\Forms\Controls\MultiSelectBox) {
				$control->getControlPrototype()->addClass('form-control');
			} elseif ($control instanceof \Nette\Forms\Controls\Checkbox || $control instanceof \Nette\Forms\Controls\CheckboxList || $control instanceof \Nette\Forms\Controls\RadioList) {
				$control->getSeparatorPrototype()->setName('div')->addClass($control->getControlPrototype()->type);
				$control->getControlPrototype()->addClass('px');
				if (isset($control->items))
					$control->items = array_map(function($value) {
						return \Nette\Utils\Html::el('span')->addClass('lbl')->setText($value);
					}, $control->items);
				else
					$control->caption = \Nette\Utils\Html::el('span')->addClass('lbl')->setText($control->caption);
			}
		}
		
		return $form;
	}
}
