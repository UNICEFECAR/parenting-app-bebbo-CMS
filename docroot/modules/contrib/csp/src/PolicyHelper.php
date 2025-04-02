<?php

declare(strict_types=1);

namespace Drupal\csp;

/**
 * A helper service for modifying Csp policy objects.
 */
class PolicyHelper {

  /**
   * Construct a PolicyHelper.
   *
   * @param Nonce $nonce
   *   The Nonce service.
   */
  public function __construct(
    private Nonce $nonce,
  ) {

  }

  /**
   * Add a script or style nonce.
   *
   * @param \Drupal\csp\Csp $policy
   *   The policy to alter.
   * @param "script"|"style" $directive
   *   The directive to alter.
   * @param array|string $fallback
   *   Values to add to the directive if the nonce cannot be applied.
   * @param string|null $value
   *   The nonce to add, or NULL to use the nonce service.
   */
  public function appendNonce(Csp $policy, string $directive, array|string $fallback, ?string $value = NULL): void {
    if ($directive != 'script' && $directive != 'style') {
      throw new \InvalidArgumentException("Directive must be 'script' or 'style'");
    }
    if (!is_null($value) && !preg_match("<^'nonce-([-A-Za-z0-9+/_]+={0,2})'$>i", $value)) {
      throw new \InvalidArgumentException("Value must be in the format \"'nonce-{base64-value}'\"");
    }

    $directive = $directive . '-src-elem';

    if (empty($value)) {
      $value = $this->nonce->getSource();
    }

    $this->appendToUnsafeDisabler($policy, $directive, $fallback, $value);
  }

  /**
   * Add a script or style hash.
   *
   * @param \Drupal\csp\Csp $policy
   *   The policy to alter.
   * @param "script"|"style" $directive
   *   The directive to alter.
   * @param "elem"|"attr" $type
   *   Whether the hash represents an element or attribute.
   * @param array|string $fallback
   *   Values to add to the directive if the nonce cannot be applied.
   * @param string $value
   *   The hash value to add.
   */
  public function appendHash(Csp $policy, string $directive, string $type, array|string $fallback, string $value): void {
    if ($directive != 'script' && $directive != 'style') {
      throw new \InvalidArgumentException("Directive must be 'script' or 'style'");
    }
    if ($type != 'elem' && $type != 'attr') {
      throw new \InvalidArgumentException("Type must be 'elem' or 'attr'");
    }
    if (!preg_match("<^'(" . implode('|', Csp::HASH_ALGORITHMS) . ")-([-A-Za-z0-9+/_]+={0,2})'$>i", $value)) {
      throw new \InvalidArgumentException("Value must be in the format \"'{hash-algorithm}-{base64-value}'\"");
    }

    // Make sure the directive for the other type has a value without the hash.
    $otherType = $type == 'elem' ? 'attr' : 'elem';
    $policy->fallbackAwareAppendIfEnabled($directive . '-src-' . $otherType, []);

    if ($type === 'attr') {
      $value = [$value, Csp::POLICY_UNSAFE_HASHES];
    }

    $this->appendToUnsafeDisabler($policy, $directive . '-src-' . $type, $fallback, $value);
  }

  /**
   * Add a value to directives if 'unsafe-inline' is not required.
   *
   * @param \Drupal\csp\Csp $policy
   *   The policy to alter.
   * @param string $directive
   *   The directive name.
   * @param array|string $fallback
   *   Value to add to directives if hash or nonce cannot be applied.
   * @param array|string $value
   *   The nonce or hash to add.
   */
  private function appendToUnsafeDisabler(Csp $policy, string $directive, array|string $fallback, array|string $value): void {
    $directiveList = $policy::getDirectiveFallbackList($directive);
    array_unshift($directiveList, $directive);

    // Do not modify the policy if all directives are not set.
    $policyEmpty = TRUE;
    foreach ($directiveList as $fallbackDirective) {
      if ($policy->hasDirective($fallbackDirective)) {
        $policyEmpty = FALSE;
        break;
      }
    }
    if ($policyEmpty) {
      return;
    }

    $appendTo = array_diff($directiveList, ['default-src']);
    foreach ($appendTo as $appendDirective) {
      if (!$policy->hasDirective($appendDirective)) {
        $policy->fallbackAwareAppendIfEnabled($appendDirective, []);
      }
      $policy->appendDirective(
        $appendDirective,
        self::requiresUnsafeInline($policy->getDirective($appendDirective))
          ? $fallback
          : $value
      );
    }
  }

