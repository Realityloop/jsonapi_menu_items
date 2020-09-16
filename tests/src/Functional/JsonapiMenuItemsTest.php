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
   * Tests the JSON:API Menu Items resource.
   */
  public function testJsonapiMenuItemsResource() {
    $link_title = $this->randomMachineName();
    $content_link = MenuLinkContent::create([
      'link' => ['uri' => 'route:menu_test.menu_callback_title'],
      'langcode' => 'en',
      'enabled' => 1,
      'title' => $link_title,
      'menu_name' => 'jsonapi_menu_items_test',
      'parent' => 'jsonapi_menu_test.open',
      'weight' => 0,
    ]);

    $content_link->save();

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';

    $url = Url::fromRoute('jsonapi_menu_items.menu', [
      'menu' => 'jsonapi_menu_items_test',
    ]);
    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode());

    $content = Json::decode($response->getBody());
    // There are 5 items in this menu - 4 from
    // jsonapi_menu_items_test.links.menu.yml and the content item created
    // above. One of the four in that file is disabled and should be filtered
    // out, leaving a total of 4.
    $this->assertCount(4, $content['data']);

    $expected_items = Json::decode(strtr(file_get_contents(dirname(__DIR__, 2) . '/fixtures/expected-items.json'), [
      '%uuid' => $content_link->uuid(),
      '%title' => $link_title,
      '%base_path' => Url::fromRoute('<front>')->toString(),
    ]));
    $this->assertSame($expected_items['data'], $content['data']);
  }

}
