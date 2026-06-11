const express = require('express');
const http = require('http'); // Back to normal HTTP
const { Server } = require('socket.io');
const Redis = require('ioredis');
const { createAdapter } = require('@socket.io/redis-adapter');

const app = express();
const server = http.createServer(app); // No SSL certs needed here!

// Use Redis client for standard operations and Pub/Sub for scaling socket.io
const redisClient = new Redis(); // defaults to 127.0.0.1:6379
const pubClient = new Redis();
const subClient = pubClient.duplicate();

const io = new Server(server, {
  cors: {
    origin: "*", // Allow WordPress frontend
    methods: ["GET", "POST"]
  }
});

io.adapter(createAdapter(pubClient, subClient));

io.on('connection', (socket) => {
  console.log('User connected:', socket.id);

  // Join a room for a specific auction
  socket.on('joinAuction', async (auctionId) => {
    socket.join(`auction_${auctionId}`);
    console.log(`Socket ${socket.id} joined auction_${auctionId}`);

    // Send the current "hot" data from Redis when joining
    try {
        const currentHighBid = await redisClient.get(`auction_${auctionId}_high_bid`);
        if (currentHighBid) {
            socket.emit('currentHighBid', { auctionId, bidAmount: parseFloat(currentHighBid) });
        }
    } catch (e) {
        console.error('Redis Error on join:', e);
    }
  });
});

// REST endpoint for PHP to push a new high bid
app.use(express.json());
app.post('/api/new-bid', async (req, res) => {
    const { auctionId, bidAmount, newEndTimestamp } = req.body;
    
    if (!auctionId || !bidAmount) {
        return res.status(400).json({ error: 'Missing auctionId or bidAmount' });
    }

    try {
        await redisClient.set(`auction_${auctionId}_high_bid`, bidAmount);
        
        // Broadcast via Socket.io to everyone watching that unit
        io.to(`auction_${auctionId}`).emit('newHighBid', {
            auctionId,
            bidAmount,
            newEndTimestamp,
            timestamp: Date.now()
        });
        
        res.json({ success: true });
    } catch (e) {
        console.error('API Error:', e);
        res.status(500).json({ error: 'Internal Server Error' });
    }
});

// Start the server
const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Real-Time Auction Server running on port ${PORT}`);
});