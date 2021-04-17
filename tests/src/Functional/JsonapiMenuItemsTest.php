<?php

namespace Drupal\Tests\jsonapi_menu_items\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Menu Items functionality.
 *
 * @group jsonapi_menu_items
 */
class JsonapiMenuItemsTest extends BrowserTestBase {
  use JsonApiRequestTestTrait;

  /**
   * The account to use for authentication.
   *
   * @var null|\Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_menu_items',
    'menu_test',
    'jsonapi_menu_items_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->account = $this->createUser();
  }

  /**
   * Tests the JSON:API Menu Items resource.
   */
  public function testJsonapiMenuItemsResource() {
    $link_title = $this->randomMachineName();
    $content_link = $this->createMenuLink($link_title, 'jsonapi_menu_test.open');

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';

    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi-menu-items-test',
    ]);
    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode());

    $content = Json::decode($response->getBody());
    // There are 5 items in this menu - 4 from
    // jsonapi_menu_items_test.links.menu.yml and the content item created
    // above. One of the four in that file is disabled and should be filtered
    // out, another is not accesible to the current users. This leaves a total
    // of 3 items in the response.
    $this->assertCount(3, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/expected-items.json'), [
      '%uuid' => $content_link->uuid(),
      '%title' => $link_title,
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));
    $this->assertEqual($expected_items['data'], $content['data']);

    // Assert response is cached with appropriate cacheability metadata such
    // that re-saving the link with a new title yields the new title in a
    // subsequent request.
    $new_title = $this->randomMachineName();
    $content_link->title = $new_title;
    $content_link->save();
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    $match = array_filter($content['data'], function (array $item) use ($content_link) {
      return $item['id'] === 'menu_link_content:' . $content_link->uuid();
    });
    $this->assertEqual($new_title, reset($match)['attributes']['title']);

    // Add another link and ensue cacheability metadata ensures the new item
    // appears in a subsequent request.
    $content_link2 = $this->createMenuLink($link_title, 'jsonapi_menu_test.open');
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    $this->assertCount(4, $content['data']);
  }

  /**
   * Tests the JSON:API Menu Items resource with filter parameters
   * filter "parents"
   */
  public function testParametersParents() {
    $this->drupalLogin($this->account);

    $link_title = $this->randomMachineName();
    $content_link = $this->createMenuLink($link_title, 'jsonapi_menu_test.user.login');
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi-menu-items-test',
      'filter' => [
        'parents' =>  "jsonapi_menu_test.open,jsonapi_menu_test.user.login"
      ],
    ]);

    $response = $this->request('GET', $url, $request_options);
    self::assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    self::assertCount(2, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/parents-expected-items.json'), [
      '%uuid' => $content_link->uuid(),
      '%title' => $link_title,
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));
    // assertSame only compares that the variables refer to exactly the same object instance or not.
    // Obviously we don't have this case here. So assertSame will always fail.
    self::assertEquals($expected_items['data'], $content['data']);
  }

  /**
   * Tests the JSON:API Menu Items resource with filter parameters
   * filter "parent"
   */
  public function testParametersParent() {
    $this->drupalLogin($this->account);

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi-menu-items-test',
      'filter' => [
        'parent' =>  "jsonapi_menu_test.open"
      ],
    ]);

    $response = $this->request('GET', $url, $request_options);
    self::assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    self::assertCount(1, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/parent-expected-items.json'), [
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));

    // assertSame only compares that the variables refer to exactly the same object instance or not.
    // Obviously we don't have this case here. So assertSame will always fail.
    self::assertEquals($expected_items['data'], $content['data']);
  }

  /**
   * Tests the JSON:API Menu Items resource with filter parameters
   * filter "min_depth"
   */
  public function testParametersMinDepth() {
    $this->drupalLogin($this->account);

    $link_title = $this->randomMachineName();
    $content_link = $this->createMenuLink($link_title, 'jsonapi_menu_test.open');

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi-menu-items-test',
      'filter' => [
        'min_depth' =>  2
      ],
    ]);

    $response = $this->request('GET', $url, $request_options);
    self::assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    self::assertCount(2, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/min-depth-expected-items.json'), [
      '%uuid' => $content_link->uuid(),
      '%title' => $link_title,
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));

    // assertSame only compares that the variables refer to exactly the same object instance or not.
    // Obviously we don't have this case here. So assertSame will always fail.
    self::assertEquals($expected_items['data'], $content['data']);
  }

  /**
   * Tests the JSON:API Menu Items resource with filter parameters
   * filter "max_depth"
   */
  public function testParametersMaxDepth() {
    $link_title = $this->randomMachineName();
    $content_link = $this->createMenuLink($link_title, 'jsonapi_menu_test.open');

    //This link should be ignored.
    $content_link2 = $this->createMenuLink($link_title, 'jsonapi_menu_test.user.logout');

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi-menu-items-test',
      'filter' => [
        'max_depth' =>  2
      ],
    ]);

    $response = $this->request('GET', $url, $request_options);
    self::assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    self::assertCount(3, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/max-depth-expected-items.json'), [
      '%uuid' => $content_link->uuid(),
      '%title' => $link_title,
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));

    // assertSame only compares that the variables refer to exactly the same object instance or not.
    // Obviously we don't have this case here. So assertSame will always fail.
    self::assertEquals($expected_items['data'], $content['data']);
  }

  /**
   * Tests the JSON:API Menu Items resource with filter parameters
   * filter conditions: filter[conditions][provider][value]=jsonapi_menu_items_test
   */
  public function testParametersConditions() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi-menu-items-test',
      'filter' => [
        'conditions' =>  [
          'provider' => [
            'value' => 'jsonapi_menu_items_test'
          ]
        ]
      ],
    ]);

    $response = $this->request('GET', $url, $request_options);
    self::assertSame(200, $response->getStatusCode());
    $content = Json::decode($response->getBody());
    self::assertCount(2, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/conditions-expected-items.json'), [
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));

    // assertSame only compares that the variables refer to exactly the same object instance or not.
    // Obviously we don't have this case here. So assertSame will always fail.
    self::assertEquals($expected_items['data'], $content['data']);
  }

  protected function createMenuLink(string $title, string $parent) {
    $content_link = MenuLinkContent::create([
      'link' => ['uri' => 'route:menu_test.menu_callback_title'],
      'langcode' => 'en',
      'enabled' => 1,
      'title' => $title,
      'menu_name' => 'jsonapi-menu-items-test',
      'parent' => $parent,
      'weight' => 0,
    ]);
    $content_link->save();

    return $content_link;
  }

}
