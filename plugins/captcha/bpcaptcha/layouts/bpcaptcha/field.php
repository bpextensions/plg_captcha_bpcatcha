<?php

// Create captcha input
use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

/**
 * @var array  $displayData
 * @var string $name
 * @var string $id
 * @var string $class
 * @var string $question
 * @var string $answers
 * @var string $hint
 */

extract($displayData);

// Prepare field HTML
$form = new Form('bpcaptcha');

$xml = new SimpleXMLElement('<form><field name="' . $name . '" type="text" /><field name="bpcaptcha_challenge" type="hidden" /></form>');

$form->load($xml);
$form->setFieldAttribute($name, 'id', $id);
$form->setFieldAttribute($name, 'class', $class);
$form->setFieldAttribute($name, 'required', 'true');
$form->setFieldAttribute($name, 'label', $question);

if( !empty($hint) ) {
    $form->setFieldAttribute($name, 'hint', $hint);
}

$form->setValue('bpcaptcha_challenge', null, $name);

// Render field
echo $form->renderField('bpcaptcha_challenge');
echo $form->renderField($name);
