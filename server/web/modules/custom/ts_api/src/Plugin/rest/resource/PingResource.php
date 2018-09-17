<?php

namespace Drupal\ts_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionForm;

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
    foreach ($body['data'] as $project_id => $project_data) {
      if (!is_array($project_data)) {
        return new ResourceResponse([
          'message' => "The property '$project_id' in 'data' should be an array."
        ], 400);
      }
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

    // All the IDs of data that was saved is added to this array.
    $saved_submissions = [];

    // This is the part where data is actually saved. The data for all projects
    // are stored as separate webform entries.
    foreach ($body['data'] as $project_id => $project_data) {

      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = \Drupal::entityTypeManager()
        ->getStorage('webform_submission')
        ->create([
          'webform_id' => $project_id,
          'data' => $project_data
        ]);

      // Try to submit the webform.
      $errors_or_submission = WebformSubmissionForm::submitWebformSubmission($webform_submission);

      // If the submission failed, an array of errors is returned. We add all
      // errors to the $errors array.
      if (is_array($errors_or_submission)) {
        foreach($errors_or_submission as $project_error) {
          $errors[] = [
            'project' => $project_id,
            'message' => $project_error,
          ];
        }
      }
      // If the submission was saved successfully, we add the uuid of the
      // created submission to the $saved_submissions array.
      elseif ($errors_or_submission instanceof WebformSubmission) {
        $saved_submissions[] = $errors_or_submission->uuid();
      }
      else {
        throw new \Exception('Unexpected response from the webform module.');
      }
    }

    // We return this data to the client, to let them know the data was saved
    // successfully.
    return new ResourceResponse([
      'site' => $body['site'],
      'ping' => $body['ping'],
      'errors' => $errors,
      'saved' => $saved_submissions,
    ], 201);
  }
}
