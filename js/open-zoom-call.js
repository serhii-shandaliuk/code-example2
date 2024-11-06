/**
 * @file
 * Close popup functionality.
 */

(function ($, Drupal) {
  "use strict";
  const selector = '.page-node-type-lesson article .zoom-meeting .join-meeting';

  $(document).ready(function() {
    console.log($(selector).attr('target'));
    if ($(selector).length) {
      $(selector).trigger('click');
      if ($(selector).attr('target')) {
        window.open($(selector).attr('href'), '_blank', "height=700,width=1200").focus();
      }
    }
  });

})(jQuery, Drupal);