  /**
   * Helper to check if directive contains a required 'unsafe-inline'.
   *
   * @param string[] $directiveValues
   *   An array of directive values.
   *
   * @return bool
   *   TRUE if the directive values contain an 'unsafe-inline' that is not
   *   disabled by another source.
   */
  private static function requiresUnsafeInline(array $directiveValues): bool {
    return in_array(Csp::POLICY_UNSAFE_INLINE, $directiveValues)
      && !in_array(Csp::POLICY_STRICT_DYNAMIC, $directiveValues)
      && !self::hasHashOrNonce($directiveValues);
  }

  /**
   * Helper to check if a directive value contains a hash or nonce.
   *
   * @param string[] $directiveValues
   *   An array of directive sources.
   *
   * @return bool
   *   TRUE if the directive contains a hash or nonce.
   */
  private static function hasHashOrNonce(array $directiveValues): bool {
    foreach ($directiveValues as $value) {
      if (preg_match("<^'(" . implode('|', Csp::HASH_ALGORITHMS) . "|nonce)-.*?'>", $value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Require 'unsafe-inline' for a directive.
   *
   * This removes values like nonce-source and hash-source that disable
   * 'unsafe-inline' on a directive to ensure it applies.
   * This will block functionality for a nonce or hash that is already present
   * on the directive if it requires a fallback other than 'unsafe-inline', so
   * this method should be called prior to any features that add a nonce or
   * hash so they can properly append their fallback values.
   *
   * @param \Drupal\csp\Csp $policy
   *   The policy to alter.
   * @param "script"|"style" $directive
   *   The directive to alter.
   * @param "elem"|"attr" $type
   *   The sub-directive to alter.
   */
  public function requireUnsafeInline(Csp $policy, string $directive, string $type): void {
    if ($directive != 'script' && $directive != 'style') {
      throw new \InvalidArgumentException("Directive must be 'script' or 'style'");
    }
    if ($type != 'elem' && $type != 'attr') {
      throw new \InvalidArgumentException("Type must be 'elem' or 'attr'");
    }

    $subdirective = $directive . '-src-' . $type;
    $directiveList = $policy::getDirectiveFallbackList($subdirective);
    array_unshift($directiveList, $subdirective);

    // Don't modify the policy if all directives are not set.
    $policyEmpty = TRUE;
    foreach ($directiveList as $fallbackDirective) {
      if ($policy->hasDirective($fallbackDirective)) {
        $policyEmpty = FALSE;
        break;
      }
    }
    if ($policyEmpty) {
      return;
    }

    $otherSubdirectiveType = ($type == 'elem' ? 'attr' : 'elem');
    $otherSubdirective = $directive . '-src-' . $otherSubdirectiveType;
    if (!$policy->hasDirective($otherSubdirective)) {
      // Set the other directive type to current fallback value so that it
      // doesn't fall back to allow 'unsafe-inline' after changes.
      $policy->fallbackAwareAppendIfEnabled($otherSubdirective, []);

      if ($otherSubdirectiveType == 'attr') {
        // Remove from attribute directive:
        // - any nonces.
        // - any hashes if 'unsafe-hashes' is not present.
        $attributeValue = $policy->getDirective($otherSubdirective);
        $attributeValueHasUnsafeHashes = in_array(Csp::POLICY_UNSAFE_HASHES, $attributeValue);
        $policy->setDirective(
          $otherSubdirective,
          array_filter(
            $attributeValue,
            function ($value) use ($attributeValueHasUnsafeHashes) {
              return !str_starts_with($value, "'nonce-")
                && (
                  $attributeValueHasUnsafeHashes
                  || !preg_match("<^'(" . implode('|', Csp::HASH_ALGORITHMS) . ")-.*?'>", $value)
                );
            }
          )
        );
      }
      else {
        // Remove 'unsafe-hashes' from element directive.
        $policy->setDirective(
          $otherSubdirective,
          array_diff($policy->getDirective($otherSubdirective), [Csp::POLICY_UNSAFE_HASHES])
        );
      }
    }

    $requireFor = array_diff($directiveList, ['default-src']);
    foreach ($requireFor as $requireForDirective) {
      if (!$policy->hasDirective($requireForDirective)) {
        $policy->fallbackAwareAppendIfEnabled($requireForDirective, []);
      }

      // Remove any sources that disable 'unsafe-inline' from the directive.
      $cleanedValue = array_filter(
        $policy->getDirective($requireForDirective),
        function ($value) {
          return (
            !self::hasHashOrNonce([$value])
            && $value !== Csp::POLICY_STRICT_DYNAMIC
            // 'unsafe-hashes' is not needed if 'unsafe-inline' present.
            && $value !== Csp::POLICY_UNSAFE_HASHES
          );
        }
      );
      $cleanedValue[] = Csp::POLICY_UNSAFE_INLINE;

      $policy->setDirective($requireForDirective, $cleanedValue);
    }
  }

}
