<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\ModuleManager\Listener;

use ArrayObject;
use InvalidArgumentException;
use Zend\EventManager\Test\EventListenerIntrospectionTrait;
use Zend\ModuleManager\Listener\ConfigListener;
use Zend\ModuleManager\Listener\ModuleResolverListener;
use Zend\ModuleManager\Listener\ListenerOptions;
use Zend\ModuleManager\ModuleManager;
use Zend\ModuleManager\ModuleEvent;
use ZendTest\ModuleManager\SetUpCacheDirTrait;

/**
 * @covers Zend\ModuleManager\Listener\AbstractListener
 * @covers Zend\ModuleManager\Listener\ConfigListener
 */
class ConfigListenerTest extends AbstractListenerTestCase
{
    use EventListenerIntrospectionTrait;
    use SetUpCacheDirTrait;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    public function setUp()
    {
        $this->moduleManager = new ModuleManager([]);
        $this->moduleManager->getEventManager()->attach(
            ModuleEvent::EVENT_LOAD_MODULE_RESOLVE,
            new ModuleResolverListener,
            1000
        );
    }

    public function testMultipleConfigsAreMerged()
    {
        $configListener = new ConfigListener;

        $moduleManager = $this->moduleManager;
        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->setModules(['SomeModule', 'ListenerTestModule']);
        $moduleManager->loadModules();

        $config = $configListener->getMergedConfig(false);
        $this->assertSame(2, count($config));
        $this->assertSame('test', $config['listener']);
        $this->assertSame('thing', $config['some']);
        $configObject = $configListener->getMergedConfig();
        $this->assertInstanceOf('Zend\Config\Config', $configObject);
    }

    public function testCanCacheMergedConfig()
    {
        $options = new ListenerOptions([
            'cache_dir'            => $this->tmpdir,
            'config_cache_enabled' => true,
        ]);
        $configListener = new ConfigListener($options);

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule', 'ListenerTestModule']);
        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->loadModules(); // This should cache the config

        $modules = $moduleManager->getLoadedModules();
        $this->assertTrue($modules['ListenerTestModule']->getConfigCalled);

        // Now we check to make sure it uses the config and doesn't hit
        // the module objects getConfig() method(s)
        $moduleManager = new ModuleManager(['SomeModule', 'ListenerTestModule']);
        $moduleManager->getEventManager()->attach(
            ModuleEvent::EVENT_LOAD_MODULE_RESOLVE,
            new ModuleResolverListener,
            1000
        );
        $configListener = new ConfigListener($options);
        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->loadModules();
        $modules = $moduleManager->getLoadedModules();
        $this->assertFalse($modules['ListenerTestModule']->getConfigCalled);
    }

