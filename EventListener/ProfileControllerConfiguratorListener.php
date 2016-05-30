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
use SymfonyId\AdminBundle\Annotation\Crud;
use SymfonyId\AdminBundle\Configuration\ConfigurationAwareInterface;
use SymfonyId\AdminBundle\Configuration\ConfigurationAwareTrait;
use SymfonyId\AdminBundle\Configuration\CrudConfigurator;
use SymfonyId\AdminBundle\Controller\ProfileController;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class ProfileControllerConfiguratorListener implements ConfigurationAwareInterface
{
    use ConfigurationAwareTrait;

    /**
     * @var array
     */
    private $showFields;

    /**
     * @var string
     */
    private $formClass;

    /**
     * @var string
     */
    private $modelClass;

    /**
     * @param array $showFields
     */
    public function setShowFields(array $showFields)
    {
        $this->showFields = $showFields;
    }

    /**
     * @param string $formClass
     */
    public function setFormClass($formClass)
    {
        $this->formClass = $formClass;
    }

    /**
     * @param string $modelClass
     */
    public function setModelClass($modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if ($this->isProduction()) {
            return;
        }

        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        $controller = $controller[0];
        if (!$controller instanceof ProfileController) {
            return;
        }

        $reflectionController = new \ReflectionObject($controller);
        $configuratorFactory = $this->getConfiguratorFactory($reflectionController->getName());
        /** @var CrudConfigurator $crudConfigurator */
        $crudConfigurator = $configuratorFactory->getConfigurator(CrudConfigurator::class);
        $crudConfiguration = $crudConfigurator->getCrud();
        $crud = new Crud(array(
            'modelClass' => $this->modelClass,
            'form' => $this->formClass,
            'menu' => $crudConfiguration->getMenu(),
            'showFields' => $this->showFields,
            'template' => $crudConfiguration->getTemplate(),
            'allowCreate' => $crudConfiguration->isAllowCreate(),
            'allowEdit' => $crudConfiguration->isAllowEdit(),
            'allowShow' => $crudConfiguration->isAllowShow(),
            'allowDelete' => $crudConfiguration->isAllowDelete(),
        ));
        $crudConfigurator->setCrud($crud);
        $configuratorFactory->addConfigurator($crudConfigurator);

        $controller->setConfiguratorFactory($configuratorFactory);
        $controller->setCacheHandler($this->cacheHandler);
        $controller->setKernel($this->kernel);
    }
}
