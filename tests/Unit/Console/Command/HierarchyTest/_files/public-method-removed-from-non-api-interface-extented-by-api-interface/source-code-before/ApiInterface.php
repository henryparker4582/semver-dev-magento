<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Test\Vcs;

/**
 * @api
 */
interface ApiClass extends BaseInterface
{
    /**
     * @param array $methodParam
     */
    public function testMembershipMethod(array $methodParam);
}
