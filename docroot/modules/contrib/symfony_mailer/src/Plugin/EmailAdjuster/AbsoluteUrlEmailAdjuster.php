<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Component\Utility\Html;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the URL to absolute Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "mailer_url_to_absolute",
 *   label = @Translation("URL to absolute"),
 *   description = @Translation("Convert URLs to absolute."),
 *   weight = 700,
 * )
 */
class AbsoluteUrlEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email) {
    $email->setHtmlBody(Html::transformRootRelativeUrlsToAbsolute($email->getHtmlBody(), \Drupal::request()->getSchemeAndHttpHost()));
  }

}
