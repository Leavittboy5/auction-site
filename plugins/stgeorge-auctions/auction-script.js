jQuery(document).ready(function($) {

    // Connect to WebSocket server for real-time updates
    var socket = null;
    if (typeof io !== 'undefined') {
        // MUST BE HTTPS and match your public domain to avoid Mixed Content errors
        // Remove the port number! The .htaccess proxy handles it now.
        socket = io('https://auction.stgeorgestorage.com');
        
        socket.on('connect', function() {
            console.log('Connected to real-time server');
            // Join rooms for all visible auctions
            $('.stg-place-bid-btn').each(function() {
                var auctionId = $(this).data('auction-id');
                socket.emit('joinAuction', auctionId);
            });
        });

        socket.on('newHighBid', function(data) {
            var auctionId = data.auctionId;
            var bidAmount = parseFloat(data.bidAmount);
            var newEndTimestamp = data.newEndTimestamp;
            
            var btn = $('.stg-place-bid-btn[data-auction-id="' + auctionId + '"]');
            if (btn.length) {
                var card = btn.closest('.facility-card');
                
                // Update Current Bid
                var currentBidDisplay = card.find('.stg-current-bid-display');
                if (currentBidDisplay.length) {
                    currentBidDisplay.text('$' + bidAmount.toFixed(2));
                }

                // Update Financial Breakdown
                var feeDisplay = card.find('.stg-fee-display');
                var totalDisplay = card.find('.stg-total-display');
                
                if (feeDisplay.length && totalDisplay.length) {
                    var newFee = bidAmount * 0.15;
                    var lockDeposit = 35.00;
                    var newTotal = bidAmount + newFee + lockDeposit;
                    
                    feeDisplay.text('$' + newFee.toFixed(2));
                    totalDisplay.text('$' + newTotal.toFixed(2));
                }
                
                // Update the input box for EVERYONE watching
                var nextMin = bidAmount + 1; // Assuming bids go up by at least $1
                var inputField = card.find('.stg-bid-input');
                if (inputField.length) {
                    inputField.attr('min', nextMin);
                    inputField.attr('placeholder', '$' + nextMin + '+');
                }
                
                // Update timer if anti-sniping kicked in
                if (newEndTimestamp) {
                    var countdownDisplay = card.find('.stg-countdown-display');
                    if (countdownDisplay.length) {
                        countdownDisplay.attr('data-end-time', newEndTimestamp);
                    }
                }
                
                // Visual feedback that a new bid came in
                card.addClass('bg-green-50 transition-colors duration-500');
                setTimeout(function() {
                    card.removeClass('bg-green-50');
                }, 1000);
            }
        });
    }

    // Sound effect for the final countdown
    var tickingSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3');

    // Countdown Timer Logic
    setInterval(function() {
        $('.stg-countdown-display').each(function() {
            var endTimeAttr = $(this).attr('data-end-time');
            if (!endTimeAttr) return;

            var endTime = parseInt(endTimeAttr) * 1000; 
            // If endTime is 0 (i.e. TBD/Not set properly but 0 string) treat as ended
            var distance = (endTimeAttr === "0") ? -1 : (endTime - new Date().getTime());

            var card = $(this).closest('.facility-card');
            var timerDisplay = $(this).find('.stg-timer-text');
            var bidButtonContainer = card.find('.stg-place-bid-btn').closest('.flex-col');

            if (distance < 0) {
                $(this).html('<span class="text-red-600 font-bold uppercase tracking-wide">Auction Ended</span>');
                
                // Change UI to ended state if it's not already
                if (bidButtonContainer.length) {
                    bidButtonContainer.replaceWith('<div class="text-right"><span class="text-red-600 font-bold uppercase text-sm">Auction Ended</span></div>');
                }
                return;
            }

            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            if (timerDisplay.length === 0) {
                $(this).html('Time Remaining: <span class="stg-timer-text">' + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's</span>');
                timerDisplay = $(this).find('.stg-timer-text');
            } else {
                timerDisplay.text(days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's');
            }

            // High Stakes feel: Pulse and turn red if < 5 minutes
            if (distance < 300000) {
                $(this).addClass('animate-pulse text-red-600 font-bold');
            } else {
                $(this).removeClass('animate-pulse text-red-600 font-bold');
            }
            
            // Audio ticking if < 60 seconds
            if (distance < 60000 && distance > 0) {
                if (typeof tickingSound.play === 'function') {
                    var playPromise = tickingSound.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(function(error) {
                            // Auto-play was prevented by browser
                        });
                    }
                }
            }
        });
    }, 1000);

    // Facility Filter Logic
    $('#stg-facility-filter').on('change', function() {
        var selectedFacility = $(this).val();

        if (selectedFacility === 'all') {
            $('.facility-card').fadeIn(); // Show all
        } else {
            $('.facility-card').hide(); // Hide all
            $('.facility-card[data-facility="' + selectedFacility + '"]').fadeIn(); // Show only selected
        }
    });

    // Bidding Logic
    $('.stg-place-bid-btn').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var card = button.closest('.facility-card');
        var auctionId = button.data('auction-id');
        var bidAmount = card.find('.stg-bid-input').val();
        var messageBox = card.find('.stg-bid-message');

        // Basic check before sending
        if (!bidAmount) {
            messageBox.removeClass('hidden text-green-600').addClass('text-red-600').text('Please enter a bid amount.');
            return;
        }

        button.prop('disabled', true).text('Bidding...');

        // Send to WordPress
        $.ajax({
            type: 'POST',
            url: stgAuctionData.ajaxurl,
            data: {
                action: 'stg_place_bid',
                security: stgAuctionData.nonce,
                auction_id: auctionId,
                bid_amount: bidAmount
            },
            success: function(response) {
                messageBox.removeClass('hidden').show();

                if (response.success) {
                    messageBox.removeClass('text-red-600').addClass('text-green-600').text('Bid successful!');
                    
                    // Update the input box for the person who bid
                    var newNextMin = response.data.next_min_bid;
                    if (newNextMin) {
                        card.find('.stg-bid-input').attr('min', newNextMin);
                        card.find('.stg-bid-input').attr('placeholder', '$' + newNextMin + '+');
                    }
                    
                    // Clear the input
                    card.find('.stg-bid-input').val('');

                    // Re-enable the button
                    setTimeout(function() {
                        button.prop('disabled', false).text('Place Bid');
                        messageBox.fadeOut();
                    }, 2000);
                    
                } else {
                    var errorMsg = (typeof response.data === 'object' && response.data.message) ? response.data.message : response.data;
                    messageBox.removeClass('text-green-600').addClass('text-red-600').text(errorMsg);
                    button.prop('disabled', false).text('Place Bid');
                }
            },
            error: function() {
                messageBox.removeClass('hidden text-green-600').addClass('text-red-600').text('System error. Please try again.');
                button.prop('disabled', false).text('Place Bid');
            }
        });
    });
});