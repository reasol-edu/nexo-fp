<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    // When running as an embedded binary (NEXO_EMBEDDED=1), the embedded
    // filesystem is read-only, so cache and logs must live on the real FS.
    public function getCacheDir(): string
    {
        if ('1' === getenv('NEXO_EMBEDDED')) {
            return rtrim((string) getcwd(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache'
                . DIRECTORY_SEPARATOR . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ('1' === getenv('NEXO_EMBEDDED')) {
            return rtrim((string) getcwd(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log';
        }

        return parent::getLogDir();
    }
}
