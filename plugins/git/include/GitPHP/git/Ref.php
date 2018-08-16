<?php

namespace Tuleap\Git\GitPHP;

/**
 * GitPHP Ref
 *
 * Base class for ref objects
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2010 Christopher Han
 * @package GitPHP
 * @subpackage Git
 */

/**
 * Git Ref class
 *
 * @package GitPHP
 * @subpackage Git
 */
abstract class Ref extends GitObject
{

    /**
     * refName
     *
     * Stores the ref name
     *
     * @access protected
     */
    protected $refName;

    /**
     * refDir
     *
     * Stores the ref directory
     *
     * @access protected
     */
    protected $refDir;

    /**
     * __construct
     *
     * Instantiates ref
     *
     * @access public
     * @param mixed $project the project
     * @param string $refDir the ref directory
     * @param string $refName the ref name
     * @param string $refHash the ref hash
     * @throws Exception if not a valid ref
     * @return mixed git ref
     */
    public function __construct($project, $refDir, $refName, $refHash = '')
    {
        $this->project = $project;
        $this->refDir = $refDir;
        $this->refName = $refName;
        if (!empty($refHash)) {
            $this->SetHash($refHash);
        }
    }

    /**
     * GetHash
     *
     * Gets the hash for this ref (overrides base)
     *
     * @access public
     * @return string object hash
     */
    public function GetHash() // @codingStandardsIgnoreLine
    {
        if (empty($this->hash)) {
            $this->FindHash();
        }

        return parent::GetHash();
    }

    /**
     * FindHash
     *
     * Looks up the hash for the ref
     *
     * @access protected
     * @throws Exception if hash is not found
     */
    protected function FindHash() // @codingStandardsIgnoreLine
    {
        $exe = new GitExe($this->GetProject());
        $args = array();
        $args[] = '--hash';
        $args[] = '--verify';
        $args[] = escapeshellarg($this->GetRefPath());
        $hash = trim($exe->Execute(GitExe::SHOW_REF, $args));

        if (empty($hash)) {
            throw new \Exception('Invalid ref ' . $this->GetRefPath());
        }

        $this->SetHash($hash);
    }

    /**
     * GetName
     *
     * Gets the ref name
     *
     * @access public
     * @return string ref name
     */
    public function GetName() // @codingStandardsIgnoreLine
    {
        return $this->refName;
    }

    /**
     * GetDirectory
     *
     * Gets the ref directory
     *
     * @access public
     * @return string ref directory
     */
    public function GetDirectory() // @codingStandardsIgnoreLine
    {
        return $this->refDir;
    }

    /**
     * GetRefPath
     *
     * Gets the path to the ref within the project
     *
     * @access public
     * @return string ref path
     */
    public function GetRefPath() // @codingStandardsIgnoreLine
    {
        return 'refs/' . $this->refDir . '/' . $this->refName;
    }

    /**
     * GetFullPath
     *
     * Gets the path to the ref including the project path
     *
     * @access public
     * @return string full ref path
     */
    public function GetFullPath() // @codingStandardsIgnoreLine
    {
        return $this->GetProject()->GetPath() . '/' . $this->GetRefPath();
    }
}
