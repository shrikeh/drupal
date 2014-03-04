<?php
/**
 * @file
 * Contains \Drupal\Core\Access\TokenGeneratorInterface.
 */

namespace Drupal\Core\Access;


interface TokenGeneratorInterface {

    /**
     * Generates a token based on $value, the user session, and the private key.
     *
     * The generated token is based on the session ID of the current user. Normally,
     * anonymous users do not have a session, so the generated token will be
     * different on every page request. To generate a token for users without a
     * session, manually start a session prior to calling this function.
     *
     * @param string $value
     *   (optional) An additional value to base the token on.
     *
     * @return string
     *   A URL-safe token for validation
     */
    public function get($value = '');

    /**
     * Validates a token based on $value, the user session, and the private key.
     *
     * @param string $token
     *   The token to be validated.
     * @param string $value
     *   (optional) An additional value to base the token on.
     * @param bool $skip_anonymous
     *   (optional) Set to TRUE to skip token validation for anonymous users.
     *
     * @return bool
     *   TRUE for a valid token, FALSE for an invalid token. When $skip_anonymous
     *   is TRUE, the return value will always be TRUE for anonymous users.
     */
    public function validate($token, $value = '', $skip_anonymous = FALSE);

} 
