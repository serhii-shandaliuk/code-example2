(function ($, Drupal) {
  "use strict";
  const selector = '.tabs-container .links .item-click';

  /**
   * Add new ReloadTabData command.
   */
  Drupal.AjaxCommands.prototype.ReloadTabData = function (ajax, response, status) {
    var hash = window.location.hash;
    let link = $('.tabs-container .links ' + hash);
    if (!hash) {
      link = $('.tabs-container .links .item-click').first().find('a');
    }
    link.trigger('click');
    $(selector).parent().removeClass('active');
    link.parent().addClass('active');
  }
})(jQuery, Drupal);
