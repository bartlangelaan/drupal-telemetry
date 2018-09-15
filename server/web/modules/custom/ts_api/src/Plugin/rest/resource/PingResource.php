<?php

namespace Drupal\ts_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a NM Activities API / RSS Feed
 *
 * @RestResource(
 *   id = "ts_api_ping",
 *   label = "Telemetry API - Ping",
 *   uri_paths = {
 *     "canonical" = "/api/ping/{uuid}",
 *     "https://www.drupal.org/link-relations/create" = "/api/ping"
 *   }
 * )
 */
class PingResource extends ResourceBase {

  public function get($uuid) {
    return new ResourceResponse(['message' => "No data for ping with uuid $uuid found."], 404);
  }

  public function post(array $body) {
    // These properties should always be present. If they are not, the request
    // is invalid.
    foreach (['site', 'ping', 'timestamp'] as $required_string) {
      if (empty($body[$required_string]) || !is_string($body[$required_string])) {
        return new ResourceResponse([
          'message' => "The property '$required_string' wasn't found in the request."
        ], 400);
      }
    }
    if (empty($body['data']) || !is_array($body['data'])) {
      return new ResourceResponse([
        'message' => "The property 'data' wasn't found in the request."
      ], 400);
    }

    // Some properties are being hashed, so these properties aren't exposed
    // anywhere.
    foreach (['site', 'ping'] as $hashed) {
      // Todo: Provide a good way to hash this stuff.
      $body[$hashed] = md5($hashed);
    }

    // If there is data that can't be saved, we still want to save the data from
    // other projects. We save all errors in a variable, so we return them in
    // the response.
    $errors = [];

    // This is the part where data is actually saved. The data for all projects
    // are stored as separate webform entries.
    foreach ($body['data'] as $project_id => $project_data) {
      // Todo: Create a webform entry with the project data.
      $errors[] = [
        'project' => $project_id,
        'message' => "No webform found for project $project_id"
      ];
    }

    return new ResourceResponse([
      'site' => $body['site'],
      'ping' => $body['ping'],
      'errors' => $errors,
    ], 201);
  }
}
