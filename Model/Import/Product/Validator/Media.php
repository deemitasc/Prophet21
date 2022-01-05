<?php

namespace Ripen\Prophet21\Model\Import\Product\Validator;

use Magento\Framework\Url\Validator;

class Media extends \Magento\CatalogImportExport\Model\Import\Product\Validator\Media
{
    const PATH_REGEXP = '#^(?!.*[\\/]\.{2}[\\/])(?!\.{2}[\\/])[-\w.\\/ ]+$#';

    /**
     * @param Validator $validator The url validator
     */
    public function __construct(Validator $validator = null)
    {
        parent::__construct($validator);
    }

    protected function checkPath($string)
    {
        return preg_match(self::PATH_REGEXP, $string);
    }
}
