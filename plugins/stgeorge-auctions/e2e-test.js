const io = require("socket.io-client");
const socket = io("http://localhost:3000");

socket.on("connect", () => {
  console.log("E2E Test: Connected to WebSocket server!");
  socket.emit("joinAuction", 999);
});

socket.on("newHighBid", (data) => {
  console.log("E2E Test Success: Received newHighBid event via WebSocket!");
  console.log(data);
  process.exit(0);
});

setTimeout(() => {
  console.log("E2E Test: Simulating internal API call from WordPress...");
  fetch("http://localhost:3000/api/new-bid", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({auctionId: 999, bidAmount: 250, newEndTimestamp: Date.now() + 120000})
  }).then(res => res.json()).then(console.log).catch(console.error);
}, 1000);

setTimeout(() => {
  console.log("E2E Test Failed: Timeout waiting for WebSocket message");
  process.exit(1);
}, 4000);
