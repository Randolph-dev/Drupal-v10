<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Tests\Core\Render\TestCacheableDependency;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\Core\Cache\CacheableMetadata
 * @group Cache
 */
class CacheableMetadataTest extends UnitTestCase {

  /**
   * @covers ::merge
   * @dataProvider providerTestMerge
   *
   * This only tests at a high level, because it reuses existing logic. Detailed
   * tests exist for the existing logic:
   *
   * @see \Drupal\Tests\Core\Cache\CacheTest::testMergeTags()
   * @see \Drupal\Tests\Core\Cache\CacheTest::testMergeMaxAges()
   * @see \Drupal\Tests\Core\Cache\CacheContextsTest
   */
  public function testMerge(CacheableMetadata $a, CacheableMetadata $b, CacheableMetadata $expected): void {
    $cache_contexts_manager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    $this->assertEqualsCanonicalizing($expected, $a->merge($b));
  }

  /**
   * @covers ::addCacheableDependency
   * @dataProvider providerTestMerge
   *
   * This only tests at a high level, because it reuses existing logic. Detailed
   * tests exist for the existing logic:
   *
   * @see \Drupal\Tests\Core\Cache\CacheTest::testMergeTags()
   * @see \Drupal\Tests\Core\Cache\CacheTest::testMergeMaxAges()
   * @see \Drupal\Tests\Core\Cache\CacheContextsTest
   */
  public function testAddCacheableDependency(CacheableMetadata $a, CacheableMetadata $b, CacheableMetadata $expected): void {
    $cache_contexts_manager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    $this->assertEqualsCanonicalizing($expected, $a->addCacheableDependency($b));
  }

  /**
   * Provides test data for testMerge().
   *
   * @return array
   */
  public static function providerTestMerge() {
    return [
      // All empty.
      [(new CacheableMetadata()), (new CacheableMetadata()), (new CacheableMetadata())],
      // Cache contexts.
      [(new CacheableMetadata())->setCacheContexts(['foo']), (new CacheableMetadata())->setCacheContexts(['bar']), (new CacheableMetadata())->setCacheContexts(['bar', 'foo'])],
      // Cache tags.
      [(new CacheableMetadata())->setCacheTags(['foo']), (new CacheableMetadata())->setCacheTags(['bar']), (new CacheableMetadata())->setCacheTags(['foo', 'bar'])],
      // Cache max-ages.
      [(new CacheableMetadata())->setCacheMaxAge(60), (new CacheableMetadata())->setCacheMaxAge(Cache::PERMANENT), (new CacheableMetadata())->setCacheMaxAge(60)],
    ];
  }

  /**
   * This delegates to Cache::mergeTags(), so just a basic test.
   *
   * @covers ::addCacheTags
   */
  public function testAddCacheTags(): void {
    $metadata = new CacheableMetadata();
    $add_expected = [
      [[], []],
      [['foo:bar'], ['foo:bar']],
      [['foo:baz'], ['foo:bar', 'foo:baz']],
      [['axx:first', 'foo:baz'], ['foo:bar', 'foo:baz', 'axx:first']],
      [[], ['foo:bar', 'foo:baz', 'axx:first']],
      [['axx:first'], ['foo:bar', 'foo:baz', 'axx:first']],
    ];

    foreach ($add_expected as $row => $data) {
      [$add, $expected] = $data;
      $metadata->addCacheTags($add);
      $this->assertEquals($expected, $metadata->getCacheTags(), sprintf("Dataset in %d row failed on validation.", $row + 1));
    }
  }

  /**
   * Tests valid and invalid values as max age.
   *
   * @covers ::setCacheMaxAge
   * @dataProvider providerSetCacheMaxAge
   */
  public function testSetCacheMaxAge($data, $expect_exception): void {
    $metadata = new CacheableMetadata();
    if ($expect_exception) {
      $this->expectException('\InvalidArgumentException');
    }
    $metadata->setCacheMaxAge($data);
    $this->assertEquals($data, $metadata->getCacheMaxAge());
  }

  /**
   * Data provider for testSetCacheMaxAge.
   */
  public static function providerSetCacheMaxAge() {
    return [
      [0 , FALSE],
      ['http', TRUE],
      ['0', TRUE],
      [new \stdClass(), TRUE],
      [300, FALSE],
      [[], TRUE],
      [8.0, TRUE],
    ];
  }

  /**
   * @covers ::createFromRenderArray
   * @dataProvider providerTestCreateFromRenderArray
   */
  public function testCreateFromRenderArray(array $render_array, CacheableMetadata $expected): void {
    $this->assertEquals($expected, CacheableMetadata::createFromRenderArray($render_array));
  }

  /**
   * Provides test data for createFromRenderArray().
   *
   * @return array
   */
  public static function providerTestCreateFromRenderArray() {
    $data = [];

    $empty_metadata = new CacheableMetadata();
    $nonempty_metadata = new CacheableMetadata();
    $nonempty_metadata->setCacheContexts(['qux'])
      ->setCacheTags(['foo:bar']);

    $empty_render_array = [];
    $nonempty_render_array = [
      '#cache' => [
        'contexts' => ['qux'],
        'tags' => ['foo:bar'],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    $data[] = [$empty_render_array, $empty_metadata];
    $data[] = [$nonempty_render_array, $nonempty_metadata];

    return $data;
  }

  /**
   * @covers ::createFromObject
   * @dataProvider providerTestCreateFromObject
   */
  public function testCreateFromObject($object, CacheableMetadata $expected): void {
    $this->assertEquals($expected, CacheableMetadata::createFromObject($object));
  }

  /**
   * Provides test data for createFromObject().
   *
   * @return array
   */
  public static function providerTestCreateFromObject() {
    $data = [];

    $empty_metadata = new CacheableMetadata();
    $nonempty_metadata = new CacheableMetadata();
    $nonempty_metadata->setCacheContexts(['qux'])
      ->setCacheTags(['foo:bar'])
      ->setCacheMaxAge(600);
    $uncacheable_metadata = new CacheableMetadata();
    $uncacheable_metadata->setCacheMaxAge(0);

    $empty_cacheable_object = new TestCacheableDependency([], [], Cache::PERMANENT);
    $nonempty_cacheable_object = new TestCacheableDependency(['qux'], ['foo:bar'], 600);
    $uncacheable_object = new \stdClass();

    $data[] = [$empty_cacheable_object, $empty_metadata];
    $data[] = [$nonempty_cacheable_object, $nonempty_metadata];
    $data[] = [$uncacheable_object, $uncacheable_metadata];

    return $data;
  }

}
