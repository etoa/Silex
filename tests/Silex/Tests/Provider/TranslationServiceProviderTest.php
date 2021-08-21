<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silex\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Silex\Application;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * TranslationProvider test cases.
 *
 * @author Daniel Tschinder <daniel@tschinder.de>
 */
class TranslationServiceProviderTest extends TestCase
{
    /**
     * @return Application
     */
    protected function getPreparedApp()
    {
        $app = new Application();

        $app->register(new LocaleServiceProvider());
        $app->register(new TranslationServiceProvider());
        $app['translator.domains'] = [
            'messages' => [
                'en' => [
                    'key1' => 'The translation',
                    'key_only_english' => 'Foo',
                    'key2' => 'One apple|%count% apples',
                    'test' => [
                        'key' => 'It works',
                    ],
                ],
                'de' => [
                    'key1' => 'The german translation',
                    'key2' => 'One german apple|%count% german apples',
                    'test' => [
                        'key' => 'It works in german',
                    ],
                ],
            ],
        ];

        return $app;
    }

    public function transProvider()
    {
        return [
            ['key1', null, 'The translation'],
            ['key1', 'de', 'The german translation'],
            ['key1', 'ru', 'The translation'], // fallback
            ['test.key', null, 'It works'],
            ['test.key', 'de', 'It works in german'],
            ['test.key', 'ru', 'It works'], // fallback
        ];
    }

    /**
     * @dataProvider transProvider
     */
    public function testTransForDefaultLanguage($key, $locale, $expected)
    {
        $app = $this->getPreparedApp();

        $result = $app['translator']->trans($key, [], null, $locale);

        $this->assertEquals($expected, $result);
    }

    public function testFallbacks()
    {
        $app = $this->getPreparedApp();
        $app['locale_fallbacks'] = ['de', 'en'];

        // fallback to english
        $result = $app['translator']->trans('key_only_english', [], null, 'ru');
        $this->assertEquals('Foo', $result);

        // fallback to german
        $result = $app['translator']->trans('key1', [], null, 'ru');
        $this->assertEquals('The german translation', $result);
    }

    public function testLocale()
    {
        $app = $this->getPreparedApp();
        $app->get('/', function () use ($app) { return $app['translator']->getLocale(); });
        $response = $app->handle(Request::create('/'));
        $this->assertEquals('en', $response->getContent());

        $app = $this->getPreparedApp();
        $app->get('/', function () use ($app) { return $app['translator']->getLocale(); });
        $request = Request::create('/');
        $request->setLocale('fr');
        $response = $app->handle($request);
        $this->assertEquals('fr', $response->getContent());

        $app = $this->getPreparedApp();
        $app->get('/{_locale}', function () use ($app) { return $app['translator']->getLocale(); });
        $response = $app->handle(Request::create('/es'));
        $this->assertEquals('es', $response->getContent());
    }

    public function testLocaleInSubRequests()
    {
        $app = $this->getPreparedApp();
        $app->get('/embed/{_locale}', function () use ($app) { return $app['translator']->getLocale(); });
        $app->get('/{_locale}', function () use ($app) {
            return $app['translator']->getLocale().
                   $app->handle(Request::create('/embed/es'), HttpKernelInterface::SUB_REQUEST)->getContent().
                   $app['translator']->getLocale();
        });
        $response = $app->handle(Request::create('/fr'));
        $this->assertEquals('fresfr', $response->getContent());

        $app = $this->getPreparedApp();
        $app->get('/embed', function () use ($app) { return $app['translator']->getLocale(); });
        $app->get('/{_locale}', function () use ($app) {
            return $app['translator']->getLocale().
                   $app->handle(Request::create('/embed'), HttpKernelInterface::SUB_REQUEST)->getContent().
                   $app['translator']->getLocale();
        });
        $response = $app->handle(Request::create('/fr'));
        // locale in sub-request must be "en" as this is the value if the sub-request is converted to an ESI
        $this->assertEquals('frenfr', $response->getContent());
    }

    public function testLocaleWithBefore()
    {
        $app = $this->getPreparedApp();
        $app->before(function (Request $request) { $request->setLocale('fr'); }, Application::EARLY_EVENT);
        $app->get('/embed', function () use ($app) { return $app['translator']->getLocale(); });
        $app->get('/', function () use ($app) {
            return $app['translator']->getLocale().
                $app->handle(Request::create('/embed'), HttpKernelInterface::SUB_REQUEST)->getContent().
                $app['translator']->getLocale();
        });
        $response = $app->handle(Request::create('/'));
        // locale in sub-request is "en" as the before filter is only executed for the main request
        $this->assertEquals('frenfr', $response->getContent());
    }
}
