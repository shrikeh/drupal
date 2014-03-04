<?php

/**
 * @file
 * Contains \Drupal\Core\Access\CsrfTokenGenerator.
 */

namespace Drupal\Core\Access;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;

/**
 * Generates and validates CSRF tokens.
 * The generated token is based on the session ID of the current user. Normally,
 * anonymous users do not have a session, so the generated token will be
 * different on every page request. To generate a token for users without a
 * session, manually start a session prior to calling this function.
 *
 * @see \Drupal\Tests\Core\Access\CsrfTokenGeneratorTest
 */
class CsrfTokenGenerator implements TokenGeneratorInterface {

  /**
   * The private key service.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs the token generator.
   *
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key service.
   */
  public function __construct(PrivateKey $private_key) {
    $this->privateKey = $private_key;
  }

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $current_user
   *  The current user service.
   */
  public function setCurrentUser(AccountInterface $current_user = NULL) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function get($value = '') {
    /*
     * A 43-character URL-safe token for validation, based on the user session
     * ID, the hash salt provided by drupal_get_hash_salt(), and the
     * 'drupal_private_key' configuration variable.
     */
    return Crypt::hmacBase64($value, session_id() . $this->privateKey->get() . drupal_get_hash_salt());
  }

  /**
   * {@inheritdoc}
   */
  public function validate($token, $value = '', $skip_anonymous = FALSE) {
   // Validates a token based on $value, the user session, and the private key.
    return ($skip_anonymous && $this->currentUser->isAnonymous()) || ($token === $this->get($value));
  }
}
