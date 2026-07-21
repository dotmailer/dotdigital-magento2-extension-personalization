<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Script;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\ReadFactory;

class ExtensionVersionFinder
{
    private const MODULE_NAME = 'Dotdigitalgroup_Personalization';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var ComponentRegistrarInterface
     */
    private $componentRegistrar;

    /**
     * @var ReadFactory
     */
    private $readFactory;

    /**
     * @param CacheInterface $cache
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ReadFactory $readFactory
     */
    public function __construct(
        CacheInterface $cache,
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory
    ) {
        $this->cache = $cache;
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
    }

    /**
     * Get extension version.
     *
     * @return string
     */
    public function getExtensionVersion(): string
    {
        $cacheKey = 'extension_version_' . self::MODULE_NAME;
        $version = $this->cache->load($cacheKey);
        if (!$version) {
            $version = $this->fetchExtensionVersion($cacheKey);
        }
        return $version;
    }

    /**
     * Fetch extension version.
     *
     * @param string $cacheKey
     * @return string
     */
    private function fetchExtensionVersion($cacheKey): string
    {
        try {
            $path = $this->componentRegistrar->getPath(
                ComponentRegistrar::MODULE,
                self::MODULE_NAME
            );
            $directoryRead = $this->readFactory->create($path);
            $composerJsonData = $directoryRead->readFile('composer.json');
            $data = json_decode($composerJsonData);

            if (empty($data->version)) {
                return 'Read error';
            }

            $this->cache->save($data->version, $cacheKey);
            return $data->version;
        } catch (FileSystemException|ValidatorException $exception) {
            return 'Read error';
        }
    }
}
