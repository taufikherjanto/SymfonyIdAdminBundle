<?php

/*
 * This file is part of the SymfonyIdAdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use SymfonyId\AdminBundle\Annotation\Crud;
use SymfonyId\AdminBundle\Annotation\Grid;
use SymfonyId\AdminBundle\Annotation\Page;
use SymfonyId\AdminBundle\Annotation\Plugin;
use SymfonyId\AdminBundle\Annotation\Util;
use SymfonyId\AdminBundle\Configuration\ConfigurationAwareInterface;
use SymfonyId\AdminBundle\Configuration\ConfigurationAwareTrait;
use SymfonyId\AdminBundle\Configuration\CrudConfigurator;
use SymfonyId\AdminBundle\Configuration\GridConfigurator;
use SymfonyId\AdminBundle\Configuration\PageConfigurator;
use SymfonyId\AdminBundle\Configuration\PluginConfigurator;
use SymfonyId\AdminBundle\Configuration\UtilConfigurator;
use SymfonyId\AdminBundle\Extractor\ExtractorFactory;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class ControllerAnnotationExtractorListener implements CrudControllerListenerAwareInterface, ConfigurationAwareInterface
{
    use CrudControllerListenerAwareTrait;
    use ConfigurationAwareTrait;

    /**
     * @var ExtractorFactory
     */
    private $extractorFactory;

    /**
     * @param KernelInterface  $kernel
     * @param ExtractorFactory $extractorFactory
     */
    public function __construct(KernelInterface $kernel, ExtractorFactory $extractorFactory)
    {
        $this->kernel = $kernel;
        $this->extractorFactory = $extractorFactory;
    }

    /**
     * @param FilterControllerEvent $event
     *
     * @throws \Exception
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if ($this->isProduction()) {
            return;
        }

        if (!$this->isValidCrudListener($event)) {
            return;
        }

        $reflectionController = new \ReflectionObject($this->controller);
        $this->extractAnnotation($reflectionController);
    }

    /**
     * @param \ReflectionObject $reflectionController
     */
    private function extractAnnotation(\ReflectionObject $reflectionController)
    {
        $configuratorFactory = $this->getConfiguratorFactory($reflectionController->getName());
        /** @var CrudConfigurator $crudConfigurator */
        $crudConfigurator = $configuratorFactory->getConfigurator(CrudConfigurator::class);
        /** @var PageConfigurator $pageConfigurator */
        $pageConfigurator = $configuratorFactory->getConfigurator(PageConfigurator::class);
        /** @var GridConfigurator $gridConfigurator */
        $gridConfigurator = $configuratorFactory->getConfigurator(GridConfigurator::class);
        /** @var PluginConfigurator $pluginConfigurator */
        $pluginConfigurator = $configuratorFactory->getConfigurator(PluginConfigurator::class);
        /** @var UtilConfigurator $utilConfigurator */
        $utilConfigurator = $configuratorFactory->getConfigurator(UtilConfigurator::class);

        $this->extractorFactory->extract($reflectionController);
        foreach ($this->extractorFactory->getClassAnnotations() as $annotation) {
            if ($annotation instanceof Crud) {
                $crudConfigurator->setCrud($annotation);
            }

            if ($annotation instanceof Page) {
                $pageConfigurator->setPage($annotation);
            }

            if ($annotation instanceof Grid) {
                $gridConfigurator->setGrid($annotation);
            }

            if ($annotation instanceof Plugin) {
                $pluginConfigurator->setPlugin($annotation);
            }

            if ($annotation instanceof Util) {
                $utilConfigurator->setUtil($annotation);
            }
        }
    }
}
