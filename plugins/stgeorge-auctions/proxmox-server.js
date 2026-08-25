app.post('/api/new-bid', (req, res) => {
    const data = req.body;
    const auctionId = data.auctionId;

    if (!auctionId) {
        return res.status(400).json({ error: 'Missing auctionId' });
    }

    const currentBid = parseFloat(data.bidAmount) || 0;
    const fee = currentBid * 0.15;
    const deposit = 35.00;
    const totalDue = currentBid + fee + deposit;
    const nextMinBid = currentBid + 1.00;

    auctionStates[auctionId] = {
        auctionId: auctionId,
        currentBid: currentBid.toFixed(2),
        fee: fee.toFixed(2),
        totalDue: totalDue.toFixed(2),
        nextMinBid: nextMinBid.toFixed(2),
        highBidderId: data.highBidderId,
        previousHighBidderId: data.previousHighBidderId,
        newEndTimestamp: data.newEndTimestamp // Ensure this is saved and broadcasted
    };

    io.to(auctionId).emit('updateAuction', auctionStates[auctionId]);
    res.json({ success: true, state: auctionStates[auctionId] });
});
