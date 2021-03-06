<?php

/*
 * This file is part of the SymfonyIdAdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use SymfonyId\AdminBundle\Configuration\ConfigurationAwareTrait;
use SymfonyId\AdminBundle\Configuration\CrudConfigurator;
use SymfonyId\AdminBundle\Controller\AnnotationConfigurationAwareInterface;
use SymfonyId\AdminBundle\Controller\AnnotationConfigurationAwareTrait;
use SymfonyId\AdminBundle\Event\FilterQueryEvent;
use SymfonyId\AdminBundle\Manager\DriverFinder;
use SymfonyId\AdminBundle\SymfonyIdAdminConstrants as Constants;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class FieldsSortSubscriber implements AnnotationConfigurationAwareInterface, EventSubscriberInterface
{
    use AnnotationConfigurationAwareTrait;
    use ConfigurationAwareTrait;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var DriverFinder
     */
    private $driverFinder;

    /**
     * @var string
     */
    private $sortBy;

    /**
     * @var string
     */
    private $direction;

    /**
     * @param Session      $session
     * @param DriverFinder $driverFinder
     */
    public function __construct(Session $session, DriverFinder $driverFinder)
    {
        $this->session = $session;
        $this->driverFinder = $driverFinder;
        $this->direction = 'ASC';
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function setSortParameter(FilterControllerEvent $event)
    {
        if (!$this->isValidListener($event) || !$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->sortBy = $request->query->get('sort_by')) {
            return;
        }
    }

    /**
     * @param FilterQueryEvent $event
     */
    public function sort(FilterQueryEvent $event)
    {
        if (!$this->sortBy) {
            $this->sortBy = 'id';
            $this->direction = 'DESC';
        }

        $this->session->set(Constants::SESSION_SORTED_ID, $this->sortBy);

        $configuratorFactory = $this->getConfiguratorFactory(new \ReflectionObject($this->controller));
        /** @var CrudConfigurator $crudConfigurator */
        $crudConfigurator = $configuratorFactory->getConfigurator(CrudConfigurator::class);

        $driver = $this->driverFinder->findDriverForClass($crudConfigurator->getCrud()->getModelClass());
        $filter = $this->container->get(sprintf('symfonyid.admin.filter.%s.sort', $driver->getDriver()));
        $filter->sort($event->getModelClass(), $event->getQueryBuilder(), $this->sortBy, $this->direction);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => array(
                array('setSortParameter', -127),
            ),
            Constants::FILTER_LIST => array(
                array('sort', 0),
            ),
        );
    }
}
