<?php
/**
 * Magento validator config factory
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\Framework\Validator;

use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Json\JsonInterface;
use Magento\Framework\Config\FileIteratorFactory;

class Factory
{
    /** cache key */
    const CACHE_KEY = __CLASS__;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * Validator config files
     *
     * @var array|null
     */
    protected $_configFiles = null;

    /**
     * @var bool
     */
    private $isDefaultTranslatorInitialized = false;

    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    private $moduleReader;

    /**
     * @var FrontendInterface
     */
    private $cache;

    /**
     * @var JsonInterface
     */
    private $json;

    /**
     * @var FileIteratorFactory
     */
    private $fileIteratorFactory;

    /**
     * Initialize dependencies
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     * @param FrontendInterface $cache
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        FrontendInterface $cache
    ) {
        $this->_objectManager = $objectManager;
        $this->moduleReader = $moduleReader;
        $this->cache = $cache;
    }

    /**
     * Init cached list of validation files
     */
    protected function _initializeConfigList()
    {
        if (!$this->_configFiles) {
            $this->_configFiles = $this->cache->load(self::CACHE_KEY);
            if (!$this->_configFiles) {
                $this->_configFiles = $this->moduleReader->getConfigurationFiles('validation.xml');
                $this->cache->save($this->getJson()->encode($this->_configFiles->toArray()), self::CACHE_KEY);
            } else {
                $filesArray = $this->getJson()->decode($this->_configFiles);
                $this->_configFiles = $this->getFileIteratorFactory()->create(array_keys($filesArray));
            }
        }
    }

    /**
     * Create and set default translator to \Magento\Framework\Validator\AbstractValidator.
     *
     * @return void
     */
    protected function _initializeDefaultTranslator()
    {
        if (!$this->isDefaultTranslatorInitialized) {
            // Pass translations to \Magento\Framework\TranslateInterface from validators
            $translatorCallback = function () {
                $argc = func_get_args();
                return (string)new \Magento\Framework\Phrase(array_shift($argc), $argc);
            };
            /** @var \Magento\Framework\Translate\Adapter $translator */
            $translator = $this->_objectManager->create(\Magento\Framework\Translate\Adapter::class);
            $translator->setOptions(['translator' => $translatorCallback]);
            \Magento\Framework\Validator\AbstractValidator::setDefaultTranslator($translator);
            $this->isDefaultTranslatorInitialized = true;
        }
    }

    /**
     * Get validator config object.
     *
     * Will instantiate \Magento\Framework\Validator\Config
     *
     * @return \Magento\Framework\Validator\Config
     */
    public function getValidatorConfig()
    {
        $this->_initializeConfigList();
        $this->_initializeDefaultTranslator();
        return $this->_objectManager->create(
            \Magento\Framework\Validator\Config::class, ['configFiles' => $this->_configFiles]);
    }

    /**
     * Create validator builder instance based on entity and group.
     *
     * @param string $entityName
     * @param string $groupName
     * @param array|null $builderConfig
     * @return \Magento\Framework\Validator\Builder
     */
    public function createValidatorBuilder($entityName, $groupName, array $builderConfig = null)
    {
        $this->_initializeDefaultTranslator();
        return $this->getValidatorConfig()->createValidatorBuilder($entityName, $groupName, $builderConfig);
    }

    /**
     * Create validator based on entity and group.
     *
     * @param string $entityName
     * @param string $groupName
     * @param array|null $builderConfig
     * @return \Magento\Framework\Validator
     */
    public function createValidator($entityName, $groupName, array $builderConfig = null)
    {
        $this->_initializeDefaultTranslator();
        return $this->getValidatorConfig()->createValidator($entityName, $groupName, $builderConfig);
    }

    /**
     * Get json encoder/decoder
     *
     * @return JsonInterface
     * @deprecated
     */
    private function getJson()
    {
        if ($this->json === null) {
            $this->json = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(JsonInterface::class);
        }
        return $this->json;
    }

    /**
     * Get file iterator factory
     *
     * @return FileIteratorFactory
     * @deprecated
     */
    private function getFileIteratorFactory()
    {
        if ($this->fileIteratorFactory === null) {
            $this->fileIteratorFactory = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(FileIteratorFactory::class);
        }
        return $this->fileIteratorFactory;
    }
}
