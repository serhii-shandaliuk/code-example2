<?php

namespace Drupal\slp_school;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\user\Entity\User;

/**
 * Interface SchoolManagerInterface.
 *
 * @package Drupal\slp_school
 */
interface SchoolManagerInterface {

  /**
   * Role gives access for all roles to platform.
   */
  const ACCESS_ROLES = [
    'student' => 'slp_student',
    'teacher' => 'teacher_paid',
    'author' => 'author_paid',
    'director' => 'director_paid',
  ];

  /**
   * Role revoke access for all roles to platform.
   */
  const REVOKE_ROLES = [
    'student' => 'slp_student',
    'teacher' => 'teacher_unpaid',
    'author' => 'author_unpaid',
    'director' => 'director_unpaid',
  ];

  /**
   * School user roles.
   */
  public const SCHOOL_ROLES = [
    'student' => 'Student',
    'teacher' => 'Teacher',
    'author' => 'Author',
    'director' => 'School',
  ];

  /**
   * Node id to be cloned for teacher on first access give.
   */
  const LESSON_NODE_ID_FOR_CLONE = 43;

  /**
   * Node id to be redirected from user page.
   */
  const LESSON_NODE_ID_FOR_REDIRECT = 10;

  /**
   * Product id to be cloned for teacher on first access give.
   */
  const COURSE_PRODUCT_ID_FOR_CLONE = 10;

  /**
   * Paragraph id to be redirected to.
   */
  const PARAGRAPH_GROUPS_TAB = 16128;

  /**
   * Paragraph id to be redirected to.
   */
  const PARAGRAPH_HOMEWORK_TAB = 9836;

  /**
   * Revokes access for user.
   *
   * @param int $uid
   *  User uid.
   * @param string|null $role
   *  User role.
   * @param string|null $with_references
   *  With references bool.
   */
  public function revokeTeacherAccess(int $uid = 0, ?string $role = 'teacher', bool $with_references = TRUE): void;

  /**
   * Gives access for user.
   *
   * @param int $uid
   *  User uid.
   * @param string|null $role
   *  User role.
   * @param string|null $school
   *  School name for director.
   * @param string|null $time
   *  Time access provided for.
   * @param string|null $today
   *  Time access provided from.
   * @param bool|null $save_due_date
   *  If due date should be changed.
   */
  public function giveTeacherAccess(int $uid = 0, ?string $role = 'teacher', ?string $school = NULL, ?string $time = '+1 day', ?string $today = '+1 day', bool $save_due_date = FALSE): void;

  /**
   * Clone node with all references.
   *
   * @param $original_entity
   * Original entity.
   * @param User $user
   * User entity.
   * @param array $additional_settings
   * Additional settings.
   *
   * @return int
   *   Entity id.
   */
  public function cloneEntity($original_entity, User $user, array $additional_settings = []): int;

  /**
   * Returns SLP groups.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Groups array.
   */
  public function getSlpGroups(int $uid = NULL): array;

  /**
   * Returns teachers.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Users array.
   */
  public function getTeachers(int $uid = NULL): array;

  /**
   * Returns active teachers.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Users array.
   */
  public function getActiveTeachers(int $uid = NULL): array;

  /**
   * Returns all students.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Users array.
   */
  public function getStudents(int $uid = NULL): array;

  /**
   * Returns active students.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Users array.
   */
  public function getActiveStudents(int $uid = NULL): array;

  /**
   * Returns lessons.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Nodes array.
   */
  public function getLessons(int $uid = NULL): array;

  /**
   * Returns courses.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Products array.
   */
  public function getCourses(int $uid = NULL): array;

  /**
   * Returns events.
   *
   * @param int|null $uid
   *   User id.
   *
   * @return array
   *   Events array.
   */
  public function getEvents(int $uid = NULL): array;

  /**
   * Returns if current user is author of the group.
   *
   * @param int $gid
   * Group id.
   *
   * @return bool
   *   Is author.
   */
  public function isAuthorOfTheGroup(int $gid): bool;

  /**
   * Returns if current user is author of the student.
   *
   * @param int|null $uid
   * User id.
   *
   * @return bool
   *   Is author.
   */
  public function isAuthorOfTheStudent(int $uid = NULL): bool;

  /**
   * Returns school roles.
   *
   * @return array
   *   School roles.
   */
  public function getSchoolRoles(): array;

  /**
   * Returns variation build.
   *
   * @param ProductVariation $variation
   *   Product variation.
   *
   * @return array
   *   Variation build.
   */
  public function getVariationBuild(ProductVariation $variation): array;

}
