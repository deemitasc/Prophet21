<?php

namespace Ripen\Prophet21\Model;

class Feed
{
    protected $io;
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\Filesystem\Io\File $io,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->io = $io;
        $this->scopeConfig = $scopeConfig;
    }

    public function getImportDir(){

        $dir = $this->scopeConfig->getValue('p21/feeds/import_directory');
        if ($dir && !is_dir($dir)) {
            $this->io->mkdir($dir, 0775);
        }
        return $dir;
    }

}
