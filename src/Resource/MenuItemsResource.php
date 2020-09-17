<?php

namespace Drupal\jsonapi_menu_items\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\GeneratedUrl;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\system\MenuInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for a collection of featured nodes.
 *
 * @internal
 */
final class MenuItemsResource extends ResourceBase {

  /**
   * A list of menu items.
   *
   * @var array
   */
  protected $menuItems = [];

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, MenuInterface $menu): ResourceResponse {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($menu);

    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks();
    $parameters->setMinDepth(0);

    $menu_tree = \Drupal::menuTree();
    $tree = $menu_tree->load($menu->id(), $parameters);

    if (empty($tree)) {
      $response = $this->createJsonapiResponse(new ResourceObjectData([]), $request, 403, []);
      $response->addCacheableDependency($cacheability);
      return $response;
    }

    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $this->getMenuItems($tree, $this->menuItems, $cacheability);

    $data = new ResourceObjectData($this->menuItems);
    $response = $this->createJsonapiResponse($data, $request, 200, [] /* , $pagination_links */);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    $resource_types = [];

    foreach (['menu_link_config', 'menu_link_content'] as $type) {
      $resource_type = $this->resourceTypeRepository->get($type, $type);
      if ($resource_type) {
        $resource_types[] = $resource_type;
      }
    }
    return $resource_types;
  }

  /**
   * Generate the menu items.
   *
   * @param array $tree
   *   The menu tree.
   * @param array $items
   *   The already created items.
   */
  protected function getMenuItems(array $tree, array &$items, CacheableMetadata $cache) {
    foreach ($tree as $menu_link) {
      $id = $menu_link->link->getPluginId();
      [$plugin] = explode(':', $id);

      switch ($plugin) {
        case 'menu_link_content':
        case 'menu_link_config':
          $resource_type = $this->resourceTypeRepository->get($plugin, $plugin);
          break;

        default:
          // @TODO - Use a custom resource type?
          $resource_type = $this->resourceTypeRepository->get('menu_link_content', 'menu_link_content');
      }

      $url = $menu_link->link->getUrlObject()->toString(TRUE);
      assert($url instanceof GeneratedUrl);
      $cache->addCacheableDependency($url);

      $fields = [
        'description' => $menu_link->link->getDescription(),
        'enabled' => $menu_link->link->isEnabled(),
        'expanded' => $menu_link->link->isExpanded(),
        'menu_name' => $menu_link->link->getMenuName(),
        'meta' => $menu_link->link->getMetaData(),
        'options' => $menu_link->link->getOptions(),
        'parent' => $menu_link->link->getParent(),
        'provider' => $menu_link->link->getProvider(),
        'route' => [
          'name' => $menu_link->link->getRouteName(),
          'parameters' => $menu_link->link->getRouteParameters(),
        ],
        // @todo Don't cast this to string once we've resolved
        // https://www.drupal.org/project/jsonapi_menu_items/issues/3171184
        'title' => (string) $menu_link->link->getTitle(),
        'url' => $url->getGeneratedUrl(),
        'weight' => $menu_link->link->getWeight(),
      ];
      $links = new LinkCollection([]);

      $resource_object_cacheability = new CacheableMetadata();
      $resource_object_cacheability->addCacheableDependency($menu_link->access);
      $resource_object_cacheability->addCacheableDependency($cache);
      $items[$id] = new ResourceObject($resource_object_cacheability, $resource_type, $id, NULL, $fields, $links);

      if ($menu_link->subtree) {
        $this->getMenuItems($menu_link->subtree, $items, $cache);
      }
    }
  }

}
