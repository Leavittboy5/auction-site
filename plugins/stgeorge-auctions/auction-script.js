(function($) {
    "use strict";

    $(document).ready(function() {

        // --- 1-SECOND DIRECT PROXMOX LIVE STATE SYNC (BYPASSES WP CACHE) ---
        var isPolling = false;
        setInterval(function() {
            if (isPolling) return;

            var auctionIds = [];
            $('.facility-card').each(function() {
                var aId = $(this).attr('data-auction-id') || $(this).data('auction-id');
                if (aId) auctionIds.push(aId);
            });

            if (auctionIds.length === 0) return;

            isPolling = true;

            var requests = auctionIds.map(function(auctionId) {
                return $.ajax({
                    url: 'https://sockets.stgeorgestorage.com/api/auction-state/' + auctionId,
                    type: 'GET',
                    dataType: 'json'
                });
            });

            $.when.apply($, requests).done(function() {
                isPolling = false;
                var responses = arguments.length === 1 ? [arguments[0]] : Array.prototype.slice.call(arguments);
                
                responses.forEach(function(responseItem) {
                    var res = Array.isArray(responseItem) ? responseItem[0] : responseItem;
                    if (res && res.success && res.state) {
                        var item = res.state;
                        var card = $('.facility-card').filter(function() {
                            return String($(this).attr('data-auction-id')) === String(item.auctionId);
                        });

                        if (!card.length) return;

                        var currentBid = parseFloat(item.currentBid);
                        var currentBidDisplay = card.find('.stg-current-bid-display');
                        if (currentBidDisplay.length && !isNaN(currentBid)) {
                            currentBidDisplay.text('$' + currentBid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        }

                        var feeDisplay = card.find('.stg-fee-display');
                        var totalDisplay = card.find('.stg-total-display');
                        if (feeDisplay.length && totalDisplay.length) {
                            feeDisplay.text('$' + parseFloat(item.fee).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                            totalDisplay.text('$' + parseFloat(item.totalDue).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        }

                        var inputField = card.find('.stg-bid-input');
                        if (inputField.length && !inputField.is(':focus')) {
                            inputField.attr('min', item.nextMinBid);
                            inputField.attr('placeholder', '$' + Math.floor(item.nextMinBid) + '+');
                        }

                        var badgeContainer = card.find('.stg-winning-badge-container');
                        var currentUserId = (typeof stgAuctionData !== 'undefined' && stgAuctionData.user_id) ? String(stgAuctionData.user_id) : null;
                        
                        if (currentUserId && currentUserId !== "0" && item.highBidderId) {
                            var isReallyWinning = (String(currentUserId) === String(item.highBidderId));
                            
                            if (isReallyWinning) {
                                var myMaxBid = parseFloat(card.attr('data-my-max-bid')) || 0;
                                var maxStr = (myMaxBid > 0) ? ' (Your Max: $' + myMaxBid.toFixed(2) + ')' : '';
                                badgeContainer.html('<span class="text-xs font-bold text-green-600 stg-winning-badge block"><i class="fa-solid fa-crown"></i> Winning!' + maxStr + '</span>');
                            } else {
                                if (badgeContainer.find('.stg-winning-badge').length > 0 || badgeContainer.find('.stg-outbid-badge').length > 0) {
                                    badgeContainer.html('<span class="text-xs font-bold text-red-600 stg-outbid-badge block"><i class="fa-solid fa-triangle-exclamation"></i> Outbid!</span>');
                                }
                            }
                        }

                        if (item.newEndTimestamp) {
                            var countdownDisplay = card.find('.stg-countdown-display');
                            if (countdownDisplay.length) {
                                countdownDisplay.attr('data-end-time', item.newEndTimestamp);
                            }
                        }
                    }
                });
            }).fail(function() {
                isPolling = false;
            });
        }, 1000);

        // --- TIMER REFRESH LOOP ---
        var tickingSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3');

        setInterval(function() {
            $('.stg-countdown-display').each(function() {
                var endTimeAttr = $(this).attr('data-end-time');
                if (!endTimeAttr) return;

                var endTime = parseInt(endTimeAttr) * 1000;
                var distance = (endTimeAttr === "0") ? -1 : (endTime - new Date().getTime());

                var card = $(this).closest('.facility-card');
                var timerDisplay = $(this).find('.stg-timer-text');

                if (distance <= 0) {
                    var bidSection = card.find('.stg-place-bid-btn').closest('.flex');
                    if (bidSection.length) {
                        bidSection.replaceWith('<div class="text-right"><span class="text-red-600 font-bold uppercase text-sm">Auction Ended</span></div>');
                    }
                    $(this).html('<span class="text-red-600 font-bold uppercase tracking-wide">Auction Ended</span>');
                    return;
                }

                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                if (timerDisplay.length === 0) {
                    $(this).html('Time Remaining: <span class="stg-timer-text">' + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's</span>');
                } else {
                    timerDisplay.text(days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's');
                }

                if (distance < 300000) {
                    $(this).addClass('animate-pulse text-red-600 font-bold');
                } else {
                    $(this).removeClass('animate-pulse text-red-600 font-bold');
                }
                
                if (distance < 60000 && distance > 0) {
                    if (typeof tickingSound.play === 'function') {
                        var playPromise = tickingSound.play();
                        if (playPromise !== undefined) {
                            playPromise.catch(function(error) {});
                        }
                    }
                }
            });
        }, 1000);

        // --- BID SUBMISSION ---
        $(document).on('click', '.stg-place-bid-btn', function(e) {
            e.preventDefault();

            var button = $(this);
            var card = button.closest('.facility-card');
            var auctionId = button.attr('data-auction-id') || button.data('auction-id');
            var bidAmount = card.find('.stg-bid-input').val();
            var messageBox = card.find('.stg-bid-message');

            if (!bidAmount) {
                messageBox.removeClass('hidden text-green-600').addClass('text-red-600').text('Please enter a bid amount.').show();
                return;
            }

            button.prop('disabled', true).text('Bidding...');
            messageBox.removeClass('hidden text-red-600 text-green-600').addClass('text-blue-600').text('Submitting bid...').show();

            $.ajax({
                url: stgAuctionData.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'stg_place_bid',
                    auction_id: auctionId,
                    bid_amount: bidAmount,
                    nonce: stgAuctionData.nonce
                },
                success: function(response) {
                    button.prop('disabled', false).text('Bid Now');
                    card.find('.stg-bid-input').val('');

                    if (response.success) {
                        if (response.data && response.data.max_bid) {
                            card.attr('data-my-max-bid', response.data.max_bid);
                        }
                        messageBox.removeClass('text-red-600 text-blue-600').addClass('text-green-600').text('Bid successful!');
                        setTimeout(function() { messageBox.fadeOut(); }, 3000);
                    } else {
                        var errorMsg = 'Bid submission failed.';
                        if (response.data) {
                            if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            } else if (response.data.message) {
                                errorMsg = response.data.message;
                            }
                        }
                        if (errorMsg.toLowerCase().includes('outbid')) {
                            card.find('.stg-winning-badge-container').html('<span class="text-xs font-bold text-red-600 stg-outbid-badge block"><i class="fa-solid fa-triangle-exclamation"></i> Outbid!</span>');
                        }
                        messageBox.removeClass('text-green-600 text-blue-600').addClass('text-red-600').text(errorMsg).show();
                    }
                },
                error: function(jqXHR) {
                    button.prop('disabled', false).text('Bid Now');
                    
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                        var errData = jqXHR.responseJSON.data;
                        var errorMsg = (typeof errData === 'string') ? errData : errData.message;
                        if (errorMsg.toLowerCase().includes('outbid')) {
                            card.find('.stg-winning-badge-container').html('<span class="text-xs font-bold text-red-600 stg-outbid-badge block"><i class="fa-solid fa-triangle-exclamation"></i> Outbid!</span>');
                        }
                        messageBox.removeClass('text-green-600 text-blue-600').addClass('text-red-600').text(errorMsg).show();
                        card.find('.stg-bid-input').val('');
                    } else {
                        messageBox.removeClass('text-green-600 text-blue-600').addClass('text-red-600').text('Network connectivity error. Please refresh.').show();
                    }
                }
            });
        });

    });
})(jQuery);
