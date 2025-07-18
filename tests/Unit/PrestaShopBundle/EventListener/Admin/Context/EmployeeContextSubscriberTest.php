<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Unit\PrestaShopBundle\EventListener\Admin\Context;

use PrestaShop\PrestaShop\Adapter\ContextStateManager;
use PrestaShop\PrestaShop\Adapter\Employee\EmployeeRepository;
use PrestaShop\PrestaShop\Adapter\Shop\Repository\ShopRepository;
use PrestaShop\PrestaShop\Core\Context\EmployeeContextBuilder;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\EventListener\Admin\Context\EmployeeContextSubscriber;
use PrestaShopBundle\Security\Admin\SessionEmployeeProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Tests\Unit\PrestaShopBundle\EventListener\ContextEventListenerTestCase;

class EmployeeContextSubscriberTest extends ContextEventListenerTestCase
{
    public function testEmployeeNotFound(): void
    {
        $event = $this->createRequestEvent(new Request());
        $employeeBuilder = new EmployeeContextBuilder(
            $this->createMock(EmployeeRepository::class),
            $this->createMock(ContextStateManager::class),
            $this->createMock(ShopRepository::class),
        );
        $listener = new EmployeeContextSubscriber(
            $employeeBuilder,
            $this->createMock(Security::class),
            $this->createMock(SessionEmployeeProvider::class),
        );
        $listener->onKernelRequest($event);
        $this->assertEquals(null, $this->getPrivateField($employeeBuilder, 'employeeId'));
    }

    public function testEmployeeFromSymfonySecurity(): void
    {
        $employeeBuilder = new EmployeeContextBuilder(
            $this->createMock(EmployeeRepository::class),
            $this->createMock(ContextStateManager::class),
            $this->createMock(ShopRepository::class),
        );
        $employeeMock = $this->createMock(Employee::class);
        $employeeMock->method('getId')->willReturn(51);
        $securityMock = $this->createMock(Security::class);
        $securityMock->method('getUser')->willReturn($employeeMock);
        $listener = new EmployeeContextSubscriber(
            $employeeBuilder,
            $securityMock,
            $this->createMock(SessionEmployeeProvider::class),
        );

        $event = $this->createRequestEvent(new Request());
        $listener->onKernelRequest($event);
        $this->assertEquals(51, $this->getPrivateField($employeeBuilder, 'employeeId'));
    }

    public function testEmployeeFromSessionEmployeeProvider(): void
    {
        $employeeBuilder = new EmployeeContextBuilder(
            $this->createMock(EmployeeRepository::class),
            $this->createMock(ContextStateManager::class),
            $this->createMock(ShopRepository::class),
        );
        $employeeMock = $this->createMock(Employee::class);
        $employeeMock->method('getId')->willReturn(51);

        $sessionEmployeeProvider = $this->createMock(SessionEmployeeProvider::class);
        $sessionEmployeeProvider->method('getEmployeeFromSession')->willReturn($employeeMock);

        $listener = new EmployeeContextSubscriber(
            $employeeBuilder,
            $this->createMock(Security::class),
            $sessionEmployeeProvider,
        );

        $event = $this->createRequestEvent(new Request());
        $listener->onKernelRequest($event);
        $this->assertEquals(51, $this->getPrivateField($employeeBuilder, 'employeeId'));
    }
}
