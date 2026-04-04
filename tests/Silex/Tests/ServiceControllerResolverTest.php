<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Tests;

use PHPUnit\Framework\TestCase;
use Silex\ServiceControllerResolver;
use Silex\Application;
use Silex\Tests\Fixtures\Php7Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for ServiceControllerResolver, see ServiceControllerResolverRouterTest for some
 * integration tests.
 */
class ServiceControllerResolverTest extends Testcase
{
    private $app;
    private $mockCallbackResolver;
    private $mockResolver;
    private $resolver;

    public function setup(): void
    {
        $this->mockResolver = $this->getMockBuilder('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockCallbackResolver = $this->getMockBuilder('Silex\CallbackResolver')
            ->disableOriginalConstructor()
            ->getMock();

        $this->app = new Application();
        $this->resolver = new ServiceControllerResolver($this->mockResolver, $this->mockCallbackResolver);
    }

    public function testShouldResolveServiceController()
    {
        $this->mockCallbackResolver->expects($this->once())
            ->method('isValid')
            ->will($this->returnValue(true));

        $this->mockCallbackResolver->expects($this->once())
            ->method('convertCallback')
            ->with('Silex\Tests\Fixtures\Php7Controller:typehintedAction')
            ->will($this->returnValue($callback = [new Php7Controller(), 'typehintedAction']));

        $this->app['some_service'] = function () { return new \stdClass(); };

        $req = Request::create('/');
        $req->attributes->set('_controller', Php7Controller::class . ':' . 'typehintedAction');

        $this->assertEquals($callback, $this->resolver->getController($req));
    }
}
