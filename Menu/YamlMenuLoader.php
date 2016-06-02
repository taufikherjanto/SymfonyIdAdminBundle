<?php

/*
 * This file is part of the SymfonyIdAdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\Menu;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Yaml\Yaml;
use SymfonyId\AdminBundle\Cache\CacheHandler;
use SymfonyId\AdminBundle\Exception\FileNotFoundException;
use SymfonyId\AdminBundle\Exception\RuntimeException;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class YamlMenuLoader extends AbstractMenuLoader implements MenuLoaderInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var MenuFactory
     */
    private $menuFactory;

    /**
     * @var CacheHandler
     */
    private $cacheHandler;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $translationDomain;

    /**
     * @var string
     */
    private $yamlPath;

    /**
     * @param KernelInterface               $kernel
     * @param MenuFactory                   $menuFactory
     * @param CacheHandler                  $cacheHandler
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TranslatorInterface           $translator
     * @param string                        $translationDomain
     */
    public function __construct(KernelInterface $kernel, MenuFactory $menuFactory, CacheHandler $cacheHandler, AuthorizationCheckerInterface $authorizationChecker, TranslatorInterface $translator, $translationDomain)
    {
        $this->kernel = $kernel;
        $this->menuFactory = $menuFactory;
        $this->cacheHandler = $cacheHandler;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        parent::__construct($authorizationChecker);
    }

    /**
     * @param string $yamlPath
     */
    public function setYamlPath($yamlPath)
    {
        $this->yamlPath = $yamlPath;
    }

    /**
     * @return string
     */
    public function getYamlPath()
    {
        return $this->yamlPath;
    }

    /**
     * @return array
     */
    public function getMenu()
    {
        if (!file_exists($this->yamlPath)) {
            new FileNotFoundException($this->yamlPath);
        }

        $rootMenu = $this->createRootMenu($this->menuFactory);
        $this->addDefaultMenu($rootMenu);
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addAdminMenu($rootMenu);
        }

        $menus = Yaml::parse(file_get_contents($this->kernel->locateResource($this->yamlPath)));
        $reflection = new \ReflectionObject($this);
        if ($this->cacheHandler->hasCache($reflection)) {
            $menuItems = $this->cacheHandler->loadCache($reflection);
        } else {
            $menuItems = $this->parseMenu($menus);
            $this->cacheHandler->writeCache($reflection, $menuItems);
        }

        $this->generateMenu($rootMenu, $menuItems);

        return $rootMenu;
    }

    /**
     * @param array $menus
     *
     * @return array
     *
     * @throws RuntimeException
     */
    private function parseMenu($menus)
    {
        $menuItems = array();
        foreach ($menus as $name => $config) {
            if (!array_key_exists('route', $config)) {
                throw new RuntimeException('Key "route" is required.');
            }

            $menuItems[$config['route']] = array();
            if (array_key_exists('child', $config)) {
                $menuItems[$config['route']]['child'] = $this->parseMenu($config['child']);
            }

            $menuItems[$config['route']]['label'] = $name;
            $menuItems[$config['route']]['role'] = array_key_exists('role', $config) ? $config['role'] : 'ROLE_USER';
            $menuItems[$config['route']]['icon'] = array_key_exists('icon', $config) ? $config['icon'] : 'fa-bars';
            $menuItems[$config['route']]['extra'] = array_key_exists('extra', $config) ? $config['extra'] : '';
        }

        return $menuItems;
    }

    /**
     * @param ItemInterface $parentMenu
     * @param string        $routeName
     * @param string        $menuLabel
     * @param string        $icon
     * @param string        $classCss
     *
     * @return ItemInterface
     */
    protected function addMenu(ItemInterface $parentMenu, $routeName, $menuLabel, $icon = 'fa-bars', $classCss = '')
    {
        return $parentMenu->addChild($menuLabel, array(
            'route' => $routeName,
            'label' => sprintf('<i class="fa %s" aria-hidden="true"></i> <span>%s</span>', $icon, $this->translator->trans($menuLabel, array(), $this->translationDomain)),
            'extras' => array('safe_label' => true),
            'attributes' => array(
                'class' => $classCss,
            ),
        ));
    }

    /**
     * @param ItemInterface $parentMenu
     * @param array         $menuItems
     */
    private function generateMenu(ItemInterface $parentMenu, array $menuItems)
    {
        foreach ($menuItems as $route => $item) {
            if ($this->authorizationChecker->isGranted($item['role'])) {
                if (array_key_exists('child', $item)) {
                    $menu = $this->addParentMenu($parentMenu, $route, $item['label'], $item['icon'], $item['extra']);
                    $this->generateMenu($menu, $item['child']);
                } else {
                    $this->addMenu($parentMenu, $route, $item['label'], $item['icon'], $item['extra']);
                }
            }
        }
    }

    /**
     * @param ItemInterface $parentMenu
     * @param string        $routeName
     * @param string        $menuLabel
     * @param string        $icon
     * @param string        $classCss
     *
     * @return ItemInterface
     */
    private function addParentMenu(ItemInterface $parentMenu, $routeName, $menuLabel, $icon = 'fa-bars', $classCss = '')
    {
        return $parentMenu->addChild($menuLabel, array(
            'route' => $routeName,
            'label' => sprintf('<i class="fa %s" aria-hidden="true"></i> <span>%s</span><i class="fa fa-angle-double-left pull-right"></i>', $icon, $this->translator->trans($menuLabel, array(), $this->translationDomain)),
            'extras' => array('safe_label' => true),
            'attributes' => array(
                'class' => $classCss,
            ),
            'childrenAttributes' => array(
                'class' => 'treeview-menu',
            ),
        ));
    }
}
