<?php

/*
 * This file is part of the SymfonyIdAdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\Document\Filter;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Query\Filter\BsonFilter;
use SymfonyId\AdminBundle\Model\SoftDeleteAwareInterface;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class SoftDeletableFilter extends BsonFilter
{
    /**
     * Gets the criteria array to add to a query.
     *
     * If there is no criteria for the class, an empty array should be returned.
     *
     * @param ClassMetadata $targetDocument
     *
     * @return array
     */
    public function addFilterCriteria(ClassMetadata $targetDocument)
    {
        if ($targetDocument->getReflectionClass()->implementsInterface(SoftDeleteAwareInterface::class)) {
            return array('isDeleted' => $this->getParameter('isDeleted'));
        }

        return array();
    }
}
