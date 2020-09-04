<?php

namespace Drupal\Tests\jsonapi_menu_items\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Menu Items functonality.
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
  protected static $modules = ['jsonapi_menu_items', 'menu_test'];

  /**
   * Tests the JSON:API Menu Items resource.
   */
  public function testJsonapiMenuItemsResource() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';

    $url = Url::fromRoute('jsonapi_menu_items.menu', ['menu' => 'original']);
    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode());
  }

}
