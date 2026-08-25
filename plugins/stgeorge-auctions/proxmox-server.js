const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
    cors: { origin: "*", methods: ["GET", "POST"] }
});

const auctionStates = {};

io.on('connection', (socket) => {
    socket.on('joinAuction', (auctionId) => {
        socket.join(auctionId);
        if (auctionStates[auctionId]) {
            socket.emit('updateAuction', auctionStates[auctionId]);
        }
    });
});

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
        newEndTimestamp: data.newEndTimestamp
    };

    io.to(auctionId).emit('updateAuction', auctionStates[auctionId]);
    res.json({ success: true, state: auctionStates[auctionId] });
});

app.get('/api/auction-state/:id', (req, res) => {
    const auctionId = req.params.id;
    if (auctionStates[auctionId]) {
        res.json({ success: true, state: auctionStates[auctionId] });
    } else {
        res.json({ success: false, error: 'Not found in memory' });
    }
});

server.listen(8080, '0.0.0.0', () => {
    console.log('Proxmox Socket Server running on port 8080');
});
