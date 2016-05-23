<?php

/*
 * This file is part of the AdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use SymfonyId\AdminBundle\Annotation\Driver;
use SymfonyId\AdminBundle\Configuration\ConfigurationFactory;
use SymfonyId\AdminBundle\Configuration\CrudConfigurator;
use SymfonyId\AdminBundle\Extractor\ExtractorFactory;
use SymfonyId\AdminBundle\Filter\FieldsFilterInterface;
use SymfonyId\AdminBundle\Manager\DriverFinder;
use SymfonyId\AdminBundle\Manager\ManagerFactory;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class EnableFieldsFilterListener
{
    use CrudControllerListenerTrait;

    /**
     * @var ManagerFactory
     */
    private $managerFactory;

    /**
     * @var ExtractorFactory
     */
    private $extractorFactory;

    /**
     * @var ConfigurationFactory
     */
    private $configurationFactory;

    /**
     * @var DriverFinder
     */
    private $driverFinder;

    /**
     * @var string
     */
    private $dateTimeFormat;

    /**
     * @param ManagerFactory       $managerFactory
     * @param ExtractorFactory     $extractorFactory
     * @param ConfigurationFactory $configurationFactory
     * @param DriverFinder         $driverFinder
     * @param string               $dateTimeFormat
     */
    public function __construct(ManagerFactory $managerFactory, ExtractorFactory $extractorFactory, ConfigurationFactory $configurationFactory, DriverFinder $driverFinder, $dateTimeFormat)
    {
        $this->managerFactory = $managerFactory;
        $this->extractorFactory = $extractorFactory;
        $this->configurationFactory = $configurationFactory;
        $this->driverFinder = $driverFinder;
        $this->dateTimeFormat = $dateTimeFormat;
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$this->isValidCrudListener($event)) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->query->get('filter')) {
            return;
        }

        /** @var CrudConfigurator $crudConfigurator */
        $crudConfigurator = $this->configurationFactory->getConfigurator(CrudConfigurator::class);

        $driver = $this->driverFinder->findDriverForClass($crudConfigurator->getCrud()->getModelClass());
        $manager = $this->managerFactory->getManager($driver);

        if (Driver::ORM === $driver->getDriver()) {
            /* @var \Doctrine\ORM\EntityManager $manager */
            /* @var FieldsFilterInterface $filter */
            $filter = $manager->getFilters()->enable('symfonyid.admin.filter.orm.fields');
            $this->applyFilter($filter, $request->query->get('filter'));
        }

        if (Driver::ODM === $driver->getDriver()) {
            /* @var \Doctrine\ODM\MongoDB\DocumentManager $manager */
            /* @var FieldsFilterInterface $filter */
            $filter = $manager->getFilterCollection()->enable('symfonyid.admin.filter.odm.fields');
            $this->applyFilter($filter, $request->query->get('filter'));
        }
    }

    /**
     * @param FieldsFilterInterface $filter
     * @param string                $keyword
     */
    private function applyFilter(FieldsFilterInterface $filter, $keyword)
    {
        $filter->setExtractor($this->extractorFactory);
        $filter->setConfigurator($this->configurationFactory);
        $filter->setDateTimeFormat($this->dateTimeFormat);
        $filter->setParameter('filter', $keyword);
    }
}
