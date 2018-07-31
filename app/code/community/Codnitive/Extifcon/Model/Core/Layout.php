<?php
/**
 * CODNITIVE
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade to newer
 * versions in the future.
 *
 * @category   Codnitive
 * @package    Codnitive_Extifcon
 * @author     Hassan Barza <support@codnitive.com>
 * @copyright  Copyright (c) 2011 CODNITIVE Co. (http://www.codnitive.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Layout model
 *
 * @category   Codnitive
 * @package    Codnitive_Extifcon
 * @author     Hassan Barza <support@codnitive.com>
 */
class Codnitive_Extifcon_Model_Core_Layout extends Mage_Core_Model_Layout
{
    /**
     * Checks ifconfig and conditions to run action or not
     *
     * @param Varien_Simplexml_Element $node
     * @param Varien_Simplexml_Element $parent
     * @return Codnitive_Extifcon_Model_Core_Layout
     */
    protected function _generateAction($node, $parent)
    {
        $compiler = Mage::getModel('extifcon/compiler');
        
        if (isset($node['ifconfig']) && ($configPath = (string)$node['ifconfig'])) {
            $condition = true;
            if (isset($node['condition'])) {
                $condition = $compiler->getXmlCondition($compiler->spaceRemover($node['condition']));
            }
            $config = $compiler->getAdminConfig($compiler->spaceRemover($configPath));
            
            if ($config !== $condition) {
                return $this;
            }
        }
        else if (isset($node['modules']) && isset($node['options'])) {
            $finalResult = false;
            $extracted   = $compiler->extractor($node);
            $operation   = $compiler->spaceRemover((string)$node['operation']);
            $valideOpe   = $operation != '' ? $compiler->validator($node) : true;
            
            if ($valideOpe) {
                $tokens      = $compiler->getToken($operation);
                $finalResult = $compiler->operation($extracted, $tokens);
            }
            if ($finalResult !== true) {
                return $this;
            }
        }

        $this->_runAction($node, $parent);
    }
    
    /**
     * If all ifconfig conditions are ok then action runs
     *
     * @param Varien_Simplexml_Element $node
     * @param Varien_Simplexml_Element $parent
     * @return Codnitive_Extifcon_Model_Core_Layout
     */
    private function _runAction($node, $parent)
    {
        $method = (string)$node['method'];
        if (!empty($node['block'])) {
            $parentName = (string)$node['block'];
        } else {
            $parentName = $parent->getBlockName();
        }

        $_profilerKey = 'BLOCK ACTION: '.$parentName.' -> '.$method;
        Varien_Profiler::start($_profilerKey);

        if (!empty($parentName)) {
            $block = $this->getBlock($parentName);
        }
        if (!empty($block)) {

            $args = (array)$node->children();
            unset($args['@attributes']);

            foreach ($args as $key => $arg) {
                if (($arg instanceof Mage_Core_Model_Layout_Element)) {
                    if (isset($arg['helper'])) {
                        $helperName = explode('/', (string)$arg['helper']);
                        $helperMethod = array_pop($helperName);
                        $helperName = implode('/', $helperName);
                        $arg = $arg->asArray();
                        unset($arg['@']);
                        $args[$key] = call_user_func_array(array(Mage::helper($helperName), $helperMethod), $arg);
                    } else {
                        /**
                         * if there is no helper we hope that this is assoc array
                         */
                        $arr = array();
                        foreach($arg as $subkey => $value) {
                            $arr[(string)$subkey] = $value->asArray();
                        }
                        if (!empty($arr)) {
                            $args[$key] = $arr;
                        }
                    }
                }
            }

            if (isset($node['json'])) {
                $json = explode(' ', (string)$node['json']);
                foreach ($json as $arg) {
                    $args[$arg] = Mage::helper('core')->jsonDecode($args[$arg]);
                }
            }

            $this->_translateLayoutNode($node, $args);
            call_user_func_array(array($block, $method), $args);
        }

        Varien_Profiler::stop($_profilerKey);

        return $this;
    }
    
}