    public function testBadConfigValueThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        $configListener = new ConfigListener;

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['BadConfigModule', 'SomeModule']);
        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->loadModules();
    }

    public function testBadGlobPathTrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $configListener = new ConfigListener;
        $configListener->addConfigGlobPath(['asd']);
    }

    public function testBadGlobPathArrayTrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $configListener = new ConfigListener;
        $configListener->addConfigGlobPaths('asd');
    }

    public function testBadStaticPathArrayTrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $configListener = new ConfigListener;
        $configListener->addConfigStaticPaths('asd');
    }

    public function testCanMergeConfigFromGlob()
    {
        $configListener = new ConfigListener;
        $configListener->addConfigGlobPath(__DIR__ . '/_files/good/*.{ini,php,xml}');

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();
        $configObjectCheck = $configListener->getMergedConfig();

        // Test as object
        $configObject = $configListener->getMergedConfig();
        $this->assertSame(spl_object_hash($configObjectCheck), spl_object_hash($configObject));
        $this->assertSame('loaded', $configObject->ini);
        $this->assertSame('loaded', $configObject->php);
        $this->assertSame('loaded', $configObject->xml);
        // Test as array
        $config = $configListener->getMergedConfig(false);
        $this->assertSame('loaded', $config['ini']);
        $this->assertSame('loaded', $config['php']);
        $this->assertSame('loaded', $config['xml']);
    }

    public function testCanMergeConfigFromStaticPath()
    {
        $configListener = new ConfigListener;
        $configListener->addConfigStaticPath(__DIR__ . '/_files/good/config.ini');
        $configListener->addConfigStaticPath(__DIR__ . '/_files/good/config.php');
        $configListener->addConfigStaticPath(__DIR__ . '/_files/good/config.xml');

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();
        $configObjectCheck = $configListener->getMergedConfig();

        // Test as object
        $configObject = $configListener->getMergedConfig();
        $this->assertSame(spl_object_hash($configObjectCheck), spl_object_hash($configObject));
        $this->assertSame('loaded', $configObject->ini);
        $this->assertSame('loaded', $configObject->php);
        $this->assertSame('loaded', $configObject->xml);
        // Test as array
        $config = $configListener->getMergedConfig(false);
        $this->assertSame('loaded', $config['ini']);
        $this->assertSame('loaded', $config['php']);
        $this->assertSame('loaded', $config['xml']);
    }

    public function testCanMergeConfigFromStaticPaths()
    {
        $configListener = new ConfigListener;
        $configListener->addConfigStaticPaths([
            __DIR__ . '/_files/good/config.ini',
            __DIR__ . '/_files/good/config.php',
            __DIR__ . '/_files/good/config.xml'
        ]);

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();
        $configObjectCheck = $configListener->getMergedConfig();

        // Test as object
        $configObject = $configListener->getMergedConfig();
        $this->assertSame(spl_object_hash($configObjectCheck), spl_object_hash($configObject));
        $this->assertSame('loaded', $configObject->ini);
        $this->assertSame('loaded', $configObject->php);
        $this->assertSame('loaded', $configObject->xml);
        // Test as array
        $config = $configListener->getMergedConfig(false);
        $this->assertSame('loaded', $config['ini']);
        $this->assertSame('loaded', $config['php']);
        $this->assertSame('loaded', $config['xml']);
    }

    public function testCanCacheMergedConfigFromGlob()
    {
        $options = new ListenerOptions([
            'cache_dir'            => $this->tmpdir,
            'config_cache_enabled' => true,
        ]);
        $configListener = new ConfigListener($options);
        $configListener->addConfigGlobPath(__DIR__ . '/_files/good/*.{ini,php,xml}');

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();
        $configObjectFromGlob = $configListener->getMergedConfig();

        // This time, don't add the glob path
        $configListener = new ConfigListener($options);
        $moduleManager = new ModuleManager(['SomeModule']);
        $moduleManager->getEventManager()->attach(
            ModuleEvent::EVENT_LOAD_MODULE_RESOLVE,
            new ModuleResolverListener,
            1000
        );

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();

        // Check if values from glob object and cache object are the same
        $configObjectFromCache = $configListener->getMergedConfig();
        $this->assertNotNull($configObjectFromGlob->ini);
        $this->assertSame($configObjectFromGlob->ini, $configObjectFromCache->ini);
        $this->assertNotNull($configObjectFromGlob->php);
        $this->assertSame($configObjectFromGlob->php, $configObjectFromCache->php);
        $this->assertNotNull($configObjectFromGlob->xml);
        $this->assertSame($configObjectFromGlob->xml, $configObjectFromCache->xml);
    }

    public function testCanCacheMergedConfigFromStatic()
    {
        $options = new ListenerOptions([
            'cache_dir'            => $this->tmpdir,
            'config_cache_enabled' => true,
        ]);
        $configListener = new ConfigListener($options);
        $configListener->addConfigStaticPaths([
            __DIR__ . '/_files/good/config.ini',
            __DIR__ . '/_files/good/config.php',
            __DIR__ . '/_files/good/config.xml'
        ]);

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();
        $configObjectFromGlob = $configListener->getMergedConfig();

        // This time, don't add the glob path
        $configListener = new ConfigListener($options);
        $moduleManager = new ModuleManager(['SomeModule']);
        $moduleManager->getEventManager()->attach(
            ModuleEvent::EVENT_LOAD_MODULE_RESOLVE,
            new ModuleResolverListener,
            1000
        );

        $configListener->attach($moduleManager->getEventManager());

        $moduleManager->loadModules();

        // Check if values from glob object and cache object are the same
        $configObjectFromCache = $configListener->getMergedConfig();
        $this->assertNotNull($configObjectFromGlob->ini);
        $this->assertSame($configObjectFromGlob->ini, $configObjectFromCache->ini);
        $this->assertNotNull($configObjectFromGlob->php);
        $this->assertSame($configObjectFromGlob->php, $configObjectFromCache->php);
        $this->assertNotNull($configObjectFromGlob->xml);
        $this->assertSame($configObjectFromGlob->xml, $configObjectFromCache->xml);
    }

    public function testCanMergeConfigFromArrayOfGlobs()
    {
        $configListener = new ConfigListener;
        $configListener->addConfigGlobPaths(new ArrayObject([
            __DIR__ . '/_files/good/*.ini',
            __DIR__ . '/_files/good/*.php',
            __DIR__ . '/_files/good/*.xml',
        ]));

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->loadModules();

        // Test as object
        $configObject = $configListener->getMergedConfig();
        $this->assertSame('loaded', $configObject->ini);
        $this->assertSame('loaded', $configObject->php);
        $this->assertSame('loaded', $configObject->xml);
    }

    public function testCanMergeConfigFromArrayOfStatic()
    {
        $configListener = new ConfigListener;
        $configListener->addConfigStaticPaths(new ArrayObject([
            __DIR__ . '/_files/good/config.ini',
            __DIR__ . '/_files/good/config.php',
            __DIR__ . '/_files/good/config.xml',
        ]));

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->loadModules();

        // Test as object
        $configObject = $configListener->getMergedConfig();
        $this->assertSame('loaded', $configObject->ini);
        $this->assertSame('loaded', $configObject->php);
        $this->assertSame('loaded', $configObject->xml);
    }

    public function testMergesWithMergeAndReplaceBehavior()
    {
        $configListener = new ConfigListener();

        $moduleManager = $this->moduleManager;
        $moduleManager->setModules(['SomeModule']);

        $configListener->addConfigStaticPaths([
            __DIR__ . '/_files/good/merge1.php',
            __DIR__ . '/_files/good/merge2.php',
        ]);

        $configListener->attach($moduleManager->getEventManager());
        $moduleManager->loadModules();

        $mergedConfig = $configListener->getMergedConfig(false);
        $this->assertSame(['foo', 'bar'], $mergedConfig['indexed']);
        $this->assertSame('bar', $mergedConfig['keyed']);
    }

    public function testConfigListenerFunctionsAsAggregateListener()
    {
        $configListener = new ConfigListener;

        $moduleManager = $this->moduleManager;
        $events        = $moduleManager->getEventManager();
        $this->assertEquals(2, count($this->getEventsFromEventManager($events)));

        $configListener->attach($events);
        $this->assertEquals(4, count($this->getEventsFromEventManager($events)));

        $configListener->detach($events);
        $this->assertEquals(2, count($this->getEventsFromEventManager($events)));
    }

    public function datasetCachedConfigs()
    {
        $datasets = [
            'valid_file' => [
                // Test for check if cache is properly used if is valid
                ['data' => 'any'], // expects to fall back to loading all modules
                '<?php return [\'data\' => \'any\'];',
            ],
            'inexistent_file' => [
                ['some' => 'thing', 'listener' => 'test'], // expects to fall back to loading all modules
                null, // file won't be created
            ],
            'file_with_data_before_php_tag' => [
                ['some' => 'thing', 'listener' => 'test'], // expects to fall back to loading all modules
                'something<?php return [\'data\' => \'any\'];',
            ],
            'malformed_open_tag' => [
                ['some' => 'thing', 'listener' => 'test'], // expects to fall back to loading all modules
                '<\?php return [\'data\' => \'any\'];',
            ],
        ];
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $datasets['invalid_file_syntax'] = [
                ['some' => 'thing', 'listener' => 'test'], // expects to fall back to loading all modules
                '<?php return (???\'data\' => \'any\');',
            ];
        }
        return $datasets;
    }

    /**
     * @dataProvider datasetCachedConfigs
     */
    public function testDoesNotReturnConfigToOutputIfFileIsMalformed(
        $expectedConfig,
        $cacheContents
    ) {
        $tempDir = sys_get_temp_dir();
        $cacheConfigFile = $tempDir . '/module-config-cache.php';
        $staticConfigFile = $tempDir . '/module-config-static.php';

        if (file_exists($cacheConfigFile)) {
            unlink($cacheConfigFile);
        }

        if ($cacheContents !== null) {
            $fileCreated = file_put_contents($cacheConfigFile, $cacheContents);
            if ($fileCreated === false) {
                $this->markTestSkipped('Running system does not allow writing to temp directory');
                return;
            }
        }

        // Prepare static config to check if config falled back to static autoloading when cache is malformed
        $fileCreated = file_put_contents($staticConfigFile, '<?php return [\'static\' => true];');
        if ($fileCreated === false) {
            $this->markTestSkipped('Running system does not allow writing to temp directory');
            return;
        }

        // Set cached config to be stored inside system temporary directory
        $options = new ListenerOptions();
        $options->setConfigCacheEnabled(true);
        $options->setCacheDir($tempDir);

        // Catch all output
        ob_start();

        try {
            // Try to get config and check if nothing was sent to output
            $configListener = new ConfigListener($options);
            // Set modules and autoload them
            $configListener->attach($this->moduleManager->getEventManager());
            $this->moduleManager->setModules(['SomeModule', 'ListenerTestModule']);
            $this->moduleManager->loadModules();
            // Check expected config
            $this->assertSame(
                $expectedConfig,
                $configListener->getMergedConfig(false),
                'Read config does not match expected one'
            );
        } finally {
            // Cleanup
            if (file_exists($cacheConfigFile)) {
                unlink($cacheConfigFile);
            }
            if (file_exists($staticConfigFile)) {
                unlink($staticConfigFile);
            }
            $output = ob_get_clean();
        }

        $this->assertEmpty($output, 'Data was sent to output: ' . $output);
    }
}
