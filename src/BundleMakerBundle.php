<?php
declare(strict_types=1);
namespace Napse\BundleMaker;

use Napse\BundleMaker\DependencyInjection\BundleMakerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BundleMakerBundle extends Bundle
{
    protected function getContainerExtensionClass(): string
    {
        return BundleMakerExtension::class;
    }
}