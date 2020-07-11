<?php

namespace Drupal\jsonapi_menu_items\Resource;

use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for a collection of featured nodes.
 *
 * @internal
 */
final class MenuItemsResource extends ResourceBase {

  protected $resourceType;

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
  public function process(Request $request): ResourceResponse {
    $menu_name = $request->get('menu');

    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks();

    $menu_tree = \Drupal::menuTree();
    $tree = $menu_tree->load($menu_name, $parameters);

    if (empty($tree)) {
      return $this->createJsonapiResponse(new ResourceObjectData([]), $request, 403, []);
    }

    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $items = [];
    foreach ($tree as $menu_link) {
      $id = $menu_link->link->getPluginId();
      list($plugin, $plugin_id) = explode(':', $id);
      $resource_type = $this->resourceTypeRepository->get('menu_link_content', 'menu_link_content');

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

      $fields = [
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
        'url' => $url->getGeneratedUrl(),
        'weight' => $menu_link->link->getWeight(),
      ];
      $links = new LinkCollection([]);

      $items[$id] = new ResourceObject($menu_link->access, $resource_type, $id, NULL, $fields, $links);
    }

    $data = new ResourceObjectData($items);
    $response = $this->createJsonapiResponse($data, $request, 200, [] /* , $pagination_links */);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [
      // @TODO - Check if module/resource exists.
      $this->resourceTypeRepository->get('menu_link_config', 'menu_link_config'),
      $this->resourceTypeRepository->get('menu_link_content', 'menu_link_content'),
    ];
  }
}
