<?php

namespace Drupal\slp_school\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Site\Settings;
use Drupal\slp_school\SchoolManagerInterface;
use Drupal\system\Controller\EntityAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a route controller for entity autocomplete form elements.
 */
class SlpAutocompleteDeluxeController extends EntityAutocompleteController {

  /**
   * The autocomplete matcher for entity references.
   *
   * @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface
   */
  protected $matcher;

  /**
   * The key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * The school manager.
   *
   * @var \Drupal\slp_school\SchoolManagerInterface
   */
  protected SchoolManagerInterface $schoolManager;

  /**
   * Constructs an EntityAutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $matcher
   *   The autocomplete matcher for entity references.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value
   *   The key value factory.
   * @param \Drupal\slp_school\SchoolManagerInterface $school_manager
   *   The school manager.
 */
  public function __construct(EntityAutocompleteMatcherInterface $matcher, KeyValueStoreInterface $key_value, SchoolManagerInterface $school_manager) {
    parent::__construct($matcher, $key_value);
    $this->schoolManager = $school_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.autocomplete_matcher'),
      $container->get('keyvalue')->get('entity_autocomplete'),
      $container->get('slp_school.school_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function handleAutocomplete(Request $request, $target_type, $selection_handler, $selection_settings_key) {
    $matches = [];
    // Get the typed string from the URL, if it exists.
    $input = trim($request->query->get('q'));
    if (!empty($input)) {
      $typed_string = Tags::explode($input);
      $typed_string = array_pop($typed_string);
    }
    else {
      // Select without entering something.
      $typed_string = '';
    }

    // Selection settings are passed in as a hashed key of a serialized array
    // stored in the key/value store.
    $selection_settings = $this->keyValue->get($selection_settings_key, FALSE);
    if ($selection_settings !== FALSE) {
      $selection_settings_hash = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
      if ($selection_settings_hash !== $selection_settings_key) {
        // Disallow access when the selection settings hash does not match the
        // passed-in key.
        throw new AccessDeniedHttpException('Invalid selection settings key.');
      }
    }
    else {
      // Disallow access when the selection settings key is not found in the
      // key/value store.
      throw new AccessDeniedHttpException();
    }

    $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, $typed_string);

    $items = [];
    $cuid = $this->currentUser()->id();
    if ($target_type === 'user') {
      $user_storage = $this->entityTypeManager()->getStorage('user');
      $node_storage = $this->entityTypeManager()->getStorage('node');
      $current_user = $user_storage->load($cuid);
      $students = $this->schoolManager->getActiveStudents();
      if ($request->attributes->get('is_teacher')) {
        $students = $this->schoolManager->getActiveTeachers();
      }
      $nid = $request->attributes->get('node');
      if ($nid) {
        foreach ($students as $key => $student) {
          $student = $user_storage->load($student);
          $lessons = $student->get('field_lessons')->getValue();
          $lessons = array_column($lessons, 'target_id');

          $courses = $student->get('field_courses')->getValue();
          $more_lessons = [];
          if ($courses) {
            $courses = array_column($courses, 'target_id');
            $courses = array_unique($courses);
            foreach ($courses as $course) {
              $properties = ['field_course' => $course];
              $curses_lessons = $node_storage->loadByProperties($properties);
              if (empty($curses_lessons)) {
                continue;
              }
              $curses_lessons = array_keys($curses_lessons);
              $more_lessons = array_merge($more_lessons, $curses_lessons);
            }

            $lessons = array_merge($more_lessons, $lessons);
          }

          if (!in_array($nid, $lessons)) {
            unset($students[$key]);
          }
        }
      }
    }
    if ($target_type === 'commerce_product') {
      $product_storage = $this->entityTypeManager()->getStorage('commerce_product');
    }
    if ($target_type === 'node') {
      $current_user = $this->entityTypeManager()->getStorage('user')->load($cuid);
      $node_storage = $this->entityTypeManager()->getStorage('node');
    }
    if ($target_type === 'slp_group') {
      $uid = $request->attributes->get('node');
      $groups = $this->schoolManager->getSlpGroups($uid);
    }
    foreach ($matches as $item) {
      preg_match('/\(([^\)]*)\)/', $item['value'], $id_matches);
      if (!$id_matches) {
        continue;
      }
      [,$id] = $id_matches;

      if ($target_type === 'user') {
        if ($id === 0) {
          continue;
        }

        if (in_array($id, $students)) {
          $items[$item['value']] = $item['value'];
        }
      }

      if ($target_type === 'commerce_product') {
        $product = $product_storage->load($id);
        if ($product) {
          $author = $product->get('uid')->target_id;
          if ($author === $cuid) {
            $items[$item['value']] = $item['value'];
          }
        }
      }
      if ($target_type === 'node') {
        $node = $node_storage->load($id);
        if ($node) {
          $author = $node->get('uid')->target_id;
          if (in_array('slp_teacher', $this->currentUser()->getRoles())) {
            $lessons = $current_user?->get('field_lessons')->getValue();
            $lessons = array_column($lessons, 'target_id');
            if (in_array($id, $lessons)) {
              $items[$item['value']] = $item['value'];
            }
          }
          elseif ($author === $cuid) {
            $items[$item['value']] = $item['value'];
          }
        }
      }
      if ($target_type === 'slp_group') {
        if (in_array($id, $groups)) {
          $items[$item['value']] = $item['value'];
        }
      }

      if (count($items) >= 10) {
        break;
      }
    }

    $matches = $items;

    return new JsonResponse($matches);
  }

}
