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

namespace Tests\Unit\PrestaShopBundle\ApiPlatform\Metadata;

use ApiPlatform\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor;

class CQRSCreateTest extends TestCase
{
    public const VALID_COMMAND_CLASS = AddProductCommand::class;
    public const OTHER_VALID_COMMAND_CLASS = UpdateProductCommand::class;
    public const INVALID_COMMAND_CLASS = 'My\\Namespace\\MyCommand';

    public function testDefaultConstructor(): void
    {
        // Without any parameters
        $operation = new CQRSCreate();
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertEquals(CQRSCreate::METHOD_POST, $operation->getMethod());
        $this->assertEquals([], $operation->getExtraProperties());
        $this->assertEquals(['json'], $operation->getFormats());

        // With positioned parameters
        $operation = new CQRSCreate('/uri');
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertEquals(CQRSCreate::METHOD_POST, $operation->getMethod());
        $this->assertEquals('/uri', $operation->getUriTemplate());
        $this->assertEquals([], $operation->getExtraProperties());
        $this->assertEquals(['json'], $operation->getFormats());

        // With named parameters
        $operation = new CQRSCreate(
            formats: ['json', 'html'],
            extraProperties: ['scopes' => ['test']]
        );
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertEquals(['scopes' => ['test']], $operation->getExtraProperties());
        $this->assertEquals(['json', 'html'], $operation->getFormats());
    }

    public function testScopes(): void
    {
        // Scopes parameters in constructor
        $operation = new CQRSCreate(
            scopes: ['test', 'test2']
        );
        $this->assertEquals(['scopes' => ['test', 'test2']], $operation->getExtraProperties());
        $this->assertEquals(['test', 'test2'], $operation->getScopes());

        // Extra properties parameters in constructor
        $operation = new CQRSCreate(
            extraProperties: ['scopes' => ['test']]
        );
        $this->assertEquals(['scopes' => ['test']], $operation->getExtraProperties());
        $this->assertEquals(['test'], $operation->getScopes());

        // Extra properties AND scopes parameters in constructor, both values get merged but remain unique
        $operation = new CQRSCreate(
            extraProperties: ['scopes' => ['test', 'test1']],
            scopes: ['test', 'test2'],
        );
        $this->assertEquals(['scopes' => ['test', 'test1', 'test2']], $operation->getExtraProperties());
        $this->assertEquals(['test', 'test1', 'test2'], $operation->getScopes());

        // Use with method, returned object is a clone All values are replaced
        $operation2 = $operation->withScopes(['test3']);
        $this->assertNotEquals($operation2, $operation);
        $this->assertEquals(['scopes' => ['test3']], $operation2->getExtraProperties());
        $this->assertEquals(['test3'], $operation2->getScopes());
        // Initial operation not modified of course
        $this->assertEquals(['scopes' => ['test', 'test1', 'test2']], $operation->getExtraProperties());
        $this->assertEquals(['test', 'test1', 'test2'], $operation->getScopes());
    }

