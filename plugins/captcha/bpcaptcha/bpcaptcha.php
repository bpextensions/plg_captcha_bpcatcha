<?php

/**
 * @package     ${package}
 *
 * @copyright   Copyright (C) ${build.year} ${copyrights},  All rights reserved.
 * @license     ${license.name}; see ${license.url}
 * @author      ${author.name}
 */

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\CaptchaField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseDriver;
use Joomla\Input\Input;
use Joomla\Session\SessionInterface;


defined('_JEXEC') or die;

/**
 * A Joomla! 4 system plugin that display management toolbar.
 */
class PlgCaptchaBpcaptcha extends CMSPlugin
{

    protected const LAYOUT_PATH = JPATH_PLUGINS.'/captcha/bpcaptcha/layouts';

    protected const SESSION_PREFIX = 'plg_captcha_bpcaptcha';

    /**
     * Application input.
     *
     * @var Input|null
     */
    protected $input;

    /**
     * Application.
     *
     * @var SiteApplication
     */
    public $app;

    /**
     * Database instance.
     *
     * @var DatabaseDriver
     */
    public $db;

    /**
     * Current user.
     *
     * @var null|User
     */
    protected $user;

    /**
     * Load language automatically.
     *
     * @var bool
     */
    protected $autoloadLanguage = true;

    /**
     * User session.
     *
     * @var    SessionInterface
     */
    private $session;

    /**
     * List of detected modules.
     *
     * @var array
     */
    protected static $modules = [];

    /**
     * @var null|string
     */
    protected $question;

    /**
     * @var object[]
     */
    protected $questions = [];

    /**
     * @var null|string
     */
    protected $hint;

    /**
     * @var null|string[]
     */
    protected $answers;

    /**
     * @var string
     */
    protected $currentLanguage;

    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);

        // Add required variables.
        $this->user  = $this->app->getIdentity();
        $this->input = $this->app->input;
        $this->session = Factory::getSession();
        $this->questions = (array)$this->params->get('questions', []);
        $this->currentLanguage = Factory::getApplication()->getLanguage()->getTag();

        if( empty($this->questions) ) {
            throw new RuntimeException(Text::sprintf('PLG_SYSTEM_BPCATCHA_ERROR_NO_QUESTIONS', $this->currentLanguage));
        }
    }

    /**
     * Initialize captcha.
     *
     * @param   string  $id
     *
     * @return bool
     * @throws Exception
     */
    public function onInit(string $id = 'bpcaptcha_1'): bool
    {
        if (!$this->app instanceof SiteApplication) {
            return false;
        }

        // Find what questions can be used on this language
        $selected_questions = [];
        foreach( $this->questions as $question ) {
            if( $question->language==='*' || $question->language===$this->currentLanguage ) {
                $selected_questions[] = $question;
            }
        }


        if( empty($selected_questions) ) {
            throw new RuntimeException(Text::sprintf('PLG_SYSTEM_BPCATCHA_ERROR_NO_QUESTIONS', $this->currentLanguage));
        }

        // Select question
        $question = $selected_questions[array_rand($selected_questions)];
        $this->question = $question->question;
        $this->hint = $question->hint;

        // Filter answers
        $question->answers = str_ireplace(["\r\n","\n"],PHP_EOL, $question->answers);
        $answers = explode(PHP_EOL, $question->answers);
        array_walk($answers, 'trim');
        array_walk($answers, static function($val){
            return strtolower($val);
        });
        $this->answers = $answers;

        return true;
    }

    /**
     * Display this captcha challenge inputs.
     *
     * @param $name
     * @param $id
     * @param $class
     *
     * @return string
     */
    public function onDisplay($name = null, $id = 'bpcaptcha_1', $class = ''): string
    {
        $layoutParams = ['name' =>$name, 'id' =>$id, 'class' =>$class, 'question' =>$this->question, 'answers' =>$this->answers, 'hint' =>$this->hint];

        $this->storeFieldChallenge($name, $this->answers);

        return LayoutHelper::render('bpcaptcha.field', $layoutParams, self::LAYOUT_PATH);
    }

    /**
     * Store challenge answers for this field into user session.
     *
     * @param   string  $name
     * @param   array   $answers
     *
     * @return void
     */
    protected function storeFieldChallenge(string $name, array $answers): void
    {
        $this->session->set(self::SESSION_PREFIX.".$name", $answers);
    }

    /**
     * Get challenge answers.
     *
     * @param   string  $name
     *
     * @return array
     */
    protected function getFieldChallenge(string $name): array
    {
        $challenge = $this->session->get(self::SESSION_PREFIX.".$name", []);

        if( !is_array($challenge) ) {
            return [];
        }

        return $challenge;
    }

    /**
     * Check captcha answer.
     *
     * @param $code
     *
     * @return bool
     */
    public function onCheckAnswer($code = null): bool
    {
        // Check
        $field = $this->input->get('bpcaptcha_challenge', null, 'raw');
        if( empty($field) ) {
            return false;
        }

        $answer = $code ?? $this->input->getString($field);
        $answers = $this->getFieldChallenge($field);

        if( empty($answers) ) {
            return false;
        }

        return in_array(strtolower(trim($answer)), $answers, true);
    }

    /**
     * Method to react on the setup of a captcha field. Gives the possibility
     * to change the field and/or the XML element for the field.
     *
     * @param   CaptchaField       $field    Captcha field instance
     * @param   \SimpleXMLElement  $element  XML form definition
     *
     * @return void
     */
    public function onSetupField(CaptchaField $field, \SimpleXMLElement $element): void
    {
        // Hide the label for the invisible recaptcha type
        $element['hiddenLabel'] = 'true';
    }
}
