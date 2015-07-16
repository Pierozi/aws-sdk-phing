<?php
/**
 * RunStackTask.php
 *
 * @date        16/07/2015
 * @author      Frederic Dewinne <frederic@continuousphp.com>
 * @copyright   Copyright (c) 2014 continuousphp (http://continuousphp.com)
 * @file        RunStackTask.php
 * @link        http://github.com/continuousphp/aws-sdk-phing for the canonical source repository
 * @license     http://opensource.org/licenses/MIT MIT License
 */

namespace Aws\Task\CloudFormation;
use Aws\CloudFormation\CloudFormationClient;
use Aws\CloudFormation\Exception\CloudFormationException;
use Aws\Task\AbstractTask;

/**
 * RunStackTask
 *
 * @package     Aws
 * @subpackage  CloudFormation
 * @author      Frederic Dewinne <frederic@continuousphp.com>
 * @license     http://opensource.org/licenses/MIT MIT License
 */
class RunStackTask extends AbstractTask
{

    /**
     * Stack name
     * @var string
     */
    protected $name;

    /**
     * Stack name
     * @var string
     */
    protected $templatePath;

    /**
     * Update on conflict
     */
    protected $updateOnConflict = false;

    /**
     * @var string
     */
    protected $capabilities;

    /**
     * Stack params array
     * @var StackParam[]
     */
    protected $params = [];

    /**
     * @var CloudFormationClient
     */
    protected $service;

    /**
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->templatePath;
    }

    /**
     * @param string $templatePath
     * @return $this
     */
    public function setTemplatePath($templatePath)
    {
        $this->templatePath = $templatePath;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdateOnConflict()
    {
        return $this->updateOnConflict;
    }

    /**
     * @param mixed $updateOnConflict
     * @return $this
     */
    public function setUpdateOnConflict($updateOnConflict)
    {
        $this->updateOnConflict = $updateOnConflict;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCapabilities()
    {
        return $this->capabilities;
    }

    /**
     * @param mixed $capabilities
     */
    public function setCapabilities($capabilities)
    {
        $this->capabilities = $capabilities;
    }

    /**
     * Called by phing for each <param/> tag
     * @return StackParam
     */
    public function createParam() {
        $param = new StackParam();
        $this->params[] = $param;
        return $param;
    }

    /**
     * Return the array representation of the params
     * @return array
     */
    public function getParamsArray() {
        $result = [];

        foreach($this->params as $param) {
            $result[] = $param->toArray();
        }

        return $result;
    }

    /**
     * @return CloudFormationClient
     */
    public function getService()
    {
        if (is_null($this->service)) {
            $this->service = $this->getServiceLocator()->get('CloudFormation');
        }

        return $this->service;
    }

    /**
     * Task entry point
     */
    public function main()
    {
        $this->validate();

        $cloudFormation = $this->getService();

        $stackProperties = [
            'StackName' => $this->getName(),
            'TemplateBody' => file_get_contents($this->getTemplatePath()),
            'Parameters'    => $this->getParamsArray()
        ];

        if ($this->getCapabilities()) {
            $stackProperties['Capabilities'] = explode(',', $this->getCapabilities());
        }

        try {
            $cloudFormation->describeStacks([
                'StackName' => $this->getName()
            ]);
            // update
            $cloudFormation->updateStack($stackProperties);
        } catch (CloudFormationException $e) {
            if ($this->getUpdateOnConflict()) {
                $cloudFormation->createStack($stackProperties);
            } else {
                throw new \BuildException('Stack ' . $this->getName() . ' already exists!');
            }
        }

        while (!$this->stackIsReady()) {
            sleep(3);
            $this->log("Wating for stack provisioning...");
        }
    }

    protected function stackIsReady()
    {
        try {
            $stack = $this->getService()
                ->describeStacks([
                    'StackName' => $this->getName()
                ]);
            switch ($stack['Stack']['StackStatus']) {
                case 'CREATE_COMPLETE':
                case 'UPDATE_COMPLETE':
                case 'UPDATE_COMPLETE_CLEANUP_IN_PROGRESS':
                    return true;
                case '':
                case 'UPDATE_IN_PROGRESS':
                case 'CREATE_IN_PROGRESS':
                    return false;
                default:
                    throw new \BuildException('Failed to run stack ' . $this->getName() . ' (' . $stack['Stack']['StackStatus'] . ') !');
            }
        } catch (CloudFormationException $e) {
            return false;
        }
    }

    /**
     * Validate attributes
     *
     * @throws \BuildException
     */
    protected function validate() {

        if(!$this->getTemplatePath()) {
            throw new \BuildException('You must set the template-path attribute.');
        }

        if(!$this->getName()) {
            throw new \BuildException('You must set the name attribute.');
        }

    }

}