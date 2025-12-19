define(
    [
        'mage/url'
    ],
    function (
        urlBuilder
    ) {
        'use strict';
        
        /**
         * Request Queue System for Session Creation
         * Ensures sequential processing of multiple concurrent session requests
         */
        const SessionQueue = {
            queue: [],
            isProcessing: false,
            minQueueInterval: 250, // Minimum time between queue item processing (ms) - 1/4 second to prevent rate limiting
            lastProcessTime: 0,
            maxRetries: 3,
            
            /**
             * Add a request to the queue
             */
            enqueue: function(params) {
                return new Promise((resolve, reject) => {
                    this.queue.push({ params, resolve, reject, retryCount: 0 });
                    this.processQueue();
                });
            },
            
            /**
             * Process the queue
             */
            processQueue: async function() {
                // If already processing or queue is empty, return
                if (this.isProcessing || this.queue.length === 0) {
                    return;
                }
                
                this.isProcessing = true;
                
                while (this.queue.length > 0) {
                    const item = this.queue.shift();
                    const { params, resolve, reject } = item;
                    let retryCount = item.retryCount || 0;
                    
                    try {
                        const result = await this.makeRequest(params);
                        this.lastProcessTime = Date.now();
                        
                        // Enforce minimum interval before next request
                        if (this.queue.length > 0) {
                            await new Promise(resolve => setTimeout(resolve, this.minQueueInterval));
                        }
                        
                        resolve(result);
                    } catch (error) {
                        this.lastProcessTime = Date.now();
                        
                        // Retry logic - if failed, add back to queue with retry count
                        if (retryCount < this.maxRetries) {
                            item.retryCount = retryCount + 1;
                            const backoffDelay = Math.pow(2, retryCount) * 500; // 500ms, 1s, 2s
                            await new Promise(resolve => setTimeout(resolve, backoffDelay));
                            this.queue.unshift(item); // Add back to front of queue
                        } else {
                            // Max retries exceeded
                            reject(error);
                        }
                    }
                }
                
                this.isProcessing = false;
            },
            
            /**
             * Make the actual HTTP request
             */
            makeRequest: async function(params) {
                let serviceUrl = 'fiserv/commercehub/getcredentials';
                
                try {
                    let response = await fetch(urlBuilder.build(serviceUrl), {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'    
                        },
                        body: JSON.stringify(params),
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        throw new Error("Credentials request failure");
                    }
                    return await response.json();
                } catch (error) {
                    throw new Error("An error occurred while creating Commercehub payment session.");
                }
            }
        };
        
        /**
         * Main export function
         * Queues the session creation request
         */
        return async function (params) {
            return SessionQueue.enqueue(params);
        };
    }
);

