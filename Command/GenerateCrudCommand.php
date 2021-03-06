<?php

/*
 * This file is part of the SymfonyIdAdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\Command;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use SymfonyId\AdminBundle\Annotation\Driver;
use SymfonyId\AdminBundle\Exception\RuntimeException;
use SymfonyId\AdminBundle\Generator\ControllerGenerator;
use SymfonyId\AdminBundle\Generator\FormGenerator;
use SymfonyId\AdminBundle\Generator\GeneratorInterface;
use SymfonyId\AdminBundle\Model\ModelMetadataAwareTrait;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class GenerateCrudCommand extends GenerateDoctrineCommand
{
    use ModelMetadataAwareTrait;

    /**
     * Command configuration.
     */
    protected function configure()
    {
        $this
            ->setName('symfonyid:generate:crud')
            ->setAliases(array('symfonyid:generate', 'symfonyid:crud:generate'))
            ->addArgument('model', InputArgument::OPTIONAL, 'The entity class name to initialize (shortcut notation)')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite any existing controller or form class when generating the CRUD contents')
            ->addOption('only-form', null, InputOption::VALUE_NONE, 'Only generate form')
            ->addOption('only-controller', null, InputOption::VALUE_NONE, 'Only generate controller')
            ->setDescription('Generate CRUD from Model using SymfonyId Admin Bundle style')
            ->setHelp(<<<'EOT'
The <info>siab:generate:crud</info> command generates a CRUD based on a Doctrine ORM or ODM using SymfonyId Admin Bundle style.

<info>php bin/console siab:generate:crud --model=AcmeBlogBundle:Post</info>

Every generated file is based on a template. There are default templates but they can be overriden by overriding config parameters.
EOT
            )
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setManagerFactory($this->getContainer()->get('symfonyid.admin.manager.manager_factory'));
        $questionHelper = $this->getQuestionHelper();

        $this->interactive($input, $output);

        $forceOverwrite = $input->getOption('overwrite');
        $onlyForm = $input->getOption('only-form');
        $onlyController = $input->getOption('only-controller');
        if ($model = $input->getArgument('model')) {
            $this->generate($output, $model, $forceOverwrite);
        } else {
            if ($input->isInteractive()) {
                $question = new ConfirmationQuestion($questionHelper->getQuestion('Are you sure generate crud from all models', 'yes', '?'), true);
                if (!$questionHelper->ask($input, $output, $question)) {
                    $output->writeln('<error>Command aborted</error>');

                    return 1;
                }
            }

            $bundles = $this->getContainer()->getParameter('symfonyid.admin.bundles');
            foreach ($bundles as $bundle) {
                $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);

                $finder = new Finder();
                $finder->name('*.php')->in(array($bundle->getPath().'/Entity', $bundle->getPath().'/Document'));

                $this->findModelAndGenerate($finder, $bundle, $output, $forceOverwrite, $onlyForm, $onlyController);
            }
        }

        /** @var KernelInterface $kernel */
        $kernel = $this->getContainer()->get('kernel');
        $cacheClearCommand = $this->getApplication()->find('cache:clear');
        $cacheClearCommand->run(new ArrayInput(array('--env' => $kernel->getEnvironment())), $output);

        $output->writeln('<info>CRUD Generation is successfully!</info>');
    }

    /**
     * @throws RuntimeException
     */
    protected function createGenerator()
    {
        throw new RuntimeException();
    }

    /**
     * Lookup in priority.
     *
     * - <Bundle>/Resources/SymfonyIdAdminBundle/skeleton
     * - app/Resources/SymfonyIdAdminBundle/skeleton
     * - <ThisBundleDir>/Resources/skeleton
     * - <ThisBundleDir/Resources
     *
     * @param BundleInterface|null $bundle
     *
     * @return array
     */
    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {
        $skeletonDirs = array();

        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/SymfonyIdAdminBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        /** @var KernelInterface $kernel */
        $kernel = $this->getContainer()->get('kernel');
        if (is_dir($dir = $kernel->getRootDir().'/Resources/SymfonyIdAdminBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        $reflClass = new \ReflectionObject($this);
        $skeletonDirs[] = dirname($reflClass->getFileName()).'/../Resources/skeleton';
        $skeletonDirs[] = dirname($reflClass->getFileName()).'/../Resources';

        return $skeletonDirs;
    }

    /**
     * @param null|string $bundle
     *
     * @return ControllerGenerator
     */
    private function getControllerGenerator($bundle = null)
    {
        $generator = new ControllerGenerator();
        $generator->setSkeletonDirs($this->getSkeletonDirs($bundle));

        return $generator;
    }

    /**
     * @param null|string $bundle
     *
     * @return FormGenerator
     */
    private function getFormGenerator($bundle = null)
    {
        $generator = new FormGenerator();
        $generator->setSkeletonDirs($this->getSkeletonDirs($bundle));

        return $generator;
    }

    /**
     * @param OutputInterface $output
     * @param string          $model
     * @param bool            $forceOverwrite
     * @param bool            $onlyForm
     * @param bool            $onlyController
     */
    private function generate(OutputInterface $output, $model, $forceOverwrite = false, $onlyForm = false, $onlyController = false)
    {
        $model = Validators::validateEntityName($model);
        list($bundle, $model) = $this->parseShortcutNotation($model);

        $driver = new Driver(array('value' => Driver::ORM));
        try {
            $modelClass = $this->getAliasNamespace($driver, $bundle).'\\'.$model;
            $metadata = $this->getClassMetadata($driver, $modelClass);
        } catch (\Exception $exception) {
            $driver = new Driver(array('value' => Driver::ODM));
            $modelClass = $this->getAliasNamespace($driver, $bundle).'\\'.$model;
            $metadata = $this->getClassMetadata($driver, $modelClass);
        }

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);

        $this->doGenerate($output, $bundle, $metadata, $modelClass, $model, $forceOverwrite, $onlyForm, $onlyController);
    }

    /**
     * @param Finder          $finder
     * @param BundleInterface $bundle
     * @param OutputInterface $output
     * @param bool            $forceOverwrite
     * @param bool            $onlyForm
     * @param bool            $onlyController
     */
    private function findModelAndGenerate(Finder $finder, BundleInterface $bundle, OutputInterface $output, $forceOverwrite = false, $onlyForm = false, $onlyController = false)
    {
        $count = 0;
        foreach ($finder as $file) {
            if ('User.php' === $file->getFilename()) {
                continue;
            }

            $model = sprintf('%s:%s', $bundle->getName(), str_replace('.php', '', $file->getFilename()));
            $this->generate($output, $model, $forceOverwrite, $onlyForm, $onlyController);
            ++$count;
        }

        if (0 === $count) {
            $output->writeln('<comment>No model is exist.</comment>');
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    private function interactive(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($this->getApplication()->has('doctrine:schema:update') && $input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you want to update your database schemas', 'yes', '?'), true);

            if ($questionHelper->ask($input, $output, $question)) {
                $schemaUpdaterCommand = $this->getApplication()->find('doctrine:schema:update');
                $schemaUpdaterCommand->run(new ArrayInput(array('--force' => true)), $output);
            }
        }

        /*
         * Question helper
         */
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }
    }

    private function doGenerate(OutputInterface $output, BundleInterface $bundle, ClassMetadata $metadata, $modelClass, $modelShort, $forceOverwrite = false, $onlyForm = false, $onlyController = false)
    {
        if ($onlyForm) {
            /** @var GeneratorInterface $formGenerator */
            $formGenerator = $this->getFormGenerator($bundle);
            $formGenerator->generate($bundle, $modelShort, $metadata, $forceOverwrite);

            $output->writeln(sprintf('<info>Form type for entity %s has been generated</info>', $modelClass));
        } elseif ($onlyController) {
            $controllerGenerator = $this->getControllerGenerator($bundle);
            $controllerGenerator->generate($bundle, $modelClass, $metadata, $forceOverwrite);

            $output->writeln(sprintf('<info>Controller for entity %s has been generated</info>', $modelClass));
        } else {
            /** @var GeneratorInterface $formGenerator */
            $formGenerator = $this->getFormGenerator($bundle);
            $formGenerator->generate($bundle, $modelShort, $metadata, $forceOverwrite);

            $output->writeln(sprintf('<info>Form type for entity %s has been generated</info>', $modelClass));

            $controllerGenerator = $this->getControllerGenerator($bundle);
            $controllerGenerator->generate($bundle, $modelClass, $metadata, $forceOverwrite);

            $output->writeln(sprintf('<info>Controller for entity %s has been generated</info>', $modelClass));
        }
    }
}
