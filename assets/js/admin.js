/**
 * SILC Fake Order Generator - Admin JavaScript
 *
 * Manages a batch queue for generating fake orders, showing a progress bar.
 */
(function ($) {
	'use strict';

	var SILC_FOG = {
		/** @type {bool} Whether generation is currently running. */
		running: false,

		/** @type {bool} Whether user requested cancellation. */
		cancelled: false,

		/** @type {int} Total number of orders to generate. */
		totalOrders: 0,

		/** @type {int} Orders created so far across all batches. */
		createdSoFar: 0,

		/** @type {object} Form parameters for the AJAX requests. */
		params: {},

		/**
		 * Initialize the admin JS.
		 */
		init: function () {
			$('#silc-fog-form').on('submit', this.onStart.bind(this));
			$('#silc-fog-cancel').on('click', this.onCancel.bind(this));
		},

		/**
		 * Handle the "Generate Orders" form submission.
		 *
		 * @param {Event} e
		 */
		onStart: function (e) {
			e.preventDefault();

			if (this.running) {
				return;
			}

			// Gather and validate form data.
			var startDate = $('#fog_start_date').val();
			var endDate = $('#fog_end_date').val();
			var numOrders = parseInt($('#fog_num_orders').val(), 10);
			var minItems = parseInt($('#fog_min_items').val(), 10);
			var maxItems = parseInt($('#fog_max_items').val(), 10);

			if (!startDate || !endDate) {
				alert('Please enter both start and end dates.');
				return;
			}

			if (new Date(startDate) >= new Date(endDate)) {
				alert('Start date must be before end date.');
				return;
			}

			if (isNaN(numOrders) || numOrders < 1) {
				alert('Number of orders must be at least 1.');
				return;
			}

			if (isNaN(minItems) || minItems < 1 || minItems > 5) {
				alert('Min items per order must be between 1 and 5.');
				return;
			}

			if (isNaN(maxItems) || maxItems < minItems || maxItems > 5) {
				alert('Max items must be between min items and 5.');
				return;
			}

			// Collect selected shipping methods.
			var shippingMethods = [];
			$('input[name="fog_shipping_methods[]"]:checked').each(function () {
				shippingMethods.push($(this).val());
			});

			// Collect selected payment methods.
			var paymentMethods = [];
			$('input[name="fog_payment_methods[]"]:checked').each(function () {
				paymentMethods.push($(this).val());
			});

			// Collect selected coupons.
			var coupons = [];
			$('input[name="fog_coupons[]"]:checked').each(function () {
				coupons.push($(this).val());
			});

			// Collect selected order statuses.
			var statuses = [];
			$('input[name="fog_statuses[]"]:checked').each(function () {
				statuses.push($(this).val());
			});

			// Store params.
			this.params = {
				start_date: startDate,
				end_date: endDate,
				num_orders: numOrders,
				min_items: minItems,
				max_items: maxItems,
				shipping_methods: shippingMethods,
				payment_methods: paymentMethods,
				coupons: coupons,
				statuses: statuses
			};

			this.totalOrders = numOrders;
			this.createdSoFar = 0;
			this.cancelled = false;
			this.running = true;

			// Show progress area.
			$('#silc-fog-form').hide();
			$('#silc-fog-progress-wrap').show();
			this.updateProgress(0, 'Starting...');
			this.clearLog();

			// Start the batch queue.
			this.processNextBatch(0);
		},

		/**
		 * Handle the "Cancel" button.
		 */
		onCancel: function () {
			if (this.running) {
				this.cancelled = true;
				this.updateProgressStatus('Cancelling after current batch...');
				$('#silc-fog-cancel').prop('disabled', true);
			}
		},

		/**
		 * Process the next batch of orders.
		 *
		 * @param {int} batchIndex Current batch index.
		 */
		processNextBatch: function (batchIndex) {
			if (this.cancelled) {
				this.running = false;
				this.updateProgressStatus('Cancelled by user.');
				$('#silc-fog-cancel').prop('disabled', false);
				$('#silc-fog-form').show();
				return;
			}

			var self = this;
			var data = {
				action: 'silc_fog_generate_batch',
				nonce: silc_fog_data.nonce,
				start_date: this.params.start_date,
				end_date: this.params.end_date,
				num_orders: this.params.num_orders,
				batch_index: batchIndex,
				min_items: this.params.min_items,
				max_items: this.params.max_items,
				shipping_methods: this.params.shipping_methods,
				payment_methods: this.params.payment_methods,
				coupons: this.params.coupons,
				statuses: this.params.statuses
			};

			$.post(silc_fog_data.ajax_url, data, function (response) {
				if (!response.success) {
					self.addLogEntry('error', 'Batch failed: ' + (response.data.message || 'Unknown error'));
					self.running = false;
					self.updateProgressStatus('Error occurred. Check log for details.');
					$('#silc-fog-cancel').prop('disabled', false);
					$('#silc-fog-form').show();
					return;
				}

				var result = response.data;
				self.createdSoFar = result.total_created || (self.createdSoFar + result.created);

				// Log results.
				if (result.created > 0) {
					self.addLogEntry('success', 'Created ' + result.created + ' order(s). Total: ' + self.createdSoFar);
				}

				if (result.errors && result.errors.length > 0) {
					for (var i = 0; i < result.errors.length; i++) {
						self.addLogEntry('error', result.errors[i]);
					}
				}

				if (result.done || self.cancelled) {
					// All done.
					self.running = false;
					self.updateProgress(self.totalOrders, 'Complete! ' + self.createdSoFar + ' orders generated.');
					$('#silc-fog-cancel').prop('disabled', false);
					$('#silc-fog-form').show();
					self.addLogEntry('info', 'Generation finished.');
					return;
				}

				// Update progress bar.
				self.updateProgress(self.createdSoFar, 'Processing...');

				// Process next batch.
				self.processNextBatch(result.batch_index);
			}).fail(function (jqXHR, textStatus) {
				self.addLogEntry('error', 'AJAX error: ' + textStatus);
				self.running = false;
				self.updateProgressStatus('AJAX error. Check log for details.');
				$('#silc-fog-cancel').prop('disabled', false);
				$('#silc-fog-form').show();
			});
		},

		/**
		 * Update the progress bar and text.
		 *
		 * @param {int}  done    Number completed.
		 * @param {string} status Status message.
		 */
		updateProgress: function (done, status) {
			var percent = this.totalOrders > 0 ? Math.min(100, Math.round((done / this.totalOrders) * 100)) : 0;
			$('#silc-fog-progress-fill').css('width', percent + '%');
			$('#silc-fog-progress-text').text(done + ' / ' + this.totalOrders);
			this.updateProgressStatus(status);
		},

		/**
		 * Update the status text.
		 *
		 * @param {string} status
		 */
		updateProgressStatus: function (status) {
			$('#silc-fog-progress-status').text(status);
		},

		/**
		 * Add a log entry.
		 *
		 * @param {string} type    'success', 'error', or 'info'.
		 * @param {string} message Log message.
		 */
		addLogEntry: function (type, message) {
			var $log = $('#silc-fog-log');
			var cls = 'log-entry log-' + type;
			var timestamp = new Date().toLocaleTimeString();
			$log.append('<div class="' + cls + '">[' + timestamp + '] ' + this.escapeHtml(message) + '</div>');
			$log.scrollTop($log[0].scrollHeight);
		},

		/**
		 * Clear the log.
		 */
		clearLog: function () {
			$('#silc-fog-log').empty();
		},

		/**
		 * Escape HTML for safe log output.
		 *
		 * @param {string} str
		 * @return {string}
		 */
		escapeHtml: function (str) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		}
	};

	// Initialize on document ready.
	$(document).ready(function () {
		SILC_FOG.init();
	});

})(jQuery);
