define(
	[
		'mage/url',
	],
	(
		urlBuilder,
	) => {
		'use strict';

		/**
         * Request Queue System for Session Creation
         * Ensures sequential processing of multiple concurrent session requests
         */
		const SessionQueue = {
			queue: [],
			isProcessing: false,
			minQueueInterval: 250,
			lastProcessTime: 0,
			maxRetries: 3,

			/**
             * Add a request to the queue
             */
			enqueue(parameters) {
				return new Promise((resolve, reject) => {
					this.queue.push({ params: parameters, resolve, reject, retryCount: 0 });
					this.processQueue();
				});
			},

			/**
             * Process the queue
             */
			async processQueue() {
				if (this.isProcessing || this.queue.length === 0) {
					return;
				}

				this.isProcessing = true;

				while (this.queue.length > 0) {
					const item = this.queue.shift();
					const { params, resolve, reject } = item;
					const retryCount = item.retryCount || 0;

					try {
						const result = await this.makeRequest(params);

						this.lastProcessTime = Date.now();

						if (this.queue.length > 0) {
							await new Promise((resolve) => setTimeout(resolve, this.minQueueInterval));
						}
						resolve(result);
					} catch (error) {
						this.lastProcessTime = Date.now();

						if (retryCount < this.maxRetries) {
							item.retryCount = retryCount + 1;
							const backoffDelay = 2 ** retryCount * 500;

							await new Promise((resolve) => setTimeout(resolve, backoffDelay));
							this.queue.unshift(item);
						} else {
							reject(error);
						}
					}
				}
				this.isProcessing = false;
			},

			/**
             * Make the actual HTTP request
             */
			async makeRequest(parameters) {
				const serviceUrl = 'fiserv/commercehub/getcredentials';

				try {
					const response = await fetch(urlBuilder.build(serviceUrl), {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
						},
						body: JSON.stringify(parameters),
						credentials: 'same-origin',
					});

					if (!response.ok) {
						throw new Error('Credentials request failure');
					}

					return await response.json();
				} catch {
					throw new Error('An error occurred while creating Commercehub payment session.');
				}
			},
		};

		/**
         * Main export function
         * Queues the session creation request
         */
		return async function (parameters) {
			return SessionQueue.enqueue(parameters);
		};
	},
);