    public function testCQRSCommand(): void
    {
        // CQRS command parameters in constructor
        $operation = new CQRSCreate(
            CQRSCommand: self::VALID_COMMAND_CLASS,
        );
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertEquals(['CQRSCommand' => self::VALID_COMMAND_CLASS], $operation->getExtraProperties());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getCQRSCommand());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getInput());

        // Extra properties parameters in constructor
        $operation = new CQRSCreate(
            extraProperties: ['CQRSCommand' => self::VALID_COMMAND_CLASS],
        );
        $this->assertEquals(['CQRSCommand' => self::VALID_COMMAND_CLASS], $operation->getExtraProperties());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getCQRSCommand());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getInput());

        // Extra properties AND CQRS query parameters in constructor, both values are equals no problem
        $operation = new CQRSCreate(
            extraProperties: ['CQRSCommand' => self::VALID_COMMAND_CLASS],
            CQRSCommand: self::VALID_COMMAND_CLASS,
        );
        $this->assertEquals(['CQRSCommand' => self::VALID_COMMAND_CLASS], $operation->getExtraProperties());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getCQRSCommand());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getInput());

        // Use with method, returned object is a clone All values are replaced
        $operation2 = $operation->withCQRSCommand('My\\Namespace\\MyOtherCommand');
        $this->assertNotEquals($operation2, $operation);
        $this->assertEquals(['CQRSCommand' => 'My\\Namespace\\MyOtherCommand'], $operation2->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyOtherCommand', $operation2->getCQRSCommand());
        $this->assertEquals('My\\Namespace\\MyOtherCommand', $operation2->getInput());
        // Initial operation not modified of course
        $this->assertEquals(['CQRSCommand' => self::VALID_COMMAND_CLASS], $operation->getExtraProperties());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getCQRSCommand());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operation->getInput());

        // When input is manually specified it is kept over the CQRSCommand
        $operationInput = new CQRSCreate(
            input: 'My\\Namespace\\MyOtherCommand',
            CQRSCommand: self::VALID_COMMAND_CLASS,
        );
        $this->assertEquals(['CQRSCommand' => self::VALID_COMMAND_CLASS], $operationInput->getExtraProperties());
        $this->assertEquals(self::VALID_COMMAND_CLASS, $operationInput->getCQRSCommand());
        $this->assertEquals('My\\Namespace\\MyOtherCommand', $operationInput->getInput());
        // Clone value keeps the initial different input value
        $operationInput2 = $operationInput->withCQRSCommand('My\\Namespace\\MyThirdCommand');
        $this->assertNotEquals($operationInput2, $operationInput);
        $this->assertEquals(['CQRSCommand' => 'My\\Namespace\\MyThirdCommand'], $operationInput2->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyThirdCommand', $operationInput2->getCQRSCommand());
        $this->assertEquals('My\\Namespace\\MyOtherCommand', $operationInput2->getInput());

        // When both values are specified, but they are different trigger an exception
        $caughtException = null;
        try {
            new CQRSCreate(
                extraProperties: ['CQRSCommand' => self::VALID_COMMAND_CLASS],
                CQRSCommand: 'My\\Namespace\\MyOtherCommand',
            );
        } catch (InvalidArgumentException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException);
        $this->assertInstanceOf(InvalidArgumentException::class, $caughtException);
        $this->assertEquals('Specifying an extra property CQRSCommand and a CQRSCommand argument that are different is invalid', $caughtException->getMessage());

        // CQRS command parameters in constructor that does not exist is not forced as the input
        $operation = new CQRSCommand(
            CQRSCommand: self::INVALID_COMMAND_CLASS,
        );
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertEquals(['CQRSCommand' => self::INVALID_COMMAND_CLASS], $operation->getExtraProperties());
        $this->assertEquals(self::INVALID_COMMAND_CLASS, $operation->getCQRSCommand());
        $this->assertNull($operation->getInput());
    }

    public function testCQRSQuery(): void
    {
        // CQRS query parameters in constructor
        $operation = new CQRSCreate(
            CQRSQuery: 'My\\Namespace\\MyQuery',
        );
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertEquals(null, $operation->getProvider());
        $this->assertEquals(['CQRSQuery' => 'My\\Namespace\\MyQuery'], $operation->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyQuery', $operation->getCQRSQuery());

        // Extra properties parameters in constructor
        $operation = new CQRSCreate(
            extraProperties: ['CQRSQuery' => 'My\\Namespace\\MyQuery'],
        );
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertEquals(null, $operation->getProvider());
        $this->assertEquals(['CQRSQuery' => 'My\\Namespace\\MyQuery'], $operation->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyQuery', $operation->getCQRSQuery());

        // Extra properties AND CQRS query parameters in constructor, both values are equals no problem
        $operation = new CQRSCreate(
            extraProperties: ['CQRSQuery' => 'My\\Namespace\\MyQuery'],
            CQRSQuery: 'My\\Namespace\\MyQuery',
        );
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertEquals(null, $operation->getProvider());
        $this->assertEquals(['CQRSQuery' => 'My\\Namespace\\MyQuery'], $operation->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyQuery', $operation->getCQRSQuery());

        // Use with method, returned object is a clone All values are replaced
        $operation2 = $operation->withCQRSQuery('My\\Namespace\\MyOtherQuery');
        $this->assertNotEquals($operation2, $operation);
        $this->assertEquals(['CQRSQuery' => 'My\\Namespace\\MyOtherQuery'], $operation2->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyOtherQuery', $operation2->getCQRSQuery());
        // Initial operation not modified of course
        $this->assertEquals(['CQRSQuery' => 'My\\Namespace\\MyQuery'], $operation->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyQuery', $operation->getCQRSQuery());

        // New operation without query, the provider is forced when it is set
        $operation = new CQRSCreate();
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertArrayNotHasKey('CQRSQuery', $operation->getExtraProperties());
        $this->assertNull($operation->getCQRSQuery());

        $operation3 = $operation->withCQRSQuery('My\\Namespace\\MyQuery');
        $this->assertEquals(CommandProcessor::class, $operation3->getProcessor());
        $this->assertEquals(null, $operation3->getProvider());
        $this->assertEquals(['CQRSQuery' => 'My\\Namespace\\MyQuery'], $operation3->getExtraProperties());
        $this->assertEquals('My\\Namespace\\MyQuery', $operation3->getCQRSQuery());
        // And initial operation as not modified of course
        $this->assertEquals(CommandProcessor::class, $operation->getProcessor());
        $this->assertNull($operation->getProvider());
        $this->assertArrayNotHasKey('CQRSQuery', $operation->getExtraProperties());
        $this->assertNull($operation->getCQRSQuery());

        // When both values are specified, but they are different trigger an exception
        $caughtException = null;
        try {
            new CQRSCreate(
                extraProperties: ['CQRSQuery' => 'My\\Namespace\\MyQuery'],
                CQRSQuery: 'My\\Namespace\\MyOtherQuery',
            );
        } catch (InvalidArgumentException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException);
        $this->assertInstanceOf(InvalidArgumentException::class, $caughtException);
        $this->assertEquals('Specifying an extra property CQRSQuery and a CQRSQuery argument that are different is invalid', $caughtException->getMessage());
    }

    public function testCQRSQueryMapping(): void
    {
        // CQRS query mapping parameters in constructor
        $queryMapping = ['[id]' => '[queryId]'];
        $operation = new CQRSCreate(
            CQRSQueryMapping: $queryMapping,
        );

        $this->assertEquals(['CQRSQueryMapping' => $queryMapping], $operation->getExtraProperties());
        $this->assertEquals($queryMapping, $operation->getCQRSQueryMapping());

        // Extra properties parameters in constructor
        $operation = new CQRSCreate(
            extraProperties: ['CQRSQueryMapping' => $queryMapping],
        );
        $this->assertEquals(['CQRSQueryMapping' => $queryMapping], $operation->getExtraProperties());
        $this->assertEquals($queryMapping, $operation->getCQRSQueryMapping());

        // Extra properties AND CQRS query mapping parameters in constructor, both values are equals no problem
        $operation = new CQRSCreate(
            extraProperties: ['CQRSQueryMapping' => $queryMapping],
            CQRSQueryMapping: $queryMapping,
        );
        $this->assertEquals(['CQRSQueryMapping' => $queryMapping], $operation->getExtraProperties());
        $this->assertEquals($queryMapping, $operation->getCQRSQueryMapping());

        // Use with method, returned object is a clone All values are replaced
        $newMapping = ['[queryId' => '[valueObjectId]'];
        $operation2 = $operation->withCQRSQueryMapping($newMapping);
        $this->assertNotEquals($operation2, $operation);
        $this->assertEquals(['CQRSQueryMapping' => $newMapping], $operation2->getExtraProperties());
        $this->assertEquals($newMapping, $operation2->getCQRSQueryMapping());
        // Initial operation not modified of course
        $this->assertEquals(['CQRSQueryMapping' => $queryMapping], $operation->getExtraProperties());
        $this->assertEquals($queryMapping, $operation->getCQRSQueryMapping());

        // When both values are specified, but they are different trigger an exception
        $caughtException = null;
        try {
            new CQRSCreate(
                extraProperties: ['CQRSQueryMapping' => $queryMapping],
                CQRSQueryMapping: $newMapping,
            );
        } catch (InvalidArgumentException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException);
        $this->assertInstanceOf(InvalidArgumentException::class, $caughtException);
        $this->assertEquals('Specifying an extra property CQRSQueryMapping and a CQRSQueryMapping argument that are different is invalid', $caughtException->getMessage());
    }

    public function testApiResourceMapping(): void
    {
        // Api resource mapping parameters in constructor
        $resourceMapping = ['[id]' => '[queryId]'];
        $operation = new CQRSCreate(
            ApiResourceMapping: $resourceMapping,
        );

        $this->assertEquals(['ApiResourceMapping' => $resourceMapping], $operation->getExtraProperties());
        $this->assertEquals($resourceMapping, $operation->getApiResourceMapping());

        // Extra properties parameters in constructor
        $operation = new CQRSCreate(
            extraProperties: ['ApiResourceMapping' => $resourceMapping],
        );
        $this->assertEquals(['ApiResourceMapping' => $resourceMapping], $operation->getExtraProperties());
        $this->assertEquals($resourceMapping, $operation->getApiResourceMapping());

        // Extra properties AND Api resource mapping parameters in constructor, both values are equals no problem
        $operation = new CQRSCreate(
            extraProperties: ['ApiResourceMapping' => $resourceMapping],
            ApiResourceMapping: $resourceMapping,
        );
        $this->assertEquals(['ApiResourceMapping' => $resourceMapping], $operation->getExtraProperties());
        $this->assertEquals($resourceMapping, $operation->getApiResourceMapping());

        // Use with method, returned object is a clone All values are replaced
        $newMapping = ['[queryId' => '[valueObjectId]'];
        $operation2 = $operation->withApiResourceMapping($newMapping);
        $this->assertNotEquals($operation2, $operation);
        $this->assertEquals(['ApiResourceMapping' => $newMapping], $operation2->getExtraProperties());
        $this->assertEquals($newMapping, $operation2->getApiResourceMapping());
        // Initial operation not modified of course
        $this->assertEquals(['ApiResourceMapping' => $resourceMapping], $operation->getExtraProperties());
        $this->assertEquals($resourceMapping, $operation->getApiResourceMapping());

        // When both values are specified, but they are different trigger an exception
        $caughtException = null;
        try {
            new CQRSCreate(
                extraProperties: ['ApiResourceMapping' => $resourceMapping],
                ApiResourceMapping: $newMapping,
            );
        } catch (InvalidArgumentException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException);
        $this->assertInstanceOf(InvalidArgumentException::class, $caughtException);
        $this->assertEquals('Specifying an extra property ApiResourceMapping and a ApiResourceMapping argument that are different is invalid', $caughtException->getMessage());
    }

    public function testExperimentalOperation(): void
    {
        // Default value is false (no extra property added)
        $operation = new CQRSCreate();
        $this->assertEquals([], $operation->getExtraProperties());
        $this->assertEquals(false, $operation->getExperimentalOperation());

        // Scopes parameters in constructor
        $operation = new CQRSCreate(
            experimentalOperation: true,
        );
        $this->assertEquals(['experimentalOperation' => true], $operation->getExtraProperties());
        $this->assertEquals(true, $operation->getExperimentalOperation());

        // Extra properties parameters in constructor
        $operation = new CQRSCreate(
            extraProperties: ['experimentalOperation' => false]
        );
        $this->assertEquals(['experimentalOperation' => false], $operation->getExtraProperties());
        $this->assertEquals(false, $operation->getExperimentalOperation());

        // Extra properties AND scopes parameters in constructor, both values get merged but remain unique
        $operation = new CQRSCreate(
            extraProperties: ['experimentalOperation' => true],
            experimentalOperation: true,
        );
        $this->assertEquals(['experimentalOperation' => true], $operation->getExtraProperties());
        $this->assertEquals(true, $operation->getExperimentalOperation());

        // Use with method, returned object is a clone All values are replaced
        $operation2 = $operation->withExperimentalOperation(false);
        $this->assertNotEquals($operation2, $operation);
        $this->assertEquals(['experimentalOperation' => false], $operation2->getExtraProperties());
        $this->assertEquals(false, $operation2->getExperimentalOperation());
        // Initial operation not modified of course
        $this->assertEquals(['experimentalOperation' => true], $operation->getExtraProperties());
        $this->assertEquals(true, $operation->getExperimentalOperation());

        // When both values are specified, but they are different trigger an exception
        $caughtException = null;
        try {
            new CQRSCreate(
                extraProperties: ['experimentalOperation' => true],
                experimentalOperation: false,
            );
        } catch (InvalidArgumentException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException);
        $this->assertInstanceOf(InvalidArgumentException::class, $caughtException);
        $this->assertEquals('Specifying an extra property experimentalOperation and a experimentalOperation argument that are different is invalid', $caughtException->getMessage());
    }
}
