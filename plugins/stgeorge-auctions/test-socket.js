const io = require("socket.io-client");
const socket = io("http://localhost:3000");

socket.on("connect", () => {
  console.log("Connected to server!");
  socket.emit("joinAuction", 123);
});

socket.on("newHighBid", (data) => {
  console.log("Received newHighBid:", data);
  process.exit(0);
});

setTimeout(() => {
  fetch("http://localhost:3000/api/new-bid", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({auctionId: 123, bidAmount: 150})
  });
}, 1000);

setTimeout(() => {
  console.log("Timeout waiting for message");
  process.exit(1);
}, 3000);
