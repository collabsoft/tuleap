<?php


namespace Tuleap\Git\GitPHP;

/**
 * GitPHP Controller Heads
 *
 * Controller for displaying heads
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2010 Christopher Han
 * @package GitPHP
 * @subpackage Controller
 */
/**
 * Heads controller class
 *
 * @package GitPHP
 * @subpackage Controller
 */
class Controller_Heads extends ControllerBase // @codingStandardsIgnoreLine
{

    /**
     * __construct
     *
     * Constructor
     *
     * @access public
     * @return controller
     */
    public function __construct()
    {
        parent::__construct();
        if (!$this->project) {
            throw new MessageException(__('Project is required'), true);
        }
    }

    /**
     * GetTemplate
     *
     * Gets the template for this controller
     *
     * @access protected
     * @return string template filename
     */
    protected function GetTemplate() // @codingStandardsIgnoreLine
    {
        return 'heads.tpl';
    }

    /**
     * GetName
     *
     * Gets the name of this controller's action
     *
     * @access public
     * @param boolean $local true if caller wants the localized action name
     * @return string action name
     */
    public function GetName($local = false) // @codingStandardsIgnoreLine
    {
        if ($local) {
            return __('heads');
        }
        return 'heads';
    }

    /**
     * ReadQuery
     *
     * Read query into parameters
     *
     * @access protected
     */
    protected function ReadQuery() // @codingStandardsIgnoreLine
    {
    }

    /**
     * LoadData
     *
     * Loads data for this template
     *
     * @access protected
     */
    protected function LoadData() // @codingStandardsIgnoreLine
    {
        $head = $this->project->GetHeadCommit();
        $this->tpl->assign("head", $head);

        $headlist = $this->project->GetHeads();
        if (isset($headlist) && (count($headlist) > 0)) {
            $this->tpl->assign("headlist", $headlist);
        }
    }
}
