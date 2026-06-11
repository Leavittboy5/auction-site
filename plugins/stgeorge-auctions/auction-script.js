jQuery(document).ready(function($) {

    var socket = null;
    if (typeof io !== 'undefined') {
        
        // --- DEVELOPMENT / LOCALHOST ---
        socket = io('http://localhost:3000');
        
        // --- PRODUCTION ---
        // socket = io('https://auction.stgeorgestorage.com');
        
        socket.on('connect', function() {
            console.log('Connected to real-time server');
            $('.facility-card').each(function() {
                var auctionId = $(this).data('auction-id');
                if (auctionId) {
                    socket.emit('joinAuction', auctionId);
                }
            });
        });

        socket.on('newHighBid', function(data) {
            var auctionId = data.auctionId;
            var bidAmount = parseFloat(data.bidAmount);
            var newEndTimestamp = data.newEndTimestamp;
            var highBidderId = data.highBidderId; 
            
            var card = $('.facility-card[data-auction-id="' + auctionId + '"]');
            
            if (card.length) {
                var currentBidDisplay = card.find('.stg-current-bid-display');
                if (currentBidDisplay.length) {
                    currentBidDisplay.text('$' + bidAmount.toFixed(2));
                }

                var feeDisplay = card.find('.stg-fee-display');
                var totalDisplay = card.find('.stg-total-display');
                
                if (feeDisplay.length && totalDisplay.length) {
                    var newFee = bidAmount * 0.15;
                    var lockDeposit = 35.00;
                    var newTotal = bidAmount + newFee + lockDeposit;
                    
                    feeDisplay.text('$' + newFee.toFixed(2));
                    totalDisplay.text('$' + newTotal.toFixed(2));
                }
                
                // Smart Proxy Logic check
                var isMe = (stgAuctionData.user_id && stgAuctionData.user_id == highBidderId);
                
                if (!isMe) {
                    // Someone else is winning. Remove the crown badge if we had it.
                    card.find('.stg-winning-badge-container').empty();
                    
                    // Update the input placeholder for non-winners
                    var nextMin = bidAmount + 1; 
                    var inputField = card.find('.stg-bid-input');
                    if (inputField.length) {
                        inputField.attr('min', nextMin);
                        inputField.attr('placeholder', '$' + nextMin + '+');
                    }
                }
                
                if (newEndTimestamp) {
                    var countdownDisplay = card.find('.stg-countdown-display');
                    if (countdownDisplay.length) {
                        countdownDisplay.attr('data-end-time', newEndTimestamp);
                    }
                }
                
                card.addClass('bg-green-50 transition-colors duration-500');
                setTimeout(function() {
                    card.removeClass('bg-green-50');
                }, 1000);
            }
        });
    }

    var tickingSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3');

    setInterval(function() {
        $('.stg-countdown-display').each(function() {
            var endTimeAttr = $(this).attr('data-end-time');
            if (!endTimeAttr) return;

            var endTime = parseInt(endTimeAttr) * 1000; 
            var distance = (endTimeAttr === "0") ? -1 : (endTime - new Date().getTime());

            var card = $(this).closest('.facility-card');
            var timerDisplay = $(this).find('.stg-timer-text');
            var bidButtonContainer = card.find('.stg-place-bid-btn').closest('.flex-col');

            if (distance < 0) {
                $(this).html('<span class="text-red-600 font-bold uppercase tracking-wide">Auction Ended</span>');
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

    $('#stg-facility-filter').on('change', function() {
        var selectedFacility = $(this).val();
        if (selectedFacility === 'all') {
            $('.facility-card').fadeIn(); 
        } else {
            $('.facility-card').hide(); 
            $('.facility-card[data-facility="' + selectedFacility + '"]').fadeIn(); 
        }
    });

    $('.stg-place-bid-btn').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var card = button.closest('.facility-card');
        var auctionId = button.data('auction-id');
        var bidAmount = card.find('.stg-bid-input').val();
        var messageBox = card.find('.stg-bid-message');

        if (!bidAmount) {
            messageBox.removeClass('hidden text-green-600').addClass('text-red-600').text('Please enter a bid amount.');
            return;
        }

        button.prop('disabled', true).text('Bidding...');

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
                    
                    var newNextMin = response.data.next_min_bid;
                    if (newNextMin) {
                        card.find('.stg-bid-input').attr('min', newNextMin);
                        card.find('.stg-bid-input').attr('placeholder', '$' + newNextMin + '+');
                    }
                    
                    // Visually grant the crown badge immediately to the winner
                    if (response.data.is_winning) {
                        var badgeHtml = '<span class="text-xs font-bold text-green-600 stg-winning-badge block"><i class="fa-solid fa-crown"></i> Winning! (Your Max: $' + parseFloat(response.data.max_bid).toFixed(2) + ')</span>';
                        card.find('.stg-winning-badge-container').html(badgeHtml);
                    }

                    card.find('.stg-bid-input').val('');

                    setTimeout(function() {
                        button.prop('disabled', false).text('Place Bid');
                        messageBox.fadeOut();
                    }, 2000);
                    
                } else {
                    var errorMsg = (typeof response.data === 'object' && response.data.message) ? response.data.message : response.data;
                    messageBox.removeClass('text-green-600').addClass('text-red-600').text(errorMsg);
                    
                    // If they got outbid immediately, update their placeholder
                    if (typeof response.data === 'object' && response.data.next_min_bid) {
                        var errorNextMin = response.data.next_min_bid;
                        card.find('.stg-bid-input').attr('min', errorNextMin);
                        card.find('.stg-bid-input').attr('placeholder', '$' + errorNextMin + '+');
                    }
                    
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