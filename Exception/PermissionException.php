<?php

/*
 * This file is part of the SymfonyIdAdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyId\AdminBundle\Exception;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class PermissionException extends \Exception
{
    public function __construct($file, $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('Failed to write file "%s". Permission problem', $file), $code, $previous);
    }
}
