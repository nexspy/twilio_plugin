(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$('#btn-apply-date').click(function(e) {
		e.preventDefault();

		var link = $('#link-twilio-settings').attr('href');
		var date = $('#txt-from-date').val();
		if (date.length) {
			window.location.href = link + '&from=' + date;
		}
	});

	var items = $("#tbl-sms .sms");
	var numItems = items.length;
	var perPage = 10;

	$('.pagination-page').pagination({
		items: numItems,
		itemsOnPage: perPage,
		cssStyle: 'light-theme',
		onPageClick: function(pageNumber) {
			// We need to show and hide `tr`s appropriately.
			var showFrom = perPage * (pageNumber - 1);
			var showTo = parseInt(showFrom) + parseInt(perPage);

			// We'll first hide everything...
			items.hide()
			// ... and then only show the appropriate rows.
				.slice(showFrom, showTo).show();
		}
	});


})( jQuery );
